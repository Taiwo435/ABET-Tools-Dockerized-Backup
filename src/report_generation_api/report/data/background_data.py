"""
This is just for example to show how connecting to the database and fetching data can work.
"""

def get_data(questionnaire):
    """
    Fetch data for the background section using the shared DB connection.
    """

    cursor = questionnaire.db.cursor()

    query = """
        SELECT college_name, institution_name
        FROM program_background
        WHERE report_year = %s
          AND department = %s
          AND degree_type = %s
        LIMIT 1
    """

    cursor.execute(
        query,
        (
            questionnaire.year,
            questionnaire.department,
            questionnaire.degree_type
        )
    )

    row = cursor.fetchone()

    if row is None:
        return {
            "year": questionnaire.year,
            "department": questionnaire.department,
            "degree_type": questionnaire.degree_type,
            "college_name": "Unknown College",
            "institution_name": "Unknown Institution"
        }

    return {
        "year": questionnaire.year,
        "department": questionnaire.department,
        "degree_type": questionnaire.degree_type,
        "college_name": row[0],
        "institution_name": row[1]
    }