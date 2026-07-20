"""AI フィードバック生成に必要なコンテキスト情報の取得を担当するリポジトリ。"""

import logging

from src.databases.resmane_database import ResManeDatabase
from src.repositories.contracts.kakeibo_context_repository_interface import (
    KakeiboContextRepositoryInterface,
)

logger = logging.getLogger(__name__)


class KakeiboContextRepository(KakeiboContextRepositoryInterface):
    """家計簿レコード・自己レビュー・スレッド履歴の取得を担当する。"""

    def __init__(self, db: ResManeDatabase) -> None:
        self._db = db

    def fetch_record(self, kakeibo_record_id: int) -> dict | None:
        """家計簿レコードをカテゴリ名・収支区分名付きで取得する。"""
        conn = self._db.get_connection()
        cursor = conn.cursor(dictionary=True)
        try:
            cursor.execute(
                "SELECT kr.id, kr.purchase_date, kr.amount, kr.details, "
                "  at.type_name AS amount_type_name, "
                "  kdc.category_name "
                "FROM kakeibo_records kr "
                "JOIN amount_types at ON kr.amount_type_id = at.id "
                "JOIN kakeibo_default_categories kdc "
                "  ON kr.kakeibo_default_category_id = kdc.id "
                "WHERE kr.id = %s AND kr.deleted_at IS NULL",
                (kakeibo_record_id,),
            )
            return cursor.fetchone()
        finally:
            cursor.close()

    def fetch_self_reviews(self, kakeibo_record_id: int) -> list[dict]:
        """家計簿レコードに紐づく自己レビューを取得する。"""
        conn = self._db.get_connection()
        cursor = conn.cursor(dictionary=True)
        try:
            cursor.execute(
                "SELECT id, review_comment, evaluation, created_at "
                "FROM self_reviews "
                "WHERE kakeibo_record_id = %s AND deleted_at IS NULL "
                "ORDER BY created_at ASC",
                (kakeibo_record_id,),
            )
            return cursor.fetchall()
        finally:
            cursor.close()

    def fetch_thread(self, kakeibo_record_id: int) -> list[dict]:
        """家計簿レコードに紐づく既存の投稿履歴を取得する。"""
        conn = self._db.get_connection()
        cursor = conn.cursor(dictionary=True)
        try:
            cursor.execute(
                "SELECT id, is_ai, content, created_at "
                "FROM posts "
                "WHERE kakeibo_record_id = %s "
                "  AND deleted_at IS NULL "
                "  AND (is_ai = 0 OR (is_ai = 1 AND ai_status_id = 3)) "
                "ORDER BY created_at ASC",
                (kakeibo_record_id,),
            )
            return cursor.fetchall()
        finally:
            cursor.close()
