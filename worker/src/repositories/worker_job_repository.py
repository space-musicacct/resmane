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

    def lock_for_ownership(self, job_id: int, claim_version: int) -> bool:
        """SELECT FOR UPDATE で所有権を検証する。トランザクション内で呼ぶこと。"""
        conn = self._db.get_connection()
        cursor = conn.cursor(dictionary=True)
        try:
            cursor.execute(
                "SELECT id FROM worker_jobs "
                "WHERE id = %s AND status = %s AND claim_version = %s "
                "FOR UPDATE",
                (job_id, WorkerStatus.PROCESSING, claim_version),
            )
            return cursor.fetchone() is not None
        finally:
            cursor.close()

    def upsert(self, post_id: int) -> tuple[int, int] | None:
        """ジョブを原子的に claim する。成功なら (job_id, claim_version)、失敗なら None。"""
        conn = self._db.get_connection()
        cursor = conn.cursor(dictionary=True)
        try:
            now = self._now()

            cursor.execute(
                "INSERT IGNORE INTO worker_jobs "
                "(post_id, status, claim_version, claimed_at, created_at, updated_at) "
                "VALUES (%s, %s, 1, %s, %s, %s)",
                (post_id, WorkerStatus.PROCESSING, now, now, now),
            )
            if cursor.rowcount == 1:
                return (cursor.lastrowid, 1)

            cursor.execute(
                "UPDATE worker_jobs "
                "SET status = %s, "
                "    claim_version = claim_version + 1, "
                "    claimed_at = %s, last_error = NULL, "
                "    termination_reason = NULL, updated_at = %s "
                "WHERE post_id = %s "
                "  AND status = %s "
                "  AND deleted_at IS NULL",
                (WorkerStatus.PROCESSING, now, now,
                 post_id, WorkerStatus.RETRY_PENDING),
            )
            if cursor.rowcount == 1:
                cursor.execute(
                    "SELECT id, claim_version FROM worker_jobs "
                    "WHERE post_id = %s AND deleted_at IS NULL",
                    (post_id,),
                )
                row = cursor.fetchone()
                return (row["id"], row["claim_version"]) if row else None

            return None
        finally:
            cursor.close()

    def mark_completed(self, job_id: int, claim_version: int) -> bool:
        """ジョブを完了にする。所有権が一致しなければ False。"""
        return self._update_with_ownership(
            job_id, claim_version, WorkerStatus.COMPLETED,
        )

    def mark_failed(self, job_id: int, claim_version: int, error: str) -> bool:
        """ジョブを失敗にする。所有権が一致しなければ False。"""
        conn = self._db.get_connection()
        cursor = conn.cursor()
        try:
            now = self._now()
            cursor.execute(
                "UPDATE worker_jobs "
                "SET status = %s, last_error = %s, updated_at = %s "
                "WHERE id = %s AND status = %s AND claim_version = %s",
                (WorkerStatus.FAILED, error, now,
                 job_id, WorkerStatus.PROCESSING, claim_version),
            )
            return cursor.rowcount > 0
        finally:
            cursor.close()

    def mark_cancelled(self, job_id: int, claim_version: int, reason: str) -> bool:
        """ジョブをキャンセルにする。所有権が一致しなければ False。"""
        conn = self._db.get_connection()
        cursor = conn.cursor()
        try:
            now = self._now()
            cursor.execute(
                "UPDATE worker_jobs "
                "SET status = %s, termination_reason = %s, updated_at = %s "
                "WHERE id = %s AND status = %s AND claim_version = %s",
                (WorkerStatus.CANCELLED, reason, now,
                 job_id, WorkerStatus.PROCESSING, claim_version),
            )
            return cursor.rowcount > 0
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

    def increment_retry_and_pend(
        self, job_id: int, claim_version: int, error: str,
    ) -> bool:
        """retry_count をインクリメントし、RETRY_PENDING にする。"""
        conn = self._db.get_connection()
        cursor = conn.cursor()
        try:
            now = self._now()
            cursor.execute(
                "UPDATE worker_jobs "
                "SET retry_count = retry_count + 1, status = %s, "
                "    last_error = %s, updated_at = %s "
                "WHERE id = %s AND status = %s AND claim_version = %s",
                (WorkerStatus.RETRY_PENDING, error, now,
                 job_id, WorkerStatus.PROCESSING, claim_version),
            )
            return cursor.rowcount > 0
        finally:
            cursor.close()

    def fetch_stale(self, timeout_sec: int) -> list[dict]:
        """PROCESSING のまま timeout_sec 秒経過したジョブを取得する。"""
        conn = self._db.get_connection()
        cursor = conn.cursor(dictionary=True)
        try:
            cursor.execute(
                "SELECT id, post_id, retry_count, max_retries, claim_version "
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

    def _update_with_ownership(
        self, job_id: int, claim_version: int, status: str,
    ) -> bool:
        conn = self._db.get_connection()
        cursor = conn.cursor()
        try:
            now = self._now()
            cursor.execute(
                "UPDATE worker_jobs SET status = %s, updated_at = %s "
                "WHERE id = %s AND status = %s AND claim_version = %s",
                (status, now, job_id, WorkerStatus.PROCESSING, claim_version),
            )
            return cursor.rowcount > 0
        finally:
            cursor.close()

    @staticmethod
    def _now() -> str:
        return datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S")
