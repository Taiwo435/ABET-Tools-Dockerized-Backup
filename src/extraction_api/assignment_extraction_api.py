from collections import defaultdict
from concurrent.futures import ThreadPoolExecutor, as_completed
import csv
import enum
import io
import logging
import tempfile
import time
import requests
import json
import os
import re
import shutil
from datetime import datetime, timezone
import threading
import uuid
from fetch_grades import CanvasGradesFetcher
from fastapi import (
    FastAPI,
    HTTPException,
    Header,
    UploadFile,
    File,
    Query,
    BackgroundTasks,
)
from fastapi.responses import JSONResponse
from typing import Annotated, NamedTuple, Optional, List, Dict, Any
from typing_extensions import TypedDict
import PyPDF2
import docx
from csv_filter import RosterMap, parse_roster_for_major_map
from xhtml2pdf import pisa
from upload_abet_reports import upload_abet_report
from quiz_statistics import render_quiz_statistics_pdf

# Logging setup
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s - %(levelname)s - %(message)s",
    handlers=[logging.StreamHandler()],
)
logger = logging.getLogger(__name__)

from fastapi.middleware.cors import CORSMiddleware

# CORS
app = FastAPI()
app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost:8080"],
    allow_methods=["*"],
    allow_headers=["*"],
)


# Enums
class TaskType(str, enum.Enum):
    """Valid task types for the process-course-with-roster endpoint."""

    EXTRACT = "extract"
    ABET = "abet"
    ALL = "all"


# CONFIGURATION
CANVAS_DOMAIN = "canvas.asu.edu"
ABET_TAG = "abet"

# Max assignments to process concurrently in Phase 1.
# Note: this limit controls work done per request and may run inside FastAPI's
# own thread pool for sync endpoints, so increasing it raises total thread usage.
MAX_PARALLEL_ASSIGNMENTS = 2

# SETUP
TEMP_DIR_PREFIX = "abet_extraction_"


def _extract_teacher_names(course_info: dict) -> list[str]:
    """Extract display names from the teachers list in course_info. Final course folder name is Course Name (Season Year) [Teacher1, Teacher2, ...]"""
    teachers = course_info.get("teachers") or []
    return [t["display_name"] for t in teachers if t.get("display_name")]


def _build_course_folder_name(
    course_info: dict, course_code: str, teacher_names: list[str]
) -> str:
    """Build the Canvas folder name: course name + (term) [teacher1, teacher2, ...]."""
    base = re.sub(r'[<>:"/\\|?*]', "", course_info.get("name") or course_code)
    if teacher_names:
        teachers_str = ", ".join(teacher_names)
        base = f"{base} [{teachers_str}]"
    return base


class CourseData(NamedTuple):
    """Pre-fetched data for a single course, can be used by multiple endpoints now."""

    course_info: dict
    course_code: str
    semester_code: str
    term_display: str
    course_folder_name: str
    teachers_display: str
    all_assignments: list[dict]
    assignment_groups: dict[int, str]
    submissions_by_assignment: dict[int, list[dict]]


def _prepare_course_data(
    course_id: str,
    grades_fetcher: CanvasGradesFetcher,
) -> CourseData:
    """Fetch and organize all shared course data needed by extraction endpoints.

    Raises ``ValueError`` if the course is not found or has no assignments.
    """
    # Dictionary of assignment groups where key is ID, value is category
    # Ex. {123456: "Assignments", 1234567: "Quizzes"}
    assignment_groups = {
        g.get("id", 0): g.get("name", "Uncategorized")
        for g in grades_fetcher.fetch_assignment_groups(course_id=course_id)
    }

    # Fetch course info - Syllabus and Term
    course_info = grades_fetcher.api_request(
        f"courses/{course_id}",
        params={"include[]": ["syllabus_body", "term", "teachers"]},
    )
    if not course_info:
        raise ValueError("Course not found or invalid token.")

    course_code = course_info.get("course_code", "course")
    term_name = course_info.get("term", {}).get("name", "")
    teacher_names = _extract_teacher_names(course_info)

    all_assignments = get_all_assignments(course_id, grades_fetcher)
    if not all_assignments:
        raise ValueError("No assignments found in the course.")

    # Data Gathering Phase (Always Runs)
    logger.info("Starting Data Gathering Phase")

    # Prefetch all submissions once and index by assignment.
    # Deduplicate by (assignment_id, user_id)
    all_submissions = grades_fetcher.fetch_all_course_submissions(int(course_id))
    submissions_by_assignment: dict[int, list[dict]] = defaultdict(list)
    _seen_sub_keys: set[tuple[int, int]] = set()
    for sub in all_submissions:
        key = (sub["assignment_id"], sub.get("user_id", 0))
        if key not in _seen_sub_keys:
            _seen_sub_keys.add(key)
            submissions_by_assignment[sub["assignment_id"]].append(sub)

    term_display = get_term_display_name(term_name)

    return CourseData(
        course_info=course_info,
        course_code=course_code,
        semester_code=get_semester_short_code(term_name),
        term_display=term_display,
        course_folder_name=_build_course_folder_name(
            course_info, course_code, teacher_names, term_display
        ),
        teachers_display=", ".join(teacher_names),
        all_assignments=all_assignments,
        assignment_groups=assignment_groups,
        submissions_by_assignment=submissions_by_assignment,
    )


def _process_single_assignment(
    assignment: dict,
    grades_fetcher: CanvasGradesFetcher,
    temp_dir: str,
    submissions_by_assignment: dict,
    assignment_groups: dict,
    course_folder_name: str,
    term_display: str,
) -> tuple[int, dict, tuple | None]:
    """Extract artifacts and generate a grade report for one assignment.

    Returns:
        (assignment_id, extracted_texts, upload_task | None)
        where upload_task is (canvas_folder, file_paths) or None if no files.
    """
    prefetched = submissions_by_assignment.get(assignment["id"])

    local_files, extracted_texts = extract_and_save_artifacts(
        assignment=assignment,
        client=grades_fetcher,
        temp_dir=temp_dir,
        prefetched_submissions=prefetched,
    )

    sanitized_name: str = sanitize_filename(assignment["name"])
    folder_path: str = os.path.join(temp_dir, f"{assignment['id']}_{sanitized_name}")

    report_path: str | None = generate_assignment_grade_report(
        grades_fetcher,
        assignment,
        folder_path,
        prefetched_submissions=prefetched,
    )
    if report_path:
        local_files.append(report_path)

    upload_task = None
    if local_files:
        assignment_type = assignment_groups.get(
            assignment.get("assignment_group_id", 0), "Uncategorized"
        )
        canvas_folder: str = (
            f"{course_folder_name}/({term_display})"
            f"/Test_Assignments/{assignment_type}/{sanitized_name}"
        )
        upload_task: tuple[str, list[str]] = (canvas_folder, local_files)

    return assignment["id"], extracted_texts, upload_task


