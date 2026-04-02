"""
Celery task wrappers for the extraction pipeline.

Main business logic lives in assignment_extraction_api.py.
"""

from celery import shared_task
import requests

from shared.base_task import TrackedTask
from extraction_api.csv_filter import RosterMap
from extraction_api.assignment_extraction_api import run_pipeline_sync


@shared_task(
    name="extraction.run_pipeline",
    bind=True,  # Means the first argument will be the task instance(self)
    base=TrackedTask,  # Specifies the base class to use for the task
    queue="extraction",
)
def run_extraction_pipeline(self, job_params: dict):
    """Deserialize params, return result."""
    roster = RosterMap(
        by_asurite=job_params["roster"]["by_asurite"],
        by_id=job_params["roster"]["by_id"],
    )

    course_ids = job_params["course_ids_to_pull"]
    try:
        result = run_pipeline_sync(
            course_id_to_push=job_params["course_id_to_push"],
            canvas_access_token=job_params["canvas_access_token"],
            user_id=str(job_params.get("submitted_by_user_id") or ""),
            course_ids_to_pull=course_ids,
            student_major_map=roster,
            on_progress=lambda pct, msg: self.update_progress(pct, msg),
        )

        self.update_progress(95, "Extraction complete. Starting Canvas module formatting...")
        
        formatting_base = "http://canvas_formatting_api:8001"
        format_url = f"{formatting_base}/format-and-upload/{job_params['course_id_to_push']}"
        
        params = {
            "course_folder_name": result.get("course_folder_name"),
            "term_display": result.get("term_display"),
        }
        
        if job_params.get("overwrite"):
            params["overwrite"] = "true"
            
        headers = {"canvas-access-token": job_params["canvas_access_token"]}
        
        try:
            resp = requests.post(format_url, params=params, headers=headers, timeout=600)
            resp.raise_for_status()
            self.update_progress(100, "Extraction and formatting complete.")
        except Exception as e:
            raise Exception(f"Extraction succeeded, but formatting failed: {str(e)}")

        return result
    finally:
        from shared.locks import release_course_lock

        for cid in course_ids:
            release_course_lock(cid, job_params["course_id_to_push"])
