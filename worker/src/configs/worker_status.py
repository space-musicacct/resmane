"""Worker ジョブステータス・終了理由の定数。"""


class WorkerStatus:
    PROCESSING = "processing"
    COMPLETED = "completed"
    FAILED = "failed"
    CANCELLED = "cancelled"


class TerminationReason:
    TARGET_DELETED = "target_deleted"
    MAX_RETRIES_EXCEEDED = "max_retries_exceeded"
