"""Gemini API クライアント。"""

import logging

import requests

from src.clients.contracts.ai_client_interface import AiClientInterface

logger = logging.getLogger(__name__)


class GeminiClient(AiClientInterface):
    """Gemini API を呼び出す具象クライアント。"""

    def __init__(
        self,
        api_key: str,
        api_url: str,
        model: str,
        max_output_tokens: int = 1500,
    ) -> None:
        self._api_key = api_key
        self._api_url = api_url.rstrip("/")
        self._model = model
        self._max_output_tokens = max_output_tokens

    def generate(
        self,
        messages: list[dict],
        system_instruction: str | None = None,
    ) -> str:
        url = f"{self._api_url}/models/{self._model}:generateContent"

        body: dict = {
            "contents": self._build_contents(messages),
            "generationConfig": {
                "maxOutputTokens": self._max_output_tokens,
            },
        }
        if system_instruction:
            body["systemInstruction"] = {
                "parts": [{"text": system_instruction}],
            }

        response = requests.post(
            url,
            json=body,
            params={"key": self._api_key},
            timeout=60,
        )
        response.raise_for_status()

        data = response.json()
        return data["candidates"][0]["content"]["parts"][0]["text"]

    def _build_contents(self, messages: list[dict]) -> list[dict]:
        """汎用メッセージ形式を Gemini API の contents 形式に変換する。"""
        contents = []
        for msg in messages:
            role = "model" if msg["role"] == "assistant" else msg["role"]
            contents.append({
                "role": role,
                "parts": [{"text": msg["content"]}],
            })
        return contents
