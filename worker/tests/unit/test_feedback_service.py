"""FeedbackService の単体テスト。"""

from unittest.mock import MagicMock, patch
from datetime import date

import pytest
import requests

from src.configs.ai_status import AiStatus
from src.configs.config import Config
from src.configs.worker_status import TerminationReason
from src.services.feedback_service import FeedbackService, CONTENT_MAX_LENGTH


def _make_config(**overrides):
    defaults = dict(
        db_host="h", db_port=1, db_name="n", db_user="u", db_password="p",
        worker_own_db_host="wh", worker_own_db_port=2,
        worker_own_db_name="wn", worker_own_db_user="wu",
        worker_own_db_password="wp",
        poll_interval_sec=30, ai_api_key="k", ai_api_url="url",
        ai_model="m", stale_timeout_sec=300,
    )
    defaults.update(overrides)
    return Config(**defaults)


def _make_service(
    config=None, db=None, worker_db=None,
    post_repo=None, context_repo=None, job_repo=None, ai_client=None,
):
    return FeedbackService(
        config=config or _make_config(),
        db=db or MagicMock(),
        worker_db=worker_db or MagicMock(),
        post_repo=post_repo or MagicMock(),
        context_repo=context_repo or MagicMock(),
        job_repo=job_repo or MagicMock(),
        ai_client=ai_client or MagicMock(),
    )


def _make_context(**overrides):
    ctx = {
        "record": {
            "id": 1,
            "user_id": 1,
            "purchase_date": date(2026, 7, 1),
            "amount": 1500,
            "details": "コンビニでお昼",
            "amount_type_name": "支出",
            "category_name": "飲食",
        },
        "self_reviews": [
            {"evaluation": 3, "review_comment": "ちょっと贅沢"},
        ],
        "thread": [],
        "upper_limit": None,
    }
    ctx.update(overrides)
    return ctx


# =========================================================
# 3.1 claim
# =========================================================

class TestClaim:
    """UFS-001 〜 UFS-004"""

    def test_claim_success(self):
        """UFS-001: 初回 claim 成功。"""
        job_repo = MagicMock()
        job_repo.upsert.return_value = (1, 1)
        post_repo = MagicMock()
        post_repo.find_for_update.return_value = {"id": 10}
        db = MagicMock()

        svc = _make_service(db=db, post_repo=post_repo, job_repo=job_repo)
        result = svc._claim(10)

        assert result == (1, 1)
        post_repo.update_status.assert_called_once_with(10, AiStatus.PROCESSING)
        db.commit.assert_called_once()

    def test_claim_upsert_none(self):
        """UFS-002: upsert が None (他 Worker が claim 済み)。"""
        job_repo = MagicMock()
        job_repo.upsert.return_value = None

        svc = _make_service(job_repo=job_repo)
        result = svc._claim(10)

        assert result is None

    def test_claim_post_not_found(self):
        """UFS-003: 投稿が見つからない。"""
        job_repo = MagicMock()
        job_repo.upsert.return_value = (1, 1)
        post_repo = MagicMock()
        post_repo.find_for_update.return_value = None
        post_repo.get_ai_status.return_value = None
        db = MagicMock()

        svc = _make_service(db=db, post_repo=post_repo, job_repo=job_repo)
        result = svc._claim(10)

        assert result is None
        job_repo.mark_cancelled.assert_called_once()

    def test_claim_post_deleted(self):
        """UFS-004: 投稿が削除済み。"""
        job_repo = MagicMock()
        job_repo.upsert.return_value = (1, 1)
        post_repo = MagicMock()
        post_repo.find_for_update.return_value = None
        post_repo.get_ai_status.return_value = {"ai_status_id": AiStatus.PENDING, "deleted_at": "2026-07-21"}
        db = MagicMock()

        svc = _make_service(db=db, post_repo=post_repo, job_repo=job_repo)
        result = svc._claim(10)

        assert result is None
        args = job_repo.mark_cancelled.call_args[0]
        assert args[2] == TerminationReason.TARGET_DELETED


# =========================================================
# 3.2 process_one 正常系
# =========================================================

