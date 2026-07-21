"""状態遷移の網羅テスト。BG 設計書の状態表を結合テストで検証する。"""

from datetime import datetime, timezone, timedelta

from src.configs.ai_status import AiStatus
from src.configs.config import Config
from src.configs.worker_status import WorkerStatus, TerminationReason
from src.databases.resmane_database import ResManeDatabase
from src.databases.resmane_worker_database import ResmaneWorkerDatabase
from src.repositories.post_repository import PostRepository
from src.repositories.worker_job_repository import WorkerJobRepository
from src.services.feedback_service import FeedbackService
from unittest.mock import MagicMock


def _insert_post(conn, post_id, ai_status_id=AiStatus.PENDING, deleted_at=None):
    now = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S")
    cursor = conn.cursor()
    cursor.execute(
        "INSERT INTO posts (id, user_id, kakeibo_record_id, ai_status_id, "
        "is_ai, created_at, updated_at, deleted_at) "
        "VALUES (%s, 1, 1, %s, 1, %s, %s, %s)",
        (post_id, ai_status_id, now, now, deleted_at),
    )
    cursor.close()


def _insert_stale_job(conn, post_id, retry_count=0, cv=1, max_retries=3):
    old = (datetime.now(timezone.utc) - timedelta(seconds=400)).strftime("%Y-%m-%d %H:%M:%S")
    now = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S")
    cursor = conn.cursor()
    cursor.execute(
        "INSERT INTO worker_jobs "
        "(post_id, status, claim_version, claimed_at, retry_count, max_retries, "
        " created_at, updated_at) "
        "VALUES (%s, %s, %s, %s, %s, %s, %s, %s)",
        (post_id, WorkerStatus.PROCESSING, cv, old, retry_count, max_retries, now, now),
    )
    cursor.close()


def _get_post_status(conn, post_id):
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT ai_status_id, deleted_at FROM posts WHERE id = %s", (post_id,))
    row = cursor.fetchone()
    cursor.close()
    return row


def _get_job(conn, post_id):
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT * FROM worker_jobs WHERE post_id = %s", (post_id,))
    row = cursor.fetchone()
    cursor.close()
    return row


def _make_service(test_config, resmane_db, worker_db):
    return FeedbackService(
        config=test_config,
        db=resmane_db,
        worker_db=worker_db,
        post_repo=PostRepository(resmane_db),
        context_repo=MagicMock(),
        job_repo=WorkerJobRepository(worker_db),
        ai_client=MagicMock(),
    )


class TestStaleRecoveryStateTable:
    """IST-001 〜 IST-008"""

    def test_pending_to_retry_pending(self, test_config, resmane_db, worker_db,
                                      raw_resmane_conn, raw_worker_conn):
        """IST-001: post=PENDING, job=PROCESSING → job=RETRY_PENDING。"""
        _insert_post(raw_resmane_conn, 1, ai_status_id=AiStatus.PENDING)
        _insert_stale_job(raw_worker_conn, 1)

        svc = _make_service(test_config, resmane_db, worker_db)
        svc.recover_stale()

        job = _get_job(raw_worker_conn, 1)
        assert job["status"] == WorkerStatus.RETRY_PENDING

    def test_processing_to_pending_retry_pending(self, test_config, resmane_db, worker_db,
                                                  raw_resmane_conn, raw_worker_conn):
        """IST-002: post=PROCESSING → PENDING, job=RETRY_PENDING。"""
        _insert_post(raw_resmane_conn, 1, ai_status_id=AiStatus.PROCESSING)
        _insert_stale_job(raw_worker_conn, 1)

        svc = _make_service(test_config, resmane_db, worker_db)
        svc.recover_stale()

        post = _get_post_status(raw_resmane_conn, 1)
        assert post["ai_status_id"] == AiStatus.PENDING
        job = _get_job(raw_worker_conn, 1)
        assert job["status"] == WorkerStatus.RETRY_PENDING

    def test_completed_syncs_job(self, test_config, resmane_db, worker_db,
                                 raw_resmane_conn, raw_worker_conn):
        """IST-003: post=COMPLETED → job=COMPLETED。"""
        _insert_post(raw_resmane_conn, 1, ai_status_id=AiStatus.COMPLETED)
        _insert_stale_job(raw_worker_conn, 1)

        svc = _make_service(test_config, resmane_db, worker_db)
        svc.recover_stale()

        job = _get_job(raw_worker_conn, 1)
        assert job["status"] == WorkerStatus.COMPLETED

    def test_failed_syncs_job(self, test_config, resmane_db, worker_db,
                              raw_resmane_conn, raw_worker_conn):
        """IST-004: post=FAILED → job=FAILED。"""
        _insert_post(raw_resmane_conn, 1, ai_status_id=AiStatus.FAILED)
        _insert_stale_job(raw_worker_conn, 1)

        svc = _make_service(test_config, resmane_db, worker_db)
        svc.recover_stale()

        job = _get_job(raw_worker_conn, 1)
        assert job["status"] == WorkerStatus.FAILED

    def test_deleted_cancels_job(self, test_config, resmane_db, worker_db,
                                 raw_resmane_conn, raw_worker_conn):
        """IST-005: post=削除済み → job=CANCELLED。"""
        _insert_post(raw_resmane_conn, 1, deleted_at="2026-07-21 00:00:00")
        _insert_stale_job(raw_worker_conn, 1)

        svc = _make_service(test_config, resmane_db, worker_db)
        svc.recover_stale()

        job = _get_job(raw_worker_conn, 1)
        assert job["status"] == WorkerStatus.CANCELLED
        assert job["termination_reason"] == TerminationReason.TARGET_DELETED

    def test_nonexistent_cancels_job(self, test_config, resmane_db, worker_db,
                                     raw_worker_conn):
        """IST-006: post=存在しない → job=CANCELLED。"""
        _insert_stale_job(raw_worker_conn, 999)

        svc = _make_service(test_config, resmane_db, worker_db)
        svc.recover_stale()

        job = _get_job(raw_worker_conn, 999)
        assert job["status"] == WorkerStatus.CANCELLED

    def test_max_retry_pending_force_fail(self, test_config, resmane_db, worker_db,
                                          raw_resmane_conn, raw_worker_conn):
        """IST-007: post=PENDING, retry>=max → post=FAILED, job=FAILED。"""
        _insert_post(raw_resmane_conn, 1, ai_status_id=AiStatus.PENDING)
        _insert_stale_job(raw_worker_conn, 1, retry_count=3, max_retries=3)

        svc = _make_service(test_config, resmane_db, worker_db)
        svc.recover_stale()

        post = _get_post_status(raw_resmane_conn, 1)
        assert post["ai_status_id"] == AiStatus.FAILED
        job = _get_job(raw_worker_conn, 1)
        assert job["status"] == WorkerStatus.FAILED

    def test_max_retry_processing_force_fail(self, test_config, resmane_db, worker_db,
                                              raw_resmane_conn, raw_worker_conn):
        """IST-008: post=PROCESSING, retry>=max → post=FAILED, job=FAILED。"""
        _insert_post(raw_resmane_conn, 1, ai_status_id=AiStatus.PROCESSING)
        _insert_stale_job(raw_worker_conn, 1, retry_count=3, max_retries=3)

        svc = _make_service(test_config, resmane_db, worker_db)
        svc.recover_stale()

        post = _get_post_status(raw_resmane_conn, 1)
        assert post["ai_status_id"] == AiStatus.FAILED
        job = _get_job(raw_worker_conn, 1)
        assert job["status"] == WorkerStatus.FAILED


