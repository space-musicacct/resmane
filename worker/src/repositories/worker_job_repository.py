"""worker_jobs テーブルの操作を担当するリポジトリ。"""

import logging
from datetime import datetime, timezone

from src.configs.worker_status import WorkerStatus
from src.databases.resmane_worker_database import ResmaneWorkerDatabase
from src.repositories.contracts.worker_job_repository_interface import (
    WorkerJobRepositoryInterface,
)

logger = logging.getLogger(__name__)


class WorkerJobRepository(WorkerJobRepositoryInterface):
    """Worker ジョブの作成・ステータス管理を担当する。"""

    def __init__(self, db: ResmaneWorkerDatabase) -> None:
        self._db = db

    def upsert(self, post_id: int) -> int | None:
        """ジョブを原子的に claim する。成功なら ID、失敗なら None。"""
        conn = self._db.get_connection()
        cursor = conn.cursor(dictionary=True)
        try:
            now = self._now()

            # 初回: INSERT IGNORE で重複時はスキップ
            cursor.execute(
                "INSERT IGNORE INTO worker_jobs "
                "(post_id, status, claimed_at, created_at, updated_at) "
                "VALUES (%s, %s, %s, %s, %s)",
                (post_id, WorkerStatus.PROCESSING, now, now, now),
            )
            if cursor.rowcount == 1:
                return cursor.lastrowid

            # リトライ: RETRY_PENDING → PROCESSING の条件付き UPDATE
            cursor.execute(
                "UPDATE worker_jobs "
                "SET status = %s, claimed_at = %s, last_error = NULL, "
                "    termination_reason = NULL, updated_at = %s "
                "WHERE post_id = %s "
                "  AND status = %s "
                "  AND deleted_at IS NULL",
                (WorkerStatus.PROCESSING, now, now,
                 post_id, WorkerStatus.RETRY_PENDING),
            )
            if cursor.rowcount == 1:
                cursor.execute(
                    "SELECT id FROM worker_jobs "
                    "WHERE post_id = %s AND deleted_at IS NULL",
                    (post_id,),
                )
                row = cursor.fetchone()
                return row["id"] if row else None

            # claim 失敗 (他 Worker が処理中 or 完了済み)
            return None
        finally:
            cursor.close()

    def mark_completed(self, job_id: int) -> None:
        """ジョブを完了にする。"""
        self._update_status(job_id, WorkerStatus.COMPLETED)

    def mark_failed(self, job_id: int, error: str) -> None:
        """ジョブを失敗にする。"""
        conn = self._db.get_connection()
        cursor = conn.cursor()
        try:
            now = self._now()
            cursor.execute(
                "UPDATE worker_jobs "
                "SET status = %s, last_error = %s, updated_at = %s "
                "WHERE id = %s",
                (WorkerStatus.FAILED, error, now, job_id),
            )
        finally:
            cursor.close()

    def mark_cancelled(self, job_id: int, reason: str) -> None:
        """ジョブをキャンセルにする。"""
        conn = self._db.get_connection()
        cursor = conn.cursor()
        try:
            now = self._now()
            cursor.execute(
                "UPDATE worker_jobs "
                "SET status = %s, termination_reason = %s, updated_at = %s "
                "WHERE id = %s",
                (WorkerStatus.CANCELLED, reason, now, job_id),
            )
        finally:
            cursor.close()

    def get_retry_info(self, job_id: int) -> dict | None:
        """retry_count と max_retries を取得する。"""
        conn = self._db.get_connection()
        cursor = conn.cursor(dictionary=True)
        try:
            cursor.execute(
                "SELECT retry_count, max_retries FROM worker_jobs WHERE id = %s",
                (job_id,),
            )
            return cursor.fetchone()
        finally:
            cursor.close()

    def increment_retry_and_pend(self, job_id: int, error: str) -> None:
        """retry_count をインクリメントし、ステータスを RETRY_PENDING にする。"""
        conn = self._db.get_connection()
        cursor = conn.cursor()
        try:
            now = self._now()
            cursor.execute(
                "UPDATE worker_jobs "
                "SET retry_count = retry_count + 1, status = %s, "
                "    last_error = %s, updated_at = %s "
                "WHERE id = %s",
                (WorkerStatus.RETRY_PENDING, error, now, job_id),
            )
        finally:
            cursor.close()

    def fetch_stale(self, timeout_sec: int) -> list[dict]:
        """PROCESSING のまま timeout_sec 秒経過したジョブを取得する。"""
        conn = self._db.get_connection()
        cursor = conn.cursor(dictionary=True)
        try:
            cursor.execute(
                "SELECT id, post_id, retry_count, max_retries "
                "FROM worker_jobs "
                "WHERE status = %s "
                "  AND deleted_at IS NULL "
                "  AND claimed_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %s SECOND)",
                (WorkerStatus.PROCESSING, timeout_sec),
            )
            return cursor.fetchall()
        finally:
            cursor.close()

    def cancel_processing_by_post_ids(self, post_ids: list[int]) -> int:
        """指定 post_id の PROCESSING ジョブを CANCELLED にする。"""
        if not post_ids:
            return 0
        conn = self._db.get_connection()
        cursor = conn.cursor()
        try:
            now = self._now()
            placeholders = ", ".join(["%s"] * len(post_ids))
            cursor.execute(
                f"UPDATE worker_jobs "
                f"SET status = %s, termination_reason = %s, updated_at = %s "
                f"WHERE post_id IN ({placeholders}) "
                f"  AND status = %s "
                f"  AND deleted_at IS NULL",
                [WorkerStatus.CANCELLED, "target_deleted", now]
                + post_ids
                + [WorkerStatus.PROCESSING],
            )
            return cursor.rowcount
        finally:
            cursor.close()

    def soft_delete_by_post_ids(self, post_ids: list[int]) -> int:
        """指定 post_id の全ジョブに deleted_at を設定する。"""
        if not post_ids:
            return 0
        conn = self._db.get_connection()
        cursor = conn.cursor()
        try:
            now = self._now()
            placeholders = ", ".join(["%s"] * len(post_ids))
            cursor.execute(
                f"UPDATE worker_jobs "
                f"SET deleted_at = %s, updated_at = %s "
                f"WHERE post_id IN ({placeholders}) "
                f"  AND deleted_at IS NULL",
                [now, now] + post_ids,
            )
            return cursor.rowcount
        finally:
            cursor.close()

    def _update_status(self, job_id: int, status: str) -> None:
        conn = self._db.get_connection()
        cursor = conn.cursor()
        try:
            now = self._now()
            cursor.execute(
                "UPDATE worker_jobs SET status = %s, updated_at = %s WHERE id = %s",
                (status, now, job_id),
            )
        finally:
            cursor.close()

    @staticmethod
    def _now() -> str:
        return datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S")
