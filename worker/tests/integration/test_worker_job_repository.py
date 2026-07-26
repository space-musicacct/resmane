"""WorkerJobRepository の結合テスト。"""

from datetime import datetime, timezone, timedelta

from src.configs.ai_status import AiStatus
from src.configs.worker_status import WorkerStatus
from src.repositories.worker_job_repository import WorkerJobRepository


def _insert_job(conn, post_id, status=WorkerStatus.PROCESSING, cv=1,
                retry_count=0, claimed_at=None, deleted_at=None):
    now = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S")
    claimed = claimed_at or now
    cursor = conn.cursor()
    cursor.execute(
        "INSERT INTO worker_jobs "
        "(post_id, status, claim_version, claimed_at, retry_count, "
        " created_at, updated_at, deleted_at) "
        "VALUES (%s, %s, %s, %s, %s, %s, %s, %s)",
        (post_id, status, cv, claimed, retry_count, now, now, deleted_at),
    )
    cursor.close()
    return cursor.lastrowid


class TestUpsert:
    """IWR-001 〜 IWR-005"""

    def test_initial_claim(self, worker_db):
        """IWR-001"""
        repo = WorkerJobRepository(worker_db)
        result = repo.upsert(100)
        assert result is not None
        job_id, cv = result
        assert cv == 1

    def test_duplicate_insert_ignored(self, worker_db, raw_worker_conn):
        """IWR-002"""
        _insert_job(raw_worker_conn, 100, status=WorkerStatus.PROCESSING)
        repo = WorkerJobRepository(worker_db)
        result = repo.upsert(100)
        assert result is None

    def test_retry_pending_reclaim(self, worker_db, raw_worker_conn):
        """IWR-003"""
        _insert_job(raw_worker_conn, 100, status=WorkerStatus.RETRY_PENDING, cv=2)
        repo = WorkerJobRepository(worker_db)
        result = repo.upsert(100)
        assert result is not None
        _, cv = result
        assert cv == 3

    def test_completed_not_reclaimable(self, worker_db, raw_worker_conn):
        """IWR-004"""
        _insert_job(raw_worker_conn, 100, status=WorkerStatus.COMPLETED)
        repo = WorkerJobRepository(worker_db)
        assert repo.upsert(100) is None

    def test_failed_not_reclaimable(self, worker_db, raw_worker_conn):
        """IWR-005"""
        _insert_job(raw_worker_conn, 100, status=WorkerStatus.FAILED)
        repo = WorkerJobRepository(worker_db)
        assert repo.upsert(100) is None


class TestOwnership:
    """IWR-010 〜 IWR-018"""

    def test_lock_correct_version(self, worker_db, raw_worker_conn):
        """IWR-010"""
        _insert_job(raw_worker_conn, 100, cv=2)
        repo = WorkerJobRepository(worker_db)
        worker_db.begin_transaction()
        assert repo.lock_for_ownership(1, 2) is True
        worker_db.rollback()

    def test_lock_wrong_version(self, worker_db, raw_worker_conn):
        """IWR-011"""
        _insert_job(raw_worker_conn, 100, cv=2)
        repo = WorkerJobRepository(worker_db)
        worker_db.begin_transaction()
        assert repo.lock_for_ownership(1, 1) is False
        worker_db.rollback()

    def test_lock_retry_pending_status(self, worker_db, raw_worker_conn):
        """IWR-012"""
        _insert_job(raw_worker_conn, 100, status=WorkerStatus.RETRY_PENDING, cv=1)
        repo = WorkerJobRepository(worker_db)
        worker_db.begin_transaction()
        assert repo.lock_for_ownership(1, 1) is False
        worker_db.rollback()

    def test_mark_completed_success(self, worker_db, raw_worker_conn):
        """IWR-013"""
        _insert_job(raw_worker_conn, 100, cv=1)
        repo = WorkerJobRepository(worker_db)
        assert repo.mark_completed(1, 1) is True
        cursor = raw_worker_conn.cursor(dictionary=True)
        cursor.execute("SELECT status FROM worker_jobs WHERE id = 1")
        assert cursor.fetchone()["status"] == WorkerStatus.COMPLETED
        cursor.close()

    def test_mark_completed_wrong_version(self, worker_db, raw_worker_conn):
        """IWR-014"""
        _insert_job(raw_worker_conn, 100, cv=2)
        repo = WorkerJobRepository(worker_db)
        assert repo.mark_completed(1, 1) is False
        cursor = raw_worker_conn.cursor(dictionary=True)
        cursor.execute("SELECT status FROM worker_jobs WHERE id = 1")
        assert cursor.fetchone()["status"] == WorkerStatus.PROCESSING
        cursor.close()

    def test_mark_failed_success(self, worker_db, raw_worker_conn):
        """IWR-015"""
        _insert_job(raw_worker_conn, 100, cv=1)
        repo = WorkerJobRepository(worker_db)
        assert repo.mark_failed(1, 1, "test error") is True
        cursor = raw_worker_conn.cursor(dictionary=True)
        cursor.execute("SELECT last_error FROM worker_jobs WHERE id = 1")
        assert cursor.fetchone()["last_error"] == "test error"
        cursor.close()

    def test_mark_cancelled_success(self, worker_db, raw_worker_conn):
        """IWR-016"""
        _insert_job(raw_worker_conn, 100, cv=1)
        repo = WorkerJobRepository(worker_db)
        assert repo.mark_cancelled(1, 1, "target_deleted") is True
        cursor = raw_worker_conn.cursor(dictionary=True)
        cursor.execute("SELECT termination_reason FROM worker_jobs WHERE id = 1")
        assert cursor.fetchone()["termination_reason"] == "target_deleted"
        cursor.close()

    def test_increment_retry_success(self, worker_db, raw_worker_conn):
        """IWR-017"""
        _insert_job(raw_worker_conn, 100, cv=1)
        repo = WorkerJobRepository(worker_db)
        assert repo.increment_retry_and_pend(1, 1, "timeout") is True
        cursor = raw_worker_conn.cursor(dictionary=True)
        cursor.execute("SELECT retry_count, status FROM worker_jobs WHERE id = 1")
        row = cursor.fetchone()
        cursor.close()
        assert row["retry_count"] == 1
        assert row["status"] == WorkerStatus.RETRY_PENDING

    def test_increment_retry_wrong_version(self, worker_db, raw_worker_conn):
        """IWR-018"""
        _insert_job(raw_worker_conn, 100, cv=2)
        repo = WorkerJobRepository(worker_db)
        assert repo.increment_retry_and_pend(1, 1, "timeout") is False
        cursor = raw_worker_conn.cursor(dictionary=True)
        cursor.execute("SELECT retry_count FROM worker_jobs WHERE id = 1")
        assert cursor.fetchone()["retry_count"] == 0
        cursor.close()


