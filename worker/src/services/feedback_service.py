"""AI フィードバック生成のビジネスロジック。"""

import logging

from src.clients.contracts.ai_client_interface import AiClientInterface
from src.configs.ai_status import AiStatus
from src.configs.config import Config
from src.configs.worker_status import TerminationReason
from src.databases.resmane_database import ResManeDatabase
from src.databases.resmane_worker_database import ResmaneWorkerDatabase
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
        worker_db: ResmaneWorkerDatabase,
        post_repo: PostRepositoryInterface,
        context_repo: KakeiboContextRepositoryInterface,
        job_repo: WorkerJobRepositoryInterface,
        ai_client: AiClientInterface,
    ) -> None:
        self._config = config
        self._db = db
        self._worker_db = worker_db
        self._post_repo = post_repo
        self._context_repo = context_repo
        self._job_repo = job_repo
        self._ai_client = ai_client

    # =========================================================
    # stale recovery
    # =========================================================

    def recover_stale(self) -> None:
        """PROCESSING のままタイムアウトしたジョブを回収する。"""
        if self._config.stale_timeout_sec <= 0:
            return
        stale_jobs = self._job_repo.fetch_stale(self._config.stale_timeout_sec)
        if not stale_jobs:
            return

        logger.info("stale ジョブ %d 件を検出", len(stale_jobs))
        for job in stale_jobs:
            self._recover_one(job)

    def _recover_one(self, job: dict) -> None:
        """1 件の stale ジョブを状態表に基づいて回収する。

        Worker DB ロック → Laravel DB 確認/更新 → Worker DB 更新 → commit の順。
        """
        job_id = job["id"]
        post_id = job["post_id"]
        cv = job["claim_version"]

        self._worker_db.begin_transaction()
        try:
            if not self._job_repo.lock_for_ownership(job_id, cv):
                self._worker_db.rollback()
                return

            post_info = self._post_repo.get_ai_status(post_id)

            if post_info is None or post_info["deleted_at"] is not None:
                self._job_repo.mark_cancelled(job_id, cv, TerminationReason.TARGET_DELETED)
                self._worker_db.commit()
                logger.info("post_id=%d: stale 回収 → 削除済みのためキャンセル", post_id)
                return

            post_status = post_info["ai_status_id"]

            if post_status == AiStatus.COMPLETED:
                self._job_repo.mark_completed(job_id, cv)
                self._worker_db.commit()
                logger.info("post_id=%d: stale 回収 → 投稿完了済み", post_id)
                return

            if post_status == AiStatus.FAILED:
                self._job_repo.mark_failed(job_id, cv, "stale recovery: post already failed")
                self._worker_db.commit()
                logger.info("post_id=%d: stale 回収 → 投稿失敗済み", post_id)
                return

            if job["retry_count"] >= job["max_retries"]:
                if self._post_repo.force_fail(post_id):
                    self._job_repo.mark_failed(job_id, cv, "stale recovery: max retries exceeded")
                    self._worker_db.commit()
                    logger.warning("post_id=%d: stale 回収 → リトライ上限到達", post_id)
                else:
                    self._sync_to_post_state(job_id, post_id, cv)
                    self._worker_db.commit()
                return

            if post_status == AiStatus.PROCESSING:
                if not self._post_repo.recover_to_pending(post_id):
                    self._sync_to_post_state(job_id, post_id, cv)
                    self._worker_db.commit()
                    return

            self._job_repo.increment_retry_and_pend(job_id, cv, "stale recovery: worker timeout")
            self._worker_db.commit()
            logger.info(
                "post_id=%d: stale 回収 → RETRY_PENDING (%d/%d)",
                post_id, job["retry_count"] + 1, job["max_retries"],
            )

        except Exception:
            self._worker_db.rollback()
            logger.exception("post_id=%d: stale 回収中にエラー", post_id)

    # =========================================================
    # pending 処理
    # =========================================================

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

        claim = self._claim(post_id)
        if claim is None:
            return

        job_id, cv = claim

        try:
            context = self._build_context(record_id)
            if context is None:
                logger.warning("post_id=%d: 家計簿レコードが見つかりません", post_id)
                self._finalize_with_ownership(
                    job_id, cv, post_id,
                    lambda: self._post_repo.mark_failed(post_id),
                    lambda: self._job_repo.mark_cancelled(job_id, cv, TerminationReason.TARGET_DELETED),
                )
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

            if not response or not response.strip():
                logger.warning("post_id=%d: AI 応答が空", post_id)
                self._handle_failure(post_id, job_id, cv, "empty ai response")
                return

            if len(response) > CONTENT_MAX_LENGTH:
                logger.warning(
                    "post_id=%d: AI 応答が %d 文字で上限超過",
                    post_id, len(response),
                )
                self._handle_failure(post_id, job_id, cv, "content exceeded max length")
                return

            self._save_with_ownership(post_id, job_id, cv, response)

        except Exception as e:
            logger.exception("post_id=%d: AI フィードバック生成に失敗", post_id)
            safe_error = self._sanitize_error(e)
            self._handle_failure(post_id, job_id, cv, safe_error)

    # =========================================================
    # 所有権保護付き状態遷移
    # =========================================================

    def _save_with_ownership(
        self, post_id: int, job_id: int, cv: int, response: str,
    ) -> None:
        """Worker DB ロック → save_response → mark_completed/cancelled → commit。"""
        self._worker_db.begin_transaction()
        try:
            if not self._job_repo.lock_for_ownership(job_id, cv):
                self._worker_db.rollback()
                logger.warning("post_id=%d: 所有権喪失、書き戻し中止", post_id)
                return

            if self._post_repo.save_response(post_id, response):
                self._job_repo.mark_completed(job_id, cv)
                self._worker_db.commit()
                logger.info("post_id=%d: AI フィードバック生成完了", post_id)
            else:
                reason = self._classify_write_failure(post_id)
                self._job_repo.mark_cancelled(job_id, cv, reason)
                self._worker_db.commit()
        except Exception:
            self._worker_db.rollback()
            raise

    def _handle_failure(self, post_id: int, job_id: int, cv: int, error: str) -> None:
        """Worker DB ロック → リトライ or FAILED → commit。"""
        self._worker_db.begin_transaction()
        try:
            if not self._job_repo.lock_for_ownership(job_id, cv):
                self._worker_db.rollback()
                return

            retry_info = self._job_repo.get_retry_info(job_id)

            if retry_info and retry_info["retry_count"] < retry_info["max_retries"]:
                if self._post_repo.recover_to_pending(post_id):
                    self._job_repo.increment_retry_and_pend(job_id, cv, error)
                    self._worker_db.commit()
                    logger.info(
                        "post_id=%d: リトライ予定 (%d/%d)",
                        post_id, retry_info["retry_count"] + 1, retry_info["max_retries"],
                    )
                else:
                    self._sync_to_post_state(job_id, post_id, cv)
                    self._worker_db.commit()
            else:
                if self._post_repo.mark_failed(post_id):
                    self._job_repo.mark_failed(job_id, cv, error)
                    self._worker_db.commit()
                    logger.warning("post_id=%d: リトライ上限到達", post_id)
                else:
                    self._sync_to_post_state(job_id, post_id, cv)
                    self._worker_db.commit()
        except Exception:
            self._worker_db.rollback()
            logger.exception("post_id=%d: 失敗処理中にエラー", post_id)

    def _finalize_with_ownership(
        self, job_id: int, cv: int, post_id: int,
        laravel_action, worker_action,
    ) -> None:
        """所有権確認付きで Laravel + Worker DB を更新する汎用ヘルパー。"""
        self._worker_db.begin_transaction()
        try:
            if not self._job_repo.lock_for_ownership(job_id, cv):
                self._worker_db.rollback()
                return
            laravel_action()
            worker_action()
            self._worker_db.commit()
        except Exception:
            self._worker_db.rollback()
            raise

    def _sync_to_post_state(self, job_id: int, post_id: int, cv: int) -> None:
        """投稿の最新状態に合わせてジョブを同期する。ロック保持中に呼ぶこと。"""
        post_info = self._post_repo.get_ai_status(post_id)

        if post_info is None or post_info["deleted_at"] is not None:
            self._job_repo.mark_cancelled(job_id, cv, TerminationReason.TARGET_DELETED)
            logger.info("post_id=%d: 再評価 → 削除済みのためキャンセル", post_id)
        elif post_info["ai_status_id"] == AiStatus.COMPLETED:
            self._job_repo.mark_completed(job_id, cv)
            logger.info("post_id=%d: 再評価 → 完了済み", post_id)
        elif post_info["ai_status_id"] == AiStatus.FAILED:
            self._job_repo.mark_failed(job_id, cv, "sync: post already failed")
            logger.info("post_id=%d: 再評価 → 失敗済み", post_id)
        else:
            self._job_repo.mark_cancelled(job_id, cv, TerminationReason.STATE_INCONSISTENCY)
            logger.warning("post_id=%d: 再評価 → 状態不整合のためキャンセル", post_id)

    # =========================================================
    # claim
    # =========================================================

    def _claim(self, post_id: int) -> tuple[int, int] | None:
        """pending → processing に確保し、worker_jobs を永続化する。"""
        result = self._job_repo.upsert(post_id)
        if result is None:
            return None

        job_id, cv = result

        self._db.begin_transaction()
        try:
            row = self._post_repo.find_for_update(post_id)
            if row is None:
                self._db.rollback()
                reason = self._classify_write_failure(post_id)
                self._job_repo.mark_cancelled(job_id, cv, reason)
                return None

            self._post_repo.update_status(post_id, AiStatus.PROCESSING)
            self._db.commit()
            return (job_id, cv)
        except Exception:
            self._db.rollback()
            raise

    # =========================================================
    # ユーティリティ
    # =========================================================

    def _classify_write_failure(self, post_id: int) -> str:
        """条件付き UPDATE が失敗した原因を判定する。"""
        post_info = self._post_repo.get_ai_status(post_id)
        if post_info is None or post_info["deleted_at"] is not None:
            logger.warning("post_id=%d: 対象が削除済み", post_id)
            return TerminationReason.TARGET_DELETED
        logger.warning("post_id=%d: 状態不整合", post_id)
        return TerminationReason.STATE_INCONSISTENCY

    def _build_context(self, record_id: int) -> dict | None:
        record = self._context_repo.fetch_record(record_id)
        if record is None:
            return None
        return {
            "record": record,
            "self_reviews": self._context_repo.fetch_self_reviews(record_id),
            "thread": self._context_repo.fetch_thread(record_id),
        }

    def _build_messages(self, context: dict, is_followup: bool) -> list[dict]:
        if is_followup:
            return self._build_followup_messages(context)
        return self._build_initial_messages(context)

    def _build_initial_messages(self, context: dict) -> list[dict]:
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
        messages = []
        for post in context["thread"]:
            role = "assistant" if post["is_ai"] else "user"
            if post["content"]:
                messages.append({"role": role, "content": post["content"]})
        return messages

    def _build_followup_instruction(self, context: dict) -> str:
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

    @staticmethod
    def _sanitize_error(e: Exception) -> str:
        error_type = type(e).__name__
        if hasattr(e, "response") and e.response is not None:
            return f"{error_type}: HTTP {e.response.status_code}"
        return error_type
