def get_outcome_assessment_info(questionnaire):
    """
    Fetch outcome assessment info (outcome number, course name, assessment method).
    """

    cursor = questionnaire.db.cursor()

    cursor.execute("""
        SELECT 
            outcome_number,
            course_name,
            assessment_method
        FROM outcome_assessment
    """)
    

    return cursor.fetchall()
def get_outcome_attainment_criteria(questionnaire):
    """
    Fetch outcome attainment criteria info (number of assessments, criteria for meeting outcome).
    """

    cursor = questionnaire.db.cursor()

    cursor.execute("""
        SELECT 
            num_assessments,
            criteria_for_meeting_outcome
        FROM outcome_attainment_criteria
    """)
    

    return cursor.fetchall()

if __name__ == "__main__":
    
    
    print("Testing criterion_cont_improv.py")

    from getdatabaseConnection import get_database_connection
    db = get_database_connection()

    cursor = db.cursor()

    cursor.execute("""
        SELECT 
            outcome_number,
            course_name,
            assessment_method
        FROM outcome_assessment
    """)

    outcome_assessment_data = cursor.fetchall()
    print(f"Retrieved {len(outcome_assessment_data)} outcome assessment records:")
    for record in outcome_assessment_data:
        print(record)
    