from report.data import criterion_program_edu_obj_data

def build(questionnaire):

    try:
        profile_rows = criterion_program_edu_obj_data.get_peo_review_info(questionnaire)
    except Exception:
        profile_rows = []

    peo_review = []
    for row in profile_rows:
        if isinstance(row, dict):


            peo_review.append({
                "in_meth": row["input_method"],
                "sched": row["schedule"],
                "const": row["constituencies"]
            })
    context = {"peo_review": peo_review}
    return context

if __name__ == "__main__":
    print("Testing criterion_program_edu_obj.py")

    from report.questionnaire import Questionnaire
    from getdatabaseConnection import get_database_connection

    questionnaire = Questionnaire(
        template_path="report_generation_api/report/templates/template.docx",
        db=get_database_connection(),
        year=2026,
        department="Computer System Engineering",
        degree_type="Bachelor's"
    )
    student_data = criterion_program_edu_obj_data.get_data(questionnaire)
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
        profile_data = criterion_program_edu_obj_data.get_peo_review_info(questionnaire)
    except Exception:
        profile_data = []

    peo_review = []
    for row in profile_data:


        peo_review.append({
            "in_meth": row["input_method"],
            "sched": row["schedule"],
            "const": row["constituencies"]
        })

    context["peo_review"] = peo_review