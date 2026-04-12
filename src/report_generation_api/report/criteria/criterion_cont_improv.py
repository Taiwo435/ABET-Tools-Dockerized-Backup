from report.data import criterion_cont_improv_data

def build(questionnaire):
    #table 4-1
    try:
        profile_rows = criterion_cont_improv_data.get_outcome_assessment_info(questionnaire)
    except Exception:
        profile_rows = []

    outcome_assessment = []
    for row in profile_rows:
        if isinstance(row, dict):


            outcome_assessment.append({
                "out_num": row["outcome_number"],
                "course_name": row["course_name"],
                "assess_method": row["assessment_method"]
            })
    #pg 40 unamed table
    try:
        profile_rows = criterion_cont_improv_data.get_outcome_attainment_criteria(questionnaire)
    except Exception:
        profile_rows = []

    outcome_attainment_criteria = []
    for row in profile_rows:
        if isinstance(row, dict):


            outcome_attainment_criteria.append({
                "num_ass": row["num_assessments"],
                "meeting": row["criteria_for_meeting_outcome"]
            })
    #table 4-6
    try:
        profile_rows = criterion_cont_improv_data.get_outcome_met_percentages(questionnaire)
    except Exception:
        profile_rows = []

    outcome_met_percentages = []
    for row in profile_rows:
        if isinstance(row, dict):


            outcome_met_percentages.append({
                "out_num": row["outcome_number"],
                "semesters_assessed": row["semesters_assessed"],
                "percentage_met": row["percentage_met"],
                "times_consecutive_not_met": row["times_consecutive_not_met"],
                "percentage_met_secondary": row["percentage_met_secondary"]
            })
    #pg 56-62 table
    try:
        profile_rows = criterion_cont_improv_data.get_improvement_actions_hardware(questionnaire)
    except Exception:
        profile_rows = []

    # Build both a list of hardware assessments and a mapping grouped by semester_year
    hardware_assessment = []
    hardware_assessment_first = {}

    for row in profile_rows:
        if not isinstance(row, dict):
            continue

        item = {
            "improvement_id": row.get("improvement_id"),
            "program_id": row.get("program_id"),
            "type": row.get("type"),
            "semester_year": row.get("semester_year"),
            "source": row.get("source"),
            "problem_analysis": row.get("problem_analysis"),
            "action_plans": row.get("actions_plans"),
            "status_actions": row.get("status_actions"),
            "result": row.get("result")
        }

        hardware_assessment.append(item)

        # keep the first row as a flat dict for backward compatibility
        if not hardware_assessment_first:
            hardware_assessment_first = item

    
    #pg 56-62 table improvement years
    try:
        profile_rows = criterion_cont_improv_data.get_improvement_actions_year(questionnaire)
    except Exception:
        profile_rows = []

    # Build both a list of hardware assessments and a mapping grouped by semester_year
 
    hardware_assessment_by_year = []


    for row in profile_rows:
        if not isinstance(row, dict):
            continue

        item = {
            "improvement_id": row.get("improvement_id"),
            "program_id": row.get("program_id"),
            "type": row.get("type"),
            "semester_year": row.get("semester_year"),
            "source": row.get("source"),
            "problem_analysis": row.get("problem_analysis"),
            "action_plans": row.get("actions_plans"),
            "status_actions": row.get("status_actions"),
            "result": row.get("result")
        }

        hardware_assessment_by_year.append((item))


    #pg 56-62 table improvement new course
    try:
        profile_rows = criterion_cont_improv_data.get_improvement_actions_new_course(questionnaire)
    except Exception:
        profile_rows = []

    # Build both a list of new course assessments and expose a first-item for templates
    hardware_assessment_new_course = []
    hardware_assessment_new_course_first = {}

    for row in profile_rows:
        if not isinstance(row, dict):
            continue

        item = {
            "improvement_id": row.get("improvement_id"),
            "program_id": row.get("program_id"),
            "type": row.get("type"),
            "semester_year": row.get("semester_year"),
            "source": row.get("source"),
            "problem_analysis": row.get("problem_analysis"),
            "action_plans": row.get("actions_plans"),
            "status_actions": row.get("status_actions"),
            "result": row.get("result")
        }

        hardware_assessment_new_course.append(item)
        if not hardware_assessment_new_course_first:
            hardware_assessment_new_course_first = item
    #pg 56-62 table improvement obj
    try:
        profile_rows = criterion_cont_improv_data.get_improvement_actions_obj(questionnaire)
    except Exception:
        profile_rows = []

    # Build both a list of new course assessments and expose a first-item for templates
    hardware_assessment_obj = []
    hardware_assessment_obj_first = {}

    for row in profile_rows:
        if not isinstance(row, dict):
            continue

        item = {
            "improvement_id": row.get("improvement_id"),
            "program_id": row.get("program_id"),
            "type": row.get("type"),
            "semester_year": row.get("semester_year"),
            "source": row.get("source"),
            "problem_analysis": row.get("problem_analysis"),
            "action_plans": row.get("actions_plans"),
            "status_actions": row.get("status_actions"),
            "result": row.get("result")
        }

        hardware_assessment_obj.append(item)
        if not hardware_assessment_obj_first:
            hardware_assessment_obj_first = item

    #pg 56-62 table improvement concentration flowchart
    try:
        profile_rows = criterion_cont_improv_data.get_improvement_concentration(questionnaire)
    except Exception:
        profile_rows = []

    # Build both a list of hardware assessments and a mapping grouped by semester_year
 
    assessment_concentration = []


    for row in profile_rows:
        if not isinstance(row, dict):
            continue

        item = {
            "improvement_id": row.get("improvement_id"),
            "program_id": row.get("program_id"),
            "type": row.get("type"),
            "semester_year": row.get("semester_year"),
            "source": row.get("source"),
            "problem_analysis": row.get("problem_analysis"),
            "action_plans": row.get("actions_plans"),
            "status_actions": row.get("status_actions"),
            "result": row.get("result")
        }

        assessment_concentration.append((item))

    #pg 56-62 table improvement concentration update
    try:
        profile_rows = criterion_cont_improv_data.get_improvement_concentration_update_flowchart(questionnaire)
    except Exception:
        profile_rows = []

    # Build both a list of hardware assessments and a mapping grouped by semester_year
 
    assessment_concentration_update_flowchart = []


    for row in profile_rows:
        if not isinstance(row, dict):
            continue

        item = {
            "improvement_id": row.get("improvement_id"),
            "program_id": row.get("program_id"),
            "type": row.get("type"),
            "semester_year": row.get("semester_year"),
            "source": row.get("source"),
            "problem_analysis": row.get("problem_analysis"),
            "action_plans": row.get("actions_plans"),
            "status_actions": row.get("status_actions"),
            "result": row.get("result")
        }

        assessment_concentration_update_flowchart.append((item))
    #pg 56-62 table improvement adhoc
    try:
        profile_rows = criterion_cont_improv_data.get_improvement_adhoc(questionnaire)
    except Exception:
        profile_rows = []

    # Build both a list of hardware assessments and a mapping grouped by semester_year
 
    assessment_adhoc = []


    for row in profile_rows:
        if not isinstance(row, dict):
            continue

        item = {
            "improvement_id": row.get("improvement_id"),
            "program_id": row.get("program_id"),
            "type": row.get("type"),
            "semester_year": row.get("semester_year"),
            "source": row.get("source"),
            "problem_analysis": row.get("problem_analysis"),
            "action_plans": row.get("actions_plans"),
            "status_actions": row.get("status_actions"),
            "result": row.get("result")
        }

        assessment_adhoc.append((item))
    try:
        profile_rows = criterion_cont_improv_data.get_improvement_underway(questionnaire)
    except Exception:  
        profile_rows = []

    # Build both a list of hardware assessments and a mapping grouped by semester_year
    assessment_improvement = []
    assessment_improvement_first = {}

    for row in profile_rows:
        if not isinstance(row, dict):
            continue

        item = {
            "improvement_id": row.get("improvement_id"),
            "program_id": row.get("program_id"),
            "type": row.get("type"),
            "semester_year": row.get("semester_year"),
            "source": row.get("source"),
            "problem_analysis": row.get("problem_analysis"),
            "action_plans": row.get("actions_plans"),
            "status_actions": row.get("status_actions"),
            "result": row.get("result")
        }
        course = item.get("program_id")
        #add one row to list if course is null, otherwise add to separate list for courses
        if course is None:
            assessment_improvement.append(item)

        # keep the first row as a flat dict for backward compatibility
        if not assessment_improvement_first and course is None:
            assessment_improvement_first = item

    #pg 56-62 table improvement courses
    try:
        profile_rows = criterion_cont_improv_data.get_improvement_underway(questionnaire)
    except Exception:
        profile_rows = []

    # Build both a list of hardware assessments and a mapping grouped by semester_year
 
    assessment_underway_courses = []


    for row in profile_rows:
        if not isinstance(row, dict):
            continue

        item = {
            "improvement_id": row.get("improvement_id"),
            "program_id": row.get("program_id"),
            "type": row.get("type"),
            "semester_year": row.get("semester_year"),
            "source": row.get("source"),
            "problem_analysis": row.get("problem_analysis"),
            "action_plans": row.get("actions_plans"),
            "status_actions": row.get("status_actions"),
            "result": row.get("result")
        }

     

        course = item.get("program_id")
        #add one row to list if course is null, otherwise add to separate list for courses
        if course is not None:
            assessment_underway_courses.append((item))




    context = {
        "outcome_assessment": outcome_assessment,
        "outcome_attainment_criteria": outcome_attainment_criteria,
        "outcome_met_percentages": outcome_met_percentages,
        "hardware_assessment": hardware_assessment_first,
        "hardware_assessment_list": hardware_assessment,
        "hardware_assessment_by_year": hardware_assessment_by_year,
        "hardware_assessment_new_course": hardware_assessment_new_course_first,
        "hardware_assessment_new_course_list": hardware_assessment_new_course,
        "hardware_assessment_obj": hardware_assessment_obj_first,
        "assessment_concentration_list": assessment_concentration,
        "assessment_concentration_update_flowchart_list": assessment_concentration_update_flowchart,
        "assessment_adhoc_list": assessment_adhoc,
        "assessment_improvement": assessment_improvement_first,
        "assessment_underway_courses": assessment_underway_courses,
        "assessment_underway_courses_list": assessment_underway_courses
    }
    return context

if __name__ == "__main__":
    print("Testing criterion_cont_improv.py")

    from report.questionnaire import Questionnaire
    from getdatabaseConnection import get_database_connection

    questionnaire = Questionnaire(
        template_path="report_generation_api/report/templates/template.docx",
        db=get_database_connection(),
        year=2026,
        department="Computer System Engineering",
        degree_type="Bachelor's"
    )
    student_data = criterion_cont_improv_data.get_data(questionnaire)
    peo_review = []

    for row in student_data:
        peo_review.append({
            "in_meth": row["input_method"],
            "sched": row["schedule"],
            "const": row["constituencies"]
        })

    context = {
        "peo_review": peo_review
    }
        # Fetch and attach student profile info
    try:
        profile_data = criterion_cont_improv_data.get_outcome_assessment_info(questionnaire)
    except Exception:
        profile_data = []

    outcome_assessment = []
    for row in profile_data:


        outcome_assessment.append({
            "out_num": row["outcome_number"],
            "course_name": row["course_name"],
            "assess_method": row["assessment_method"]
        })

    context["outcome_assessment"] = outcome_assessment