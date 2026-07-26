"""WorkerJobRepository の単体テスト。"""

from unittest.mock import MagicMock

from src.configs.worker_status import WorkerStatus
from src.repositories.worker_job_repository import WorkerJobRepository


def _make_repo(insert_rowcount=0, update_rowcount=0, fetchone=None, lastrowid=1):
    db = MagicMock()
    cursor = MagicMock()
    cursor.rowcount = insert_rowcount
    cursor.lastrowid = lastrowid
    cursor.fetchone.return_value = fetchone
    conn = MagicMock()
    conn.cursor.return_value = cursor

    def set_rowcount(*args, **kwargs):
        nonlocal insert_rowcount, update_rowcount
        if cursor.execute.call_count == 1:
            cursor.rowcount = insert_rowcount
        else:
            cursor.rowcount = update_rowcount

    cursor.execute.side_effect = set_rowcount
    db.get_connection.return_value = conn
    return WorkerJobRepository(db), cursor


class TestUpsert:
    """UWR-001 〜 UWR-003"""

    def test_initial_insert_success(self):
        """UWR-001: 初回 INSERT 成功。"""
        repo, cursor = _make_repo(insert_rowcount=1, lastrowid=42)
        result = repo.upsert(10)
        assert result == (42, 1)

    def test_retry_update_success(self):
        """UWR-002: リトライ UPDATE 成功。"""
        repo, cursor = _make_repo(
            insert_rowcount=0, update_rowcount=1,
            fetchone={"id": 5, "claim_version": 3},
        )
        result = repo.upsert(10)
        assert result == (5, 3)

    def test_claim_failure(self):
        """UWR-003: claim 失敗。"""
        repo, cursor = _make_repo(insert_rowcount=0, update_rowcount=0)
        result = repo.upsert(10)
        assert result is None


class TestLockForOwnership:
    """UWR-004, UWR-005"""

    def test_lock_success(self):
        """UWR-004: 成功。"""
        db = MagicMock()
        cursor = MagicMock()
        cursor.fetchone.return_value = {"id": 1}
        db.get_connection.return_value.cursor.return_value = cursor
        repo = WorkerJobRepository(db)
        assert repo.lock_for_ownership(1, 1) is True

    def test_lock_failure(self):
        """UWR-005: 失敗。"""
        db = MagicMock()
        cursor = MagicMock()
        cursor.fetchone.return_value = None
        db.get_connection.return_value.cursor.return_value = cursor
        repo = WorkerJobRepository(db)
        assert repo.lock_for_ownership(1, 1) is False


class TestMarkCompleted:
    """UWR-006, UWR-007"""

    def test_ownership_match(self):
        """UWR-006: 所有権あり。"""
        db = MagicMock()
        cursor = MagicMock()
        cursor.rowcount = 1
        db.get_connection.return_value.cursor.return_value = cursor
        repo = WorkerJobRepository(db)
        assert repo.mark_completed(1, 1) is True

    def test_ownership_mismatch(self):
        """UWR-007: 所有権なし。"""
        db = MagicMock()
        cursor = MagicMock()
        cursor.rowcount = 0
        db.get_connection.return_value.cursor.return_value = cursor
        repo = WorkerJobRepository(db)
        assert repo.mark_completed(1, 99) is False