# Helpers
def create_temp_dir() -> str:
    """Creates a unique temporary directory for a single request."""
    return tempfile.mkdtemp(prefix=TEMP_DIR_PREFIX)


def cleanup_temp_dir(temp_dir: str):
    """Safely removes a temporary directory if it exists."""
    if temp_dir and os.path.exists(temp_dir):
        shutil.rmtree(temp_dir)
        logger.info("Temp directory cleaned up: %s", temp_dir)


def xls_bytes_to_csv_stream(file_bytes: bytes) -> io.StringIO:
    """Convert a PeopleSoft .xls file (HTML table) to an in-memory CSV stream.

    ASU roster exports from PeopleSoft are HTML tables saved with a .xls
    extension.  The HTML is often malformed (missing </tr> tags, <br> inside
    cells), so we handle that explicitly.
    """
    from html.parser import HTMLParser

    class _TableParser(HTMLParser):
        def __init__(self):
            super().__init__()
            self.rows: list[list[str]] = []
            self._current_row: list[str] | None = None
            self._capture = False
            self._cell_text = ""

        def _flush_row(self):
            if self._current_row is not None:
                self.rows.append(self._current_row)
                self._current_row = None

        def handle_starttag(self, tag, attrs):
            if tag == "tr":
                self._flush_row()  # handles missing </tr>
                self._current_row = []
            elif tag in ("td", "th"):
                self._capture = True
                self._cell_text = ""

        def handle_endtag(self, tag):
            if tag in ("td", "th") and self._capture:
                if self._current_row is not None:
                    self._current_row.append(self._cell_text.strip())
                self._capture = False
            elif tag == "tr":
                self._flush_row()

        def handle_data(self, data):
            if self._capture:
                self._cell_text += data

    parser = _TableParser()
    parser.feed(file_bytes.decode("utf-8", errors="replace"))
    parser._flush_row()  # flush last row if file ends without </tr>

    output = io.StringIO()
    writer = csv.writer(output)
    for row in parser.rows:
        writer.writerow(row)
    output.seek(0)
    return output


def parse_roster_upload(roster_file: UploadFile) -> RosterMap:
    """
    Parses an uploaded roster file (CSV or XLS) and returns a RosterMap.

    For .xls files the first sheet is converted to CSV in memory before parsing.
    """
    try:
        contents = roster_file.file.read()
        filename = roster_file.filename or ""
        ext = os.path.splitext(filename)[1].lower()

        if ext == ".xls":
            logger.info("Detected .xls file. Converting to CSV...")
            text_stream = xls_bytes_to_csv_stream(contents)
        else:
            # Default: treat as CSV
            text_stream = io.TextIOWrapper(io.BytesIO(contents), encoding="utf-8-sig")

        roster = parse_roster_for_major_map(text_stream)
        logger.info(
            "Parsed roster (%s): %d by ASURITE, %d by ID.",
            ext or ".csv",
            len(roster.by_asurite),
            len(roster.by_id),
        )
        return roster
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=400, detail=f"Error parsing roster file: {e}")


def extract_text_from_pdf(file_path: str) -> str:
    """Extracts text content from a PDF file."""
    try:
        with open(file_path, "rb") as f:
            reader = PyPDF2.PdfReader(f)
            return "".join(page.extract_text() for page in reader.pages)
    except Exception as e:
        logger.error("Error extracting text from PDF '%s': %s", file_path, e)
        return ""


def extract_text_from_docx(file_path: str) -> str:
    """Extracts text content from a DOCX file."""
    try:
        doc = docx.Document(file_path)
        return "\n".join(para.text for para in doc.paragraphs)
    except Exception as e:
        logger.error("Error extracting text from DOCX '%s': %s", file_path, e)
        return ""


def get_semester_short_code(term_name: str) -> str:
    """Converts 'Fall 2025' to 'f25'."""
    if not term_name:
        return "term"
    match = re.search(r"(\w+)\s+(\d{4})", term_name)
    if match:
        season = match.group(1)[0].lower()
        year = match.group(2)[-2:]
        return f"{season}{year}"
    return "term"


def get_term_display_name(term_name: str) -> str:
    """Converts Canvas term names like '2023 Fall C' → 'Fall 2023'."""
    match = re.match(r"(\d{4})\s+(Fall|Spring|Summer)", term_name, re.IGNORECASE)
    if match:
        return f"{match.group(2).capitalize()} {match.group(1)}"
    return term_name


def generate_filename(assignment_name, label, extension, score=None):
    # Generates format like: Homework_1_high_95.pdf
    base = f"{sanitize_filename(assignment_name)}_{label}"
    if score is not None:
        # Format score: remove trailing .0 for clean integers
        score_val = (
            int(score) if isinstance(score, float) and score == int(score) else score
        )
        base = f"{base}_{score_val}"
    return f"{base}{extension}"


def sanitize_filename(name: str) -> str:
    """Replaces characters that are invalid in Windows/Linux filenames"""
    return re.sub(r'[<>:"/\\|?*]', "", name).strip()


def extract_and_save_syllabus(
    course_id, course_info, client: CanvasGradesFetcher, temp_dir: str
):
    """Saves syllabus body as HTML, converts it to PDF, and downloads linked PDFs."""
    logger.info("Extracting Syllabus...")
    folder_path = os.path.join(temp_dir, "_Syllabus")
    os.makedirs(folder_path, exist_ok=True)

    body = course_info.get("syllabus_body", "")
    if not body:
        return folder_path

    # 1. Save Raw HTML Body
    html_path = os.path.join(folder_path, "syllabus_body.html")
    with open(html_path, "w", encoding="utf-8") as f:
        f.write(body)

    # 2. Convert HTML to PDF
    # We wrap the body in basic html tags to ensure the renderer handles it correctly
    pdf_path = os.path.join(folder_path, "syllabus_body.pdf")
    try:
        with open(pdf_path, "wb") as pdf_file:
            # Allow blank images to fail gracefully without stopping the script
            pisa.CreatePDF(f"<html><body>{body}</body></html>", dest=pdf_file)
        logger.info("Rendered syllabus HTML to PDF: syllabus_body.pdf")
    except Exception as e:
        logger.error("Failed to render syllabus PDF: %s", e)

    # 3. Download linked PDF if it exists in the body
    # Regex to find file links: /files/12345
    file_ids = re.findall(r"/files/(\d+)", body)
    for fid in file_ids:
        f_info = client.api_request(f"files/{fid}")
        if not f_info:
            logger.warning("Could not fetch file info for file ID %s", fid)
            continue

        if f_info and f_info.get("filename", "").lower().endswith(".pdf"):
            # Save as syllabus.pdf (or keep original name)
            local_path = os.path.join(folder_path, f"syllabus_{f_info['filename']}")
            client.download_file(f_info["url"], local_path)
            logger.info("Downloaded linked syllabus PDF: %s", f_info["filename"])

    return folder_path


