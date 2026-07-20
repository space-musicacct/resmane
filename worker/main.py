"""レスマネ AI Worker エントリーポイント。"""

import time
import logging
from pathlib import Path

import schedule

from src.configs.config import Config
from src.databases.resmane_database import ResManeDatabase
from src.databases.resmane_worker_database import ResmaneWorkerDatabase
from src.repositories.post_repository import PostRepository
from src.repositories.kakeibo_context_repository import KakeiboContextRepository
from src.repositories.worker_job_repository import WorkerJobRepository
from src.clients.gemini_client import GeminiClient
from src.services.feedback_service import FeedbackService

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
)
logger = logging.getLogger(__name__)

HEALTH_CHECK_PATH = Path("/tmp/worker_healthy")


def main() -> None:
    config = Config.from_env()

    db = ResManeDatabase(config)
    worker_db = ResmaneWorkerDatabase(config)
    post_repo = PostRepository(db)
    context_repo = KakeiboContextRepository(db)
    job_repo = WorkerJobRepository(worker_db)
    ai_client = GeminiClient(
        api_key=config.ai_api_key,
        api_url=config.ai_api_url,
        model=config.ai_model,
    )

    service = FeedbackService(
        config=config,
        db=db,
        post_repo=post_repo,
        context_repo=context_repo,
        job_repo=job_repo,
        ai_client=ai_client,
    )

    def tick() -> None:
        try:
            service.recover_stale()
            service.process_pending()
        except Exception:
            logger.exception("ポーリング中にエラーが発生")

    logger.info(
        "レスマネ Worker を起動しました (間隔: %d 秒, stale timeout: %d 秒)",
        config.poll_interval_sec,
        config.stale_timeout_sec,
    )

    schedule.every(config.poll_interval_sec).seconds.do(tick)

    while True:
        schedule.run_pending()
        HEALTH_CHECK_PATH.touch()
        time.sleep(1)


if __name__ == "__main__":
    main()
