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

    def fetch_deleted_since(
        self,
        last_deleted_at: str,
        last_id: int,
        limit: int = 100,
    ) -> list[dict]:
        """指定した watermark 以降に論理削除された投稿を取得する。"""
        conn = self._db.get_connection()
        cursor = conn.cursor(dictionary=True)
        try:
            cursor.execute(
                "SELECT id, deleted_at FROM posts "
                "WHERE is_ai = 1 "
                "  AND deleted_at IS NOT NULL "
                "  AND (deleted_at > %s OR (deleted_at = %s AND id > %s)) "
                "ORDER BY deleted_at ASC, id ASC "
                "LIMIT %s",
                (last_deleted_at, last_deleted_at, last_id, limit),
            )
            return cursor.fetchall()
        finally:
            cursor.close()

    def get_ai_status(self, post_id: int) -> dict | None:
        """投稿の ai_status_id と deleted_at を取得する。存在しなければ None。"""
        conn = self._db.get_connection()
        cursor = conn.cursor(dictionary=True)
        try:
            cursor.execute(
                "SELECT ai_status_id, deleted_at FROM posts WHERE id = %s",
                (post_id,),
            )
            return cursor.fetchone()
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