class TestProcessOneSuccess:
    """UFS-010 〜 UFS-012"""

    def test_initial_feedback_success(self):
        """UFS-010: 初回フィードバック正常完了。"""
        post_repo = MagicMock()
        post_repo.save_response.return_value = True
        context_repo = MagicMock()
        context_repo.fetch_record.return_value = _make_context()["record"]
        context_repo.fetch_self_reviews.return_value = []
        context_repo.fetch_thread.return_value = []
        context_repo.fetch_upper_limit.return_value = None
        context_repo.fetch_monthly_income.return_value = 0
        ai_client = MagicMock()
        ai_client.generate.return_value = "良い買い物ですね"
        job_repo = MagicMock()
        job_repo.lock_for_ownership.return_value = True
        worker_db = MagicMock()

        svc = _make_service(
            post_repo=post_repo, context_repo=context_repo,
            ai_client=ai_client, job_repo=job_repo, worker_db=worker_db,
        )

        post = {"id": 10, "kakeibo_record_id": 1, "parent_id": None}
        svc._claim = MagicMock(return_value=(1, 1))
        svc._process_one(post)

        post_repo.save_response.assert_called_once_with(10, "良い買い物ですね")
        job_repo.mark_completed.assert_called_once()

    def test_followup_chat_success(self):
        """UFS-011: 追加チャット正常完了。"""
        context_repo = MagicMock()
        context_repo.fetch_record.return_value = _make_context()["record"]
        context_repo.fetch_self_reviews.return_value = []
        context_repo.fetch_thread.return_value = [
            {"id": 1, "is_ai": 0, "content": "アドバイスほしい"},
            {"id": 2, "is_ai": 1, "content": "承知しました"},
        ]
        context_repo.fetch_upper_limit.return_value = None
        context_repo.fetch_monthly_income.return_value = 0
        ai_client = MagicMock()
        ai_client.generate.return_value = "追加アドバイス"
        post_repo = MagicMock()
        post_repo.save_response.return_value = True
        job_repo = MagicMock()
        job_repo.lock_for_ownership.return_value = True
        worker_db = MagicMock()

        svc = _make_service(
            post_repo=post_repo, context_repo=context_repo,
            ai_client=ai_client, job_repo=job_repo, worker_db=worker_db,
        )

        post = {"id": 10, "kakeibo_record_id": 1, "parent_id": 2}
        svc._claim = MagicMock(return_value=(1, 1))
        svc._process_one(post)

        call_args = ai_client.generate.call_args
        messages = call_args[1]["messages"] if "messages" in call_args[1] else call_args[0][0]
        assert len(messages) == 3
        assert "参照データ" in messages[0]["content"]
        si = call_args[1].get("system_instruction", call_args[0][1] if len(call_args[0]) > 1 else "")
        assert "追加チャット" in si

    def test_save_2000_char_response(self):
        """UFS-012: AI 応答をそのまま保存。"""
        response_text = "あ" * 2000
        ai_client = MagicMock()
        ai_client.generate.return_value = response_text
        post_repo = MagicMock()
        post_repo.save_response.return_value = True
        context_repo = MagicMock()
        context_repo.fetch_record.return_value = _make_context()["record"]
        context_repo.fetch_self_reviews.return_value = []
        context_repo.fetch_thread.return_value = []
        context_repo.fetch_upper_limit.return_value = None
        context_repo.fetch_monthly_income.return_value = 0
        job_repo = MagicMock()
        job_repo.lock_for_ownership.return_value = True
        worker_db = MagicMock()

        svc = _make_service(
            post_repo=post_repo, context_repo=context_repo,
            ai_client=ai_client, job_repo=job_repo, worker_db=worker_db,
        )
        post = {"id": 10, "kakeibo_record_id": 1, "parent_id": None}
        svc._claim = MagicMock(return_value=(1, 1))
        svc._process_one(post)

        post_repo.save_response.assert_called_once_with(10, response_text)


# =========================================================
# 3.3 process_one 異常系
# =========================================================

