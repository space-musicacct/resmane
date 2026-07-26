CREATE TABLE IF NOT EXISTS sync_watermarks (
    id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    table_name      VARCHAR(64)      NOT NULL,
    last_deleted_at DATETIME         NOT NULL DEFAULT '1970-01-01 00:00:00',
    last_id         BIGINT UNSIGNED  NOT NULL DEFAULT 0,
    updated_at      DATETIME         NOT NULL,
    UNIQUE KEY uq_sync_watermarks_table_name (table_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
