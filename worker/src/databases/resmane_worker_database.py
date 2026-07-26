"""Worker 専用 DB 接続 (worker_jobs 等)。"""

from src.configs.config import Config
from src.databases.database import Database


class ResmaneWorkerDatabase(Database):
    """Worker 専用のデータベースに接続する。"""

    def __init__(self, config: Config) -> None:
        super().__init__(
            host=config.worker_own_db_host,
            port=config.worker_own_db_port,
            database=config.worker_own_db_name,
            user=config.worker_own_db_user,
            password=config.worker_own_db_password,
        )
