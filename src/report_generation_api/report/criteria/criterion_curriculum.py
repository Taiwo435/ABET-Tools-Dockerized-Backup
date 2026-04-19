from typing import Any, Dict, List, Optional
import re

from docxtpl import RichText

import getElectiveCourses
from report.data import criterion_curriculum_data




def _required_for_display(required_for: str) -> str:
    return "CbS" if required_for == "Cbs" else ""


def _course_type_display(course_type: Any) -> str:
    value = str(course_type or "").strip()
    lowered = value.lower()
    if lowered in {"r", "required"}:
        return "R"
    if lowered in {"e", "elective"}:
        return "E"
    if lowered in {"se", "selected elective", "selected_elective", "selected-elective"}:
        return "SE"
    return value


def _hours_display(value: Any) -> str:
    if value is None:
        return ""
    if isinstance(value, (int, float)) and value == 0:
        return ""
    text = str(value).strip()
    return "" if text in {"0", "0.0", "0.00"} else text


def _coerce_tags(value: Any) -> List[str]:
    if value is None:
        return []
    if isinstance(value, list):
        return [t.strip() for t in value if isinstance(t, str) and t.strip()]
    if isinstance(value, str):
        # Backwards compatibility: sometimes tags may be stored as a string
        return [t.strip() for t in value.split(",") if t.strip()]
    return []

def _is_cbs(concentration: Any) -> bool:
    return str(concentration or "").strip().lower() == "cbs"


def _rt(value: Any, *, bold: bool = False) -> RichText:
    rt = RichText()
    rt.add("" if value is None else str(value), bold=bold)
    return rt


def _year_bucket(year_level: Any) -> str:
    text = str(year_level or "").strip().lower()
    if "fresh" in text:
        return "freshman"
    if "soph" in text:
        return "sophomore"
    if "jun" in text:
        return "junior"
    if "sen" in text:
        return "senior"
    return text or "other"


def _courses_cell_text(value: Any) -> str:
    text = str(value or "").strip()
    if not text:
        return ""
    parts = [p.strip() for p in re.split(r"[,\n]+", text) if p.strip()]
    return "\n".join(parts)


_OUTCOME_NUM_RE = re.compile(r"^\s*\(?\s*(\d+)\s*\)?")


def _outcome_sort_key(text: Any) -> tuple[int, str]:
    s = str(text or "").strip()
    m = _OUTCOME_NUM_RE.match(s)
    if m:
        try:
            return (int(m.group(1)), s)
        except Exception:
            pass
    return (10**9, s)


# def _bullet(code: str, number: str, name: str) -> str:
#     # Matches: "CSE365: Title"
#     return f"{code}{number}: {name}"


