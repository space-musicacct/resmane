"""AI API クライアントの契約。"""

from abc import ABC, abstractmethod


class AiClientInterface(ABC):
    """外部 AI API への問い合わせの契約。"""

    @abstractmethod
    def generate(
        self,
        messages: list[dict],
        system_instruction: str | None = None,
    ) -> str:
        """メッセージを送信し、AI の応答テキストを返す。

        Args:
            messages: 会話履歴。[{"role": "user"|"assistant", "content": "..."}]
            system_instruction: システムプロンプト。
        """
