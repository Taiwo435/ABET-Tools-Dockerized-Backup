from typing import Any


def get_data(questionnaire) -> list[dict[str, Any]]:
    """Fetch syllabus rows for the report's program and year."""
    cursor = questionnaire.db.cursor()
    cursor.execute(
        """
        SELECT
            cs.course_subject,
            cs.course_number,
            cs.course_name,
            cs.credits,
            cs.contact_hours,
            cs.credit_categorization,
            cs.instructor_name,
            cs.textbook,
            cs.catalog_description,
            cs.prerequisites,
            cs.course_type,
            cs.specific_goals,
            cs.student_outcomes,
            cs.topics_covered
        FROM course_syllabi AS cs
        JOIN programs AS p ON p.program_id = cs.program_id
        WHERE p.program_year = %s
          AND (p.program_name = %s OR p.program_code = %s)
        ORDER BY cs.course_type, cs.course_subject, cs.course_number
        """,
        (
            questionnaire.year,
            questionnaire.department,
            questionnaire.degree_type,
        ),
    )

    rows = cursor.fetchall() or []
    if not isinstance(rows, list):
        return []
    return [row for row in rows if isinstance(row, dict)]
