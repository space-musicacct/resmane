"""デッドロック検証。threading で並行操作し、デッドロックが発生しないことを確認する。"""

import threading
from datetime import datetime, timezone, timedelta
from unittest.mock import MagicMock

from src.configs.ai_status import AiStatus
from src.configs.worker_status import WorkerStatus, TerminationReason
from src.databases.resmane_database import ResManeDatabase
from src.databases.resmane_worker_database import ResmaneWorkerDatabase
from src.repositories.post_repository import PostRepository
from src.repositories.worker_job_repository import WorkerJobRepository
from src.repositories.sync_watermark_repository import SyncWatermarkRepository
from src.services.feedback_service import FeedbackService
from src.services.delete_sync_service import DeleteSyncService


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


def _insert_stale_job(conn, post_id, cv=1, status=WorkerStatus.PROCESSING):
    old = (datetime.now(timezone.utc) - timedelta(seconds=400)).strftime("%Y-%m-%d %H:%M:%S")
    now = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S")
    cursor = conn.cursor()
    cursor.execute(
        "INSERT INTO worker_jobs "
        "(post_id, status, claim_version, claimed_at, created_at, updated_at) "
        "VALUES (%s, %s, %s, %s, %s, %s)",
        (post_id, status, cv, old, now, now),
    )
    cursor.close()


def _get_job(conn, post_id):
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT * FROM worker_jobs WHERE post_id = %s", (post_id,))
    row = cursor.fetchone()
    cursor.close()
    return row


def _get_post(conn, post_id):
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT ai_status_id FROM posts WHERE id = %s", (post_id,))
    row = cursor.fetchone()
    cursor.close()
    return row


def _make_feedback_service(test_config):
    db = ResManeDatabase(test_config)
    wdb = ResmaneWorkerDatabase(test_config)
    return FeedbackService(
        config=test_config,
        db=db,
        worker_db=wdb,
        post_repo=PostRepository(db),
        context_repo=MagicMock(),
        job_repo=WorkerJobRepository(wdb),
        ai_client=MagicMock(),
    ), db, wdb


def _make_delete_sync_service(test_config):
    db = ResManeDatabase(test_config)
    wdb = ResmaneWorkerDatabase(test_config)
    return DeleteSyncService(
        worker_db=wdb,
        post_repo=PostRepository(db),
        job_repo=WorkerJobRepository(wdb),
        watermark_repo=SyncWatermarkRepository(wdb),
    ), db, wdb


def _assert_threads_finished(*threads):
    for t in threads:
        assert not t.is_alive(), f"Thread {t.name} がデッドロックまたはタイムアウト"


