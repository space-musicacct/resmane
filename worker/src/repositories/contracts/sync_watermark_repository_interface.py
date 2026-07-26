"""sync_watermarks テーブルへのデータアクセスの契約。"""

from abc import ABC, abstractmethod


class SyncWatermarkRepositoryInterface(ABC):
    """同期 watermark の取得・更新の契約。"""

    @abstractmethod
    def get_for_update(self, table_name: str) -> dict:
        """排他ロック付きで watermark を取得する。存在しなければ初期値を返す。"""

    @abstractmethod
    def save(self, table_name: str, last_deleted_at: str, last_id: int) -> None:
        """watermark を upsert する。"""
