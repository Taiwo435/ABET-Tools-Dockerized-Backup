import json
import ast
from report.data import criterion_faculty_data

#using this to get certifcations too lazy to rebuild the query in criterion_faculty_data
from report.data import appendix_b_data

#converting letters into full rank names
_RANK_LABELS: dict[str, str] = {
    "P": "Professor",
    "ASC": "Associate Professor",
    "AST": "Assistant Professor",
    "I": "Instructor",
    "A": "Adjunct",
    "O": "Other",
}
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

        semesters = classes_taught["data"]["semesters"]

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
    #table 6-1
    # Fetch and attach faculty profile info (handle rows as tuples or dicts) 
    try:
        profile_rows = criterion_faculty_data.get_faculty_info(questionnaire)
    except Exception:
        profile_rows = []

    profile_list = []


    for row in profile_rows:
        if isinstance(row, dict):
            #this section gets rid of json formatting for certifications and converts into string
            certifications = appendix_b_data.json_to_list(row.get("certifications", "[]"))

            cert_list = []
            for c in certifications:

                parsed = ast.literal_eval(c)
                if isinstance(parsed, dict):
                    name = parsed.get("certification_registration_name", "") or ""
                    other = parsed.get("certification_registration_other_info", "") or ""
                    cert_list.append(f"{name} {other}")


            certifications_text = "\n".join(cert_list).strip() or "NA"


        profile_list.append({
            "name": row["name"],
            "status": row["pt_or_ft"],
            "highest_degree": row["highest_degree"],
            "faculty_rank": row["faculty_rank"],
            "academic_appointment": row["academic_appointment"],
            "faculty_id": row["faculty_id"],
            "certifications_text": certifications_text,
            "years_experience_gov_industry": row["years_experience_gov_industry"],
            "years_experience_teaching": row["years_experience_teaching"],
            "years_experience_institution": row["years_experience_institution"],
            "activity_prof_orgs": row["activity_prof_orgs"],
            "activity_prof_dev": row["activity_prof_dev"],
            "activity_consulting": row["activity_consulting"]
        })
    #faculty qualification table
    try:
        profile_info = criterion_faculty_data.get_more_faculty_info(questionnaire)
    except Exception:
        profile_info = []

    profile_info_list = []
    for row in profile_info:
        #based on appendix b which is getting rank full names 
        rank_code = (row.get("faculty_rank") or "").strip()
        rank = _RANK_LABELS.get(rank_code, rank_code or "N/A")

        profile_info_list.append({
            "name": row["name"],
            "faculty_rank": rank,
            "areas_of_interest": row["areas_of_interest"]

        })

    #gets number of faculty by rank
    try:
        profile_nums = criterion_faculty_data.get_faculty_nums(questionnaire)
    except Exception:
        profile_nums = []
    result = {}

    for row in profile_nums:
        result[row["faculty_rank"]] = row["num_faculty"]

    profile_nums_dict = {
        "professors": result.get("P", 0),
        "associate_professors": result.get("ASC", 0),
        "assistant_professors": result.get("AST", 0),
        "other": result.get("I", 0) + result.get("O", 0)+ result.get("A", 0),
    }
    profile_nums_list = []
    profile_nums_list.append(profile_nums_dict)
    # for row in profile_nums:


    context = {"faculty_profile": profile_list, "faculty_workload": faculty_list, "faculty_info": profile_info_list, "faculty_nums": profile_nums_list, "faculty_nums_dict": profile_nums_dict}
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

        semesters = classes_taught["data"]["semesters"]

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

     # Fetch and attach faculty profile info
    try:
        profile_info = criterion_faculty_data.get_faculty_info(questionnaire)
    except Exception:
        profile_info = []

    profile_info_list = []
    for row in profile_info:


        profile_info_list.append({
            "name": row["name"],
            "faculty_rank": row["faculty_rank"],
            "areas_of_interest": row["areas_of_interest"]

        })

    context["faculty_profile"] = profile_info_list

    try:
        profile_nums = criterion_faculty_data.get_more_faculty_info(questionnaire)
    except Exception:
        profile_nums = []

    profile_nums_list = []
    for row in profile_nums:


        profile_nums_list.append({
            "faculty_rank": row["faculty_rank"]

        })
    context["faculty_nums"] = profile_nums_list



    print("Context for criterion_faculty:")
    context = json.dumps(context, indent=4)  # Pretty-print the context dictionary
    print(context)


    
    