class TestProcessOneFailure:
    """UFS-020 〜 UFS-027"""

    def _setup_svc(self, ai_response="OK", save_result=True):
        ai_client = MagicMock()
        ai_client.generate.return_value = ai_response
        post_repo = MagicMock()
        post_repo.save_response.return_value = save_result
        context_repo = MagicMock()
        context_repo.fetch_record.return_value = _make_context()["record"]
        context_repo.fetch_self_reviews.return_value = []
        context_repo.fetch_thread.return_value = []
        context_repo.fetch_upper_limit.return_value = None
        context_repo.fetch_monthly_income.return_value = 0
        job_repo = MagicMock()
        job_repo.lock_for_ownership.return_value = True
        job_repo.get_retry_info.return_value = {"retry_count": 0, "max_retries": 3}
        worker_db = MagicMock()

        svc = _make_service(
            post_repo=post_repo, context_repo=context_repo,
            ai_client=ai_client, job_repo=job_repo, worker_db=worker_db,
        )
        svc._claim = MagicMock(return_value=(1, 1))
        return svc, post_repo, job_repo

    def test_empty_response(self):
        """UFS-020: AI 応答が空 → empty ai response で失敗処理。"""
        svc, post_repo, job_repo = self._setup_svc(ai_response="")
        svc._process_one({"id": 10, "kakeibo_record_id": 1, "parent_id": None})
        post_repo.save_response.assert_not_called()
        post_repo.recover_to_pending.assert_called_once_with(10)
        job_repo.increment_retry_and_pend.assert_called_once()
        assert "empty ai response" in job_repo.increment_retry_and_pend.call_args[0][2]

    def test_whitespace_response(self):
        """UFS-021: AI 応答が空白のみ → empty ai response で失敗処理。"""
        svc, post_repo, job_repo = self._setup_svc(ai_response="  \n  ")
        svc._process_one({"id": 10, "kakeibo_record_id": 1, "parent_id": None})
        post_repo.save_response.assert_not_called()
        post_repo.recover_to_pending.assert_called_once()
        assert "empty ai response" in job_repo.increment_retry_and_pend.call_args[0][2]

    def test_over_max_length(self):
        """UFS-022: AI 応答が 3001 文字 → content exceeded で失敗処理。"""
        svc, post_repo, job_repo = self._setup_svc(ai_response="あ" * 3001)
        svc._process_one({"id": 10, "kakeibo_record_id": 1, "parent_id": None})
        post_repo.save_response.assert_not_called()
        post_repo.recover_to_pending.assert_called_once()
        assert "content exceeded max length" in job_repo.increment_retry_and_pend.call_args[0][2]

    def test_exactly_max_length(self):
        """UFS-023: AI 応答がちょうど 3000 文字。"""
        svc, post_repo, job_repo = self._setup_svc(ai_response="あ" * 3000)
        svc._process_one({"id": 10, "kakeibo_record_id": 1, "parent_id": None})
        post_repo.save_response.assert_called_once()

    def test_api_error(self):
        """UFS-024: AI API がエラー → 正規化済みエラーで失敗処理。"""
        svc, post_repo, job_repo = self._setup_svc()
        response = MagicMock(status_code=500)
        svc._ai_client.generate.side_effect = requests.HTTPError(response=response)
        svc._process_one({"id": 10, "kakeibo_record_id": 1, "parent_id": None})
        post_repo.save_response.assert_not_called()
        post_repo.recover_to_pending.assert_called_once()
        assert "HTTPError: HTTP 500" in job_repo.increment_retry_and_pend.call_args[0][2]

    def test_record_not_found(self):
        """UFS-025: 家計簿レコードが見つからない → 投稿 FAILED + ジョブ CANCELLED。"""
        svc, post_repo, job_repo = self._setup_svc()
        svc._context_repo.fetch_record.return_value = None
        post_repo.get_ai_status.return_value = None
        svc._process_one({"id": 10, "kakeibo_record_id": 1, "parent_id": None})
        svc._ai_client.generate.assert_not_called()
        post_repo.mark_failed.assert_called_once_with(10)
        args = job_repo.mark_cancelled.call_args[0]
        assert args[2] == TerminationReason.TARGET_DELETED

    def test_save_response_false_deleted(self):
        """UFS-026: save_response が False (削除済み)。"""
        svc, post_repo, job_repo = self._setup_svc(save_result=False)
        post_repo.get_ai_status.return_value = None
        svc._process_one({"id": 10, "kakeibo_record_id": 1, "parent_id": None})
        args = job_repo.mark_cancelled.call_args[0]
        assert args[2] == TerminationReason.TARGET_DELETED

    def test_save_response_false_inconsistency(self):
        """UFS-027: save_response が False (状態不整合)。"""
        svc, post_repo, job_repo = self._setup_svc(save_result=False)
        post_repo.get_ai_status.return_value = {"ai_status_id": AiStatus.PENDING, "deleted_at": None}
        svc._process_one({"id": 10, "kakeibo_record_id": 1, "parent_id": None})
        args = job_repo.mark_cancelled.call_args[0]
        assert args[2] == TerminationReason.STATE_INCONSISTENCY


