import tempfile
import logging
import os
import re
import shutil
from pathlib import Path
from typing import Dict

import abetReportGenerator
from fetch_grades import CanvasGradesFetcher

logger = logging.getLogger(__name__)


def _extract_short_course_code(raw_code: str) -> str:
    """Extract compact course code like 'CSE423' from '2023Fall-T-CSE423-70483'."""
    m = re.search(r"([A-Z]{2,4})\s*(\d{3})", raw_code)
    return f"{m.group(1)}{m.group(2)}" if m else raw_code


def _extract_outcome_number(title: str) -> str:
    """Extract outcome number like '3' from 'CSE ABET 3 - Communication'."""
    m = re.search(r"ABET\s*(\d+)", title, re.IGNORECASE)
    return m.group(1) if m else title.replace(" ", "_")


def upload_abet_report(
    course_id: str,
    report_json: Dict,
    grades_fetcher: CanvasGradesFetcher,
):
    """Generates ABET report PDFs from extracted data and uploads to Canvas.

    Args:
        course_id: Canvas course ID to upload into.
        report_json: The JSON payload from generate-report-json (with 'metadata' and 'outcomes').
        grades_fetcher: Already-initialized CanvasGradesFetcher instance.
    """
    res = report_json
    metadata = res.get("metadata", {})
    course_code_raw = metadata.get("course_code", "")
    term_display = metadata.get("term_display", "")  # e.g. "Fall 2023"
    term_for_filename = term_display.replace(" ", "_")  # e.g. "Fall_2023"
    course_folder_name = metadata.get("course_folder_name", "")
    short_code = _extract_short_course_code(course_code_raw)  # e.g. "CSE423"

    try:
        with tempfile.TemporaryDirectory() as tmpdir:
            template_name = os.path.join(tmpdir, "_ABET_TEMPLATE.docx")
            abetReportGenerator.reportgen(
                template_name, res.get("outcomes", {}), out_dir=tmpdir
            )

            # Group files by their target destination folder
            uploads_by_folder = {}

            # Rename report files to format: CSE423_ABET_3_f23.docx
            outcomes = res.get("outcomes", [])
            for outcome in outcomes:
                title = outcome.get("outcome_title", "")
                outcome_num = _extract_outcome_number(title)
                old_name = f"{title}_ABET_Report.docx"
                new_name = f"{short_code}_ABET_{outcome_num}_{term_for_filename}.docx"

                old_path = Path(tmpdir) / old_name
                new_path = Path(tmpdir) / new_name
                if old_path.exists():
                    old_path.rename(new_path)
                    logger.info("Renamed %s -> %s", old_name, new_name)
                    
                    # Store this specific report in its designated outcome folder
                    if new_path.name != "_ABET_TEMPLATE.docx":
                        folder_path = f"{course_folder_name}/({term_display})/Test_Assignments/Project Evaluations/Abet {outcome_num}"
                        if folder_path not in uploads_by_folder:
                            uploads_by_folder[folder_path] = []
                        uploads_by_folder[folder_path].append(str(new_path))

            # Batch upload files to their respective folders
            for folder_path, file_paths in uploads_by_folder.items():
                grades_fetcher.upload_files(
                    course_id=course_id,
                    folder_path=folder_path,
                    file_paths=file_paths,
                )

    except Exception as e:
        logger.error("Failed to generate/upload ABET reports: %s", e)
        raise