def get_all_assignments(course_id: str, client: CanvasGradesFetcher) -> list[dict]:
    """Fetches all assignments for a given course."""
    logger.info("Fetching all assignments for course %s...", course_id)
    endpoint = f"courses/{course_id}/assignments"
    return client.get_paginated_list(endpoint, params={"include[]": "rubric"})


def find_abet_assignments(all_assignments: list[dict]) -> list[dict]:
    """
    Finds all ABET-related assignments by searching names and rubrics.

    Args:
        all_assignments (list): The list of all assignment objects to filter.

    Returns:
        list: A list of assignment objects that match the ABET criteria.
    """
    logger.info("Filtering for ABET assignments...")
    return [
        a
        for a in all_assignments
        if ABET_TAG in a.get("name", "").lower()
        or any(
            ABET_TAG in r.get("description", "").lower() for r in a.get("rubric", [])
        )
    ]


def extract_rubric_assessment_data(submission):
    """Extracts and anonymizes rubric assessment data from a submission."""
    rubric_data = submission.get("rubric_assessment", {})
    if not rubric_data:
        return None
    return {
        cid: {"points": data.get("points"), "comments": data.get("comments", "")}
        for cid, data in rubric_data.items()
    }


def find_abet_outcomes(all_assignments: list[dict]) -> tuple[defaultdict, dict]:
    """Scans assignments, groups them by ABET outcome, and extracts outcome details."""
    outcome_map = defaultdict(list)
    outcome_details = (
        {}
    )  # Store title, description, and long_description for each outcome
    for assign in all_assignments:
        if not (rubric := assign.get("rubric")):
            continue
        for criterion in rubric:
            # We check the main 'description' for the ABET tag
            if "abet" in criterion.get("description", "").lower() and (
                oid := criterion.get("outcome_id")
            ):
                outcome_map[oid].append(assign)
                if oid not in outcome_details:
                    # Use 'description' for the title and main outcome text
                    title_description = criterion.get("description", "").strip()
                    long_description = criterion.get("long_description", "").strip()
                    clean_title = re.sub(r"<[^>]+>", "", title_description).strip()

                    outcome_details[oid] = {
                        "title": clean_title,
                        "full_description": title_description,
                        "long_description": long_description,
                    }
    return outcome_map, outcome_details


def get_representative_submissions(
    course_id: str,
    assignment_id: int,
    client: CanvasGradesFetcher,
    prefetched_submissions: list[dict] | None = None,
) -> tuple[dict | None, dict | None, dict | None]:
    """
    Fetches submissions and identifies High, Average, and Low graded artifacts.

    Accepts optional `prefetched_submissions` to avoid per-assignment API calls
    when callers have already fetched submissions in bulk.
    """
    if prefetched_submissions is not None:
        submissions = prefetched_submissions
    else:
        endpoint = f"courses/{course_id}/assignments/{assignment_id}/submissions"
        submissions = client.get_paginated_list(endpoint, params={"include[]": "user"})

    if not submissions:
        return None, None, None

    # Filter for graded submissions only
    graded = sorted(
        [
            s
            for s in submissions
            if s.get("workflow_state") == "graded"
            and s.get("score") is not None
            and s.get("attachments")
        ],
        key=lambda s: s["score"],
    )

    if not graded:
        return None, None, None

    # 1. High and Low
    low_sub = graded[0]
    high_sub = graded[-1]

    # 2. Calculate Average
    scores = [s["score"] for s in graded]
    avg_score = sum(scores) / len(scores)

    # 3. Find submission closest to the statistical average
    avg_sub = min(graded, key=lambda s: abs(s["score"] - avg_score))

    return high_sub, avg_sub, low_sub


def _save_representative_submission(
    sub: dict,
    label: str,
    assignment: dict,
    local_path: str,
    client: CanvasGradesFetcher,
) -> list[str]:
    """
    Downloads and saves a single representative submission (high/avg/low)
    and its metadata. Returns a list of saved file paths.
    """
    saved = []
    if not (sub and sub.get("attachments")):
        return saved

    attachment = sub["attachments"][0]
    ext = os.path.splitext(attachment.get("filename", ""))[1]

    new_filename = generate_filename(
        assignment["name"], label, ext, score=sub.get("score")
    )
    file_save_path = os.path.join(local_path, new_filename)

    if client.download_file(attachment["url"], file_save_path):
        saved.append(file_save_path)

    metadata_path = os.path.join(local_path, f"{label}_details.json")
    with open(metadata_path, "w", encoding="utf-8") as f:
        json.dump(
            {
                "score": sub.get("score"),
                "points_possible": assignment.get("points_possible"),
                "original_filename": attachment.get("filename"),
                "user_id": sub.get("user", {}).get("id"),
                "rubric_assessment": extract_rubric_assessment_data(sub),
            },
            f,
            indent=2,
        )
    # saved.append(metadata_path)
    return saved


