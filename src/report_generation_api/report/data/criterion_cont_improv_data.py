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

def get_outcome_met_percentages(questionnaire):
    """
    Fetch outcome met percentages info (outcome number, semesters assessed, percentage met, times consecutive not met, percentage met secondary).
    """

    cursor = questionnaire.db.cursor()

    cursor.execute("""
        SELECT 
            outcome_number,
                   semesters_assessed,
                   percentage_met,
                   times_consecutive_not_met,
                   percentage_met_secondary
        FROM outcome_met_percentages
    """)
    

    return cursor.fetchall()
def get_improvement_actions_hardware(questionnaire):
    """
    Fetch improvement actions info from continuous_improvement.
    """

    cursor = questionnaire.db.cursor()

    #if improvement_type is not None:
    cursor.execute("""
        SELECT
            improvement_id,
            program_id,
            type,
            semester_year,
            source,
            problem_analysis,
            actions_plans,
            status_actions,
            result
        FROM continuous_improvement
        WHERE type = 'hardware';
    """)

    return cursor.fetchall()

def get_improvement_actions_year(questionnaire):
    

    cursor = questionnaire.db.cursor()

    #if improvement_type is not None:
    cursor.execute("""
        SELECT
            improvement_id,
            program_id,
            type,
            status,
            semester_year,
            source,
            problem_analysis,
            actions_plans,
            status_actions,
            result
        FROM continuous_improvement
        WHERE type = 'semester_improvement' and semester_year IS NOT NULL;
    """)

    return cursor.fetchall()

def get_improvement_actions_new_course(questionnaire):
    

    cursor = questionnaire.db.cursor()

    #if improvement_type is not None:
    cursor.execute("""
        SELECT
            improvement_id,
            program_id,
            type,
            semester_year,
            source,
            problem_analysis,
            actions_plans,
            status_actions,
            result
        FROM continuous_improvement
        WHERE type = 'new_course';
    """)

    return cursor.fetchall()
def get_improvement_actions_obj(questionnaire):
    

    cursor = questionnaire.db.cursor()

    #if improvement_type is not None:
    cursor.execute("""
        SELECT
            improvement_id,
            program_id,
            type,
            semester_year,
            source,
            problem_analysis,
            actions_plans,
            status_actions,
            result
        FROM continuous_improvement
        WHERE type = 'peo_update';
    """)
    return cursor.fetchall()

def get_improvement_concentration(questionnaire):
    

    cursor = questionnaire.db.cursor()

    #if improvement_type is not None:
    cursor.execute("""
        SELECT
            improvement_id,
            program_id,
            type,
            semester_year,
            source,
            problem_analysis,
            actions_plans,
            status_actions,
            result
        FROM continuous_improvement
        WHERE type = 'concentration_update';
    """)

    return cursor.fetchall()

def get_improvement_concentration_update_flowchart(questionnaire):
    

    cursor = questionnaire.db.cursor()

    #if improvement_type is not None:
    cursor.execute("""
        SELECT
            improvement_id,
            program_id,
            type,
            semester_year,
            source,
            problem_analysis,
            actions_plans,
            status_actions,
            result
        FROM continuous_improvement
        WHERE type = 'concentration_flowchart';
    """)

    return cursor.fetchall()
def get_improvement_adhoc(questionnaire):
    

    cursor = questionnaire.db.cursor()

    #if improvement_type is not None:
    cursor.execute("""
        SELECT
            improvement_id,
            program_id,
            type,
            semester_year,
            source,
            problem_analysis,
            actions_plans,
            status_actions,
            result
        FROM continuous_improvement
        WHERE type = 'adhoc';
    """)

    return cursor.fetchall()

def get_improvement_underway(questionnaire):
    

    cursor = questionnaire.db.cursor()

    #if improvement_type is not None:
    cursor.execute("""
        SELECT
            improvement_id,
            program_id,
            type,
            status,
            semester_year,
            source,
            problem_analysis,
            actions_plans,
            status_actions,
            result
        FROM continuous_improvement
        WHERE type = 'semester_improvement' and status = 'ongoing';
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
    