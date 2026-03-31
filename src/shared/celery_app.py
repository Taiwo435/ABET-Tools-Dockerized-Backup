"""
Shared Celery application — imported by all services and the worker.

API containers import this to send tasks (via .delay() or send_task()).
The worker container imports this to discover and execute tasks.
"""

import os
from celery import Celery
from kombu import Queue

celery_app = Celery("abet_tools")

# ---------- Broker & Backend ----------
celery_app.conf.broker_url = os.getenv("CELERY_BROKER_URL", "redis://redis:6379/0")
celery_app.conf.result_backend = os.getenv(
    "CELERY_RESULT_BACKEND", "redis://redis:6379/1"
)

# ---------- Serialization ----------
celery_app.conf.task_serializer = "json"
celery_app.conf.result_serializer = "json"
celery_app.conf.accept_content = ["json"]
celery_app.conf.timezone = "UTC"
celery_app.conf.enable_utc = True

# ---------- Startup ----------
celery_app.conf.broker_connection_retry_on_startup = (
    True  # Redis may not be ready when containers start
)

# ---------- Reliability ----------
celery_app.conf.task_acks_late = (
    True  # Acknowledge after execution so tasks survive worker crashes
)
celery_app.conf.worker_prefetch_multiplier = 1  # One task at a time per worker process
celery_app.conf.task_reject_on_worker_lost = (
    True  # If a worker is killed mid-task, put the message back on the queue
)
celery_app.conf.task_track_started = (
    True  # Track STARTED state in result backend (needed for progress polling)
)

# ---------- Time Limits ----------
celery_app.conf.task_soft_time_limit = 1800  # 30 min
# Hard limit: SIGKILL after this
celery_app.conf.task_time_limit = 2100  # 35 min

# ---------- Redis Visibility Timeout ----------
# Must exceed your longest possible task duration.
# If a task runs longer than this, Redis assumes the worker died
# and redelivers the message, causing duplicate execution.
celery_app.conf.broker_transport_options = {"visibility_timeout": 43200}  # 12 hours

# ---------- Result Storage ----------
celery_app.conf.result_expires = 86400  # 24h, store to mysql for persistent storage
celery_app.conf.result_extended = (
    False  # Don't store task args/kwargs in result backend
)

# ---------- Worker Health ----------
celery_app.conf.worker_max_tasks_per_child = 200
celery_app.conf.worker_max_memory_per_child = 512_000  # 512 MB cap

# ---------- Queue Routing ----------
celery_app.conf.task_queues = (
    Queue("default"),
    Queue("extraction"),
    Queue("formatting"),
    Queue("reportgen"),
)
celery_app.conf.task_default_queue = "default"
celery_app.conf.task_routes = {
    "extraction.*": {"queue": "extraction"},
    "formatting.*": {"queue": "formatting"},
    "reportgen.*": {"queue": "reportgen"},
}

# ---------- Task Discovery ----------
# Each entry = a package containing a tasks.py module.
# The worker imports these at startup. API containers only need
# celery_app to call .delay() or send_task().
celery_app.autodiscover_tasks(
    [
        "extraction_api",
        # Uncomment when these services add tasks.py:
        # "canvas_formatting_api",
        # "report_generation_api",
    ]
)