class TestClaimConcurrency:
    """IDL-001, IDL-002"""

    def test_two_workers_same_post(self, test_config, resmane_db, worker_db, raw_resmane_conn):
        """IDL-001: 2 Worker が同じ post_id を同時 claim。"""
        _insert_post(raw_resmane_conn, 1)

        results = [None, None]
        errors = []

        def claim_worker(idx):
            try:
                wdb = ResmaneWorkerDatabase(test_config)
                repo = WorkerJobRepository(wdb)
                results[idx] = repo.upsert(1)
                wdb.close()
            except Exception as e:
                errors.append(e)

        t1 = threading.Thread(target=claim_worker, args=(0,), name="worker-A")
        t2 = threading.Thread(target=claim_worker, args=(1,), name="worker-B")
        t1.start()
        t2.start()
        t1.join(timeout=10)
        t2.join(timeout=10)

        _assert_threads_finished(t1, t2)
        assert not errors, f"エラー発生: {errors}"
        success = [r for r in results if r is not None]
        assert len(success) == 1

    def test_claim_and_stale_recovery_on_same_job(self, test_config, resmane_db, worker_db,
                                                   raw_resmane_conn, raw_worker_conn):
        """IDL-002: stale recovery と _claim が同じジョブで同時実行。"""
        _insert_post(raw_resmane_conn, 1, ai_status_id=AiStatus.PROCESSING)
        _insert_stale_job(raw_worker_conn, 1, cv=1)

        errors = []
        barrier = threading.Barrier(2, timeout=10)
        stale_done = threading.Event()
        claim_result = [None]

        def do_stale():
            try:
                svc, db, wdb = _make_feedback_service(test_config)
                barrier.wait()
                svc.recover_stale()
                stale_done.set()
                db.close()
                wdb.close()
            except Exception as e:
                errors.append(e)
                stale_done.set()

        def do_claim():
            try:
                svc, db, wdb = _make_feedback_service(test_config)
                barrier.wait()
                stale_done.wait(timeout=10)
                claim_result[0] = svc._claim(1)
                db.close()
                wdb.close()
            except Exception as e:
                errors.append(e)

        t1 = threading.Thread(target=do_stale, name="stale")
        t2 = threading.Thread(target=do_claim, name="claim")
        t1.start()
        t2.start()
        t1.join(timeout=15)
        t2.join(timeout=15)

        _assert_threads_finished(t1, t2)
        assert not errors, f"エラー発生: {errors}"

        job = _get_job(raw_worker_conn, 1)
        post = _get_post(raw_resmane_conn, 1)
        assert job is not None

        if claim_result[0] is not None:
            assert job["status"] == WorkerStatus.PROCESSING
            assert job["claim_version"] >= 2
            assert post["ai_status_id"] == AiStatus.PROCESSING
        else:
            assert job["status"] in (WorkerStatus.RETRY_PENDING, WorkerStatus.PROCESSING)


class TestWriteBackConcurrency:
    """IDL-010 〜 IDL-012"""

    def test_save_and_stale_concurrent(self, test_config, resmane_db, worker_db,
                                       raw_resmane_conn, raw_worker_conn):
        """IDL-010: save_with_ownership と recover_one の同時実行。最終状態が一貫している。"""
        _insert_post(raw_resmane_conn, 1, ai_status_id=AiStatus.PROCESSING)
        _insert_stale_job(raw_worker_conn, 1, cv=1)

        errors = []
        barrier = threading.Barrier(2, timeout=10)

        def do_save():
            try:
                svc, db, wdb = _make_feedback_service(test_config)
                barrier.wait()
                svc._save_with_ownership(1, 1, 1, "AI応答")
                db.close()
                wdb.close()
            except Exception as e:
                errors.append(e)

        def do_stale():
            try:
                svc, db, wdb = _make_feedback_service(test_config)
                barrier.wait()
                svc._recover_one({
                    "id": 1, "post_id": 1, "retry_count": 0,
                    "max_retries": 3, "claim_version": 1,
                })
                db.close()
                wdb.close()
            except Exception as e:
                errors.append(e)

        t1 = threading.Thread(target=do_save, name="save-worker")
        t2 = threading.Thread(target=do_stale, name="stale-worker")
        t1.start()
        t2.start()
        t1.join(timeout=10)
        t2.join(timeout=10)

        _assert_threads_finished(t1, t2)
        assert not errors, f"デッドロック発生: {errors}"

        post = _get_post(raw_resmane_conn, 1)
        job = _get_job(raw_worker_conn, 1)

        if post["ai_status_id"] == AiStatus.COMPLETED:
            assert job["status"] == WorkerStatus.COMPLETED
        elif post["ai_status_id"] == AiStatus.PENDING:
            assert job["status"] == WorkerStatus.RETRY_PENDING
        else:
            raise AssertionError(
                f"不正な最終状態: post={post['ai_status_id']}, job={job['status']}"
            )

    def test_old_worker_mark_completed(self, test_config, worker_db, raw_worker_conn):
        """IDL-011: 旧 cv と新 cv の mark_completed 競合。"""
        _insert_stale_job(raw_worker_conn, 1, cv=2)

        errors = []
        results = [None, None]

        def mark(idx, cv):
            try:
                wdb = ResmaneWorkerDatabase(test_config)
                repo = WorkerJobRepository(wdb)
                results[idx] = repo.mark_completed(1, cv)
                wdb.close()
            except Exception as e:
                errors.append(e)

        t1 = threading.Thread(target=mark, args=(0, 1), name="old-cv")
        t2 = threading.Thread(target=mark, args=(1, 2), name="new-cv")
        t1.start()
        t2.start()
        t1.join(timeout=10)
        t2.join(timeout=10)

        _assert_threads_finished(t1, t2)
        assert not errors
        assert results[0] is False
        assert results[1] is True

    def test_old_worker_handle_failure_blocked(self, test_config, resmane_db, worker_db,
                                                raw_resmane_conn, raw_worker_conn):
        """IDL-012: 旧 Worker の _handle_failure が新 claim を破壊しない。"""
        _insert_post(raw_resmane_conn, 1, ai_status_id=AiStatus.PROCESSING)
        _insert_stale_job(raw_worker_conn, 1, cv=2)

        errors = []

        def old_worker_failure():
            try:
                svc, db, wdb = _make_feedback_service(test_config)
                svc._handle_failure(1, 1, 1, "old worker error")
                db.close()
                wdb.close()
            except Exception as e:
                errors.append(e)

        t = threading.Thread(target=old_worker_failure, name="old-worker")
        t.start()
        t.join(timeout=10)

        _assert_threads_finished(t)
        assert not errors

        post = _get_post(raw_resmane_conn, 1)
        assert post["ai_status_id"] == AiStatus.PROCESSING


