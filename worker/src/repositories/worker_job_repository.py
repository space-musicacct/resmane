"""worker_jobs テーブルの操作を担当するリポジトリ。"""

import logging
from datetime import datetime, timezone

from src.configs.worker_status import TerminationReason, WorkerStatus
from src.databases.resmane_worker_database import ResmaneWorkerDatabase
from src.repositories.contracts.worker_job_repository_interface import (
    WorkerJobRepositoryInterface,
)

logger = logging.getLogger(__name__)


class WorkerJobRepository(WorkerJobRepositoryInterface):
    """Worker ジョブの作成・ステータス管理を担当する。"""

    def __init__(self, db: ResmaneWorkerDatabase) -> None:
        self._db = db

    def create(self, post_id: int) -> int:
        """ジョブを作成し、ID を返す。"""
        conn = self._db.get_connection()
        cursor = conn.cursor()
        try:
            now = self._now()
            cursor.execute(
                "INSERT INTO worker_jobs "
                "(post_id, status, claimed_at, created_at, updated_at) "
                "VALUES (%s, %s, %s, %s, %s)",
                (post_id, WorkerStatus.PROCESSING, now, now, now),
            )
            return cursor.lastrowid
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

    def increment_retry(self, job_id: int, error: str) -> None:
        """retry_count をインクリメントし、last_error を記録する。"""
        conn = self._db.get_connection()
        cursor = conn.cursor()
        try:
            now = self._now()
            cursor.execute(
                "UPDATE worker_jobs "
                "SET retry_count = retry_count + 1, last_error = %s, updated_at = %s "
                "WHERE id = %s",
                (error, now, job_id),
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
