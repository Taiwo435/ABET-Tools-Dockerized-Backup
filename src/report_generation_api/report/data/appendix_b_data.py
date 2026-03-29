import json
import logging
from typing import Any

logger = logging.getLogger(__name__)


def get_data(questionnaire) -> list[dict[str, Any]]:
    """
    Fetch faculty vitae data for Appendix B.

    Data source:
      - faculty_info (name, rank)
      - faculty_vitae (long-form vitae fields)

    Returns:
      A list of rows (dicts) suitable for Appendix B context building.
    """
    cursor = questionnaire.db.cursor()

    cursor.execute(
        """
        SELECT
            fi.user_id,
            fi.first_name,
            fi.last_name,
            fi.faculty_rank,
            fv.education,
            fv.academic_experience,
            fv.non_academic_experience,
            fv.certifications,
            fv.professional_memberships,
            fv.honors_and_awards,
            fv.service_activities,
            fv.publications_presentations,
            fv.professional_development
        FROM faculty_info fi
        LEFT JOIN faculty_vitae fv
            ON fi.user_id = fv.user_id
        ORDER BY fi.last_name, fi.first_name;
        """
    )

    rows = cursor.fetchall() or []
    # PyMySQL DictCursor should yield dict rows; keep a defensive fallback.
    if not isinstance(rows, list):
        return []
    return [r for r in rows if isinstance(r, dict)]


def json_to_list(value: Any) -> list[str]:
    """
    Normalize a MySQL JSON column into a list of strings.

    The DB layer may return JSON as:
      - Python list (already decoded)
      - JSON string
      - None
    """
    if value is None:
        return []

    if isinstance(value, list):
        return [str(v).strip() for v in value if str(v).strip()]

    if isinstance(value, (dict, int, float, bool)):
        # Not expected for these columns, but keep it readable.
        s = str(value).strip()
        return [s] if s else []

    text = str(value).strip()
    if not text:
        return []

    try:
        decoded = json.loads(text)
    except Exception:
        # Not JSON - treat as a single blob of text.
        return [text]

    if isinstance(decoded, list):
        return [str(v).strip() for v in decoded if str(v).strip()]
    if isinstance(decoded, dict):
        # If someone stored structured objects, stringify them.
        return [json.dumps(decoded, ensure_ascii=False)]

    s = str(decoded).strip()
    return [s] if s else []


# Backwards-compatible alias (some modules may import the underscored name)
_json_to_list = json_to_list
