def get_student_info(questionnaire):
    """
    Fetch basic student info (concatenated name and other profile fields).
    """

    cursor = questionnaire.db.cursor()

    cursor.execute("""
        SELECT 
            GROUP_CONCAT(DISTINCT p.program_name ORDER BY p.program_name SEPARATOR ', ') AS majors,
            s.freshman,
            s.transfer_12_23,
            s.transfer_24_primary,
            s.transfer_24_secondary
            FROM student_admission_requirements s
            LEFT JOIN admission_major_map ad ON s.admission_id = ad.admission_id
            LEFT JOIN programs p ON ad.program_id = p.program_id
            GROUP BY s.admission_id;
                   """)
    

    return cursor.fetchall()

if __name__ == "__main__":
    
    
    print("Testing criterion_students_data.py")

    from getdatabaseConnection import get_database_connection
    db = get_database_connection()

    cursor = db.cursor()

    cursor.execute("""
        SELECT
            s.admission_id,
            GROUP_CONCAT(DISTINCT p.program_name ORDER BY p.program_name SEPARATOR ', ') AS majors,
            s.freshman,
            s.transfer_12_23,
            s.transfer_24_primary,
            s.transfer_24_secondary
            FROM student_admission_requirements s
            LEFT JOIN admission_major_map ad ON s.admission_id = ad.admission_id
            LEFT JOIN programs p ON ad.program_id = p.program_id
            GROUP BY s.admission_id;
        
    """)

    student_data = cursor.fetchall()
    print(f"Retrieved {len(student_data)} student records:")
    for record in student_data:
        print(record)
    