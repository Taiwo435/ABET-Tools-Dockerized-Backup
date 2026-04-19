from report.data import criterion_facilities_data


def build(questionnaire):

    try:
        profile_rows = criterion_facilities_data.get_facilities_data(questionnaire)
    except Exception:
        profile_rows = []

    facilities_list = []
    for row in profile_rows:
        if isinstance(row, dict):


            facilities_list.append({
                "bldg_code": row["bldg_code"],
                "room_number": row["room_number"],
                "capacity": row["capacity"],
                "use_description": row["use_description"],
                "zoom_level": row["zoom_level"]
            })
    context = {"facilities": facilities_list}
    return context