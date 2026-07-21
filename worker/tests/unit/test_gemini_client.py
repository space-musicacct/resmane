"""GeminiClient の単体テスト。"""

from unittest.mock import patch, MagicMock

import pytest
import requests

from src.clients.gemini_client import GeminiClient


def _make_response(text="テスト応答"):
    return {
        "candidates": [
            {"content": {"parts": [{"text": text}], "role": "model"}}
        ]
    }


class TestGeminiClient:
    """UGC-001 〜 UGC-008"""

    def setup_method(self):
        self.client = GeminiClient(
            api_key="test-key",
            api_url="https://api.example.com/v1",
            model="test-model",
        )

    @patch("src.clients.gemini_client.requests.post")
    def test_generate_success(self, mock_post):
        """UGC-001: 正常応答。"""
        mock_post.return_value = MagicMock(
            status_code=200, json=lambda: _make_response("OK")
        )
        result = self.client.generate([{"role": "user", "content": "hi"}])
        assert result == "OK"

    @patch("src.clients.gemini_client.requests.post")
    def test_generate_with_system_instruction(self, mock_post):
        """UGC-002: system_instruction 付き。"""
        mock_post.return_value = MagicMock(
            status_code=200, json=lambda: _make_response()
        )
        self.client.generate(
            [{"role": "user", "content": "hi"}],
            system_instruction="be helpful",
        )
        body = mock_post.call_args[1]["json"]
        assert "systemInstruction" in body
        assert body["systemInstruction"]["parts"][0]["text"] == "be helpful"

    @patch("src.clients.gemini_client.requests.post")
    def test_generate_without_system_instruction(self, mock_post):
        """UGC-003: system_instruction なし。"""
        mock_post.return_value = MagicMock(
            status_code=200, json=lambda: _make_response()
        )
        self.client.generate([{"role": "user", "content": "hi"}])
        body = mock_post.call_args[1]["json"]
        assert "systemInstruction" not in body

    @patch("src.clients.gemini_client.requests.post")
    def test_generate_role_conversion(self, mock_post):
        """UGC-004: role 変換 (assistant → model)。"""
        mock_post.return_value = MagicMock(
            status_code=200, json=lambda: _make_response()
        )
        self.client.generate([
            {"role": "user", "content": "hi"},
            {"role": "assistant", "content": "hello"},
        ])
        contents = mock_post.call_args[1]["json"]["contents"]
        assert contents[0]["role"] == "user"
        assert contents[1]["role"] == "model"

    @patch("src.clients.gemini_client.requests.post")
    def test_generate_http_error(self, mock_post):
        """UGC-005: HTTP エラー。"""
        response = MagicMock(status_code=500)
        response.raise_for_status.side_effect = requests.HTTPError(response=response)
        mock_post.return_value = response
        with pytest.raises(requests.HTTPError):
            self.client.generate([{"role": "user", "content": "hi"}])

    @patch("src.clients.gemini_client.requests.post")
    def test_generate_timeout(self, mock_post):
        """UGC-006: タイムアウト。"""
        mock_post.side_effect = requests.Timeout()
        with pytest.raises(requests.Timeout):
            self.client.generate([{"role": "user", "content": "hi"}])

    @patch("src.clients.gemini_client.requests.post")
    def test_api_key_in_header(self, mock_post):
        """UGC-007: API キーがヘッダーで送信される。"""
        mock_post.return_value = MagicMock(
            status_code=200, json=lambda: _make_response()
        )
        self.client.generate([{"role": "user", "content": "hi"}])
        headers = mock_post.call_args[1]["headers"]
        assert headers["x-goog-api-key"] == "test-key"
        url = mock_post.call_args[0][0]
        assert "test-key" not in url

    @patch("src.clients.gemini_client.requests.post")
    def test_max_output_tokens_in_request(self, mock_post):
        """UGC-008: maxOutputTokens がリクエストに含まれる。"""
        mock_post.return_value = MagicMock(
            status_code=200, json=lambda: _make_response()
        )
        self.client.generate([{"role": "user", "content": "hi"}])
        body = mock_post.call_args[1]["json"]
        assert body["generationConfig"]["maxOutputTokens"] == 1500
