"""Config の単体テスト。"""

import os
from unittest.mock import patch

from src.configs.config import Config


class TestConfig:
    """UC-001 〜 UC-003"""

    def test_from_env_reads_all_fields(self):
        """UC-001: 環境変数から全フィールドを読み取る。"""
        env = {
            "WORKER_DB_HOST": "testhost",
            "WORKER_DB_PORT": "3307",
            "WORKER_DB_NAME": "testdb",
            "WORKER_DB_USER": "testuser",
            "WORKER_DB_PASSWORD": "testpass",
            "WORKER_OWN_DB_HOST": "workerhost",
            "WORKER_OWN_DB_PORT": "3308",
            "WORKER_OWN_DB_NAME": "workerdb",
            "WORKER_OWN_DB_USER": "workeruser",
            "WORKER_OWN_DB_PASSWORD": "workerpass",
            "WORKER_POLL_INTERVAL_SEC": "60",
            "WORKER_STALE_TIMEOUT_SEC": "600",
            "AI_API_KEY": "test-key",
            "AI_API_URL": "https://test.example.com/v1",
            "AI_MODEL": "test-model",
        }
        with patch.dict(os.environ, env, clear=True):
            config = Config.from_env()

        assert config.db_host == "testhost"
        assert config.db_port == 3307
        assert config.db_name == "testdb"
        assert config.db_user == "testuser"
        assert config.db_password == "testpass"
        assert config.worker_own_db_host == "workerhost"
        assert config.worker_own_db_port == 3308
        assert config.worker_own_db_name == "workerdb"
        assert config.worker_own_db_user == "workeruser"
        assert config.worker_own_db_password == "workerpass"
        assert config.poll_interval_sec == 60
        assert config.stale_timeout_sec == 600
        assert config.ai_api_key == "test-key"
        assert config.ai_api_url == "https://test.example.com/v1"
        assert config.ai_model == "test-model"

    def test_from_env_defaults(self):
        """UC-002: 未設定の環境変数はデフォルト値。"""
        with patch.dict(os.environ, {}, clear=True):
            config = Config.from_env()

        assert config.db_host == "db"
        assert config.db_port == 3306
        assert config.db_name == "resmane"
        assert config.db_user == ""
        assert config.worker_own_db_host == "db"
        assert config.worker_own_db_name == "resmane_worker"
        assert config.poll_interval_sec == 30
        assert config.stale_timeout_sec == 300
        assert config.ai_model == "gemini-3.5-flash"

    def test_constructor_preserves_args(self):
        """UC-003: 全引数がそのまま保持される。"""
        config = Config(
            db_host="h", db_port=1, db_name="n", db_user="u", db_password="p",
            worker_own_db_host="wh", worker_own_db_port=2,
            worker_own_db_name="wn", worker_own_db_user="wu",
            worker_own_db_password="wp",
            poll_interval_sec=10, ai_api_key="k", ai_api_url="url",
            ai_model="m", stale_timeout_sec=99,
        )

        assert config.db_host == "h"
        assert config.db_port == 1
        assert config.ai_api_key == "k"
        assert config.stale_timeout_sec == 99
