def get_student_info(questionnaire):
    """
    Fetch basic student info (concatenated name and other profile fields).
    """

    cursor = questionnaire.db.cursor()

    cursor.execute("""
        SELECT 
            freshman,
            transfer_12_23,
            transfer_24_primary,
            transfer_24_secondary
        FROM student_admission_requirements;
        """)

    return cursor.fetchall()

if __name__ == "__main__":
    
    
    print("Testing criterion_students_data.py")

    from getdatabaseConnection import get_database_connection
    db = get_database_connection()

    cursor = db.cursor()

    cursor.execute("""
        SELECT
                   
            freshman,
            transfer_12_23,
            transfer_24_primary,
            transfer_24_secondary
        FROM student_admission_requirements;
        
    """)

    student_data = cursor.fetchall()
    print(f"Retrieved {len(student_data)} student records:")
    for record in student_data:
        print(record)
    