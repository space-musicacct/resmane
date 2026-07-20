"""AI フィードバック生成のビジネスロジック。"""

import logging

from src.clients.contracts.ai_client_interface import AiClientInterface
from src.configs.ai_status import AiStatus
from src.configs.config import Config
from src.configs.worker_status import TerminationReason, WorkerStatus
from src.databases.resmane_database import ResManeDatabase
from src.repositories.contracts.kakeibo_context_repository_interface import (
    KakeiboContextRepositoryInterface,
)
from src.repositories.contracts.post_repository_interface import (
    PostRepositoryInterface,
)
from src.repositories.contracts.worker_job_repository_interface import (
    WorkerJobRepositoryInterface,
)

logger = logging.getLogger(__name__)

CONTENT_MAX_LENGTH = 3000

SYSTEM_INSTRUCTION = (
    "あなたは家計簿アプリ「レスマネ」のAIアシスタントです。\n"
    "ユーザーの支出に関する入力内容をもとに、お金の使い方を見直しやすくなるフィードバックを返してください。\n"
    "\n"
    "## 出力方針\n"
    "- ユーザーの行動や考え方の良い点を示す（肯定的な反応）\n"
    "- 次回以降のお金の使い方に活かせる視点を示す（見直しの視点）\n"
    "- 必要に応じて、使いすぎや無理のある支出に気づける表現を行う（注意喚起）\n"
    "- 強く責める表現ではなく、継続しやすい表現を行う\n"
    "\n"
    "## 禁止事項\n"
    "- 投資助言や金融商品の推奨\n"
    "- 支出の強い否定やユーザーを責める表現\n"
    "- 絶対的な判断（支出の価値は利用者ごとに異なる）\n"
    "- 法的・金融的な断定\n"
)


