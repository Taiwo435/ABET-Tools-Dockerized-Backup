def get_peo_review_info(questionnaire):
    """
    Fetch PEO review info (input method, schedule, constituencies).
    """

    cursor = questionnaire.db.cursor()

    cursor.execute("""
        SELECT 
            input_method,
            schedule,
            constituencies
        FROM peo_review
            """)
    

    return cursor.fetchall()

if __name__ == "__main__":
    
    
    print("Testing criterion_program_edu_obj_data.py")

    from getdatabaseConnection import get_database_connection
    db = get_database_connection()

    cursor = db.cursor()

    cursor.execute("""
        SELECT 
            input_method,
            schedule,
            constituencies
        FROM peo_review
        
    """)

    peo_review_data = cursor.fetchall()
    print(f"Retrieved {len(peo_review_data)} PEO review records:")
    for record in peo_review_data:
        print(record)
    