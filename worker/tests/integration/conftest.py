"""結合テスト用 fixture。テスト専用 DB にテーブルを作成し、各テストで TRUNCATE する。"""

import os

import mysql.connector
import pytest

from src.configs.config import Config
from src.databases.resmane_database import ResManeDatabase
from src.databases.resmane_worker_database import ResmaneWorkerDatabase

RESMANE_TEST_DB = os.environ.get("RESMANE_TEST_DB", "resmane_test")
WORKER_TEST_DB = os.environ.get("WORKER_TEST_DB", "resmane_worker_test")
TEST_DB_HOST = os.environ.get("WORKER_DB_HOST", "db")
TEST_DB_PORT = int(os.environ.get("WORKER_DB_PORT", "3306"))
TEST_DB_USER = os.environ.get("WORKER_OWN_DB_USER", "resmane_worker")
TEST_DB_PASSWORD = os.environ.get("WORKER_OWN_DB_PASSWORD", "")

_RESMANE_TABLES_SQL = """
CREATE TABLE IF NOT EXISTS ai_statuses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    status_name VARCHAR(32) NOT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS amount_types (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    type_name VARCHAR(32) NOT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kakeibo_default_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    amount_type_id BIGINT UNSIGNED NOT NULL,
    category_name VARCHAR(50) NOT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    login_id VARCHAR(15) NOT NULL,
    name VARCHAR(50) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kakeibo_records (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    purchase_date DATE NOT NULL,
    amount_type_id BIGINT UNSIGNED NOT NULL,
    amount INT NOT NULL,
    details VARCHAR(250) NULL,
    kakeibo_default_category_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS self_reviews (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    kakeibo_record_id BIGINT UNSIGNED NOT NULL,
    review_comment VARCHAR(250) NOT NULL,
    evaluation TINYINT NOT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS posts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    kakeibo_record_id BIGINT UNSIGNED NOT NULL,
    ai_status_id BIGINT UNSIGNED NULL,
    parent_id BIGINT UNSIGNED NULL,
    is_ai TINYINT NOT NULL,
    content VARCHAR(3000) NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
"""

_WORKER_TABLES_SQL = """
CREATE TABLE IF NOT EXISTS worker_jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    post_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'processing',
    claim_version INT UNSIGNED NOT NULL DEFAULT 0,
    claimed_at DATETIME NOT NULL,
    retry_count INT UNSIGNED NOT NULL DEFAULT 0,
    max_retries INT UNSIGNED NOT NULL DEFAULT 3,
    last_error TEXT NULL,
    termination_reason VARCHAR(40) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_worker_jobs_post_id (post_id),
    INDEX idx_worker_jobs_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sync_watermarks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(64) NOT NULL,
    last_deleted_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
    last_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_sync_watermarks_table_name (table_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
"""

_SEED_SQL = """
INSERT IGNORE INTO ai_statuses (id, status_name) VALUES
    (1, 'pending'), (2, 'processing'), (3, 'completed'), (4, 'failed');

INSERT IGNORE INTO amount_types (id, type_name) VALUES
    (1, '支出'), (2, '収入');

INSERT IGNORE INTO kakeibo_default_categories (id, amount_type_id, category_name) VALUES
    (1, 1, '飲食'), (2, 1, '交通費'), (3, 2, '給与');

INSERT IGNORE INTO users (id, login_id, name, email, password_hash, created_at, updated_at) VALUES
    (1, 'testuser', 'テストユーザー', 'test@example.com', 'hash', NOW(), NOW());

INSERT IGNORE INTO kakeibo_records (id, user_id, purchase_date, amount_type_id, amount, details, kakeibo_default_category_id, created_at, updated_at) VALUES
    (1, 1, '2026-07-21', 1, 1500, 'コンビニでお昼', 1, NOW(), NOW());
"""

_RESMANE_TRUNCATE_TABLES = ["posts", "self_reviews"]
_WORKER_TRUNCATE_TABLES = ["worker_jobs", "sync_watermarks"]


def _exec_multi(conn, sql):
    for statement in sql.strip().split(";"):
        statement = statement.strip()
        if statement:
            cursor = conn.cursor()
            cursor.execute(statement)
            cursor.close()
    conn.commit()


def _make_test_config():
    return Config(
        db_host=TEST_DB_HOST,
        db_port=TEST_DB_PORT,
        db_name=RESMANE_TEST_DB,
        db_user=TEST_DB_USER,
        db_password=TEST_DB_PASSWORD,
        worker_own_db_host=TEST_DB_HOST,
        worker_own_db_port=TEST_DB_PORT,
        worker_own_db_name=WORKER_TEST_DB,
        worker_own_db_user=TEST_DB_USER,
        worker_own_db_password=TEST_DB_PASSWORD,
        poll_interval_sec=30,
        ai_api_key="test",
        ai_api_url="https://test.example.com",
        ai_model="test-model",
        stale_timeout_sec=300,
    )


@pytest.fixture(scope="session")
def test_config():
    return _make_test_config()


@pytest.fixture(scope="session", autouse=True)
def setup_databases(test_config):
    resmane_conn = mysql.connector.connect(
        host=test_config.db_host,
        port=test_config.db_port,
        database=test_config.db_name,
        user=test_config.db_user,
        password=test_config.db_password,
        charset="utf8mb4",
    )
    _exec_multi(resmane_conn, _RESMANE_TABLES_SQL)
    _exec_multi(resmane_conn, _SEED_SQL)
    resmane_conn.close()

    worker_conn = mysql.connector.connect(
        host=test_config.worker_own_db_host,
        port=test_config.worker_own_db_port,
        database=test_config.worker_own_db_name,
        user=test_config.worker_own_db_user,
        password=test_config.worker_own_db_password,
        charset="utf8mb4",
    )
    _exec_multi(worker_conn, _WORKER_TABLES_SQL)
    worker_conn.close()


@pytest.fixture
def resmane_db(test_config):
    db = ResManeDatabase(test_config)
    conn = db.get_connection()
    for table in _RESMANE_TRUNCATE_TABLES:
        cursor = conn.cursor()
        cursor.execute(f"TRUNCATE TABLE {table}")
        cursor.close()
    conn.commit()
    yield db
    db.close()


@pytest.fixture
def worker_db(test_config):
    db = ResmaneWorkerDatabase(test_config)
    conn = db.get_connection()
    for table in _WORKER_TRUNCATE_TABLES:
        cursor = conn.cursor()
        cursor.execute(f"TRUNCATE TABLE {table}")
        cursor.close()
    conn.commit()
    yield db
    db.close()


@pytest.fixture
def raw_resmane_conn(test_config):
    conn = mysql.connector.connect(
        host=test_config.db_host,
        port=test_config.db_port,
        database=test_config.db_name,
        user=test_config.db_user,
        password=test_config.db_password,
        charset="utf8mb4",
        autocommit=True,
    )
    yield conn
    conn.close()


@pytest.fixture
def raw_worker_conn(test_config):
    conn = mysql.connector.connect(
        host=test_config.worker_own_db_host,
        port=test_config.worker_own_db_port,
        database=test_config.worker_own_db_name,
        user=test_config.worker_own_db_user,
        password=test_config.worker_own_db_password,
        charset="utf8mb4",
        autocommit=True,
    )
    yield conn
    conn.close()