# =========================================================
# 3.4 所有権保護
# =========================================================

class TestOwnership:
    """UFS-030 〜 UFS-032"""

    def test_save_ownership_lost(self):
        """UFS-030: save_with_ownership 所有権喪失。"""
        post_repo = MagicMock()
        job_repo = MagicMock()
        job_repo.lock_for_ownership.return_value = False
        worker_db = MagicMock()

        svc = _make_service(post_repo=post_repo, job_repo=job_repo, worker_db=worker_db)
        svc._save_with_ownership(10, 1, 1, "response")

        post_repo.save_response.assert_not_called()

    def test_handle_failure_ownership_lost(self):
        """UFS-031: handle_failure 所有権喪失。"""
        post_repo = MagicMock()
        job_repo = MagicMock()
        job_repo.lock_for_ownership.return_value = False
        worker_db = MagicMock()

        svc = _make_service(post_repo=post_repo, job_repo=job_repo, worker_db=worker_db)
        svc._handle_failure(10, 1, 1, "error")

        post_repo.recover_to_pending.assert_not_called()
        post_repo.mark_failed.assert_not_called()

    def test_save_ownership_valid(self):
        """UFS-032: save_with_ownership 所有権あり。"""
        post_repo = MagicMock()
        post_repo.save_response.return_value = True
        job_repo = MagicMock()
        job_repo.lock_for_ownership.return_value = True
        worker_db = MagicMock()

        svc = _make_service(post_repo=post_repo, job_repo=job_repo, worker_db=worker_db)
        svc._save_with_ownership(10, 1, 1, "response")

        post_repo.save_response.assert_called_once()


# =========================================================
# 3.5 リトライ
# =========================================================

class TestRetry:
    """UFS-040 〜 UFS-042"""

    def test_retry_under_limit(self):
        """UFS-040: 上限未満でリトライ。"""
        post_repo = MagicMock()
        post_repo.recover_to_pending.return_value = True
        job_repo = MagicMock()
        job_repo.lock_for_ownership.return_value = True
        job_repo.get_retry_info.return_value = {"retry_count": 0, "max_retries": 3}
        worker_db = MagicMock()

        svc = _make_service(post_repo=post_repo, job_repo=job_repo, worker_db=worker_db)
        svc._handle_failure(10, 1, 1, "error")

        post_repo.recover_to_pending.assert_called_once()
        job_repo.increment_retry_and_pend.assert_called_once()

    def test_retry_at_limit(self):
        """UFS-041: 上限到達で FAILED。"""
        post_repo = MagicMock()
        post_repo.mark_failed.return_value = True
        job_repo = MagicMock()
        job_repo.lock_for_ownership.return_value = True
        job_repo.get_retry_info.return_value = {"retry_count": 3, "max_retries": 3}
        worker_db = MagicMock()

        svc = _make_service(post_repo=post_repo, job_repo=job_repo, worker_db=worker_db)
        svc._handle_failure(10, 1, 1, "error")

        post_repo.mark_failed.assert_called_once()
        job_repo.mark_failed.assert_called_once()

    def test_retry_recover_fails(self):
        """UFS-042: recover_to_pending 失敗で再評価。"""
        post_repo = MagicMock()
        post_repo.recover_to_pending.return_value = False
        post_repo.get_ai_status.return_value = {"ai_status_id": AiStatus.COMPLETED, "deleted_at": None}
        job_repo = MagicMock()
        job_repo.lock_for_ownership.return_value = True
        job_repo.get_retry_info.return_value = {"retry_count": 0, "max_retries": 3}
        worker_db = MagicMock()

        svc = _make_service(post_repo=post_repo, job_repo=job_repo, worker_db=worker_db)
        svc._handle_failure(10, 1, 1, "error")

        job_repo.mark_completed.assert_called_once()


