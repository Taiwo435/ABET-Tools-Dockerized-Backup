def get_data(questionnaire):
    """
    Fetch rows from the `curriculum` table using the shared DB connection.
    """

    cursor = questionnaire.db.cursor()

    cursor.execute(
        """
        SELECT
            `curriculum_id`,
            `program_id`,
            `concentration`,
            `semester_year`,
            `course`,
            `course_type`,
            `credit_hours_math_science`,
            `credit_hours_engineering`,
            `credit_hours_other`,
            `last_two_terms`,
            `max_section_enrollment`,
            `updated_at`
        FROM `curriculum`
        ORDER BY `semester_year`, `course`, `curriculum_id`;
        """
    )

    return cursor.fetchall()


if __name__ == "__main__":
    print("Testing criterion_curriculum_data.py")

    from getdatabaseConnection import get_database_connection

    db = get_database_connection()
    class _Q:  # minimal questionnaire shape
        def __init__(self, db):
            self.db = db

    rows = get_data(_Q(db))
    print(f"Retrieved {len(rows)} curriculum rows")
