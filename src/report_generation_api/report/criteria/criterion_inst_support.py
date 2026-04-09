from report.data import criterion_inst_support_data


def build(questionnaire):

    try:
        profile_rows = criterion_inst_support_data.get_scai_staff(questionnaire)
    except Exception:
        profile_rows = []

    scai_staff_list = []
    for row in profile_rows:
        if isinstance(row, dict):


            scai_staff_list.append({
                "function_description": row["function_description"],
                "manager": row["manager"],
                "staff_size": row["staff_size"]
            })
    context = {"scai_staff": scai_staff_list}
    return context