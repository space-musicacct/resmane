"""worker_jobs テーブルへのデータアクセスの契約。"""

from abc import ABC, abstractmethod


class WorkerJobRepositoryInterface(ABC):
    """Worker ジョブの作成・ステータス管理の契約。"""

    @abstractmethod
    def lock_for_ownership(self, job_id: int, claim_version: int) -> bool:
        """SELECT FOR UPDATE で所有権を検証する。トランザクション内で呼ぶこと。"""

    @abstractmethod
    def upsert(self, post_id: int) -> tuple[int, int] | None:
        """ジョブを原子的に claim する。成功なら (job_id, claim_version)、失敗なら None。"""

    @abstractmethod
    def mark_completed(self, job_id: int, claim_version: int) -> bool:
        """ジョブを完了にする。所有権が一致しなければ False。"""

    @abstractmethod
    def mark_failed(self, job_id: int, claim_version: int, error: str) -> bool:
        """ジョブを失敗にする。所有権が一致しなければ False。"""

    @abstractmethod
    def mark_cancelled(self, job_id: int, claim_version: int, reason: str) -> bool:
        """ジョブをキャンセルにする。所有権が一致しなければ False。"""

    @abstractmethod
    def get_retry_info(self, job_id: int) -> dict | None:
        """retry_count と max_retries を取得する。"""

    @abstractmethod
    def increment_retry_and_pend(
        self, job_id: int, claim_version: int, error: str,
    ) -> bool:
        """retry_count をインクリメントし、RETRY_PENDING にする。所有権が一致しなければ False。"""

    @abstractmethod
    def fetch_stale(self, timeout_sec: int) -> list[dict]:
        """PROCESSING のまま timeout_sec 秒経過したジョブを取得する。"""

    @abstractmethod
    def cancel_processing_by_post_ids(self, post_ids: list[int]) -> int:
        """指定 post_id の PROCESSING ジョブを CANCELLED にする。"""

    @abstractmethod
    def soft_delete_by_post_ids(self, post_ids: list[int]) -> int:
        """指定 post_id の全ジョブに deleted_at を設定する。"""
