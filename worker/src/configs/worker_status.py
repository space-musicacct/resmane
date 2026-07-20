"""Worker ジョブステータス・終了理由の定数。"""


class WorkerStatus:
    PROCESSING = "processing"
    RETRY_PENDING = "retry_pending"
    COMPLETED = "completed"
    FAILED = "failed"
    CANCELLED = "cancelled"


class TerminationReason:
    TARGET_DELETED = "target_deleted"
    STATE_INCONSISTENCY = "state_inconsistency"
    MAX_RETRIES_EXCEEDED = "max_retries_exceeded"
