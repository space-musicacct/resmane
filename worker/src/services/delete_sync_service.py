"""Laravel DB の削除差分を Worker DB へ同期する。"""

import logging

from src.databases.resmane_worker_database import ResmaneWorkerDatabase
from src.repositories.contracts.post_repository_interface import (
    PostRepositoryInterface,
)
from src.repositories.contracts.sync_watermark_repository_interface import (
    SyncWatermarkRepositoryInterface,
)
from src.repositories.contracts.worker_job_repository_interface import (
    WorkerJobRepositoryInterface,
)

logger = logging.getLogger(__name__)

SYNC_TABLE = "posts"
SYNC_BATCH_SIZE = 100


class DeleteSyncService:
    """posts の削除差分を検出し、Worker DB を同期する。"""

    def __init__(
        self,
        worker_db: ResmaneWorkerDatabase,
        post_repo: PostRepositoryInterface,
        job_repo: WorkerJobRepositoryInterface,
        watermark_repo: SyncWatermarkRepositoryInterface,
    ) -> None:
        self._worker_db = worker_db
        self._post_repo = post_repo
        self._job_repo = job_repo
        self._watermark_repo = watermark_repo

    def sync(self) -> None:
        """削除同期を実行する。失敗時は rollback して次回再実行。"""
        try:
            self._worker_db.begin_transaction()

            watermark = self._watermark_repo.get_for_update(SYNC_TABLE)
            last_deleted_at = watermark["last_deleted_at"]
            last_id = watermark["last_id"]

            deleted_posts = self._post_repo.fetch_deleted_since(
                last_deleted_at, last_id, SYNC_BATCH_SIZE,
            )

            if not deleted_posts:
                self._worker_db.commit()
                return

            post_ids = [p["id"] for p in deleted_posts]

            cancelled = self._job_repo.cancel_processing_by_post_ids(post_ids)
            soft_deleted = self._job_repo.soft_delete_by_post_ids(post_ids)

            last_post = deleted_posts[-1]
            new_deleted_at = last_post["deleted_at"]
            if hasattr(new_deleted_at, "strftime"):
                new_deleted_at = new_deleted_at.strftime("%Y-%m-%d %H:%M:%S")

            self._watermark_repo.save(SYNC_TABLE, new_deleted_at, last_post["id"])

            self._worker_db.commit()

            logger.info(
                "削除同期完了: %d 件検出, %d 件キャンセル, %d 件論理削除",
                len(deleted_posts), cancelled, soft_deleted,
            )

        except Exception:
            self._worker_db.rollback()
            logger.exception("削除同期に失敗 (次回ポーリングで再実行)")