def build(questionnaire):
    curriculum_error: Optional[str] = None
    try:
        curriculum_db_rows = criterion_curriculum_data.get_data(questionnaire)
    except Exception as e:
        curriculum_db_rows = []
        curriculum_error = str(e)

    curriculum_rows: List[Dict[str, Any]] = []
    for row in curriculum_db_rows:
        if not isinstance(row, dict):
            continue
        curriculum_rows.append(
            {
                "program_id": row.get("program_id"),
                "concentration": row.get("concentration"),
                "is_cbs": _is_cbs(row.get("concentration")),
                "semester_year": row.get("semester_year"),
                "course": row.get("course"),
                "course_type": row.get("course_type"),
                "credit_hours_math_science": row.get("credit_hours_math_science"),
                "credit_hours_engineering": row.get("credit_hours_engineering"),
                "credit_hours_other": row.get("credit_hours_other"),
                "last_two_terms": row.get("last_two_terms"),
                "max_section_enrollment": row.get("max_section_enrollment"),
            }
        )

    curriculum_semesters_map: Dict[str, List[Dict[str, Any]]] = {}
    for row in curriculum_rows:
        semester = str(row.get("semester_year") or "").strip() or "Unspecified"
        item = {
            "program_id": row.get("program_id"),
            "concentration": row.get("concentration"),
            "is_cbs": _is_cbs(row.get("concentration")),
            "semester_year": row.get("semester_year"),
            "course": row.get("course"),
            "course_type": row.get("course_type"),
            "course_type_display": _course_type_display(row.get("course_type")),
            "credit_hours_math_science": row.get("credit_hours_math_science"),
            "credit_hours_math_science_display": _hours_display(row.get("credit_hours_math_science")),
            "credit_hours_engineering": row.get("credit_hours_engineering"),
            "credit_hours_engineering_display": _hours_display(row.get("credit_hours_engineering")),
            "credit_hours_other": row.get("credit_hours_other"),
            "credit_hours_other_display": _hours_display(row.get("credit_hours_other")),
            "last_two_terms": row.get("last_two_terms"),
            "max_section_enrollment": row.get("max_section_enrollment"),
        }
        curriculum_semesters_map.setdefault(semester, []).append(item)

    curriculum_semesters = [
        {"semester": semester, "courses": courses}
        for semester, courses in curriculum_semesters_map.items()
    ]

    curriculum_table_rows: List[Dict[str, Any]] = []
    for semester_group in curriculum_semesters:
        curriculum_table_rows.append(
            {
                "is_semester_header": True,
                "semester": semester_group["semester"],
            }
        )
        for course_row in semester_group["courses"]:
            curriculum_table_rows.append(
                {
                    "is_semester_header": False,
                    "semester": semester_group["semester"],
                    **course_row,
                }
            )

    # RichText variants for docxtpl-safe conditional bolding.
    curriculum_table_rows_5_1_rt: List[Dict[str, Any]] = []
    curriculum_table_rows_5_1a_rt: List[Dict[str, Any]] = []

    for r in curriculum_table_rows:
        if r.get("is_semester_header"):
            semester = r.get("semester")
            row_rt = {**r, "semester_rt": _rt(semester, bold=True)}
            curriculum_table_rows_5_1_rt.append(row_rt)
            curriculum_table_rows_5_1a_rt.append(row_rt)
            continue

        course_text = r.get("course")
        is_cbs = bool(r.get("is_cbs"))
        curriculum_table_rows_5_1_rt.append({**r, "course_rt": _rt(course_text, bold=False)})
        curriculum_table_rows_5_1a_rt.append({**r, "course_rt": _rt(course_text, bold=is_cbs)})

    # Table 5-2: Course alignment with program educational objectives
    try:
        peo_rows = criterion_curriculum_data.get_peo_alignment(questionnaire)
    except Exception:
        peo_rows = []

    peo_alignment_map: Dict[int, Dict[str, str]] = {}
    for row in peo_rows:
        if not isinstance(row, dict):
            continue
        try:
            obj_num = int(row.get("objective_number") or 0)
        except Exception:
            obj_num = 0
        if obj_num <= 0:
            continue

        bucket = _year_bucket(row.get("year_level"))
        courses_text = _courses_cell_text(row.get("courses"))
        peo_alignment_map.setdefault(obj_num, {})
        peo_alignment_map[obj_num][bucket] = courses_text

    peo_alignment_table: List[Dict[str, Any]] = []
    for obj_num in sorted(peo_alignment_map.keys()):
        entry = peo_alignment_map[obj_num]
        peo_alignment_table.append(
            {
                "objective_number": obj_num,
                "freshman": entry.get("freshman", ""),
                "sophomore": entry.get("sophomore", ""),
                "junior": entry.get("junior", ""),
                "senior": entry.get("senior", ""),
            }
        )

    # Table 5-3: Course alignment with ABET required student outcomes
    try:
        outcome_rows = criterion_curriculum_data.get_outcome_alignment(questionnaire)
    except Exception:
        outcome_rows = []

    outcome_map: Dict[str, Dict[str, str]] = {}
    for row in outcome_rows:
        if not isinstance(row, dict):
            continue
        outcome = str(row.get("student_outcome") or "").strip()
        if not outcome:
            continue
        bucket = _year_bucket(row.get("year_level"))
        courses_text = _courses_cell_text(row.get("courses"))
        outcome_map.setdefault(outcome, {})
        outcome_map[outcome][bucket] = courses_text

    outcome_alignment_table: List[Dict[str, Any]] = []
    for outcome in sorted(outcome_map.keys(), key=_outcome_sort_key):
        entry = outcome_map[outcome]
        outcome_alignment_table.append(
            {
                "student_outcome": outcome,
                "freshman": entry.get("freshman", ""),
                "sophomore": entry.get("sophomore", ""),
                "junior": entry.get("junior", ""),
                "senior": entry.get("senior", ""),
            }
        )

    year_raw = getattr(questionnaire, "year", 0) or 0
    try:
        year = int(year_raw)
    except Exception:
        year = 0

    courses: List[Dict[str, Any]] = []
    error: Optional[str] = None
    if year:
        try:
            courses = getElectiveCourses.build_merged_cse_courses_json_from_url(year, timeout=15)
        except Exception as e:
            courses = []
            error = str(e)

    rows: List[Dict[str, Any]] = []
    cse_technical_electives: List[Dict[str, Any]] = []
    cyber_direct_required_courses: List[Dict[str, Any]] = []
    cyber_focus_courses: List[Dict[str, Any]] = []
    cyber_elective_courses: List[Dict[str, Any]] = []

    for idx, course in enumerate(courses, start=1):
        if not isinstance(course, dict):
            continue

        code = str(course.get("code") or "").strip()
        number = str(course.get("number") or "").strip()
        name = str(course.get("name") or "").strip()
        required_for = str(course.get("required_for") or "").strip()
        tags = _coerce_tags(course.get("tags"))

        if not (code and number and name):
            continue

        row: Dict[str, Any] = {
            "idx": str(idx),
            "code": code,
            "number": number,
            "course": f"{code} {number}",
            "course_compact": f"{code}{number}",
            "name": name,
            "required_for": required_for,
            "required_for_display": _required_for_display(required_for),
            "tags": tags,
            "tags_display": ", ".join(tags) if tags else "",
            "is_cse_technical_elective": "cse_technical_elective" in tags,
            "is_direct_required": "direct_required" in tags,
            "is_focus_course": "focus_course" in tags,
            "is_cyber_elective": "elective" in tags,
            # "bullet": _bullet(code, number, name),
        }

        rows.append(row)

        if row["is_cse_technical_elective"]:
            cse_technical_electives.append(row)
        if row["is_direct_required"]:
            cyber_direct_required_courses.append(row)
        if row["is_focus_course"]:
            cyber_focus_courses.append(row)
        if row["is_cyber_elective"]:
            cyber_elective_courses.append(row)
        #faculty qualification table
        try:
            profile_info = criterion_curriculum_data.get_pre_co_requisite_data(questionnaire)
        except Exception:
            profile_info = []

        requisite_list = []
        for row in profile_info:

            requisite_list.append({
                "name": row["program_name"],
                "pre_co_requisite": row["pre_co_requisite"]

            })


    return {
        # Raw DB rows from the `curriculum` table (requested).
        # "curriculum_rows": curriculum_rows,
        # "curriculum_semesters": curriculum_semesters,
        "curriculum_table_rows": curriculum_table_rows,
        "curriculum_table_rows_5_1_rt": curriculum_table_rows_5_1_rt,
        "curriculum_table_rows_5_1a_rt": curriculum_table_rows_5_1a_rt,
        # "peo_alignment_rows": peo_rows,
        "peo_alignment_table": peo_alignment_table,
        # "outcome_alignment_rows": outcome_rows,
        "outcome_alignment_table": outcome_alignment_table,
        "curriculum_error": curriculum_error,

        "cse_elective_courses": rows,
        "cse_technical_electives": cse_technical_electives,
        "cyber_direct_required_courses": cyber_direct_required_courses,
        "cyber_focus_courses": cyber_focus_courses,
        "cyber_elective_courses": cyber_elective_courses,
        "requisite_list": requisite_list,

    }
