def get_facilities_data(questionnaire):
    """
    Fetch rows from the `7.1 facilities` table using the shared DB connection.
    """

    cursor = questionnaire.db.cursor()

    cursor.execute(
        """
        SELECT
            bldg_code,
            room_number,
            capacity,
            use_description,
            zoom_level
        FROM facility_rooms
    """)
    

    return cursor.fetchall()