# =========================================================
# 3.6 stale recovery
# =========================================================

class TestStaleRecovery:
    """UFS-050 〜 UFS-059"""

    def _make_job(self, retry_count=0, max_retries=3, cv=1):
        return {"id": 1, "post_id": 10, "retry_count": retry_count,
                "max_retries": max_retries, "claim_version": cv}

    def test_stale_deleted(self):
        """UFS-050: 投稿が削除済み。"""
        post_repo = MagicMock()
        post_repo.get_ai_status.return_value = {"ai_status_id": AiStatus.PENDING, "deleted_at": "2026-07-21"}
        job_repo = MagicMock()
        job_repo.lock_for_ownership.return_value = True
        worker_db = MagicMock()

        svc = _make_service(post_repo=post_repo, job_repo=job_repo, worker_db=worker_db)
        svc._recover_one(self._make_job())

        job_repo.mark_cancelled.assert_called_once()
        assert job_repo.mark_cancelled.call_args[0][2] == TerminationReason.TARGET_DELETED

    def test_stale_completed(self):
        """UFS-051: 投稿が COMPLETED。"""
        post_repo = MagicMock()
        post_repo.get_ai_status.return_value = {"ai_status_id": AiStatus.COMPLETED, "deleted_at": None}
        job_repo = MagicMock()
        job_repo.lock_for_ownership.return_value = True
        worker_db = MagicMock()

        svc = _make_service(post_repo=post_repo, job_repo=job_repo, worker_db=worker_db)
        svc._recover_one(self._make_job())

        job_repo.mark_completed.assert_called_once()

    def test_stale_failed(self):
        """UFS-052: 投稿が FAILED。"""
        post_repo = MagicMock()
        post_repo.get_ai_status.return_value = {"ai_status_id": AiStatus.FAILED, "deleted_at": None}
        job_repo = MagicMock()
        job_repo.lock_for_ownership.return_value = True
        worker_db = MagicMock()

        svc = _make_service(post_repo=post_repo, job_repo=job_repo, worker_db=worker_db)
        svc._recover_one(self._make_job())

        job_repo.mark_failed.assert_called_once()

    def test_stale_processing_retry(self):
        """UFS-053: 投稿が PROCESSING、リトライ可。"""
        post_repo = MagicMock()
        post_repo.get_ai_status.return_value = {"ai_status_id": AiStatus.PROCESSING, "deleted_at": None}
        post_repo.recover_to_pending.return_value = True
        job_repo = MagicMock()
        job_repo.lock_for_ownership.return_value = True
        worker_db = MagicMock()

        svc = _make_service(post_repo=post_repo, job_repo=job_repo, worker_db=worker_db)
        svc._recover_one(self._make_job())

        post_repo.recover_to_pending.assert_called_once()
        job_repo.increment_retry_and_pend.assert_called_once()

    def test_stale_pending_retry(self):
        """UFS-054: 投稿が PENDING、リトライ可。"""
        post_repo = MagicMock()
        post_repo.get_ai_status.return_value = {"ai_status_id": AiStatus.PENDING, "deleted_at": None}
        job_repo = MagicMock()
        job_repo.lock_for_ownership.return_value = True
        worker_db = MagicMock()

        svc = _make_service(post_repo=post_repo, job_repo=job_repo, worker_db=worker_db)
        svc._recover_one(self._make_job())

        post_repo.recover_to_pending.assert_not_called()
        job_repo.increment_retry_and_pend.assert_called_once()

    def test_stale_limit_pending(self):
        """UFS-055: 上限到達、投稿が PENDING。"""
        post_repo = MagicMock()
        post_repo.get_ai_status.return_value = {"ai_status_id": AiStatus.PENDING, "deleted_at": None}
        post_repo.force_fail.return_value = True
        job_repo = MagicMock()
        job_repo.lock_for_ownership.return_value = True
        worker_db = MagicMock()

        svc = _make_service(post_repo=post_repo, job_repo=job_repo, worker_db=worker_db)
        svc._recover_one(self._make_job(retry_count=3))

        post_repo.force_fail.assert_called_once()
        job_repo.mark_failed.assert_called_once()

    def test_stale_limit_force_fail_fails(self):
        """UFS-056: 上限到達、force_fail 失敗で再評価。"""
        post_repo = MagicMock()
        post_repo.get_ai_status.side_effect = [
            {"ai_status_id": AiStatus.PENDING, "deleted_at": None},
            {"ai_status_id": AiStatus.COMPLETED, "deleted_at": None},
        ]
        post_repo.force_fail.return_value = False
        job_repo = MagicMock()
        job_repo.lock_for_ownership.return_value = True
        worker_db = MagicMock()

        svc = _make_service(post_repo=post_repo, job_repo=job_repo, worker_db=worker_db)
        svc._recover_one(self._make_job(retry_count=3))

        job_repo.mark_completed.assert_called_once()

    def test_stale_ownership_lost(self):
        """UFS-057: 所有権喪失。"""
        job_repo = MagicMock()
        job_repo.lock_for_ownership.return_value = False
        post_repo = MagicMock()
        worker_db = MagicMock()

        svc = _make_service(post_repo=post_repo, job_repo=job_repo, worker_db=worker_db)
        svc._recover_one(self._make_job())

        post_repo.get_ai_status.assert_not_called()

    def test_stale_recover_to_pending_fails(self):
        """UFS-058: recover_to_pending 失敗で再評価。"""
        post_repo = MagicMock()
        post_repo.get_ai_status.side_effect = [
            {"ai_status_id": AiStatus.PROCESSING, "deleted_at": None},
            {"ai_status_id": AiStatus.COMPLETED, "deleted_at": None},
        ]
        post_repo.recover_to_pending.return_value = False
        job_repo = MagicMock()
        job_repo.lock_for_ownership.return_value = True
        worker_db = MagicMock()

        svc = _make_service(post_repo=post_repo, job_repo=job_repo, worker_db=worker_db)
        svc._recover_one(self._make_job())

        job_repo.mark_completed.assert_called_once()

    def test_stale_disabled(self):
        """UFS-059: stale_timeout_sec <= 0 で即 return。"""
        config = _make_config(stale_timeout_sec=0)
        job_repo = MagicMock()

        svc = _make_service(config=config, job_repo=job_repo)
        svc.recover_stale()

        job_repo.fetch_stale.assert_not_called()


