CREATE TABLE IF NOT EXISTS worker_jobs (
    id                  BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    post_id             BIGINT UNSIGNED  NOT NULL,
    status              VARCHAR(20)      NOT NULL DEFAULT 'processing',
    claimed_at          DATETIME         NOT NULL,
    retry_count         INT UNSIGNED     NOT NULL DEFAULT 0,
    max_retries         INT UNSIGNED     NOT NULL DEFAULT 3,
    last_error          TEXT             NULL,
    termination_reason  VARCHAR(40)      NULL,
    created_at          DATETIME         NOT NULL,
    updated_at          DATETIME         NOT NULL,
    deleted_at          DATETIME         NULL,
    INDEX idx_worker_jobs_post_id (post_id),
    INDEX idx_worker_jobs_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
