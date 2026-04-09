def get_scai_staff(questionnaire):
    """
    Fetch rows from the `curriculum` table using the shared DB connection.
    """

    cursor = questionnaire.db.cursor()

    cursor.execute(
        """
        SELECT
            function_description,
            manager,
            staff_size
        FROM scai_staff
    """)
    

    return cursor.fetchall()




