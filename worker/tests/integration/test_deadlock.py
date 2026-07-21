"""デッドロック検証。threading で並行操作し、デッドロックが発生しないことを確認する。"""

import threading
from datetime import datetime, timezone, timedelta

import mysql.connector

from src.configs.ai_status import AiStatus
from src.configs.worker_status import WorkerStatus
from src.databases.resmane_database import ResManeDatabase
from src.databases.resmane_worker_database import ResmaneWorkerDatabase
from src.repositories.post_repository import PostRepository
from src.repositories.worker_job_repository import WorkerJobRepository


def _insert_post(conn, post_id, ai_status_id=AiStatus.PENDING):
    now = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S")
    cursor = conn.cursor()
    cursor.execute(
        "INSERT INTO posts (id, user_id, kakeibo_record_id, ai_status_id, "
        "is_ai, created_at, updated_at) VALUES (%s, 1, 1, %s, 1, %s, %s)",
        (post_id, ai_status_id, now, now),
    )
    cursor.close()


def _insert_job(conn, post_id, status=WorkerStatus.PROCESSING, cv=1, claimed_at=None):
    now = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S")
    cursor = conn.cursor()
    cursor.execute(
        "INSERT INTO worker_jobs "
        "(post_id, status, claim_version, claimed_at, created_at, updated_at) "
        "VALUES (%s, %s, %s, %s, %s, %s)",
        (post_id, status, cv, claimed_at or now, now, now),
    )
    cursor.close()


class TestClaimConcurrency:
    """IDL-001, IDL-002"""

    def test_two_workers_same_post(self, test_config, resmane_db, worker_db, raw_resmane_conn):
        """IDL-001: 2 Worker が同じ post_id を同時 claim。"""
        _insert_post(raw_resmane_conn, 1)

        results = [None, None]
        errors = []

        def claim_worker(idx):
            try:
                wdb = ResmaneWorkerDatabase(test_config)
                repo = WorkerJobRepository(wdb)
                results[idx] = repo.upsert(1)
                wdb.close()
            except Exception as e:
                errors.append(e)

        t1 = threading.Thread(target=claim_worker, args=(0,))
        t2 = threading.Thread(target=claim_worker, args=(1,))
        t1.start()
        t2.start()
        t1.join(timeout=10)
        t2.join(timeout=10)

        assert not errors, f"エラー発生: {errors}"
        success = [r for r in results if r is not None]
        assert len(success) == 1, f"一方だけ成功すべき: {results}"

    def test_claim_and_stale_concurrent(self, test_config, resmane_db, worker_db,
                                        raw_resmane_conn, raw_worker_conn):
        """IDL-002: claim と stale recovery の同時実行。"""
        _insert_post(raw_resmane_conn, 1, ai_status_id=AiStatus.PENDING)
        old = (datetime.now(timezone.utc) - timedelta(seconds=400)).strftime("%Y-%m-%d %H:%M:%S")
        _insert_job(raw_worker_conn, 1, status=WorkerStatus.RETRY_PENDING, cv=1, claimed_at=old)

        errors = []
        claim_result = [None]
        stale_result = [None]

        def do_claim():
            try:
                wdb = ResmaneWorkerDatabase(test_config)
                repo = WorkerJobRepository(wdb)
                claim_result[0] = repo.upsert(1)
                wdb.close()
            except Exception as e:
                errors.append(e)

        def do_stale():
            try:
                wdb = ResmaneWorkerDatabase(test_config)
                repo = WorkerJobRepository(wdb)
                stale_result[0] = repo.fetch_stale(300)
                wdb.close()
            except Exception as e:
                errors.append(e)

        t1 = threading.Thread(target=do_claim)
        t2 = threading.Thread(target=do_stale)
        t1.start()
        t2.start()
        t1.join(timeout=10)
        t2.join(timeout=10)

        assert not errors, f"デッドロック発生: {errors}"


