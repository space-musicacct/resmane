"""投稿テーブルへのデータアクセスの契約。"""

from abc import ABC, abstractmethod


class PostRepositoryInterface(ABC):
    """AI 投稿の取得・ステータス更新・生成結果の書き戻しの契約。"""

    @abstractmethod
    def fetch_pending(self) -> list[dict]:
        """pending かつ未削除の AI 投稿を取得する。"""

    @abstractmethod
    def find_for_update(self, post_id: int) -> dict | None:
        """排他ロック付きで pending の投稿を取得する。"""

    @abstractmethod
    def update_status(self, post_id: int, status_id: int) -> None:
        """ステータスを更新する。"""

    @abstractmethod
    def save_response(self, post_id: int, content: str) -> bool:
        """AI 生成結果を書き込み、ステータスを completed にする。

        対象が削除済み or processing 以外なら書き戻さず False を返す。
        """

    @abstractmethod
    def mark_failed(self, post_id: int) -> bool:
        """ステータスを failed にする。

        対象が削除済み or processing 以外なら更新せず False を返す。
        """

    @abstractmethod
    def get_ai_status(self, post_id: int) -> dict | None:
        """投稿の ai_status_id と deleted_at を取得する。存在しなければ None。"""

    @abstractmethod
    def fetch_deleted_since(
        self,
        last_deleted_at: str,
        last_id: int,
        limit: int = 100,
    ) -> list[dict]:
        """指定した watermark 以降に論理削除された投稿を取得する。"""

    @abstractmethod
    def recover_to_pending(self, post_id: int) -> bool:
        """PROCESSING かつ未削除の投稿を PENDING に戻す。

        条件に合致しなければ False を返す。
        """
