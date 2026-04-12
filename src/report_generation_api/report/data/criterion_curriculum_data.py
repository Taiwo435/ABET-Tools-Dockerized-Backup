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
def get_pre_co_requisite_data(questionnaire):
    """
    Fetch rows from the `curriculum` table using the shared DB connection.
    """

    cursor = questionnaire.db.cursor()

    cursor.execute(
        """
        SELECT
            p.program_id,
            p.program_name,
            p.program_code,
            c.pre_co_requisite
        FROM course_pre_co_requisite as c
        INNER JOIN programs as p
        ON c.program_id = p.program_id
        ORDER BY p.program_name;
    """)
    

    return cursor.fetchall()


def get_peo_alignment(questionnaire):
    """
    Fetch Table 5-2 data: course alignment with program educational objectives.
    """

    cursor = questionnaire.db.cursor()
    cursor.execute(
        """
        SELECT
            `program_id`,
            `objective_number`,
            `year_level`,
            `courses`,
            `updated_at`
        FROM `curriculum_peo_alignment`
        ORDER BY `objective_number`, `year_level`, `alignment_id`;
        """
    )
    return cursor.fetchall()

def get_outcome_alignment(questionnaire):
    """
    Fetch Table 5-3 data: course alignment with ABET required student outcomes.
    """

    cursor = questionnaire.db.cursor()
    cursor.execute(
        """
        SELECT
            `program_id`,
            `student_outcome`,
            `year_level`,
            `courses`,
            `updated_at`
        FROM `curriculum_outcome_alignment`
        ORDER BY `student_outcome`, `year_level`, `alignment_id`;
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
