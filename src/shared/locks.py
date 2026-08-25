import redis
import os

_redis = redis.Redis.from_url(
    os.getenv("CELERY_BROKER_URL", "redis://redis:6379/0"),
    decode_responses=True,
)

def _build_lock_key(source_course_id: str, dest_course_id: str) -> str:
    REDIS_PREFIX = os.getenv("REDIS_PREFIX_CELERY", "celery_")
    return f"{REDIS_PREFIX}extract_lock:{source_course_id}_to_{dest_course_id}"

def acquire_course_lock(source_course_id: str, dest_course_id: str, ttl_seconds: int = 2700) -> bool:
    """Try to lock a source course to a specific destination. Returns True if acquired."""
    key = _build_lock_key(source_course_id, dest_course_id)
    return bool(_redis.set(key, "locked", nx=True, ex=ttl_seconds))

def release_course_lock(source_course_id: str, dest_course_id: str) -> None:
    """Release a source to destination course lock."""
    key = _build_lock_key(source_course_id, dest_course_id)
    _redis.delete(key)

def is_course_locked(source_course_id: str, dest_course_id: str) -> bool:
    """Check for: is this course currently being extracted to this destination?"""
    key = _build_lock_key(source_course_id, dest_course_id)
    return bool(_redis.exists(key))
