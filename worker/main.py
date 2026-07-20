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
from src.repositories.sync_watermark_repository import SyncWatermarkRepository
from src.clients.gemini_client import GeminiClient
from src.services.delete_sync_service import DeleteSyncService
from src.services.feedback_service import FeedbackService

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
)
logger = logging.getLogger(__name__)

HEALTH_CHECK_PATH = Path("/tmp/worker_healthy")
RECONCILE_INTERVAL_SEC = 3600


def main() -> None:
    config = Config.from_env()

    db = ResManeDatabase(config)
    worker_db = ResmaneWorkerDatabase(config)
    post_repo = PostRepository(db)
    context_repo = KakeiboContextRepository(db)
    job_repo = WorkerJobRepository(worker_db)
    watermark_repo = SyncWatermarkRepository(worker_db)
    ai_client = GeminiClient(
        api_key=config.ai_api_key,
        api_url=config.ai_api_url,
        model=config.ai_model,
    )

    delete_sync = DeleteSyncService(
        worker_db=worker_db,
        post_repo=post_repo,
        job_repo=job_repo,
        watermark_repo=watermark_repo,
    )

    feedback = FeedbackService(
        config=config,
        db=db,
        post_repo=post_repo,
        context_repo=context_repo,
        job_repo=job_repo,
        ai_client=ai_client,
    )

    def tick() -> None:
        try:
            delete_sync.sync()
        except Exception:
            logger.exception("削除同期に失敗、後続処理を中止")
            return

        try:
            feedback.recover_stale()
            feedback.process_pending()
        except Exception:
            logger.exception("フィードバック処理中にエラーが発生")

    def reconcile() -> None:
        try:
            delete_sync.reconcile()
        except Exception:
            logger.exception("全件照合に失敗")

    logger.info(
        "レスマネ Worker を起動しました (間隔: %d 秒, stale timeout: %d 秒)",
        config.poll_interval_sec,
        config.stale_timeout_sec,
    )

    schedule.every(config.poll_interval_sec).seconds.do(tick)
    schedule.every(RECONCILE_INTERVAL_SEC).seconds.do(reconcile)

    while True:
        schedule.run_pending()
        HEALTH_CHECK_PATH.touch()
        time.sleep(1)


if __name__ == "__main__":
    main()
