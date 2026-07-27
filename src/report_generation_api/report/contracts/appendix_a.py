import copy
import json
from collections.abc import Mapping
from typing import Any

SCHEMA_VERSION = "1.0"

COURSE_FIELDS = {
    "course_code",
    "course_name",
    "credits",
    "contact_hours",
    "credit_category",
    "delivery_type",
    "instructors",
    "textbooks",
    "catalog_description",
    "prerequisites",
    "course_type",
    "specific_goals",
    "student_outcomes",
    "topics_covered",
}

REQUIRED_STRING_FIELDS = {
    "course_code",
    "course_name",
    "contact_hours",
    "credit_category",
    "catalog_description",
}

REQUIRED_LIST_FIELDS = {
    "instructors",
    "specific_goals",
    "student_outcomes",
    "topics_covered",
}

DELIVERY_TYPES = {"in_person", "online", "hybrid", "unspecified"}
COURSE_TYPES = {"R", "E", "SE"}

COURSE_TYPE_LABELS = {
    "R": "Required",
    "E": "Elective",
    "SE": "Selected elective",
}

DELIVERY_TYPE_LABELS = {
    "in_person": "In person",
    "online": "Online",
    "hybrid": "Hybrid",
    "unspecified": "Unspecified",
}


class AppendixAContractError(ValueError):
    """Raised when Appendix A input does not satisfy the versioned contract."""

    def __init__(self, errors: list[str]):
        self.errors = errors
        super().__init__("Appendix A contract validation failed: " + "; ".join(errors))


def _decode_string_list(value: Any) -> list[str]:
    if value is None:
        return []

    if isinstance(value, (list, tuple, set)):
        return [str(item).strip() for item in value if str(item).strip()]

    if isinstance(value, str):
        text = value.strip()
        if not text:
            return []
        try:
            decoded = json.loads(text)
        except (TypeError, ValueError):
            return [text]
        if isinstance(decoded, list):
            return [str(item).strip() for item in decoded if str(item).strip()]
        return [str(decoded).strip()] if str(decoded).strip() else []

    text = str(value).strip()
    return [text] if text else []


def normalize_database_row(row: Mapping[str, Any]) -> dict[str, Any]:
    """Convert one database row to the Appendix A v1 course shape."""
    subject = str(row.get("course_subject") or "").strip()
    number = str(row.get("course_number") or "").strip()

    return {
        "course_code": " ".join(part for part in (subject, number) if part),
        "course_name": str(row.get("course_name") or "").strip(),
        "credits": row.get("credits"),
        "contact_hours": str(row.get("contact_hours") or "").strip(),
        "credit_category": str(row.get("credit_categorization") or "").strip(),
        "delivery_type": str(row.get("delivery_type") or "unspecified").strip(),
        "instructors": _decode_string_list(row.get("instructor_name")),
        "textbooks": _decode_string_list(row.get("textbook")),
        "catalog_description": str(row.get("catalog_description") or "").strip(),
        "prerequisites": str(row.get("prerequisites") or "").strip(),
        "course_type": str(row.get("course_type") or "").strip(),
        "specific_goals": _decode_string_list(row.get("specific_goals")),
        "student_outcomes": _decode_string_list(row.get("student_outcomes")),
        "topics_covered": _decode_string_list(row.get("topics_covered")),
    }


