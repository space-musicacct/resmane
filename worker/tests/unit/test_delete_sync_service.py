"""DeleteSyncService の単体テスト。"""

from unittest.mock import MagicMock, call
from datetime import datetime

import pytest

from src.services.delete_sync_service import DeleteSyncService


class TestSync:
    """UDS-001 〜 UDS-003"""

    def test_sync_no_diff(self):
        """UDS-001: 差分なし。"""
        worker_db = MagicMock()
        post_repo = MagicMock()
        post_repo.fetch_deleted_since.return_value = []
        watermark_repo = MagicMock()
        watermark_repo.get_for_update.return_value = {
            "last_deleted_at": "1970-01-01 00:00:00", "last_id": 0,
        }
        job_repo = MagicMock()

        svc = DeleteSyncService(worker_db, post_repo, job_repo, watermark_repo)
        svc.sync()

        watermark_repo.save.assert_not_called()
        worker_db.commit.assert_called_once()

    def test_sync_with_diff(self):
        """UDS-002: 差分あり。"""
        worker_db = MagicMock()
        post_repo = MagicMock()
        post_repo.fetch_deleted_since.return_value = [
            {"id": 10, "deleted_at": datetime(2026, 7, 21, 5, 0, 0)},
            {"id": 11, "deleted_at": datetime(2026, 7, 21, 5, 0, 1)},
            {"id": 12, "deleted_at": datetime(2026, 7, 21, 5, 0, 2)},
        ]
        watermark_repo = MagicMock()
        watermark_repo.get_for_update.return_value = {
            "last_deleted_at": "1970-01-01 00:00:00", "last_id": 0,
        }
        job_repo = MagicMock()
        job_repo.cancel_processing_by_post_ids.return_value = 1
        job_repo.soft_delete_by_post_ids.return_value = 3

        svc = DeleteSyncService(worker_db, post_repo, job_repo, watermark_repo)
        svc.sync()

        job_repo.cancel_processing_by_post_ids.assert_called_once_with([10, 11, 12])
        job_repo.soft_delete_by_post_ids.assert_called_once_with([10, 11, 12])
        watermark_repo.save.assert_called_once()

    def test_sync_failure_rollback(self):
        """UDS-003: 失敗時に rollback + re-raise。"""
        worker_db = MagicMock()
        watermark_repo = MagicMock()
        watermark_repo.get_for_update.return_value = {
            "last_deleted_at": "1970-01-01 00:00:00", "last_id": 0,
        }
        post_repo = MagicMock()
        post_repo.fetch_deleted_since.return_value = [
            {"id": 10, "deleted_at": datetime(2026, 7, 21)},
        ]
        job_repo = MagicMock()
        job_repo.cancel_processing_by_post_ids.side_effect = RuntimeError("db error")

        svc = DeleteSyncService(worker_db, post_repo, job_repo, watermark_repo)
        with pytest.raises(RuntimeError):
            svc.sync()

        worker_db.rollback.assert_called_once()


class TestReconcile:
    """UDS-004 〜 UDS-007"""

    def test_reconcile_one_batch(self):
        """UDS-004: 1 バッチで完了。"""
        worker_db = MagicMock()
        post_repo = MagicMock()
        post_repo.fetch_deleted_since.side_effect = [
            [{"id": i, "deleted_at": datetime(2026, 7, 21)} for i in range(50)],
            [],
        ]
        job_repo = MagicMock()
        job_repo.cancel_processing_by_post_ids.return_value = 0
        job_repo.soft_delete_by_post_ids.return_value = 0

        svc = DeleteSyncService(worker_db, post_repo, job_repo, MagicMock())
        svc.reconcile()

        assert post_repo.fetch_deleted_since.call_count == 2

    def test_reconcile_multiple_batches(self):
        """UDS-005: 複数バッチ。"""
        worker_db = MagicMock()
        post_repo = MagicMock()
        batch1 = [{"id": i, "deleted_at": datetime(2026, 7, 21)} for i in range(1000)]
        batch2 = [{"id": i + 1000, "deleted_at": datetime(2026, 7, 21)} for i in range(500)]
        post_repo.fetch_deleted_since.side_effect = [batch1, batch2, []]
        job_repo = MagicMock()
        job_repo.cancel_processing_by_post_ids.return_value = 0
        job_repo.soft_delete_by_post_ids.return_value = 0

        svc = DeleteSyncService(worker_db, post_repo, job_repo, MagicMock())
        svc.reconcile()

        assert post_repo.fetch_deleted_since.call_count == 3

    def test_reconcile_no_diff(self):
        """UDS-006: 差分なし。"""
        post_repo = MagicMock()
        post_repo.fetch_deleted_since.return_value = []
        job_repo = MagicMock()

        svc = DeleteSyncService(MagicMock(), post_repo, job_repo, MagicMock())
        svc.reconcile()

        job_repo.cancel_processing_by_post_ids.assert_not_called()

    def test_reconcile_batch_failure_on_second(self):
        """UDS-007: 2 バッチ目で失敗。1 バッチ目は commit 済み。"""
        worker_db = MagicMock()
        post_repo = MagicMock()
        batch1 = [{"id": i, "deleted_at": datetime(2026, 7, 21, 5, 0, 0)} for i in range(1, 4)]
        batch2 = [{"id": i, "deleted_at": datetime(2026, 7, 21, 6, 0, 0)} for i in range(4, 7)]
        post_repo.fetch_deleted_since.side_effect = [batch1, batch2]
        job_repo = MagicMock()
        job_repo.cancel_processing_by_post_ids.side_effect = [0, RuntimeError("fail")]
        job_repo.soft_delete_by_post_ids.return_value = 0

        svc = DeleteSyncService(worker_db, post_repo, job_repo, MagicMock())
        with pytest.raises(RuntimeError):
            svc.reconcile()

        assert worker_db.commit.call_count == 1
        assert worker_db.rollback.call_count == 1

    def test_reconcile_cursor_advances(self):
        """UDS-005 補足: 次バッチに前バッチ末尾のカーソルが渡される。"""
        worker_db = MagicMock()
        post_repo = MagicMock()
        batch1 = [{"id": 99, "deleted_at": datetime(2026, 7, 21, 5, 30, 0)}]
        post_repo.fetch_deleted_since.side_effect = [batch1, []]
        job_repo = MagicMock()
        job_repo.cancel_processing_by_post_ids.return_value = 0
        job_repo.soft_delete_by_post_ids.return_value = 0

        svc = DeleteSyncService(worker_db, post_repo, job_repo, MagicMock())
        svc.reconcile()

        second_call = post_repo.fetch_deleted_since.call_args_list[1]
        assert second_call[0][0] == "2026-07-21 05:30:00"
        assert second_call[0][1] == 99
