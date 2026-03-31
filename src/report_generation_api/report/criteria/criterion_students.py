from report.data import criterion_students_data


def build(questionnaire):

    try:
        profile_rows = criterion_students_data.get_student_info(questionnaire)
    except Exception:
        profile_rows = []

    student_admissions_list = []
    for row in profile_rows:
        if isinstance(row, dict):


            student_admissions_list.append({
                "freshman": row["freshman"],
                "transfer_12": row["transfer_12_23"],
                "transfer_24_p": row["transfer_24_primary"],
                "transfer_24_s": row["transfer_24_secondary"]
            })
    context = {"student_admissions": student_admissions_list}
    return context

if __name__ == "__main__":
    print("Testing criterion_students.py")

    from report.questionnaire import Questionnaire
    from getdatabaseConnection import get_database_connection

    questionnaire = Questionnaire(
        template_path="report_generation_api/report/templates/template.docx",
        db=get_database_connection(),
        year=2026,
        department="Computer System Engineering",
        degree_type="Bachelor's"
    )
    student_data = criterion_students_data.get_data(questionnaire)
    student_list = []

    for row in student_data:
        student_list.append({
            "freshman": row["freshman"],
            "transfer_12": row["transfer_12_23"],
            "transfer_24_p": row["transfer_24_primary"],
            "transfer_24_s": row["transfer_24_secondary"]
        })

    context = {
        "student_admissions": student_list
    }
        # Fetch and attach student profile info
    try:
        profile_data = criterion_students_data.get_student_info(questionnaire)
    except Exception:
        profile_data = []

    student_admissions_list = []
    for row in profile_data:


        student_admissions_list.append({
            "freshman": row["freshman"],
            "transfer_12": row["transfer_12_23"],
            "transfer_24_p": row["transfer_24_primary"],
            "transfer_24_s": row["transfer_24_secondary"]
        })

    context["student_admissions"] = student_admissions_list