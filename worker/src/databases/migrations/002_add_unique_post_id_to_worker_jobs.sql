ALTER TABLE worker_jobs
    ADD CONSTRAINT uq_worker_jobs_post_id UNIQUE (post_id);
