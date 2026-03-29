"""
TrackedTask — base class for all ABET Tools Celery tasks.

Automatically syncs task lifecycle to the job_history MySQL table.
Provides self.update_progress(percent, message) for in-task progress reporting.

Usage in any service's tasks.py:

    from celery import shared_task
    from shared.base_task import TrackedTask

    @shared_task(name="extraction.run_pipeline", bind=True, base=TrackedTask)
    def run_pipeline(self, params: dict):
        self.update_progress(10, "Starting...")
        # ... do work ...
        self.update_progress(90, "Almost done...")
        return {"status": "ok"}
"""

import logging
from datetime import datetime, timezone

from celery import Task
from shared.db import update_job

logger = logging.getLogger(__name__)


class TrackedTask(Task):
    """Celery Task subclass that writes state to both Redis AND MySQL."""

    # Enable STARTED state in result backend
    track_started = True

    def before_start(self, task_id, args, kwargs):
        """Called by Celery just before the task function executes."""
        update_job(
            task_id,
            status="processing",
            started_at=datetime.now(timezone.utc),
            attempts=1, 
        )

    def on_success(self, retval, task_id, args, kwargs):
        """Called when the task completes without error."""
        result_meta = retval if isinstance(retval, dict) else None
        update_job(
            task_id,
            status="completed",
            progress=100,
            message="Complete.",
            result_meta=result_meta,
            completed_at=datetime.now(timezone.utc),
        )

    def on_failure(self, exc, task_id, args, kwargs, einfo):
        """Called when the task raises an unhandled exception."""
        update_job(
            task_id,
            status="failed",
            error_message=str(exc),
            completed_at=datetime.now(timezone.utc),
        )

    def on_retry(self, exc, task_id, args, kwargs, einfo):
        """Called when the task schedules a retry."""
        update_job(
            task_id,
            status="processing",
            message=f"Retrying (attempt {self.request.retries + 1}): {exc}",
        )

    def update_progress(self, percent: int, message: str = ""):
        """Call from within a running task to update both Redis and MySQL.

        Example:
            self.update_progress(45, "Processing Step 3/7...")
        """
        # Redis
        self.update_state(
            state="PROGRESS",
            meta={"progress": percent, "message": message},
        )
        # MySQL
        update_job(
            self.request.id,
            status="processing",
            progress=percent,
            message=message,
        )