class TestFetchStale:
    """IWR-020 〜 IWR-023"""

    def test_stale_detected(self, worker_db, raw_worker_conn):
        """IWR-020"""
        old = (datetime.now(timezone.utc) - timedelta(seconds=400)).strftime("%Y-%m-%d %H:%M:%S")
        _insert_job(raw_worker_conn, 100, claimed_at=old, cv=3)
        repo = WorkerJobRepository(worker_db)
        result = repo.fetch_stale(300)
        assert len(result) == 1
        assert result[0]["claim_version"] == 3

    def test_not_stale_yet(self, worker_db, raw_worker_conn):
        """IWR-021"""
        recent = (datetime.now(timezone.utc) - timedelta(seconds=100)).strftime("%Y-%m-%d %H:%M:%S")
        _insert_job(raw_worker_conn, 100, claimed_at=recent)
        repo = WorkerJobRepository(worker_db)
        assert len(repo.fetch_stale(300)) == 0

    def test_retry_pending_excluded(self, worker_db, raw_worker_conn):
        """IWR-022"""
        old = (datetime.now(timezone.utc) - timedelta(seconds=400)).strftime("%Y-%m-%d %H:%M:%S")
        _insert_job(raw_worker_conn, 100, status=WorkerStatus.RETRY_PENDING, claimed_at=old)
        repo = WorkerJobRepository(worker_db)
        assert len(repo.fetch_stale(300)) == 0

    def test_deleted_excluded(self, worker_db, raw_worker_conn):
        """IWR-023"""
        old = (datetime.now(timezone.utc) - timedelta(seconds=400)).strftime("%Y-%m-%d %H:%M:%S")
        _insert_job(raw_worker_conn, 100, claimed_at=old, deleted_at="2026-07-21 00:00:00")
        repo = WorkerJobRepository(worker_db)
        assert len(repo.fetch_stale(300)) == 0


class TestDeleteSync:
    """IWR-030 〜 IWR-033"""

    def test_cancel_processing(self, worker_db, raw_worker_conn):
        """IWR-030"""
        _insert_job(raw_worker_conn, 10)
        _insert_job(raw_worker_conn, 11)
        repo = WorkerJobRepository(worker_db)
        assert repo.cancel_processing_by_post_ids([10, 11]) == 2

    def test_cancel_skips_completed(self, worker_db, raw_worker_conn):
        """IWR-031"""
        _insert_job(raw_worker_conn, 10, status=WorkerStatus.COMPLETED)
        repo = WorkerJobRepository(worker_db)
        assert repo.cancel_processing_by_post_ids([10]) == 0

    def test_soft_delete(self, worker_db, raw_worker_conn):
        """IWR-032"""
        _insert_job(raw_worker_conn, 10)
        _insert_job(raw_worker_conn, 11)
        _insert_job(raw_worker_conn, 12)
        repo = WorkerJobRepository(worker_db)
        assert repo.soft_delete_by_post_ids([10, 11, 12]) == 3

    def test_soft_delete_skips_already_deleted(self, worker_db, raw_worker_conn):
        """IWR-033"""
        _insert_job(raw_worker_conn, 10, deleted_at="2026-07-21 00:00:00")
        repo = WorkerJobRepository(worker_db)
        assert repo.soft_delete_by_post_ids([10]) == 0
