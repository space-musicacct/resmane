"""worker_jobs テーブルへのデータアクセスの契約。"""

from abc import ABC, abstractmethod


class WorkerJobRepositoryInterface(ABC):
    """Worker ジョブの作成・ステータス管理の契約。"""

    @abstractmethod
    def upsert(self, post_id: int) -> int:
        """ジョブを作成または再利用し、ID を返す。"""

    @abstractmethod
    def mark_completed(self, job_id: int) -> None:
        """ジョブを完了にする。"""

    @abstractmethod
    def mark_failed(self, job_id: int, error: str) -> None:
        """ジョブを失敗にする。"""

    @abstractmethod
    def mark_cancelled(self, job_id: int, reason: str) -> None:
        """ジョブをキャンセルにする。"""

    @abstractmethod
    def get_retry_info(self, job_id: int) -> dict | None:
        """retry_count と max_retries を取得する。"""

    @abstractmethod
    def increment_retry(self, job_id: int, error: str) -> None:
        """retry_count をインクリメントし、last_error を記録する。"""

    @abstractmethod
    def fetch_stale(self, timeout_sec: int) -> list[dict]:
        """PROCESSING のまま timeout_sec 秒経過したジョブを取得する。"""
