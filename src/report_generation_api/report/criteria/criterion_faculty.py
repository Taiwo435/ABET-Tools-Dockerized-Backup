import json
from report.data import criterion_faculty_data

def build(questionnaire):

    faculty_data = criterion_faculty_data.get_data(questionnaire)
    #print(faculty_data)
    faculty_list = []

    for row in faculty_data:
        #print(row["first_name"])
        #print(row["last_name"])
        #print(row["pt_or_ft"])
        #print(row["teaching_pct"])
        #print(row["research_or_scholarship_pct"])
        #print(row["other_pct"])
        #print(row["pct_time_devoted_to_program"])

        classes_taught = json.loads(row["classes_taught"])
        #print(f"Classes Taught: {classes_taught}")

        semesters = classes_taught[0]["data"]["semesters"]

        #String used to capture taught classes
        classes = ""

        current_year = questionnaire.year


        for semester in semesters:

            #print(f"Semester: {int(semester['semester'][:4])} Current Year: {current_year}")

            if(int(semester["semester"][:4]) <= current_year and int(semester["semester"][:4]) >= current_year - 1):
                    

                classes += semester["semester"] + ": "

                for course in semester["courses"]:

                    if(course["amountTaught"] > 1):
                        classes += f"{course['course']}({course['units']})x{course['amountTaught']}, "
                    else:
                        classes += f"{course['course']}({course['units']}), "

                    #print(course)
                #print(semester["semester"])
                classes = classes[:-1] #Remove the last comma
                classes = classes + "\a" #Add a newline character for separation between semesters
                

        faculty_list.append({
            "name": f"{row['first_name']} {row['last_name']}",
            "status": row["pt_or_ft"],
            "classes_taught": classes,
            "teaching": row["teaching_pct"],
            "research": row["research_or_scholarship_pct"],
            "other": row["other_pct"],
            "percent_time": row["pct_time_devoted_to_program"]
        })

    # context = {
    #     "faculty_workload": faculty_list
    # }
    # Fetch and attach faculty profile info (handle rows as tuples or dicts)
    try:
        profile_rows = criterion_faculty_data.get_faculty_info(questionnaire)
    except Exception:
        profile_rows = []

    profile_list = []
    for row in profile_rows:
        if isinstance(row, dict):


            profile_list.append({
                "name": row["name"],
                "status": row["pt_or_ft"],
                "highest_degree": row["highest_degree"],
                "faculty_rank": row["faculty_rank"],
                "academic_appointment": row["academic_appointment"],
                "faculty_id": row["faculty_id"],
                "years_experience_gov_industry": row["years_experience_gov_industry"],
                "years_experience_teaching": row["years_experience_teaching"],
                "years_experience_institution": row["years_experience_institution"],
                "activity_prof_orgs": row["activity_prof_orgs"],
                "activity_prof_dev": row["activity_prof_dev"],
                "activity_consulting": row["activity_consulting"]
            })
    context = {"faculty_profile": profile_list, "faculty_workload": faculty_list}
    return context

if __name__ == "__main__":
    print("Testing criterion_faculty.py")

    from report.questionnaire import Questionnaire
    from getdatabaseConnection import get_database_connection

    questionnaire = Questionnaire(
        template_path="report_generation_api/report/templates/template.docx",
        db=get_database_connection(),
        year=2026,
        department="Computer System Engineering",
        degree_type="Bachelor's"
    )

    faculty_data = criterion_faculty_data.get_data(questionnaire)
    #print(faculty_data)
    faculty_list = []

    for row in faculty_data:
        #print(row["first_name"])
        #print(row["last_name"])
        #print(row["pt_or_ft"])
        #print(row["teaching_pct"])
        #print(row["research_or_scholarship_pct"])
        #print(row["other_pct"])
        #print(row["pct_time_devoted_to_program"])

        classes_taught = json.loads(row["classes_taught"])
        #print(f"Classes Taught: {classes_taught}")

        semesters = classes_taught[0]["data"]["semesters"]

        #String used to capture taught classes
        classes = ""

        current_year = questionnaire.year


        for semester in semesters:

            #print(f"Semester: {int(semester['semester'][:4])} Current Year: {current_year}")

            if(int(semester["semester"][:4]) <= current_year and int(semester["semester"][:4]) >= current_year - 1):
                    

                classes += semester["semester"] + ": "

                for course in semester["courses"]:

                    if(course["amountTaught"] > 1):
                        classes += f"{course['course']}({course['units']})x{course['amountTaught']}, "
                    else:
                        classes += f"{course['course']}({course['units']}), "

                    #print(course)
                #print(semester["semester"])
                classes = classes[:-1] #Remove the last comma
                classes = classes + "\a" #Add a newline character for separation between semesters
                

        faculty_list.append({
            "name": f"{row['first_name']} {row['last_name']}",
            "status": row["pt_or_ft"],
            "classes_taught": classes,
            "teaching": row["teaching_pct"],
            "research": row["research_or_scholarship_pct"],
            "other": row["other_pct"],
            "percent_time": row["pct_time_devoted_to_program"]
        })

    context = {
        "faculty_workload": faculty_list
    }
        # Fetch and attach faculty profile info
    try:
        profile_data = criterion_faculty_data.get_faculty_info(questionnaire)
    except Exception:
        profile_data = []

    profile_list = []
    for row in profile_data:


        profile_list.append({
            "name": row["name"],
            "status": row["pt_or_ft"],
            "highest_degree": row["highest_degree"],
            "faculty_rank": row["faculty_rank"],
            "academic_appointment": row["academic_appointment"],
            "faculty_id": row["faculty_id"],
            "years_experience_gov_industry": row["years_experience_gov_industry"],
            "years_experience_teaching": row["years_experience_teaching"],
            "years_experience_institution": row["years_experience_institution"],
            "activity_prof_orgs": row["activity_prof_orgs"],
            "activity_prof_dev": row["activity_prof_dev"],
            "activity_consulting": row["activity_consulting"]
        })

    context["faculty_profile"] = profile_list



    print("Context for criterion_faculty:")
    context = json.dumps(context, indent=4)  # Pretty-print the context dictionary
    print(context)


    
    