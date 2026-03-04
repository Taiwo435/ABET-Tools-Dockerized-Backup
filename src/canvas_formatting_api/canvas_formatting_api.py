"""
FastAPI for Canvas formatting: HTML generation and module creation.
Converts create_html.py and create_modules.py into HTTP endpoints.
"""

import logging
from typing import Annotated, Optional

from fastapi import FastAPI, Header, HTTPException, Query

from create_modules import generate_course_html, run_formatting_pipeline

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s - %(levelname)s - %(message)s",
    handlers=[logging.StreamHandler()],
)
logger = logging.getLogger(__name__)

app = FastAPI(
    title="Canvas Formatting API",
    description="Generate course/ABET HTML pages and upload to Canvas modules.",
    version="0.1.0",
)


@app.get("/")
def root():
    """Health check / API info."""
    return {
        "service": "Canvas Formatting API",
        "docs": "/docs",
        "endpoints": [
            "POST /generate-html/{course_id}",
            "POST /format-and-upload/{course_id}",
        ],
    }


@app.post("/generate-html/{course_id}")
def generate_html(
    course_id: str,
    canvas_access_token: Annotated[str, Header(alias="canvas_access_token")],
    semester: str = Query("fall", description="Semester (e.g., fall, spring)"),
    year: str = Query("2023", description="Year (e.g., 2023)"),
    canvas_domain: str = Query("canvas.asu.edu", description="Canvas instance domain"),
    course_code: str = Query("", description="Course code filter (e.g., CSE 423)"),
    instructor_name: str = Query("", description="Instructor name filter"),
):
    """
    Generate course page HTML and ABET HTML without uploading to Canvas.
    Returns the raw HTML strings for both pages.
    """
    try:
        result = generate_course_html(
            canvas_access_token=canvas_access_token,
            source_course_id=course_id,
            semester=semester,
            year=year,
            canvas_domain=canvas_domain,
            course_code=course_code,
            instructor_name=instructor_name,
        )
        return result
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))
    except RuntimeError as e:
        raise HTTPException(status_code=404, detail=str(e))


@app.post("/format-and-upload/{course_id}")
def format_and_upload(
    course_id: str,
    canvas_access_token: str = Header(..., description="Canvas API access token"),
    destination_course_id: Optional[str] = Query(
        None,
        description="Course to upload to (defaults to source course)",
    ),
    semester: str = Query("fall", description="Semester (e.g., fall, spring)"),
    year: str = Query("2023", description="Year (e.g., 2023)"),
    canvas_domain: str = Query("canvas.asu.edu", description="Canvas instance domain"),
    course_code: str = Query("", description="Course code filter (e.g., CSE 423)"),
    instructor_name: str = Query("", description="Instructor name filter"),
    course_folder_name: str = Query(
        "",
        description="Exact folder name from extraction (overrides semester/year search)",
    ),
    term_display: str = Query(
        "", description="Term display string from extraction (e.g., 'Fall 2023')"
    ),
):
    """
    Run the full pipeline: fetch course data, build HTML, create module,
    upload course page and ABET page to Canvas, and publish the module.
    """
    try:
        result = run_formatting_pipeline(
            canvas_access_token=canvas_access_token,
            source_course_id=course_id,
            destination_course_id=destination_course_id,
            semester=semester,
            year=year,
            canvas_domain=canvas_domain,
            course_code=course_code,
            instructor_name=instructor_name,
            course_folder_name=course_folder_name,
            term_display=term_display,
        )
        return {
            "message": "Formatting and upload complete.",
            "course_page": result["course_page"],
            "abet_page": result["abet_page"],
            "module": result["module"],
            "course_name": result["course_name"],
        }
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))
    except RuntimeError as e:
        raise HTTPException(status_code=404, detail=str(e))


if __name__ == "__main__":
    import uvicorn

    uvicorn.run(app, host="0.0.0.0", port=8001)
