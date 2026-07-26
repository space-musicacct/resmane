SELECT @@character_set_database, @@collation_database;

-- Worker 専用データベース
CREATE DATABASE IF NOT EXISTS `resmane_worker`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- Worker 専用ユーザー (resmane 本体 DB への読み書き権限 + Worker DB への全権限)
CREATE USER IF NOT EXISTS 'resmane_worker'@'%' IDENTIFIED BY 'resmane_password';
GRANT SELECT, INSERT, UPDATE ON `resmane`.* TO 'resmane_worker'@'%';
GRANT ALL PRIVILEGES ON `resmane_worker`.* TO 'resmane_worker'@'%';
FLUSH PRIVILEGES;

-- Worker 専用テーブル
USE `resmane_worker`;

CREATE TABLE IF NOT EXISTS worker_jobs (
    id                  BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    post_id             BIGINT UNSIGNED  NOT NULL,
    status              VARCHAR(20)      NOT NULL DEFAULT 'processing',
    claim_version       INT UNSIGNED     NOT NULL DEFAULT 0,
    claimed_at          DATETIME         NOT NULL,
    retry_count         INT UNSIGNED     NOT NULL DEFAULT 0,
    max_retries         INT UNSIGNED     NOT NULL DEFAULT 3,
    last_error          TEXT             NULL,
    termination_reason  VARCHAR(40)      NULL,
    created_at          DATETIME         NOT NULL,
    updated_at          DATETIME         NOT NULL,
    deleted_at          DATETIME         NULL,
    UNIQUE KEY uq_worker_jobs_post_id (post_id),
    INDEX idx_worker_jobs_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sync_watermarks (
    id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    table_name      VARCHAR(64)      NOT NULL,
    last_deleted_at DATETIME         NOT NULL DEFAULT '1970-01-01 00:00:00',
    last_id         BIGINT UNSIGNED  NOT NULL DEFAULT 0,
    updated_at      DATETIME         NOT NULL,
    UNIQUE KEY uq_sync_watermarks_table_name (table_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
