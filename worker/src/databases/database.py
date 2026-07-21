"""DB 接続管理の抽象基底クラス。"""

import logging

import mysql.connector
from mysql.connector import MySQLConnection

logger = logging.getLogger(__name__)


class Database:
    """DB 接続の確立・維持・トランザクション制御を担当する基底クラス。"""

    def __init__(
        self,
        host: str,
        port: int,
        database: str,
        user: str,
        password: str,
    ) -> None:
        self._host = host
        self._port = port
        self._database = database
        self._user = user
        self._password = password
        self._connection: MySQLConnection | None = None

    def get_connection(self) -> MySQLConnection:
        """有効な接続を返す。切断されていれば再接続する。"""
        if self._connection is None or not self._connection.is_connected():
            self._connect()
        return self._connection

    def begin_transaction(self) -> None:
        """トランザクションを開始する。"""
        self.get_connection().start_transaction()

    def commit(self) -> None:
        """トランザクションをコミットする。"""
        self.get_connection().commit()

    def rollback(self) -> None:
        """トランザクションをロールバックする。"""
        self.get_connection().rollback()

    def close(self) -> None:
        """接続を閉じる。"""
        if self._connection and self._connection.is_connected():
            self._connection.close()
            self._connection = None

    def _connect(self) -> None:
        self._connection = mysql.connector.connect(
            host=self._host,
            port=self._port,
            database=self._database,
            user=self._user,
            password=self._password,
            charset="utf8mb4",
            autocommit=True,
        )
        logger.info("DB 接続完了: %s:%d/%s", self._host, self._port, self._database)
