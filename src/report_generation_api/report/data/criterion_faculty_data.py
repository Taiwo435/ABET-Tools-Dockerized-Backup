def get_data(questionnaire):
    """
    Fetch data for the criterion_faculty section using the shared DB connection.
    """

    #Get the cursor from the shared database connection
    cursor = questionnaire.db.cursor()

    cursor.execute("""
        SELECT
            faculty_info.first_name,
            faculty_info.last_name,
            faculty_workload.pt_or_ft,
            faculty_workload.classes_taught,
            faculty_workload.teaching_pct,
            faculty_workload.research_or_scholarship_pct,
            faculty_workload.other_pct,
            faculty_workload.pct_time_devoted_to_program      
        FROM faculty_info
        INNER JOIN faculty_workload
            ON faculty_info.user_id = faculty_workload.user_id;
    """)

    faculty_data = cursor.fetchall()
    return faculty_data

def get_faculty_info(questionnaire):
    """
    Fetch basic faculty info (concatenated name and other profile fields).
    """

    cursor = questionnaire.db.cursor()

    cursor.execute("""
        SELECT CONCAT(fi.first_name, ' ', fi.last_name) AS name,
            fw.pt_or_ft,
            fi.highest_degree,
            fi.faculty_rank,
            fi.academic_appointment,
            fi.faculty_id,
            fv.certifications,
            fi.years_experience_gov_industry,
            fi.years_experience_teaching,
            fi.years_experience_institution,
            fi.activity_prof_orgs,
            fi.activity_prof_dev,
            fi.activity_consulting
        FROM faculty_info fi
        LEFT JOIN faculty_workload fw
            ON fi.user_id = fw.user_id
        LEFT JOIN faculty_vitae fv
            ON fi.user_id = fv.user_id;""")

    return cursor.fetchall()

def get_more_faculty_info(questionnaire):
    """
    Fetch basic faculty info (concatenated name and other profile fields).
    """

    cursor = questionnaire.db.cursor()

    cursor.execute("""
        SELECT CONCAT(first_name, ' ', last_name) AS name,
            faculty_rank,
            areas_of_interest
        FROM faculty_info;
                   """)

    return cursor.fetchall()

def get_faculty_nums(questionnaire):
    """
    Fetch amount of faculty members by rank.
    """

    cursor = questionnaire.db.cursor()

    cursor.execute("""
        SELECT faculty_rank, COUNT(*) AS num_faculty
        FROM faculty_info
        GROUP BY faculty_rank;
                   """)

    return cursor.fetchall()

if __name__ == "__main__":

    
    print("Testing criterion_faculty_data.py")

    from getdatabaseConnection import get_database_connection
    db = get_database_connection()

    cursor = db.cursor()

    cursor.execute("""
        SELECT
            faculty_info.first_name,
            faculty_info.last_name,
            faculty_workload.pt_or_ft,
            faculty_workload.teaching_pct,
            faculty_workload.research_or_scholarship_pct,
            faculty_workload.other_pct,
            faculty_workload.pct_time_devoted_to_program,
            faculty_workload.classes_taught
        FROM faculty_info
        INNER JOIN faculty_workload
            ON faculty_info.user_id = faculty_workload.user_id;
    """)

    faculty_data = cursor.fetchall()
    print(f"Retrieved {len(faculty_data)} faculty records:")
    for record in faculty_data:
        print(record)
    