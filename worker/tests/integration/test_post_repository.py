"""PostRepository の結合テスト。"""

from datetime import datetime, timezone

from src.configs.ai_status import AiStatus
from src.repositories.post_repository import PostRepository


def _insert_post(conn, post_id, ai_status_id=AiStatus.PENDING, is_ai=1,
                 deleted_at=None, content=None, record_id=1):
    cursor = conn.cursor()
    now = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S")
    cursor.execute(
        "INSERT INTO posts (id, user_id, kakeibo_record_id, ai_status_id, "
        "is_ai, content, created_at, updated_at, deleted_at) "
        "VALUES (%s, 1, %s, %s, %s, %s, %s, %s, %s)",
        (post_id, record_id, ai_status_id, is_ai, content, now, now, deleted_at),
    )
    cursor.close()


class TestFetchPending:
    """IPR-001 〜 IPR-004"""

    def test_fetch_pending_posts(self, resmane_db, raw_resmane_conn):
        """IPR-001"""
        _insert_post(raw_resmane_conn, 1)
        _insert_post(raw_resmane_conn, 2)
        repo = PostRepository(resmane_db)
        result = repo.fetch_pending()
        assert len(result) == 2
        assert result[0]["id"] < result[1]["id"]

    def test_exclude_deleted(self, resmane_db, raw_resmane_conn):
        """IPR-002"""
        _insert_post(raw_resmane_conn, 1, deleted_at="2026-07-21 00:00:00")
        repo = PostRepository(resmane_db)
        assert len(repo.fetch_pending()) == 0

    def test_exclude_processing(self, resmane_db, raw_resmane_conn):
        """IPR-003"""
        _insert_post(raw_resmane_conn, 1, ai_status_id=AiStatus.PROCESSING)
        repo = PostRepository(resmane_db)
        assert len(repo.fetch_pending()) == 0

    def test_exclude_non_ai(self, resmane_db, raw_resmane_conn):
        """IPR-004"""
        _insert_post(raw_resmane_conn, 1, is_ai=0)
        repo = PostRepository(resmane_db)
        assert len(repo.fetch_pending()) == 0


class TestConditionalUpdate:
    """IPR-010 〜 IPR-019"""

    def test_save_response_processing(self, resmane_db, raw_resmane_conn):
        """IPR-010"""
        _insert_post(raw_resmane_conn, 1, ai_status_id=AiStatus.PROCESSING)
        repo = PostRepository(resmane_db)
        assert repo.save_response(1, "AI応答") is True
        cursor = raw_resmane_conn.cursor(dictionary=True)
        cursor.execute("SELECT content, ai_status_id FROM posts WHERE id = 1")
        row = cursor.fetchone()
        cursor.close()
        assert row["content"] == "AI応答"
        assert row["ai_status_id"] == AiStatus.COMPLETED

    def test_save_response_deleted(self, resmane_db, raw_resmane_conn):
        """IPR-011"""
        _insert_post(raw_resmane_conn, 1, ai_status_id=AiStatus.PROCESSING,
                     deleted_at="2026-07-21 00:00:00")
        repo = PostRepository(resmane_db)
        assert repo.save_response(1, "AI応答") is False

    def test_save_response_pending(self, resmane_db, raw_resmane_conn):
        """IPR-012"""
        _insert_post(raw_resmane_conn, 1, ai_status_id=AiStatus.PENDING)
        repo = PostRepository(resmane_db)
        assert repo.save_response(1, "AI応答") is False

    def test_mark_failed_processing(self, resmane_db, raw_resmane_conn):
        """IPR-013"""
        _insert_post(raw_resmane_conn, 1, ai_status_id=AiStatus.PROCESSING)
        repo = PostRepository(resmane_db)
        assert repo.mark_failed(1) is True

    def test_mark_failed_pending(self, resmane_db, raw_resmane_conn):
        """IPR-014"""
        _insert_post(raw_resmane_conn, 1, ai_status_id=AiStatus.PENDING)
        repo = PostRepository(resmane_db)
        assert repo.mark_failed(1) is False

    def test_force_fail_pending(self, resmane_db, raw_resmane_conn):
        """IPR-015"""
        _insert_post(raw_resmane_conn, 1, ai_status_id=AiStatus.PENDING)
        repo = PostRepository(resmane_db)
        assert repo.force_fail(1) is True

    def test_force_fail_processing(self, resmane_db, raw_resmane_conn):
        """IPR-016"""
        _insert_post(raw_resmane_conn, 1, ai_status_id=AiStatus.PROCESSING)
        repo = PostRepository(resmane_db)
        assert repo.force_fail(1) is True

    def test_force_fail_completed(self, resmane_db, raw_resmane_conn):
        """IPR-017"""
        _insert_post(raw_resmane_conn, 1, ai_status_id=AiStatus.COMPLETED)
        repo = PostRepository(resmane_db)
        assert repo.force_fail(1) is False

    def test_recover_to_pending_processing(self, resmane_db, raw_resmane_conn):
        """IPR-018"""
        _insert_post(raw_resmane_conn, 1, ai_status_id=AiStatus.PROCESSING)
        repo = PostRepository(resmane_db)
        assert repo.recover_to_pending(1) is True

    def test_recover_to_pending_already_pending(self, resmane_db, raw_resmane_conn):
        """IPR-019"""
        _insert_post(raw_resmane_conn, 1, ai_status_id=AiStatus.PENDING)
        repo = PostRepository(resmane_db)
        assert repo.recover_to_pending(1) is False


