"""SyncWatermarkRepository の結合テスト。"""

import threading

from src.repositories.sync_watermark_repository import SyncWatermarkRepository


class TestSyncWatermark:
    """ISW-001 〜 ISW-005"""

    def test_initial_creates_row(self, worker_db):
        """ISW-001: 初回は初期行を作成。"""
        repo = SyncWatermarkRepository(worker_db)
        worker_db.begin_transaction()
        result = repo.get_for_update("posts")
        worker_db.commit()
        assert result["last_deleted_at"] == "1970-01-01 00:00:00"
        assert result["last_id"] == 0

    def test_get_existing(self, worker_db):
        """ISW-002: 既存行を取得。"""
        repo = SyncWatermarkRepository(worker_db)
        repo.save("posts", "2026-07-21 10:00:00", 42)
        worker_db.begin_transaction()
        result = repo.get_for_update("posts")
        worker_db.commit()
        assert result["last_deleted_at"] == "2026-07-21 10:00:00"
        assert result["last_id"] == 42

    def test_save_insert(self, worker_db, raw_worker_conn):
        """ISW-003: 新規作成。"""
        repo = SyncWatermarkRepository(worker_db)
        repo.save("posts", "2026-07-21 10:00:00", 1)
        cursor = raw_worker_conn.cursor(dictionary=True)
        cursor.execute("SELECT * FROM sync_watermarks WHERE table_name = 'posts'")
        row = cursor.fetchone()
        cursor.close()
        assert row is not None

    def test_save_update(self, worker_db):
        """ISW-004: 既存更新。"""
        repo = SyncWatermarkRepository(worker_db)
        repo.save("posts", "2026-07-21 10:00:00", 1)
        repo.save("posts", "2026-07-21 11:00:00", 5)
        worker_db.begin_transaction()
        result = repo.get_for_update("posts")
        worker_db.commit()
        assert result["last_deleted_at"] == "2026-07-21 11:00:00"
        assert result["last_id"] == 5

    def test_for_update_blocks(self, worker_db, test_config):
        """ISW-005: 排他ロック。"""
        import mysql.connector

        repo = SyncWatermarkRepository(worker_db)
        repo.save("posts", "2026-07-21 10:00:00", 1)

        worker_db.begin_transaction()
        repo.get_for_update("posts")

        blocked = threading.Event()
        acquired = threading.Event()

        def other_thread():
            conn2 = mysql.connector.connect(
                host=test_config.worker_own_db_host,
                port=test_config.worker_own_db_port,
                database=test_config.worker_own_db_name,
                user=test_config.worker_own_db_user,
                password=test_config.worker_own_db_password,
                charset="utf8mb4",
            )
            conn2.start_transaction()
            cursor = conn2.cursor(dictionary=True)
            blocked.set()
            cursor.execute(
                "SELECT * FROM sync_watermarks WHERE table_name = 'posts' FOR UPDATE"
            )
            acquired.set()
            conn2.rollback()
            conn2.close()

        t = threading.Thread(target=other_thread)
        t.start()

        blocked.wait(timeout=5)
        assert not acquired.wait(timeout=1), "他トランザクションがブロックされていない"

        worker_db.commit()
        t.join(timeout=5)
        assert acquired.is_set(), "ロック解放後に取得できた"
