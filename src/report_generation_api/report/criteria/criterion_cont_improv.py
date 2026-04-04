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

    context = {"outcome_assessment": outcome_assessment, "outcome_attainment_criteria": outcome_attainment_criteria}
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