class TestWriteBackConcurrency:
    """IDL-010 〜 IDL-012"""

    def test_save_and_stale_concurrent(self, test_config, resmane_db, worker_db,
                                       raw_resmane_conn, raw_worker_conn):
        """IDL-010: save_response と stale recovery の同時実行。"""
        _insert_post(raw_resmane_conn, 1, ai_status_id=AiStatus.PROCESSING)
        old = (datetime.now(timezone.utc) - timedelta(seconds=400)).strftime("%Y-%m-%d %H:%M:%S")
        _insert_job(raw_worker_conn, 1, cv=1, claimed_at=old)

        errors = []

        def do_save():
            try:
                wdb = ResmaneWorkerDatabase(test_config)
                wdb.begin_transaction()
                repo = WorkerJobRepository(wdb)
                repo.lock_for_ownership(1, 1)
                db = ResManeDatabase(test_config)
                post_repo = PostRepository(db)
                post_repo.save_response(1, "AI応答")
                repo.mark_completed(1, 1)
                wdb.commit()
                wdb.close()
                db.close()
            except Exception as e:
                errors.append(e)

        def do_stale():
            try:
                wdb = ResmaneWorkerDatabase(test_config)
                wdb.begin_transaction()
                repo = WorkerJobRepository(wdb)
                locked = repo.lock_for_ownership(1, 1)
                if locked:
                    repo.increment_retry_and_pend(1, 1, "stale")
                wdb.commit()
                wdb.close()
            except Exception as e:
                errors.append(e)

        t1 = threading.Thread(target=do_save)
        t2 = threading.Thread(target=do_stale)
        t1.start()
        t2.start()
        t1.join(timeout=10)
        t2.join(timeout=10)

        assert not errors, f"デッドロック発生: {errors}"

    def test_old_worker_mark_completed(self, test_config, worker_db, raw_worker_conn):
        """IDL-011: 旧 cv と新 cv の mark_completed 競合。"""
        _insert_job(raw_worker_conn, 1, cv=2)

        errors = []
        results = [None, None]

        def mark(idx, cv):
            try:
                wdb = ResmaneWorkerDatabase(test_config)
                repo = WorkerJobRepository(wdb)
                results[idx] = repo.mark_completed(1, cv)
                wdb.close()
            except Exception as e:
                errors.append(e)

        t1 = threading.Thread(target=mark, args=(0, 1))
        t2 = threading.Thread(target=mark, args=(1, 2))
        t1.start()
        t2.start()
        t1.join(timeout=10)
        t2.join(timeout=10)

        assert not errors
        assert results[0] is False
        assert results[1] is True

    def test_old_worker_handle_failure_blocked(self, test_config, resmane_db, worker_db,
                                                raw_resmane_conn, raw_worker_conn):
        """IDL-012: 旧 Worker の _handle_failure が新 claim を破壊しない。"""
        _insert_post(raw_resmane_conn, 1, ai_status_id=AiStatus.PROCESSING)
        _insert_job(raw_worker_conn, 1, cv=2)

        errors = []

        def old_worker_failure():
            try:
                wdb = ResmaneWorkerDatabase(test_config)
                wdb.begin_transaction()
                repo = WorkerJobRepository(wdb)
                locked = repo.lock_for_ownership(1, 1)
                if locked:
                    db = ResManeDatabase(test_config)
                    post_repo = PostRepository(db)
                    post_repo.recover_to_pending(1)
                    db.close()
                wdb.commit()
                wdb.close()
            except Exception as e:
                errors.append(e)

        t = threading.Thread(target=old_worker_failure)
        t.start()
        t.join(timeout=10)

        assert not errors
        cursor = raw_resmane_conn.cursor(dictionary=True)
        cursor.execute("SELECT ai_status_id FROM posts WHERE id = 1")
        row = cursor.fetchone()
        cursor.close()
        assert row["ai_status_id"] == AiStatus.PROCESSING, "投稿が PROCESSING のまま"