def extract_and_save_artifacts(
    assignment: dict,
    client: CanvasGradesFetcher,
    temp_dir: str,
    prefetched_submissions: list[dict] | None = None,
) -> tuple[list[str], dict[str, str]]:
    """
    Saves all relevant artifacts for an assignment to a local temporary directory.
    This includes the description, rubric, any documents attached in the description,
    and files from the highest, average, and lowest graded student submissions.

    Args:
        assignment (dict): The assignment object.
        temp_dir (str): The temporary directory for this request.

    Returns:
        tuple: A (list of file paths, dict of extracted texts).
    """
    sanitized_name = sanitize_filename(assignment["name"])
    assignment_name = f"{assignment['id']}_{sanitized_name}"
    local_path = os.path.join(temp_dir, assignment_name)
    os.makedirs(local_path, exist_ok=True)

    saved_files = []
    extracted_texts = {}

    if description := assignment.get("description"):
        path = os.path.join(local_path, "description.html")
        with open(path, "w", encoding="utf-8") as f:
            f.write(description)
        saved_files.append(path)

        for file_id in set(re.findall(r"/files/(\d+)", description)):
            f_info = client.api_request(f"files/{file_id}")
            if not f_info:
                logger.warning("Could not fetch file info for file ID %s", file_id)
                continue

            file_local_path = os.path.join(local_path, f_info["filename"])
            if client.download_file(f_info["url"], file_local_path):
                saved_files.append(file_local_path)
                # After downloading, check extension and extract text
                if file_local_path.lower().endswith(".pdf"):
                    extracted_texts[f_info["filename"]] = extract_text_from_pdf(
                        file_local_path
                    )
                elif file_local_path.lower().endswith(".docx"):
                    extracted_texts[f_info["filename"]] = extract_text_from_docx(
                        file_local_path
                    )

    if rubric := assignment.get("rubric"):
        rubric_path = os.path.join(local_path, "rubric.json")
        with open(rubric_path, "w", encoding="utf-8") as f:
            json.dump(rubric, f, indent=4)
        saved_files.append(rubric_path)

    high, avg, low = (
        get_representative_submissions(
            assignment["course_id"],
            assignment["id"],
            client,
            prefetched_submissions=prefetched_submissions,
        )
        if not assignment.get("quiz_id")
        else (None, None, None)
    )

    if assignment.get("quiz_id"):
        quiz_id = assignment.get("quiz_id")
        pdf_path = render_quiz_statistics_pdf(
            client=client,
            course_id=str(assignment["course_id"]),
            quiz_id=quiz_id,
            quiz_title=assignment["name"],
            output_path=local_path,
        )

        if pdf_path:
            saved_files.append(pdf_path)

    for sub, label in [(high, "high"), (avg, "avg"), (low, "low")]:
        saved_files.extend(
            _save_representative_submission(sub, label, assignment, local_path, client)
        )

    return saved_files, extracted_texts


def generate_assignment_grade_report(
    grades_fetcher: CanvasGradesFetcher,
    assignment: dict,
    local_path: str,
    prefetched_submissions: list[dict] | None = None,
) -> str | None:
    """
    Creates a detailed CSV grade report for a single assignment.

    Args:
        grades_fetcher (CanvasGradesFetcher): The fetcher instance to get data.
        assignment (dict): The assignment object.
        local_path (str): The local directory to save the report in.
        prefetched_submissions (list[dict] | None): Optionally supply submissions
            already fetched in bulk to avoid another API call.

    Returns:
        str or None: The file path to the generated CSV, or None if no submissions exist.
    """
    logger.info("Generating detailed grade report...")
    if prefetched_submissions is not None:
        submissions = prefetched_submissions
    else:
        submissions = grades_fetcher.fetch_assignment_submissions(
            assignment["course_id"], assignment["id"]
        )

    if not submissions:
        logger.info("No submissions found.")
        return None

    report_path = os.path.join(local_path, f"grade_report_{assignment['id']}.csv")
    header = ["user_id", "user_name", "score", "submitted_at", "workflow_state"]

    with open(report_path, "w", newline="", encoding="utf-8") as f:
        writer = csv.writer(f)
        writer.writerow(header)
        for sub in submissions:
            user = sub.get("user", {})
            writer.writerow(
                [
                    user.get("id", "N/A"),
                    user.get("name", "N/A"),
                    sub.get("score", ""),
                    sub.get("submitted_at", "N/A"),
                    sub.get("workflow_state", "N/A"),
                ]
            )
    logger.info("Grade report saved to %s", report_path)
    return report_path


