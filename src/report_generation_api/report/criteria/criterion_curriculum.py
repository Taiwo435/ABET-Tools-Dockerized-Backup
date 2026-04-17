from typing import Any, Dict, List, Optional

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
            # cyber_direct_required_bullets.append(row["bullet"])
        if row["is_focus_course"]:
            cyber_focus_courses.append(row)
            # cyber_focus_bullets.append(row["bullet"])
        if row["is_cyber_elective"]:
            cyber_elective_courses.append(row)

    return {
        # Raw DB rows from the `curriculum` table (requested).
        "curriculum_rows": curriculum_rows,
        "curriculum_semesters": curriculum_semesters,
        "curriculum_table_rows": curriculum_table_rows,
        "curriculum_error": curriculum_error,

        "cse_elective_courses": rows,
        "cse_technical_electives": cse_technical_electives,
        "cyber_direct_required_courses": cyber_direct_required_courses,
        "cyber_focus_courses": cyber_focus_courses,
        "cyber_elective_courses": cyber_elective_courses,

        # Bullet lists (optinal)
        # "cyber_direct_required_bullets": cyber_direct_required_bullets,
        # "cyber_focus_bullets": cyber_focus_bullets,
    }