# =========================================================
# 3.7 メッセージ組み立て
# =========================================================

class TestMessageBuilding:
    """UFS-060 〜 UFS-064"""

    def test_initial_contains_record_info(self):
        """UFS-060: 初回メッセージに家計簿情報。"""
        svc = _make_service()
        ctx = _make_context()
        messages = svc._build_initial_messages(ctx)
        content = messages[0]["content"]
        assert "1,500円" in content
        assert "飲食" in content
        assert "コンビニでお昼" in content

    def test_initial_contains_self_review(self):
        """UFS-061: 初回メッセージに自己レビュー。"""
        svc = _make_service()
        ctx = _make_context()
        messages = svc._build_initial_messages(ctx)
        content = messages[0]["content"]
        assert "3/5" in content
        assert "ちょっと贅沢" in content

    def test_initial_no_self_review(self):
        """UFS-062: 自己レビューなし。"""
        svc = _make_service()
        ctx = _make_context()
        ctx["self_reviews"] = []
        messages = svc._build_initial_messages(ctx)
        content = messages[0]["content"]
        assert "自己レビュー" not in content

    def test_followup_thread_in_messages(self):
        """UFS-063: スレッド履歴が messages に。"""
        svc = _make_service()
        ctx = _make_context()
        ctx["thread"] = [
            {"id": 1, "is_ai": 0, "content": "msg1"},
            {"id": 2, "is_ai": 1, "content": "msg2"},
            {"id": 3, "is_ai": 0, "content": "msg3"},
        ]
        messages = svc._build_followup_messages(ctx)
        assert len(messages) == 4
        assert "参照データ" in messages[0]["content"]
        assert messages[1]["role"] == "user"
        assert messages[2]["role"] == "assistant"

    def test_followup_background_in_messages(self):
        """UFS-064: 追加チャットの背景情報が messages 先頭に user メッセージとして含まれる。"""
        svc = _make_service()
        ctx = _make_context()
        messages = svc._build_followup_messages(ctx)
        background = messages[0]["content"]
        assert messages[0]["role"] == "user"
        assert "1,500円" in background
        assert "飲食" in background