def build_outcome_report_data(
    grades_fetcher,
    outcome_map,
    outcome_details,
    course_info,
    course_id: str,
    student_major_map: RosterMap,
    assignment_texts_map: dict,
    prefetched_submissions: dict | None = None,
) -> list[dict]:
    """
    Pure data builder: gathers submissions, computes competency stats, and
    returns a list of structured report dicts (one per ABET outcome).

    No file I/O or Canvas uploads are performed here.
    """
    logger.info(
        "Building ABET Outcome Report Data with Major Breakdown and File Content"
    )
    outcome_reports = []

    # Build submission cache: use prefetched data if available, otherwise fetch.
    all_assignment_ids = set()
    for assignments in outcome_map.values():
        for assign in assignments:
            all_assignment_ids.add(assign["id"])

    submission_cache = defaultdict(list)

    if prefetched_submissions is not None:
        # Reuse submissions already fetched during the data gathering phase.
        for aid in all_assignment_ids:
            submission_cache[aid] = prefetched_submissions.get(aid, [])
    else:
        # No prefetched data — fetch from Canvas (used by standalone endpoints).
        all_assignment_ids_list = list(all_assignment_ids)
        all_submissions_flat: list[dict] = []
        BATCH_SIZE = 50
        for i in range(0, len(all_assignment_ids_list), BATCH_SIZE):
            batch = all_assignment_ids_list[i : i + BATCH_SIZE]
            try:
                all_submissions_flat.extend(
                    grades_fetcher.fetch_all_course_submissions(
                        int(course_id), assignment_ids=batch
                    )
                )
            except Exception as e:
                logger.warning(
                    "Bulk submissions fetch failed for batch %s: %s", batch, e
                )

        if all_submissions_flat:
            # Detect whether bulk responses include full_rubric_assessment
            has_rubric_data: bool = any(
                sub.get("full_rubric_assessment") is not None
                for sub in all_submissions_flat
            )

            if has_rubric_data:
                _seen: set[tuple[int, int]] = set()
                for sub in all_submissions_flat:
                    # submissions returned by the bulk endpoint include assignment_id
                    key = (sub["assignment_id"], sub.get("user_id", 0))
                    if key not in _seen:
                        _seen.add(key)
                        submission_cache[sub["assignment_id"]].append(sub)
            else:
                # Roll back to per-assignment fetching if rubric details are missing
                logger.warning(
                    "Bulk submissions missing `full_rubric_assessment`. Falling back to per-assignment fetch."
                )
                for aid in all_assignment_ids_list:
                    submission_cache[aid] = grades_fetcher.fetch_assignment_submissions(
                        course_id, aid
                    )
        else:
            # No bulk data returned. Use per-assignment fetch.
            for aid in all_assignment_ids_list:
                submission_cache[aid] = grades_fetcher.fetch_assignment_submissions(
                    course_id, aid
                )

    for outcome_id, assignments in outcome_map.items():
        outcome_info = outcome_details.get(outcome_id, {})
        outcome_title = outcome_info.get("title", f"Outcome_ID_{outcome_id}")

        logger.debug(
            "Processing Outcome: '%s' (Outcome ID: %s)", outcome_title, outcome_id
        )

        # Per-student aggregation for this outcome: key=user_id -> {
        #   'score_sum': float, 'possible_sum': float, 'major': str|None
        # }
        student_outcomes: dict[int, dict] = {}
        contributing_assignments_data = []

        for assign in assignments:
            logger.debug(
                "Gathering data from assignment: '%s' (ID: %s)",
                assign["name"],
                assign["id"],
            )

            abet_criterion = next(
                (
                    criteria
                    for criteria in assign.get("rubric", [])
                    if criteria.get("outcome_id") == outcome_id
                ),
                None,
            )
            if not abet_criterion:
                logger.debug(
                    "SKIPPED: Assignment has no rubric criterion for this specific outcome."
                )
                continue

            abet_points_possible = abet_criterion.get("points") or 1
            assign_id = assign["id"]
            submissions = submission_cache.get(assign_id, [])
            logger.debug(
                "Fetched %d submissions. Parsing for rubric assessments...",
                len(submissions),
            )

            for sub in submissions:
                if assessment := sub.get("full_rubric_assessment"):
                    for graded_criterion in assessment.get("data", []):
                        if graded_criterion.get("learning_outcome_id") == outcome_id:
                            score = graded_criterion.get("points", 0)
                            user_id = sub.get("user_id")

                            # If submission does not expose a top-level user_id, skip it
                            if user_id is None:
                                break

                            logger.debug(
                                "Found relevant score for Submission ID %s. Score: %s/%s",
                                sub["id"],
                                score,
                                abet_points_possible,
                            )

                            # Initialize per-student accumulator on first seeing student
                            if user_id not in student_outcomes:
                                major = None
                                if user_data := sub.get("user"):
                                    if student_major_map.by_asurite:
                                        login_id = user_data.get("login_id", "")
                                        major = student_major_map.by_asurite.get(
                                            login_id
                                        )
                                    if not major and student_major_map.by_id:
                                        sis_id = str(user_data.get("sis_user_id", ""))
                                        major = student_major_map.by_id.get(sis_id)

                                student_outcomes[user_id] = {
                                    "score_sum": 0.0,
                                    "possible_sum": 0.0,
                                    "major": major,
                                }

                            # Accumulate weighted totals for this student
                            student_outcomes[user_id]["score_sum"] += score
                            student_outcomes[user_id][
                                "possible_sum"
                            ] += abet_points_possible

                            break  # Move to the next submission

            assignment_info = assign.copy()
            assignment_info["description_files_content"] = assignment_texts_map.get(
                assign["id"], {}
            )
            contributing_assignments_data.append(assignment_info)

        if not student_outcomes:
            logger.warning(
                "Skipping report for '%s'. No relevant rubric-graded submissions found.",
                outcome_title,
            )
            continue

        logger.debug(
            "Data gathering complete. Unique students assessed: %d",
            len(student_outcomes),
        )

        # Group students by major using their weighted averages
        major_buckets = defaultdict(list)
        for uid, data in student_outcomes.items():
            if data.get("major"):
                weighted_avg = (
                    data["score_sum"] / data["possible_sum"]
                    if data["possible_sum"]
                    else 0
                )
                major_buckets[data["major"]].append(weighted_avg)

        major_specific_results = {}
        for major, averages in major_buckets.items():
            num_competent = sum(1 for avg in averages if avg >= 0.7)
            total_students = len(averages)
            percent_competent = (
                (num_competent / total_students) * 100 if total_students else 0
            )
            major_specific_results[major] = {
                "sample_size": total_students,
                "number_competent": num_competent,
                "percent_competent": round(percent_competent, 2),
                "outcome_met": percent_competent >= 70.0,
            }

        # Compute overall competency results from per-student weighted averages
        all_weighted_averages = [
            (data["score_sum"] / data["possible_sum"]) if data["possible_sum"] else 0
            for data in student_outcomes.values()
        ]
        overall_num_competent = sum(1 for avg in all_weighted_averages if avg >= 0.7)
        overall_total_students = len(all_weighted_averages)
        overall_percent_competent = (
            (overall_num_competent / overall_total_students) * 100
            if overall_total_students
            else 0
        )

        # Create a clean list of contributing assignments for the report
        clean_assignments = [
            {
                "id": assign.get("id"),
                "name": assign.get("name"),
                "description": assign.get("description"),
                "description_files_content": assign.get(
                    "description_files_content", {}
                ),
            }
            for assign in contributing_assignments_data
        ]

        # Assemble the final, structured report object
        report_data = {
            # Corresponds to requirement 1.a and 1.d (Identification and Description)
            "outcome_identification": {
                "title": outcome_title,
                "description": outcome_info.get("full_description", ""),
                "long_description": outcome_info.get("long_description", ""),
            },
            # Course identification
            "course_identification": course_info,
            # Corresponds to requirement 1.e (Results)
            "results": {
                "overall_summary": {
                    "sample_size": overall_total_students,
                    "number_competent": overall_num_competent,
                    "percent_competent": round(overall_percent_competent, 2),
                    "outcome_met": overall_percent_competent >= 70.0,
                },
                "distribution_by_major": major_specific_results,
            },
            # Corresponds to "Actual instrument used"
            "contributing_assignments": clean_assignments,
        }

        # Derive the outcome label for identification
        match = re.search(r"(CS|CSE)\s*ABET\s*\d+", outcome_title, re.IGNORECASE)
        outcome_label = (
            match.group(0).replace(" ", "_")
            if match
            else sanitize_filename(outcome_title)
        )

        outcome_reports.append(
            {
                "outcome_id": str(outcome_id),
                "outcome_title": outcome_title,
                "outcome_label": outcome_label,
                "data": report_data,
            }
        )

    return outcome_reports


def generate_outcome_reports(
    grades_fetcher: CanvasGradesFetcher,
    outcome_map,
    outcome_details,
    course_info,
    course_folder_name: str,
    course_id: str,
    student_major_map: RosterMap,
    assignment_texts_map: dict,
    temp_dir: str,
):
    """
    Generates ABET outcome JSON reports, writes them to disk, and uploads to Canvas.
    Delegates data building to build_outcome_report_data.
    """
    outcome_reports = build_outcome_report_data(
        grades_fetcher=grades_fetcher,
        outcome_map=outcome_map,
        outcome_details=outcome_details,
        course_info=course_info,
        course_id=course_id,
        student_major_map=student_major_map,
        assignment_texts_map=assignment_texts_map,
    )

    if not outcome_reports:
        logger.info("No outcome reports were generated.")
        return

    local_reports_to_upload = []
    for report in outcome_reports:
        report_filename = f"OUTCOME_{report['outcome_label']}.json"
        report_path = os.path.join(temp_dir, report_filename)
        with open(report_path, "w", encoding="utf-8") as f:
            json.dump(report["data"], f, indent=4)
        local_reports_to_upload.append(report_path)

    if local_reports_to_upload:
        canvas_folder = f"{course_folder_name}/_ABET_Outcome_Reports"
        grades_fetcher.upload_files(course_id, canvas_folder, local_reports_to_upload)


