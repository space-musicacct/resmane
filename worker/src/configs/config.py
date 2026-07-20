"""アプリケーション設定。"""

import os


class Config:
    """環境変数から読み取った設定値を保持する。"""

    def __init__(
        self,
        db_host: str,
        db_port: int,
        db_name: str,
        db_user: str,
        db_password: str,
        worker_own_db_host: str,
        worker_own_db_port: int,
        worker_own_db_name: str,
        worker_own_db_user: str,
        worker_own_db_password: str,
        poll_interval_sec: int,
        ai_api_key: str,
        ai_api_url: str,
        ai_model: str,
        stale_timeout_sec: int,
    ) -> None:
        self.db_host = db_host
        self.db_port = db_port
        self.db_name = db_name
        self.db_user = db_user
        self.db_password = db_password
        self.worker_own_db_host = worker_own_db_host
        self.worker_own_db_port = worker_own_db_port
        self.worker_own_db_name = worker_own_db_name
        self.worker_own_db_user = worker_own_db_user
        self.worker_own_db_password = worker_own_db_password
        self.poll_interval_sec = poll_interval_sec
        self.ai_api_key = ai_api_key
        self.ai_api_url = ai_api_url
        self.ai_model = ai_model
        self.stale_timeout_sec = stale_timeout_sec

    @classmethod
    def from_env(cls) -> "Config":
        """os.environ から設定を読み取ってインスタンスを生成する。"""
        return cls(
            db_host=os.environ.get("WORKER_DB_HOST", "db"),
            db_port=int(os.environ.get("WORKER_DB_PORT", "3306")),
            db_name=os.environ.get("WORKER_DB_NAME", "resmane"),
            db_user=os.environ.get("WORKER_DB_USER", ""),
            db_password=os.environ.get("WORKER_DB_PASSWORD", ""),
            worker_own_db_host=os.environ.get("WORKER_OWN_DB_HOST", "db"),
            worker_own_db_port=int(os.environ.get("WORKER_OWN_DB_PORT", "3306")),
            worker_own_db_name=os.environ.get("WORKER_OWN_DB_NAME", "resmane_worker"),
            worker_own_db_user=os.environ.get("WORKER_OWN_DB_USER", ""),
            worker_own_db_password=os.environ.get("WORKER_OWN_DB_PASSWORD", ""),
            poll_interval_sec=int(os.environ.get("WORKER_POLL_INTERVAL_SEC", "30")),
            ai_api_key=os.environ.get("AI_API_KEY", ""),
            ai_api_url=os.environ.get("AI_API_URL", ""),
            ai_model=os.environ.get("AI_MODEL", "gemini-3.5-flash"),
            stale_timeout_sec=int(os.environ.get("WORKER_STALE_TIMEOUT_SEC", "300")),
        )
