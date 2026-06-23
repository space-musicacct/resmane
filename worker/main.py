import os
import time
import logging

import schedule

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
)
logger = logging.getLogger(__name__)

POLL_INTERVAL = int(os.environ.get("WORKER_POLL_INTERVAL_SEC", "30"))


def check_pending_tasks() -> None:
    logger.info("未処理タスクを確認中...")
    # TODO: MySQL から未処理レコードを取得 -> AI API 呼び出し -> 結果を書き戻す


def main() -> None:
    logger.info("レスマネ Worker を起動しました (間隔: %d 秒)", POLL_INTERVAL)
    schedule.every(POLL_INTERVAL).seconds.do(check_pending_tasks)

    while True:
        schedule.run_pending()
        time.sleep(1)


if __name__ == "__main__":
    main()