# Fast api endpoint
@app.get("/verify-course/{course_id}")
def verify_course(
    course_id: str,
    canvas_access_token: Annotated[str, Header()],
    dest_course_id: Optional[str] = None,
):
    """Validates a Canvas token against a course. Returns basic course info and duplicate status."""
    if not canvas_access_token or not str(canvas_access_token).strip():
        raise HTTPException(status_code=401, detail="Canvas access token is required.")

    client = CanvasGradesFetcher(access_token=canvas_access_token)
    course_info = client.api_request(
        f"courses/{course_id}", params={"include[]": ["term"]}
    )
    if not course_info:
        raise HTTPException(
            status_code=404, detail="Course not found or invalid token."
        )

    duplicate = False
    if dest_course_id:
        try:
            course_name = course_info.get("name", "").replace(":", "")
            modules = client.get_paginated_list(f"courses/{dest_course_id}/modules")
            for module in modules:
                module_id = module.get("id")
                items = client.get_paginated_list(
                    f"courses/{dest_course_id}/modules/{module_id}/items"
                )
                for item in items:
                    item_title = item.get("title", "")
                    if item_title and (item_title in course_name or course_name in item_title):
                        duplicate = True
                        break
                if duplicate:
                    break
        except Exception:
            logging.exception(
                "Error while checking for duplicate modules for course_id=%s, dest_course_id=%s",
                course_id,
                dest_course_id,
            )

    return {
        "course_id": course_id,
        "name": course_info.get("name"),
        "course_code": course_info.get("course_code"),
        "term": course_info.get("term", {}).get("name"),
        "duplicate_status": duplicate,
    }


# ------------------------------------
# Deprecating process-course-with-roster endpoint
# ------------------------------------


# @app.post("/process-course-with-roster/{course_id}")
# def process_course_with_roster(
#     course_id: str,
#     canvas_access_token: Annotated[str, Header()],
#     roster_file: Optional[UploadFile] = File(None),
#     tasks: TaskType = Query(
#         TaskType.ALL, description="Tasks to run: 'extract', 'abet', or 'all'"
#     ),
# ):
#     # Early token validation
#     if not canvas_access_token or not str(canvas_access_token).strip():
#         raise HTTPException(status_code=401, detail="Canvas access token is required.")

#     student_major_map = RosterMap()

#     # Only run roster parsing if the task actually requires it (ABET or ALL)
#     if tasks in (TaskType.ABET, TaskType.ALL):
#         if not roster_file:
#             raise HTTPException(
#                 status_code=400,
#                 detail="The 'roster_file' is required when tasks include 'abet' or 'all'.",
#             )
#         student_major_map = parse_roster_upload(roster_file)

#     temp_dir = create_temp_dir()
#     try:
#         grades_fetcher = CanvasGradesFetcher(access_token=canvas_access_token)
#         course_info = grades_fetcher.api_request(
#             f"courses/{course_id}", params={"include[]": ["syllabus_body", "term"]}
#         )

#         if not course_info:
#             raise HTTPException(
#                 status_code=404, detail="Course not found or invalid token."
#             )

#         course_code = course_info.get(
#             "course_code", "course"
#         )  # e.g., "2023Fall-T-CSE423-70483" for a real course
#         semester_code = get_semester_short_code(
#             course_info.get("term", {}).get("name", "")
#         )  # e.g., f25

#         course_folder_name = re.sub(
#             r'[<>:"/\\|?*]', "", course_info.get("name") or course_code
#         )

#         all_assignments = get_all_assignments(course_id, grades_fetcher)
#         if not all_assignments:
#             raise HTTPException(
#                 status_code=404, detail="No assignments found in the course."
#             )

#         if tasks in (TaskType.EXTRACT, TaskType.ALL):
#             syllabus_path = extract_and_save_syllabus(
#                 course_id, course_info, grades_fetcher, temp_dir
#             )
#             if syllabus_path:
#                 syllabus_files = [
#                     os.path.join(syllabus_path, f) for f in os.listdir(syllabus_path)
#                 ]
#                 grades_fetcher.upload_files(
#                     course_id,
#                     f"{course_folder_name}/Syllabus",
#                     syllabus_files,
#                 )

#         # Data Gathering Phase (Always Runs)
#         assignment_texts_map = {}
#         logger.info("Starting Data Gathering Phase")

#         # Prefetch all submissions once and index by assignment_id to avoid
#         # redundant per-assignment API calls.
#         all_submissions = grades_fetcher.fetch_all_course_submissions(int(course_id))
#         submissions_by_assignment = defaultdict(list)
#         for sub in all_submissions:
#             submissions_by_assignment[sub["assignment_id"]].append(sub)

#         for assignment in all_assignments:
#             logger.info("Gathering artifacts for: %s", assignment["name"])
#             local_files, extracted_texts = extract_and_save_artifacts(
#                 assignment,
#                 grades_fetcher,
#                 temp_dir,
#                 prefetched_submissions=submissions_by_assignment.get(assignment["id"]),
#             )
#             assignment_texts_map[assignment["id"]] = extracted_texts

#             sanitized_name = sanitize_filename(assignment["name"])
#             assignment_folder_path = os.path.join(
#                 temp_dir, f"{assignment['id']}_{sanitized_name}"
#             )
#             report_path = generate_assignment_grade_report(
#                 grades_fetcher,
#                 assignment,
#                 assignment_folder_path,
#                 prefetched_submissions=submissions_by_assignment.get(assignment["id"]),
#             )
#             if report_path:
#                 local_files.append(report_path)

#             if tasks in (TaskType.EXTRACT, TaskType.ALL):
#                 if local_files:
#                     logger.info("Uploading artifacts for '%s'...", assignment["name"])
#                     canvas_folder = f"{course_folder_name}/Assignments/{sanitized_name}"
#                     grades_fetcher.upload_files(course_id, canvas_folder, local_files)
#                 else:
#                     logger.info("No artifacts found to upload for this assignment.")

#         logger.info("Data Gathering Complete")

#         # ABET Report Generation Phase (Conditional)
#         if tasks in (TaskType.ABET, TaskType.ALL):
#             logger.info("Starting ABET Report Generation Phase")
#             if abet_assignments := find_abet_assignments(all_assignments):
#                 outcome_map, outcome_details = find_abet_outcomes(abet_assignments)
#                 if outcome_map:
#                     generate_outcome_reports(
#                         grades_fetcher,
#                         outcome_map,
#                         outcome_details,
#                         course_info,
#                         course_folder_name,
#                         course_id,
#                         student_major_map,
#                         assignment_texts_map,
#                         temp_dir,
#                     )
#                 else:
#                     logger.info(
#                         "No assignments with rubric outcomes found for summary report generation."
#                     )
#             else:
#                 logger.info("No ABET-tagged assignments found.")