# =========================================================
# 3.8 エラー正規化
# =========================================================

class TestSanitizeError:
    """UFS-070 〜 UFS-072"""

    def test_http_error(self):
        """UFS-070: HTTPError。"""
        response = MagicMock(status_code=500)
        e = requests.HTTPError(response=response)
        result = FeedbackService._sanitize_error(e)
        assert result == "HTTPError: HTTP 500"

    def test_timeout(self):
        """UFS-071: Timeout。"""
        e = requests.Timeout()
        result = FeedbackService._sanitize_error(e)
        assert result == "Timeout"

    def test_generic_exception(self):
        """UFS-072: 汎用例外。"""
        e = RuntimeError("something")
        result = FeedbackService._sanitize_error(e)
        assert result == "RuntimeError"


# =========================================================
# 3.9 上限設定
# =========================================================

class TestUpperLimit:
    """UFS-080 〜 UFS-085"""

    def test_initial_with_fixed_amount(self):
        """UFS-080: 固定額の上限情報が含まれる。"""
        svc = _make_service()
        ctx = _make_context(
            upper_limit={
                "upper_limit_type_id": 2, "type_name": "固定額",
                "max_value": 50000, "ave_monthly_income": None,
            },
        )
        messages = svc._build_initial_messages(ctx)
        content = messages[0]["content"]
        assert "支出上限設定" in content
        assert "固定額" in content
        assert "50,000円" in content

    def test_initial_with_percentage(self):
        """UFS-081: 割合の上限情報 (ave_monthly_income から算出)。"""
        svc = _make_service()
        ctx = _make_context(
            upper_limit={
                "upper_limit_type_id": 1, "type_name": "割合",
                "max_value": 30, "ave_monthly_income": 200000,
            },
        )
        messages = svc._build_initial_messages(ctx)
        content = messages[0]["content"]
        assert "割合" in content
        assert "30%" in content
        assert "200,000円" in content
        assert "60,000円" in content

    def test_initial_no_upper_limit(self):
        """UFS-082: 上限設定なしの場合は上限セクションなし。"""
        svc = _make_service()
        ctx = _make_context(upper_limit=None)
        messages = svc._build_initial_messages(ctx)
        content = messages[0]["content"]
        assert "支出上限設定" not in content

    def test_followup_messages_with_upper_limit(self):
        """UFS-083: 追加チャットの背景メッセージに上限情報。"""
        svc = _make_service()
        ctx = _make_context(
            upper_limit={
                "upper_limit_type_id": 2, "type_name": "固定額",
                "max_value": 30000, "ave_monthly_income": None,
            },
        )
        messages = svc._build_followup_messages(ctx)
        background = messages[0]["content"]
        assert "支出上限設定" in background
        assert "30,000円" in background

    def test_percentage_with_null_income(self):
        """UFS-084: 割合で ave_monthly_income が None の場合。"""
        svc = _make_service()
        ctx = _make_context(
            upper_limit={
                "upper_limit_type_id": 1, "type_name": "割合",
                "max_value": 30, "ave_monthly_income": None,
            },
        )
        messages = svc._build_initial_messages(ctx)
        content = messages[0]["content"]
        assert "0円" in content

    def test_build_context_includes_upper_limit(self):
        """UFS-085: _build_context に upper_limit が含まれる。"""
        from datetime import date as date_cls
        context_repo = MagicMock()
        context_repo.fetch_record.return_value = {
            "id": 1, "user_id": 1, "purchase_date": date_cls(2026, 7, 1),
            "amount": 1500, "details": "test", "amount_type_name": "支出",
            "category_name": "飲食",
        }
        context_repo.fetch_self_reviews.return_value = []
        context_repo.fetch_thread.return_value = []
        context_repo.fetch_upper_limit.return_value = {
            "upper_limit_type_id": 2, "type_name": "固定額",
            "max_value": 50000, "ave_monthly_income": None,
        }

        svc = _make_service(context_repo=context_repo)
        ctx = svc._build_context(1)

        assert ctx["upper_limit"] is not None
        assert ctx["upper_limit"]["max_value"] == 50000
        context_repo.fetch_upper_limit.assert_called_once_with(1)
