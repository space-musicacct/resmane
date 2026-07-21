"""KakeiboContextRepository の結合テスト。"""

from datetime import datetime, timezone

from src.configs.ai_status import AiStatus
from src.repositories.kakeibo_context_repository import KakeiboContextRepository


def _insert_post(conn, post_id, is_ai=0, ai_status_id=None, content=None,
                 record_id=1, deleted_at=None):
    now = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S")
    cursor = conn.cursor()
    cursor.execute(
        "INSERT INTO posts (id, user_id, kakeibo_record_id, ai_status_id, "
        "is_ai, content, created_at, updated_at, deleted_at) "
        "VALUES (%s, 1, %s, %s, %s, %s, %s, %s, %s)",
        (post_id, record_id, ai_status_id, is_ai, content, now, now, deleted_at),
    )
    cursor.close()


def _insert_self_review(conn, review_id, record_id=1, comment="テスト", evaluation=3,
                        deleted_at=None):
    now = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S")
    cursor = conn.cursor()
    cursor.execute(
        "INSERT INTO self_reviews (id, kakeibo_record_id, review_comment, evaluation, "
        "created_at, updated_at, deleted_at) VALUES (%s, %s, %s, %s, %s, %s, %s)",
        (review_id, record_id, comment, evaluation, now, now, deleted_at),
    )
    cursor.close()


class TestFetchRecord:
    """IKC-001 〜 IKC-003"""

    def test_returns_record_with_joins(self, resmane_db):
        """IKC-001: 家計簿レコードをカテゴリ名・収支区分名付きで取得。"""
        repo = KakeiboContextRepository(resmane_db)
        result = repo.fetch_record(1)
        assert result is not None
        assert result["amount"] == 1500
        assert result["category_name"] == "飲食"
        assert result["amount_type_name"] == "支出"

    def test_returns_none_for_nonexistent(self, resmane_db):
        """IKC-002: 存在しないレコードは None。"""
        repo = KakeiboContextRepository(resmane_db)
        assert repo.fetch_record(999) is None

    def test_returns_none_for_deleted(self, resmane_db, raw_resmane_conn):
        """IKC-003: 削除済みレコードは None。"""
        cursor = raw_resmane_conn.cursor()
        cursor.execute("UPDATE kakeibo_records SET deleted_at = NOW() WHERE id = 1")
        cursor.close()

        repo = KakeiboContextRepository(resmane_db)
        assert repo.fetch_record(1) is None

        cursor = raw_resmane_conn.cursor()
        cursor.execute("UPDATE kakeibo_records SET deleted_at = NULL WHERE id = 1")
        cursor.close()


class TestFetchSelfReviews:
    """IKC-010 〜 IKC-012"""

    def test_returns_reviews(self, resmane_db, raw_resmane_conn):
        """IKC-010: 自己レビューを取得。"""
        _insert_self_review(raw_resmane_conn, 1, comment="良い買い物", evaluation=4)
        _insert_self_review(raw_resmane_conn, 2, comment="贅沢した", evaluation=2)
        repo = KakeiboContextRepository(resmane_db)
        result = repo.fetch_self_reviews(1)
        assert len(result) == 2
        assert result[0]["review_comment"] == "良い買い物"

    def test_excludes_deleted(self, resmane_db, raw_resmane_conn):
        """IKC-011: 削除済みレビューは除外。"""
        _insert_self_review(raw_resmane_conn, 1, deleted_at="2026-07-21 00:00:00")
        repo = KakeiboContextRepository(resmane_db)
        assert len(repo.fetch_self_reviews(1)) == 0

    def test_empty_for_no_reviews(self, resmane_db):
        """IKC-012: レビューなしは空リスト。"""
        repo = KakeiboContextRepository(resmane_db)
        assert repo.fetch_self_reviews(1) == []