#         return {"message": f"Processing complete for tasks: '{tasks.value}'."}
#     finally:
#         cleanup_temp_dir(temp_dir)


@app.post("/generate-report-json/{course_id}")
def generate_report_json(
    course_id: str,
    canvas_access_token: Annotated[str, Header()],
    roster_file: UploadFile = File(...),
) -> JSONResponse:
    """Returns ABET outcome report data as a JSON response without uploading to Canvas."""
    # Early token validation
    if not canvas_access_token or not str(canvas_access_token).strip():
        raise HTTPException(status_code=401, detail="Canvas access token is required.")
    if not roster_file:
        raise HTTPException(
            status_code=400, detail="The 'roster_file' is required for this endpoint."
        )

    student_major_map: RosterMap = parse_roster_upload(roster_file)

    temp_dir = create_temp_dir()
    try:
        grades_fetcher = CanvasGradesFetcher(access_token=canvas_access_token)

        try:
            cd: CourseData = _prepare_course_data(course_id, grades_fetcher)
        except ValueError as e:
            raise HTTPException(status_code=404, detail=str(e))

        # Extract text content from assignment artifacts (PDFs, DOCX, etc.)
        assignment_texts_map = {}
        for assignment in cd.all_assignments:
            _, extracted_texts = extract_and_save_artifacts(
                assignment=assignment,
                client=grades_fetcher,
                temp_dir=temp_dir,
                prefetched_submissions=cd.submissions_by_assignment.get(
                    assignment["id"]
                ),
            )
            assignment_texts_map[assignment["id"]] = extracted_texts

        # Filter for ABET assignments and build outcome data
        abet_assignments = find_abet_assignments(cd.all_assignments)
        if not abet_assignments:
            raise HTTPException(
                status_code=404, detail="No ABET-tagged assignments found."
            )

        outcome_map, outcome_details = find_abet_outcomes(abet_assignments)
        if not outcome_map:
            raise HTTPException(
                status_code=404,
                detail="No assignments with rubric outcomes found for report generation.",
            )

        outcome_reports = build_outcome_report_data(
            grades_fetcher=grades_fetcher,
            outcome_map=outcome_map,
            outcome_details=outcome_details,
            course_info=cd.course_info,
            course_id=course_id,
            student_major_map=student_major_map,
            assignment_texts_map=assignment_texts_map,
            prefetched_submissions=cd.submissions_by_assignment,
        )

        # Wrap in the metadata
        response_payload = {
            "metadata": {
                "course_id": str(course_id),
                "course_code": cd.course_code,
                "semester": cd.semester_code,
                "timestamp": datetime.now(timezone.utc).isoformat(),
                "course_folder_name": cd.course_folder_name,
                "teachers_display": cd.teachers_display,
            },
            # # Corresponds to requirement 1.c (Class number)
            # "course_identification": course_info,
            "outcomes": [
                {
                    "outcome_id": report["outcome_id"],
                    "outcome_title": report["outcome_title"],
                    "data": report["data"],
                }
                for report in outcome_reports
            ],
        }
        return JSONResponse(content=response_payload)

    finally:
        cleanup_temp_dir(temp_dir)


class _JobStatusRequired(TypedDict):
    status: str


class JobStatusData(_JobStatusRequired, total=False):
    """Typed dict for background job progress tracking.

    ``status`` is always present (``processing``, ``completed``, or ``failed``).
    Other keys are populated as the pipeline progresses.
    """

    message: str
    error: str
    course_folder_name: str
    term_display: str
    course_code: str
    progress: int
    completed_at: float  # time.time() — used for TTL eviction


# In-memory job store.  Safe for a single-worker uvicorn process.
JOB_STATUS: dict[str, JobStatusData] = {}
_job_status_lock = threading.Lock()

# Completed/failed jobs are evicted after this many seconds.
_JOB_TTL_SECONDS = 3600  # 1 hour


def _evict_stale_jobs() -> None:
    """Remove finished jobs older than ``_JOB_TTL_SECONDS``."""
    now = time.time()
    with _job_status_lock:
        stale = [
            jid
            for jid, data in JOB_STATUS.items()
            if "completed_at" in data
            and now - data.get("completed_at", 0) > _JOB_TTL_SECONDS
        ]
        for jid in stale:
            JOB_STATUS.pop(jid, None)
    if stale:
        logger.info("Evicted %d stale job(s) from JOB_STATUS.", len(stale))


def _set_job_status(job_id: str, data: JobStatusData) -> None:
    """Thread-safe update of a job's status entry."""
    with _job_status_lock:
        JOB_STATUS[job_id] = data


@app.get("/job-status/{job_id}")
def get_job_status(job_id: str) -> JobStatusData:
    # Do eviction on reads so we don't need a background timer.
    _evict_stale_jobs()
    with _job_status_lock:
        if job_id not in JOB_STATUS:
            raise HTTPException(status_code=404, detail="Job not found")
        return JOB_STATUS[job_id]