class FeedbackService:
    """ポーリング → コンテキスト取得 → AI 呼び出し → 結果書き戻しを統括する。"""

    def __init__(
        self,
        config: Config,
        db: ResManeDatabase,
        post_repo: PostRepositoryInterface,
        context_repo: KakeiboContextRepositoryInterface,
        job_repo: WorkerJobRepositoryInterface,
        ai_client: AiClientInterface,
    ) -> None:
        self._config = config
        self._db = db
        self._post_repo = post_repo
        self._context_repo = context_repo
        self._job_repo = job_repo
        self._ai_client = ai_client

    def recover_stale(self) -> None:
        """PROCESSING のままタイムアウトしたジョブを回収する。"""
        stale_jobs = self._job_repo.fetch_stale(self._config.stale_timeout_sec)
        if not stale_jobs:
            return

        logger.info("stale ジョブ %d 件を検出", len(stale_jobs))
        for job in stale_jobs:
            self._recover_one(job)

    def _recover_one(self, job: dict) -> None:
        """1 件の stale ジョブを回収する。"""
        job_id = job["id"]
        post_id = job["post_id"]

        if job["retry_count"] < job["max_retries"]:
            self._post_repo.update_status(post_id, AiStatus.PENDING)
            self._job_repo.increment_retry(job_id, "stale recovery: worker timeout")
            logger.info(
                "post_id=%d: stale 回収 → PENDING に戻し (%d/%d)",
                post_id, job["retry_count"] + 1, job["max_retries"],
            )
        else:
            self._post_repo.mark_failed(post_id)
            self._job_repo.mark_failed(job_id, "stale recovery: max retries exceeded")
            logger.warning("post_id=%d: stale 回収 → リトライ上限到達", post_id)

    def process_pending(self) -> None:
        """pending な投稿を全て処理する。"""
        posts = self._post_repo.fetch_pending()
        if not posts:
            return

        logger.info("未処理タスク %d 件を検出", len(posts))
        for post in posts:
            self._process_one(post)

    def _process_one(self, post: dict) -> None:
        """1 件の pending 投稿を処理する。"""
        post_id = post["id"]
        record_id = post["kakeibo_record_id"]
        parent_id = post["parent_id"]

        job_id = self._claim(post_id)
        if job_id is None:
            return

        try:
            context = self._build_context(record_id)
            if context is None:
                logger.warning("post_id=%d: 家計簿レコードが見つかりません", post_id)
                self._post_repo.mark_failed(post_id)
                self._job_repo.mark_cancelled(job_id, TerminationReason.TARGET_DELETED)
                return

            is_followup = parent_id is not None
            messages = self._build_messages(context, is_followup)

            system_instruction = SYSTEM_INSTRUCTION
            if is_followup:
                system_instruction = self._build_followup_instruction(context)

            response = self._ai_client.generate(
                messages=messages,
                system_instruction=system_instruction,
            )

            if len(response) > CONTENT_MAX_LENGTH:
                logger.warning(
                    "post_id=%d: AI 応答が %d 文字で上限 %d 文字を超過",
                    post_id, len(response), CONTENT_MAX_LENGTH,
                )
                self._handle_failure(post_id, job_id, "content exceeded max length")
                return

            if self._post_repo.save_response(post_id, response):
                logger.info("post_id=%d: AI フィードバック生成完了", post_id)
                self._job_repo.mark_completed(job_id)
            else:
                logger.warning("post_id=%d: 書き戻しスキップ (削除済み or 状態不整合)", post_id)
                self._job_repo.mark_cancelled(job_id, TerminationReason.TARGET_DELETED)

        except Exception as e:
            logger.exception("post_id=%d: AI フィードバック生成に失敗", post_id)
            self._handle_failure(post_id, job_id, str(e))

    def _claim(self, post_id: int) -> int | None:
        """pending → processing に確保し、worker_jobs を作成する。

        posts の更新 (レスマネ本体 DB) と worker_jobs の INSERT (Worker 専用 DB) は
        別 DB のためトランザクションを分離する。posts 側を先に確定させる。
        """
        self._db.begin_transaction()
        try:
            row = self._post_repo.find_for_update(post_id)
            if row is None:
                self._db.rollback()
                return None

            self._post_repo.update_status(post_id, AiStatus.PROCESSING)
            self._db.commit()
        except Exception:
            self._db.rollback()
            raise

        job_id = self._job_repo.create(post_id)
        return job_id

    def _handle_failure(self, post_id: int, job_id: int, error: str) -> None:
        """失敗処理。リトライ可能なら PENDING に戻し、上限なら FAILED にする。"""
        self._job_repo.increment_retry(job_id, error)
        retry_info = self._job_repo.get_retry_info(job_id)

        if retry_info and retry_info["retry_count"] < retry_info["max_retries"]:
            self._post_repo.update_status(post_id, AiStatus.PENDING)
            logger.info(
                "post_id=%d: リトライ予定 (%d/%d)",
                post_id, retry_info["retry_count"], retry_info["max_retries"],
            )
        else:
            self._post_repo.mark_failed(post_id)
            self._job_repo.mark_failed(job_id, error)
            logger.warning("post_id=%d: リトライ上限到達", post_id)

    def _build_context(self, record_id: int) -> dict | None:
        """AI に渡すコンテキスト情報を組み立てる。"""
        record = self._context_repo.fetch_record(record_id)
        if record is None:
            return None

        return {
            "record": record,
            "self_reviews": self._context_repo.fetch_self_reviews(record_id),
            "thread": self._context_repo.fetch_thread(record_id),
        }

    def _build_messages(self, context: dict, is_followup: bool) -> list[dict]:
        """コンテキストから AI に送信するメッセージリストを組み立てる。"""
        if is_followup:
            return self._build_followup_messages(context)
        return self._build_initial_messages(context)

    def _build_initial_messages(self, context: dict) -> list[dict]:
        """初回フィードバック用メッセージ。家計簿情報 + 自己レビュー。"""
        record = context["record"]
        user_message = (
            f"【家計簿情報】\n"
            f"日付: {record['purchase_date']}\n"
            f"区分: {record['amount_type_name']}\n"
            f"カテゴリ: {record['category_name']}\n"
            f"金額: {record['amount']:,}円\n"
            f"内容: {record['details'] or '(なし)'}\n"
        )

        self_reviews = context["self_reviews"]
        if self_reviews:
            user_message += "\n【自己レビュー】\n"
            for review in self_reviews:
                user_message += (
                    f"- 評価: {review['evaluation']}/5\n"
                    f"  コメント: {review['review_comment']}\n"
                )

        return [{"role": "user", "content": user_message}]

    def _build_followup_messages(self, context: dict) -> list[dict]:
        """追加チャット用メッセージ。スレッド履歴をそのまま渡す。"""
        messages = []
        for post in context["thread"]:
            role = "assistant" if post["is_ai"] else "user"
            if post["content"]:
                messages.append({"role": role, "content": post["content"]})
        return messages

    def _build_followup_instruction(self, context: dict) -> str:
        """追加チャット用システムプロンプト。家計簿情報を背景コンテキストとして含める。"""
        record = context["record"]
        record_context = (
            f"\n## 背景情報（この会話の対象となる家計簿レコード）\n"
            f"- 日付: {record['purchase_date']}\n"
            f"- 区分: {record['amount_type_name']}\n"
            f"- カテゴリ: {record['category_name']}\n"
            f"- 金額: {record['amount']:,}円\n"
            f"- 内容: {record['details'] or '(なし)'}\n"
        )
        return SYSTEM_INSTRUCTION + record_context
