"""posts テーブルの操作を担当するリポジトリ。"""

import logging
from datetime import datetime, timezone

from src.configs.ai_status import AiStatus
from src.databases.resmane_database import ResManeDatabase
from src.repositories.contracts.post_repository_interface import (
    PostRepositoryInterface,
)

logger = logging.getLogger(__name__)


class PostRepository(PostRepositoryInterface):
    """AI 投稿の取得・ステータス更新・生成結果の書き戻しを担当する。"""

    def __init__(self, db: ResManeDatabase) -> None:
        self._db = db

    def fetch_pending(self) -> list[dict]:
        """pending かつ未削除の AI 投稿を取得する。"""
        conn = self._db.get_connection()
        cursor = conn.cursor(dictionary=True)
        try:
            cursor.execute(
                "SELECT id, user_id, kakeibo_record_id, parent_id "
                "FROM posts "
                "WHERE is_ai = 1 "
                "  AND ai_status_id = %s "
                "  AND deleted_at IS NULL "
                "ORDER BY id ASC",
                (AiStatus.PENDING,),
            )
            return cursor.fetchall()
        finally:
            cursor.close()

    def find_for_update(self, post_id: int) -> dict | None:
        """排他ロック付きで pending の投稿を取得する。"""
        conn = self._db.get_connection()
        cursor = conn.cursor(dictionary=True)
        try:
            cursor.execute(
                "SELECT id, kakeibo_record_id FROM posts "
                "WHERE id = %s AND ai_status_id = %s AND deleted_at IS NULL "
                "FOR UPDATE",
                (post_id, AiStatus.PENDING),
            )
            return cursor.fetchone()
        finally:
            cursor.close()

    def update_status(self, post_id: int, status_id: int) -> None:
        """ステータスを更新する。"""
        conn = self._db.get_connection()
        cursor = conn.cursor()
        try:
            now = self._now()
            cursor.execute(
                "UPDATE posts SET ai_status_id = %s, updated_at = %s WHERE id = %s",
                (status_id, now, post_id),
            )
        finally:
            cursor.close()

    def save_response(self, post_id: int, content: str) -> bool:
        """AI 生成結果を書き込み、ステータスを completed にする。"""
        return self._conditional_update(post_id, AiStatus.COMPLETED, content)

    def mark_failed(self, post_id: int) -> bool:
        """ステータスを failed にする。"""
        return self._conditional_update(post_id, AiStatus.FAILED, None)

    def _conditional_update(
        self, post_id: int, status_id: int, content: str | None
    ) -> bool:
        """deleted_at IS NULL かつ PROCESSING の行のみ原子的に更新する。"""
        conn = self._db.get_connection()
        cursor = conn.cursor()
        try:
            now = self._now()
            cursor.execute(
                "UPDATE posts "
                "SET ai_status_id = %s, content = %s, updated_at = %s "
                "WHERE id = %s "
                "  AND deleted_at IS NULL "
                "  AND ai_status_id = %s",
                (status_id, content, now, post_id, AiStatus.PROCESSING),
            )
            return cursor.rowcount > 0
        finally:
            cursor.close()

    def is_deleted(self, post_id: int) -> bool:
        """投稿が論理削除済みかどうかを返す。"""
        conn = self._db.get_connection()
        cursor = conn.cursor(dictionary=True)
        try:
            cursor.execute(
                "SELECT deleted_at FROM posts WHERE id = %s",
                (post_id,),
            )
            row = cursor.fetchone()
            return row is None or row["deleted_at"] is not None
        finally:
            cursor.close()

    def recover_to_pending(self, post_id: int) -> bool:
        """PROCESSING かつ未削除の投稿を PENDING に戻す。"""
        conn = self._db.get_connection()
        cursor = conn.cursor()
        try:
            now = self._now()
            cursor.execute(
                "UPDATE posts SET ai_status_id = %s, updated_at = %s "
                "WHERE id = %s "
                "  AND deleted_at IS NULL "
                "  AND ai_status_id = %s",
                (AiStatus.PENDING, now, post_id, AiStatus.PROCESSING),
            )
            return cursor.rowcount > 0
        finally:
            cursor.close()

    @staticmethod
    def _now() -> str:
        return datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S")
