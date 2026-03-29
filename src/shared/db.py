"""
Database helpers for Celery workers.

Uses PyMySQL
Connection-per-call pattern since workers are long-lived processes, so we don't hold connections open between tasks.
"""

import os
import json
import logging
from contextlib import contextmanager
from datetime import datetime, timezone

import pymysql
import pymysql.cursors

logger = logging.getLogger(__name__)


@contextmanager
def get_db_connection():
    """Yield a short-lived pymysql connection using project env vars."""
    conn = pymysql.connect(
        host=os.getenv("MYSQL_HOSTNAME", "mysql"),
        user=os.getenv("MYSQL_USER"),
        password=os.getenv("MYSQL_PASS"),
        database=os.getenv("MYSQL_DATABASE"),
        port=int(os.getenv("MYSQL_PORT", 3306)),
        cursorclass=pymysql.cursors.DictCursor,
        connect_timeout=10,
    )
    try:
        yield conn
    finally:
        conn.close()


def insert_job(
    job_id: str,
    job_type: str,
    service: str,
    submitted_by: int | None = None,
    params: dict | None = None,
) -> None:
    """Insert a new row into job_history when a task is queued."""
    try:
        with get_db_connection() as conn:
            with conn.cursor() as cur:
                cur.execute(
                    """
                    INSERT INTO job_history
                        (id, job_type, service, submitted_by, status, progress, params)
                    VALUES (%s, %s, %s, %s, 'pending', 0, %s)
                    """,
                    (
                        job_id,
                        job_type,
                        service,
                        submitted_by,
                        json.dumps(params, default=str) if params else None,
                    ),
                )
            conn.commit()
    except Exception:
        logger.exception("Failed to insert job %s", job_id)


def update_job(job_id: str, **fields) -> None:
    """Update an existing job_history row.

    Accepts any column name as a keyword argument:
        update_job("abc-123", status="processing", progress=45, message="Working...")
    """
    if not fields:
        return

    sets = []
    vals = []
    for col, val in fields.items():
        if col in ("params", "result_meta") and isinstance(val, dict):
            val = json.dumps(val, default=str)
        sets.append(f"{col} = %s")
        vals.append(val)

    vals.append(job_id)

    try:
        with get_db_connection() as conn:
            with conn.cursor() as cur:
                cur.execute(
                    f"UPDATE job_history SET {', '.join(sets)} WHERE id = %s",
                    tuple(vals),
                )
            conn.commit()
    except Exception:
        logger.exception("Failed to update job %s", job_id)


def get_job(job_id: str) -> dict | None:
    """Fetch a single job row."""
    try:
        with get_db_connection() as conn:
            with conn.cursor() as cur:
                cur.execute("SELECT * FROM job_history WHERE id = %s", (job_id,))
                return cur.fetchone()
    except Exception:
        logger.exception("Failed to fetch job %s", job_id)
        return None


def list_jobs(
    status: str | None = None,
    service: str | None = None,
    limit: int = 50,
) -> list[dict]:
    """List jobs, newest first. Optional filters."""
    clauses = []
    vals = []
    if status:
        clauses.append("status = %s")
        vals.append(status)
    if service:
        clauses.append("service = %s")
        vals.append(service)

    where = f"WHERE {' AND '.join(clauses)}" if clauses else ""
    vals.append(limit)

    try:
        with get_db_connection() as conn:
            with conn.cursor() as cur:
                cur.execute(
                    f"SELECT * FROM job_history {where} ORDER BY created_at DESC LIMIT %s",
                    tuple(vals),
                )
                return cur.fetchall()
    except Exception:
        logger.exception("Failed to list jobs")
        return []