class TestDeleteSyncConcurrency:
    """IDL-020, IDL-021"""

    def test_sync_and_claim_same_post(self, test_config, resmane_db, worker_db,
                                       raw_resmane_conn, raw_worker_conn):
        """IDL-020: 同一 post_id で sync と claim を同時実行。削除済み投稿は claim 不可。"""
        now = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S")
        _insert_post(raw_resmane_conn, 1, ai_status_id=AiStatus.PENDING, deleted_at=now)
        _insert_stale_job(raw_worker_conn, 1, cv=1)

        errors = []
        barrier = threading.Barrier(2, timeout=10)
        claim_result = [None]

        def do_sync():
            try:
                svc, db, wdb = _make_delete_sync_service(test_config)
                barrier.wait()
                svc.sync()
                db.close()
                wdb.close()
            except Exception as e:
                errors.append(e)

        def do_claim():
            try:
                svc, db, wdb = _make_feedback_service(test_config)
                barrier.wait()
                claim_result[0] = svc._claim(1)
                db.close()
                wdb.close()
            except Exception as e:
                errors.append(e)

        t1 = threading.Thread(target=do_sync, name="sync")
        t2 = threading.Thread(target=do_claim, name="claim")
        t1.start()
        t2.start()
        t1.join(timeout=10)
        t2.join(timeout=10)

        _assert_threads_finished(t1, t2)
        assert not errors, f"デッドロック発生: {errors}"

        job = _get_job(raw_worker_conn, 1)
        assert job is not None
        assert job["status"] == WorkerStatus.CANCELLED
        assert job["termination_reason"] == "target_deleted"
        assert job["deleted_at"] is not None
        assert claim_result[0] is None

    def test_two_syncs_concurrent(self, test_config, resmane_db, worker_db, raw_resmane_conn):
        """IDL-021: 2 sync の同時実行。watermark の FOR UPDATE で直列化。"""
        now = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S")
        _insert_post(raw_resmane_conn, 1, deleted_at=now)

        errors = []
        barrier = threading.Barrier(2, timeout=10)

        def do_sync():
            try:
                svc, db, wdb = _make_delete_sync_service(test_config)
                barrier.wait()
                svc.sync()
                db.close()
                wdb.close()
            except Exception as e:
                errors.append(e)

        t1 = threading.Thread(target=do_sync, name="sync-A")
        t2 = threading.Thread(target=do_sync, name="sync-B")
        t1.start()
        t2.start()
        t1.join(timeout=10)
        t2.join(timeout=10)

        _assert_threads_finished(t1, t2)
        assert not errors, f"デッドロック発生: {errors}"