class TestIntermediateStopRecovery:
    """IST-010 〜 IST-013"""

    def test_upsert_then_crash(self, test_config, resmane_db, worker_db,
                                raw_resmane_conn, raw_worker_conn):
        """IST-010: upsert 後、posts 更新前に停止 → stale で回収。"""
        _insert_post(raw_resmane_conn, 1, ai_status_id=AiStatus.PENDING)
        _insert_stale_job(raw_worker_conn, 1)

        svc = _make_service(test_config, resmane_db, worker_db)
        svc.recover_stale()

        job = _get_job(raw_worker_conn, 1)
        assert job["status"] == WorkerStatus.RETRY_PENDING

    def test_processing_then_crash(self, test_config, resmane_db, worker_db,
                                    raw_resmane_conn, raw_worker_conn):
        """IST-011: posts=PROCESSING 後、AI 呼び出し前に停止。"""
        _insert_post(raw_resmane_conn, 1, ai_status_id=AiStatus.PROCESSING)
        _insert_stale_job(raw_worker_conn, 1)

        svc = _make_service(test_config, resmane_db, worker_db)
        svc.recover_stale()

        post = _get_post_status(raw_resmane_conn, 1)
        assert post["ai_status_id"] == AiStatus.PENDING
        job = _get_job(raw_worker_conn, 1)
        assert job["status"] == WorkerStatus.RETRY_PENDING

    def test_ai_complete_then_crash(self, test_config, resmane_db, worker_db,
                                     raw_resmane_conn, raw_worker_conn):
        """IST-012: AI 完了後、save_response 前に停止。"""
        _insert_post(raw_resmane_conn, 1, ai_status_id=AiStatus.PROCESSING)
        _insert_stale_job(raw_worker_conn, 1)

        svc = _make_service(test_config, resmane_db, worker_db)
        svc.recover_stale()

        post = _get_post_status(raw_resmane_conn, 1)
        assert post["ai_status_id"] == AiStatus.PENDING
        job = _get_job(raw_worker_conn, 1)
        assert job["status"] == WorkerStatus.RETRY_PENDING

    def test_save_response_then_crash(self, test_config, resmane_db, worker_db,
                                       raw_resmane_conn, raw_worker_conn):
        """IST-013: save_response 後、mark_completed 前に停止。"""
        _insert_post(raw_resmane_conn, 1, ai_status_id=AiStatus.COMPLETED)
        _insert_stale_job(raw_worker_conn, 1)

        svc = _make_service(test_config, resmane_db, worker_db)
        svc.recover_stale()

        job = _get_job(raw_worker_conn, 1)
        assert job["status"] == WorkerStatus.COMPLETED