def _run_extraction_pipeline_sync(
    job_id: str,
    course_id_to_push: str,
    canvas_access_token: str,
    course_ids_to_pull: list[str],
    student_major_map: RosterMap,
):
    """
    Abstacted away logic for running the full extraction and report generation pipeline out of the endpoint.
    """
    temp_dir = create_temp_dir()
    try:
        grades_fetcher = CanvasGradesFetcher(access_token=canvas_access_token)

        # Only the last course's metadata is stored in the job status.
        # Multi-course support requires pairing each source course with its own
        # roster (students differ across courses), so this design is intentionally
        # limited to single-course runs for now.
        last_course_folder_name = ""
        last_term_display = ""
        last_course_code = ""
        total_courses = len(course_ids_to_pull)

        for course_idx, course_id_to_pull in enumerate(course_ids_to_pull):
            course_slice = 90 / total_courses
            course_base = int(course_idx * course_slice)

            _set_job_status(
                job_id=job_id,
                data={
                    "status": "processing",
                    "progress": course_base + int(course_slice * 0.05),
                    "message": f"Course {course_idx + 1}/{total_courses}: Fetching course data...",
                },
            )

            cd: CourseData = _prepare_course_data(course_id_to_pull, grades_fetcher)
            last_course_folder_name = cd.course_folder_name
            last_term_display = cd.term_display
            last_course_code = cd.course_code

            # Extract and save syllabus
            syllabus_path = extract_and_save_syllabus(
                course_id_to_pull, cd.course_info, grades_fetcher, temp_dir
            )
            syllabus_files = (
                [os.path.join(syllabus_path, f) for f in os.listdir(syllabus_path)]
                if syllabus_path
                else []
            )

            # Upload syllabus files to Canvas
            if syllabus_files:
                grades_fetcher.upload_files(
                    course_id=course_id_to_push,
                    folder_path=f"{cd.course_folder_name}/({cd.term_display})/Syllabus",
                    file_paths=syllabus_files,
                )

            _set_job_status(
                job_id=job_id,
                data={
                    "status": "processing",
                    "progress": course_base + int(course_slice * 0.33),
                    "message": f"Course {course_idx + 1}/{total_courses}: Gathering Canvas assignments...",
                },
            )

            # Phase 1: Process all assignments locally (concurrent)
            assignment_texts_map = {}
            upload_tasks = []
            with ThreadPoolExecutor(max_workers=MAX_PARALLEL_ASSIGNMENTS) as pool:
                futures = {
                    pool.submit(
                        _process_single_assignment,
                        assignment,
                        grades_fetcher,
                        temp_dir,
                        cd.submissions_by_assignment,
                        cd.assignment_groups,
                        cd.course_folder_name,
                        cd.term_display,
                    ): i
                    for i, assignment in enumerate(cd.all_assignments)
                }
                total_assignments = len(futures)
                done_count = 0

                # For better progress reporting
                for future in as_completed(futures):
                    assign_id, texts, upload_task = future.result()
                    assignment_texts_map[assign_id] = texts
                    if upload_task:
                        upload_tasks.append(upload_task)
                    done_count += 1
                    pct = course_base + int(
                        course_slice * (0.33 + 0.22 * done_count / total_assignments)
                    )
                    _set_job_status(
                        job_id=job_id,
                        data={
                            "status": "processing",
                            "progress": pct,
                            "message": f"Course {course_idx + 1}/{total_courses}: Processing assignment {done_count}/{total_assignments}...",
                        },
                    )

            _set_job_status(
                job_id=job_id,
                data={
                    "status": "processing",
                    "progress": course_base + int(course_slice * 0.55),
                    "message": f"Course {course_idx + 1}/{total_courses}: Uploading files to Canvas...",
                },
            )
            # Phase 2: Upload all collected files to Canvas
            for canvas_folder, files in upload_tasks:
                grades_fetcher.upload_files(course_id_to_push, canvas_folder, files)

            logger.info("Data Gathering Complete")

            _set_job_status(
                job_id=job_id,
                data={
                    "status": "processing",
                    "progress": course_base + int(course_slice * 0.75),
                    "message": f"Course {course_idx + 1}/{total_courses}: Generating ABET outcome reports...",
                },
            )

            abet_assignments = find_abet_assignments(cd.all_assignments)

            # If there are ABET assignments, build the outcome reports and upload them.
            if abet_assignments:
                outcome_map, outcome_details = find_abet_outcomes(abet_assignments)
                if outcome_map:
                    outcome_reports = build_outcome_report_data(
                        grades_fetcher=grades_fetcher,
                        outcome_map=outcome_map,
                        outcome_details=outcome_details,
                        course_info=cd.course_info,
                        course_id=course_id_to_pull,
                        student_major_map=student_major_map,
                        assignment_texts_map=assignment_texts_map,
                        prefetched_submissions=cd.submissions_by_assignment,
                    )
                    report_json = {
                        "metadata": {
                            "course_id": str(course_id_to_pull),
                            "course_code": cd.course_code,
                            "semester": cd.semester_code,
                            "term_display": cd.term_display,
                            "course_folder_name": cd.course_folder_name,
                            "teachers_display": cd.teachers_display,
                        },
                        "outcomes": [
                            {
                                "outcome_id": r["outcome_id"],
                                "outcome_title": r["outcome_title"],
                                "data": r["data"],
                            }
                            for r in outcome_reports
                        ],
                    }
                    upload_abet_report(course_id_to_push, report_json, grades_fetcher)
                    logger.info(
                        "Uploaded ABET reports for course '%s'", course_id_to_pull
                    )

        _set_job_status(
            job_id=job_id,
            data={
                "status": "completed",
                "message": "Data transfer complete.",
                "course_folder_name": last_course_folder_name,
                "term_display": last_term_display,
                "course_code": last_course_code,
                "progress": 100,
                "completed_at": time.time(),
            },
        )

    except requests.exceptions.RequestException as e:
        logger.exception("Canvas API error in background task: %s", e)
        _set_job_status(
            job_id=job_id,
            data={
                "status": "failed",
                "error": "A Canvas API error occurred. Please check your token and try again.",
                "completed_at": time.time(),
            },
        )
    except ValueError as e:
        logger.error("Pipeline configuration error: %s", e)
        _set_job_status(
            job_id=job_id,
            data={
                "status": "failed",
                "error": str(e),
                "completed_at": time.time(),
            },
        )
    except Exception as e:
        logger.exception("Unexpected error in background task: %s", e)
        _set_job_status(
            job_id=job_id,
            data={
                "status": "failed",
                "error": "An unexpected error occurred. Please try again or contact support.",
                "completed_at": time.time(),
            },
        )
    finally:
        cleanup_temp_dir(temp_dir)


@app.post("/move-data-between-courses/{course_id_to_push}")
def move_data_between_courses(
    course_id_to_push: str,
    canvas_access_token: Annotated[str, Header()],
    course_ids_to_pull: Annotated[
        List[str], Query(min_length=1, description="Enter all Course IDs to pull from")
    ],
    background_tasks: BackgroundTasks,
    roster_file: UploadFile = File(...),
):
    # Validate Course IDs
    if not course_ids_to_pull or not course_id_to_push:
        raise HTTPException(status_code=400, detail="Course IDs must both be filled")

    # Early token validation
    if not canvas_access_token or not str(canvas_access_token).strip():
        raise HTTPException(status_code=401, detail="Canvas access token is required.")

    if not roster_file:
        raise HTTPException(
            status_code=400, detail="The 'roster_file' is required for this endpoint."
        )

    # Parse roster early so we don't need to do it in the background
    # roster is needed for generation of project evaluations folder which contains
    # the abet reports for each outcome for the course
    try:
        student_major_map = parse_roster_upload(roster_file)
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=400, detail=f"Failed to parse roster file: {e}")

    job_id = str(uuid.uuid4())
    _set_job_status(job_id=job_id, data={"status": "processing"})

    background_tasks.add_task(
        _run_extraction_pipeline_sync,
        job_id=job_id,
        course_id_to_push=course_id_to_push,
        canvas_access_token=canvas_access_token,
        course_ids_to_pull=course_ids_to_pull,
        student_major_map=student_major_map,
    )

    return {
        "message": "Extraction started in background.",
        "job_id": job_id,
        "status": "processing",
    }


if __name__ == "__main__":
    import uvicorn

    uvicorn.run(app, host="0.0.0.0", port=8000)
