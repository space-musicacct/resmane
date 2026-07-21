"""PostRepository の単体テスト (rowcount 判定ロジック)。"""

from unittest.mock import MagicMock

from src.configs.ai_status import AiStatus
from src.repositories.post_repository import PostRepository


def _make_repo(rowcount=0):
    db = MagicMock()
    cursor = MagicMock()
    cursor.rowcount = rowcount
    conn = MagicMock()
    conn.cursor.return_value = cursor
    db.get_connection.return_value = conn
    return PostRepository(db)


class TestSaveResponse:
    """UPR-001, UPR-002"""

    def test_rowcount_1_returns_true(self):
        """UPR-001"""
        repo = _make_repo(rowcount=1)
        assert repo.save_response(1, "content") is True

    def test_rowcount_0_returns_false(self):
        """UPR-002"""
        repo = _make_repo(rowcount=0)
        assert repo.save_response(1, "content") is False


class TestMarkFailed:
    """UPR-003, UPR-004"""

    def test_rowcount_1_returns_true(self):
        """UPR-003"""
        repo = _make_repo(rowcount=1)
        assert repo.mark_failed(1) is True

    def test_rowcount_0_returns_false(self):
        """UPR-004"""
        repo = _make_repo(rowcount=0)
        assert repo.mark_failed(1) is False


class TestForceFail:
    """UPR-005, UPR-006"""

    def test_rowcount_1_returns_true(self):
        """UPR-005"""
        repo = _make_repo(rowcount=1)
        assert repo.force_fail(1) is True

    def test_rowcount_0_returns_false(self):
        """UPR-006"""
        repo = _make_repo(rowcount=0)
        assert repo.force_fail(1) is False


class TestRecoverToPending:
    """UPR-007, UPR-008"""

    def test_rowcount_1_returns_true(self):
        """UPR-007"""
        repo = _make_repo(rowcount=1)
        assert repo.recover_to_pending(1) is True

    def test_rowcount_0_returns_false(self):
        """UPR-008"""
        repo = _make_repo(rowcount=0)
        assert repo.recover_to_pending(1) is False
