"""sync_watermarks テーブルの操作を担当するリポジトリ。"""

import logging
from datetime import datetime, timezone

from src.databases.resmane_worker_database import ResmaneWorkerDatabase
from src.repositories.contracts.sync_watermark_repository_interface import (
    SyncWatermarkRepositoryInterface,
)

logger = logging.getLogger(__name__)

_INITIAL_WATERMARK = {
    "last_deleted_at": "1970-01-01 00:00:00",
    "last_id": 0,
}


class SyncWatermarkRepository(SyncWatermarkRepositoryInterface):
    """同期 watermark の取得・更新を担当する。"""

    def __init__(self, db: ResmaneWorkerDatabase) -> None:
        self._db = db

    def get_for_update(self, table_name: str) -> dict:
        """排他ロック付きで watermark を取得する。存在しなければ初期行を作成する。"""
        conn = self._db.get_connection()
        cursor = conn.cursor(dictionary=True)
        try:
            now = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S")
            cursor.execute(
                "INSERT IGNORE INTO sync_watermarks "
                "(table_name, last_deleted_at, last_id, updated_at) "
                "VALUES (%s, %s, %s, %s)",
                (table_name, _INITIAL_WATERMARK["last_deleted_at"],
                 _INITIAL_WATERMARK["last_id"], now),
            )

            cursor.execute(
                "SELECT last_deleted_at, last_id FROM sync_watermarks "
                "WHERE table_name = %s "
                "FOR UPDATE",
                (table_name,),
            )
            row = cursor.fetchone()

            if hasattr(row["last_deleted_at"], "strftime"):
                row["last_deleted_at"] = row["last_deleted_at"].strftime(
                    "%Y-%m-%d %H:%M:%S"
                )
            return row
        finally:
            cursor.close()

    def save(self, table_name: str, last_deleted_at: str, last_id: int) -> None:
        """watermark を upsert する。"""
        conn = self._db.get_connection()
        cursor = conn.cursor()
        try:
            now = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S")
            cursor.execute(
                "INSERT INTO sync_watermarks "
                "(table_name, last_deleted_at, last_id, updated_at) "
                "VALUES (%s, %s, %s, %s) "
                "ON DUPLICATE KEY UPDATE "
                "last_deleted_at = VALUES(last_deleted_at), "
                "last_id = VALUES(last_id), "
                "updated_at = VALUES(updated_at)",
                (table_name, last_deleted_at, last_id, now),
            )
        finally:
            cursor.close()