class TestFetchThread:
    """IKC-020 〜 IKC-023"""

    def test_returns_user_and_completed_ai_posts(self, resmane_db, raw_resmane_conn):
        """IKC-020: ユーザー投稿 + completed AI 投稿を取得。"""
        _insert_post(raw_resmane_conn, 1, is_ai=0, content="質問です")
        _insert_post(raw_resmane_conn, 2, is_ai=1, ai_status_id=AiStatus.COMPLETED,
                     content="回答です")
        repo = KakeiboContextRepository(resmane_db)
        result = repo.fetch_thread(1)
        assert len(result) == 2
        assert result[0]["is_ai"] == 0
        assert result[1]["is_ai"] == 1

    def test_excludes_pending_ai_posts(self, resmane_db, raw_resmane_conn):
        """IKC-021: pending AI 投稿は除外。"""
        _insert_post(raw_resmane_conn, 1, is_ai=1, ai_status_id=AiStatus.PENDING)
        repo = KakeiboContextRepository(resmane_db)
        assert len(repo.fetch_thread(1)) == 0

    def test_excludes_deleted_posts(self, resmane_db, raw_resmane_conn):
        """IKC-022: 削除済み投稿は除外。"""
        _insert_post(raw_resmane_conn, 1, is_ai=0, content="msg",
                     deleted_at="2026-07-21 00:00:00")
        repo = KakeiboContextRepository(resmane_db)
        assert len(repo.fetch_thread(1)) == 0

    def test_ordered_by_created_at_and_id(self, resmane_db, raw_resmane_conn):
        """IKC-023: created_at, id の昇順。"""
        fixed_time = "2026-07-21 10:00:00"
        cursor = raw_resmane_conn.cursor()
        for pid, content in [(3, "third"), (1, "first"), (2, "second")]:
            cursor.execute(
                "INSERT INTO posts (id, user_id, kakeibo_record_id, is_ai, "
                "content, created_at, updated_at) "
                "VALUES (%s, 1, 1, 0, %s, %s, %s)",
                (pid, content, fixed_time, fixed_time),
            )
        cursor.close()
        repo = KakeiboContextRepository(resmane_db)
        result = repo.fetch_thread(1)
        ids = [r["id"] for r in result]
        assert ids == sorted(ids)


def _insert_upper_limit(conn, user_id, type_id, max_value, ave_income=None, deleted_at=None):
    now = "2026-07-21 10:00:00"
    cursor = conn.cursor()
    cursor.execute(
        "INSERT INTO upper_limit_settings "
        "(user_id, upper_limit_type_id, max_value, ave_monthly_income, "
        "created_at, updated_at, deleted_at) VALUES (%s, %s, %s, %s, %s, %s, %s)",
        (user_id, type_id, max_value, ave_income, now, now, deleted_at),
    )
    cursor.close()


class TestFetchUpperLimit:
    """IKC-030 〜 IKC-033"""

    def test_fixed_amount(self, resmane_db, raw_resmane_conn):
        """IKC-030: 固定額設定を取得。"""
        _insert_upper_limit(raw_resmane_conn, 1, 2, 50000)
        repo = KakeiboContextRepository(resmane_db)
        result = repo.fetch_upper_limit(1)
        assert result is not None
        assert result["max_value"] == 50000
        assert result["type_name"] == "固定額"

    def test_percentage(self, resmane_db, raw_resmane_conn):
        """IKC-031: 割合設定を取得 (タイプ名・ave_monthly_income 付き)。"""
        _insert_upper_limit(raw_resmane_conn, 1, 1, 30, ave_income=200000)
        repo = KakeiboContextRepository(resmane_db)
        result = repo.fetch_upper_limit(1)
        assert result is not None
        assert result["type_name"] == "割合"
        assert result["upper_limit_type_id"] == 1
        assert result["ave_monthly_income"] == 200000

    def test_not_set(self, resmane_db):
        """IKC-032: 未設定なら None。"""
        repo = KakeiboContextRepository(resmane_db)
        assert repo.fetch_upper_limit(1) is None

    def test_deleted(self, resmane_db, raw_resmane_conn):
        """IKC-033: 削除済みなら None。"""
        _insert_upper_limit(raw_resmane_conn, 1, 2, 50000, deleted_at="2026-07-21 00:00:00")
        repo = KakeiboContextRepository(resmane_db)
        assert repo.fetch_upper_limit(1) is None

    def test_deleted_type(self, resmane_db, raw_resmane_conn):
        """IKC-034: 設定は有効でもタイプが削除済みなら None。"""
        cursor = raw_resmane_conn.cursor()
        cursor.execute("UPDATE upper_limit_types SET deleted_at = NOW() WHERE id = 2")
        cursor.close()

        _insert_upper_limit(raw_resmane_conn, 1, 2, 50000)
        repo = KakeiboContextRepository(resmane_db)
        assert repo.fetch_upper_limit(1) is None

        cursor = raw_resmane_conn.cursor()
        cursor.execute("UPDATE upper_limit_types SET deleted_at = NULL WHERE id = 2")
        cursor.close()

    def test_other_user_excluded(self, resmane_db, raw_resmane_conn):
        """IKC-035: 別ユーザーの設定を取得しない。"""
        _insert_upper_limit(raw_resmane_conn, 999, 2, 30000)
        repo = KakeiboContextRepository(resmane_db)
        assert repo.fetch_upper_limit(1) is None