def validate_contract(payload: Mapping[str, Any]) -> dict[str, Any]:
    """Validate and normalize an Appendix A v1 payload."""
    if not isinstance(payload, Mapping):
        raise AppendixAContractError(["root must be an object"])

    errors: list[str] = []
    unknown_root_fields = set(payload) - {"schema_version", "courses"}
    if unknown_root_fields:
        errors.append(
            "root contains unsupported fields: "
            + ", ".join(sorted(unknown_root_fields))
        )

    if payload.get("schema_version") != SCHEMA_VERSION:
        errors.append(f"schema_version must be {SCHEMA_VERSION}")

    courses = payload.get("courses")
    if not isinstance(courses, list) or not courses:
        errors.append("courses must be a non-empty array")
        raise AppendixAContractError(errors)

    normalized_courses: list[dict[str, Any]] = []
    seen_codes: set[str] = set()

    for index, raw_course in enumerate(courses):
        path = f"courses[{index}]"
        if not isinstance(raw_course, Mapping):
            errors.append(f"{path} must be an object")
            continue

        course = copy.deepcopy(dict(raw_course))
        course.setdefault("textbooks", [])
        course.setdefault("prerequisites", "")

        unknown_fields = set(course) - COURSE_FIELDS
        if unknown_fields:
            errors.append(
                f"{path} contains unsupported fields: "
                + ", ".join(sorted(unknown_fields))
            )

        for field in sorted(REQUIRED_STRING_FIELDS):
            value = course.get(field)
            if not isinstance(value, str) or not value.strip():
                errors.append(f"{path}.{field} is required")
            elif value != value.strip():
                course[field] = value.strip()

        credits = course.get("credits")
        if (
            isinstance(credits, bool)
            or not isinstance(credits, (int, float))
            or credits <= 0
        ):
            errors.append(f"{path}.credits must be a positive number")

        for field in sorted(REQUIRED_LIST_FIELDS):
            value = course.get(field)
            if not isinstance(value, list) or not value:
                errors.append(f"{path}.{field} must be a non-empty array")
                continue
            invalid_item = next(
                (
                    item_index
                    for item_index, item in enumerate(value)
                    if not isinstance(item, str) or not item.strip()
                ),
                None,
            )
            if invalid_item is not None:
                errors.append(
                    f"{path}.{field}[{invalid_item}] must be a non-empty string"
                )
            else:
                course[field] = [item.strip() for item in value]

        textbooks = course.get("textbooks")
        if not isinstance(textbooks, list):
            errors.append(f"{path}.textbooks must be an array")
        elif any(not isinstance(item, str) or not item.strip() for item in textbooks):
            errors.append(f"{path}.textbooks must contain only non-empty strings")
        else:
            course["textbooks"] = [item.strip() for item in textbooks]

        prerequisites = course.get("prerequisites")
        if not isinstance(prerequisites, str):
            errors.append(f"{path}.prerequisites must be a string")
        else:
            course["prerequisites"] = prerequisites.strip()

        if course.get("delivery_type") not in DELIVERY_TYPES:
            errors.append(
                f"{path}.delivery_type must be one of: "
                + ", ".join(sorted(DELIVERY_TYPES))
            )

        if course.get("course_type") not in COURSE_TYPES:
            errors.append(
                f"{path}.course_type must be one of: " + ", ".join(sorted(COURSE_TYPES))
            )

        code = course.get("course_code")
        if isinstance(code, str) and code.strip():
            normalized_code = code.strip().upper()
            if normalized_code in seen_codes:
                errors.append(f"{path}.course_code duplicates {normalized_code}")
            seen_codes.add(normalized_code)

        normalized_courses.append(course)

    if errors:
        raise AppendixAContractError(errors)

    return {
        "schema_version": SCHEMA_VERSION,
        "courses": normalized_courses,
    }


def _display_course(course: dict[str, Any]) -> dict[str, Any]:
    result = copy.deepcopy(course)
    credits = course["credits"]
    credits_text = f"{credits:g}" if isinstance(credits, float) else str(credits)

    result.update(
        {
            "course_number_and_name": (
                f"{course['course_code']} {course['course_name']}"
            ),
            "credits_contact_and_category": (
                f"{credits_text} credits; {course['contact_hours']}; "
                f"{course['credit_category']}"
            ),
            "course_type_label": COURSE_TYPE_LABELS[course["course_type"]],
            "delivery_type_label": DELIVERY_TYPE_LABELS[course["delivery_type"]],
            "instructors_text": "\n".join(course["instructors"]),
            "textbooks_text": (
                "\n".join(course["textbooks"])
                if course["textbooks"]
                else "No textbook specified."
            ),
            "specific_goals_text": "\n".join(course["specific_goals"]),
            "student_outcomes_text": "\n".join(course["student_outcomes"]),
            "topics_text": "\n".join(course["topics_covered"]),
        }
    )
    return result


def _group_courses(
    courses: list[dict[str, Any]], field: str
) -> dict[str, list[dict[str, Any]]]:
    grouped: dict[str, list[dict[str, Any]]] = {}
    for course in courses:
        grouped.setdefault(course[field], []).append(course)
    return grouped


def empty_context() -> dict[str, Any]:
    return {
        "schema_version": SCHEMA_VERSION,
        "course_syllabi": [],
        "courses_by_type": {},
        "courses_by_delivery": {},
    }


def build_context(payload: Mapping[str, Any]) -> dict[str, Any]:
    """Build docxtpl-ready Appendix A context from contract-compliant data."""
    contract = validate_contract(payload)
    courses = [_display_course(course) for course in contract["courses"]]

    return {
        "schema_version": contract["schema_version"],
        "course_syllabi": courses,
        "courses_by_type": _group_courses(courses, "course_type"),
        "courses_by_delivery": _group_courses(courses, "delivery_type"),
    }
