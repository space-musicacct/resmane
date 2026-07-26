"""AI フィードバック生成に必要なコンテキスト情報の取得の契約。"""

from abc import ABC, abstractmethod


class KakeiboContextRepositoryInterface(ABC):
    """家計簿レコード・自己レビュー・スレッド履歴の取得の契約。"""

    @abstractmethod
    def fetch_record(self, kakeibo_record_id: int) -> dict | None:
        """家計簿レコードをカテゴリ名・収支区分名付きで取得する。"""

    @abstractmethod
    def fetch_self_reviews(self, kakeibo_record_id: int) -> list[dict]:
        """家計簿レコードに紐づく自己レビューを取得する。"""

    @abstractmethod
    def fetch_thread(self, kakeibo_record_id: int) -> list[dict]:
        """家計簿レコードに紐づく既存の投稿履歴を取得する。"""

    @abstractmethod
    def fetch_upper_limit(self, user_id: int) -> dict | None:
        """ユーザーの上限設定をタイプ名付きで取得する。未設定なら None。"""
