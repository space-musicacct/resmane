"""レスマネ本体 DB 接続 (posts, kakeibo_records 等)。"""

from src.configs.config import Config
from src.databases.database import Database


class ResManeDatabase(Database):
    """レスマネ本体のデータベースに接続する。"""

    def __init__(self, config: Config) -> None:
        super().__init__(
            host=config.db_host,
            port=config.db_port,
            database=config.db_name,
            user=config.db_user,
            password=config.db_password,
        )