class TestFindForUpdate:
    """IPR-020, IPR-021"""

    def test_locks_pending_row(self, resmane_db, raw_resmane_conn, test_config):
        """IPR-020: PENDING な行をロックし、別接続がブロックされる。"""
        import threading
        import mysql.connector

        _insert_post(raw_resmane_conn, 1, ai_status_id=AiStatus.PENDING)
        repo = PostRepository(resmane_db)
        resmane_db.begin_transaction()
        row = repo.find_for_update(1)
        assert row is not None

        blocked = threading.Event()
        acquired = threading.Event()

        def other_thread():
            conn2 = mysql.connector.connect(
                host=test_config.db_host, port=test_config.db_port,
                database=test_config.db_name, user=test_config.db_user,
                password=test_config.db_password, charset="utf8mb4",
            )
            conn2.start_transaction()
            cursor = conn2.cursor()
            blocked.set()
            cursor.execute("SELECT id FROM posts WHERE id = 1 FOR UPDATE")
            acquired.set()
            conn2.rollback()
            conn2.close()

        t = threading.Thread(target=other_thread)
        t.start()
        blocked.wait(timeout=5)
        assert not acquired.wait(timeout=1), "別接続がブロックされていない"

        resmane_db.rollback()
        t.join(timeout=5)
        assert acquired.is_set()

    def test_deleted_returns_none(self, resmane_db, raw_resmane_conn):
        """IPR-021: 削除済みなら None。"""
        _insert_post(raw_resmane_conn, 1, ai_status_id=AiStatus.PENDING,
                     deleted_at="2026-07-21 00:00:00")
        repo = PostRepository(resmane_db)
        resmane_db.begin_transaction()
        row = repo.find_for_update(1)
        resmane_db.rollback()
        assert row is None


class TestFetchDeletedSince:
    """IPR-030 〜 IPR-032"""

    def test_compound_cursor(self, resmane_db, raw_resmane_conn):
        """IPR-030"""
        _insert_post(raw_resmane_conn, 1, deleted_at="2026-07-20 10:00:00")
        _insert_post(raw_resmane_conn, 2, deleted_at="2026-07-21 10:00:00")
        _insert_post(raw_resmane_conn, 3, deleted_at="2026-07-21 11:00:00")
        repo = PostRepository(resmane_db)
        result = repo.fetch_deleted_since("2026-07-20 10:00:00", 1)
        assert len(result) == 2
        assert result[0]["id"] == 2

    def test_same_time_id_cursor(self, resmane_db, raw_resmane_conn):
        """IPR-031"""
        _insert_post(raw_resmane_conn, 1, deleted_at="2026-07-21 10:00:00")
        _insert_post(raw_resmane_conn, 2, deleted_at="2026-07-21 10:00:00")
        repo = PostRepository(resmane_db)
        result = repo.fetch_deleted_since("2026-07-21 10:00:00", 1)
        assert len(result) == 1
        assert result[0]["id"] == 2

    def test_limit(self, resmane_db, raw_resmane_conn):
        """IPR-032"""
        for i in range(1, 6):
            _insert_post(raw_resmane_conn, i, deleted_at=f"2026-07-21 10:00:0{i}")
        repo = PostRepository(resmane_db)
        result = repo.fetch_deleted_since("1970-01-01 00:00:00", 0, limit=3)
        assert len(result) == 3
