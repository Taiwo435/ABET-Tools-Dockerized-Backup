"""
Celery task wrappers for the extraction pipeline.

Main business logic lives in assignment_extraction_api.py.
"""

from celery import shared_task

from shared.base_task import TrackedTask
from extraction_api.csv_filter import RosterMap
from extraction_api.assignment_extraction_api import run_pipeline_sync


@shared_task(
    name="extraction.run_pipeline",
    bind=True, # Means the first argument will be the task instance(self)
    base=TrackedTask, # Specifies the base class to use for the task
    queue="extraction",
)
def run_extraction_pipeline(self, job_params: dict):
    """Deserialize params, return result."""
    roster = RosterMap(
        by_asurite=job_params["roster"]["by_asurite"],
        by_id=job_params["roster"]["by_id"],
    )

    result = run_pipeline_sync(
        course_id_to_push=job_params["course_id_to_push"],
        canvas_access_token=job_params["canvas_access_token"],
        course_ids_to_pull=job_params["course_ids_to_pull"],
        student_major_map=roster,
        on_progress=lambda pct, msg: self.update_progress(pct, msg),
    )

    return result
