from report.data import criterion_cont_improv_data

def build(questionnaire):
    #table 4-1
    try:
        profile_rows = criterion_cont_improv_data.get_outcome_assessment_info(questionnaire)
    except Exception:
        profile_rows = []

    #outcomes are fixed at 7 for now probably will change it in the future
    all_outcomes = ["1", "2", "3", "4", "5", "6", "7"]
    all_programs = []
    all_course_names = []
    outcome_assessment = []
    data_map = {}

    for row in profile_rows:
        if isinstance(row, dict):
            out_num = str(row["outcome_number"]).strip()
            course_name = str(row["course_name"]).strip()
            assess_method = str(row["assessment_method"]).strip()
            program_name = str(row["program_name"]).strip()

        if program_name and program_name not in all_programs:
            all_programs.append(program_name)

        if assess_method:
            key = (out_num, program_name)
            if key in data_map:
                existing = []
                for m in data_map[key].split(","):
                    existing.append(m.strip())
                if assess_method not in existing:
                    data_map[key] = data_map[key] + ", " + assess_method
            else:
                data_map[key] = assess_method

        if course_name and course_name not in all_course_names:
            all_course_names.append(course_name)
            outcome_assessment.append({
                "out_num": out_num,
                "course_name": course_name,
                "assess_method": assess_method,
                "program_name": program_name
            })

    #this section builds the 2d matrix for assessment outcomes table
    matrix_data = []
    for outcome in all_outcomes:
        row_data = []
        for program in all_programs:
            row_data.append(data_map.get((outcome, program), ""))
        matrix_data.append(row_data)

    row_colors = ['D9D2E9', 'FFFFFF']
    rows_with_data = []
    for i in range(len(all_outcomes)):
        rows_with_data.append({
            "row_label": all_outcomes[i],
            "cells": matrix_data[i],
            "row_color": row_colors[i % 2]
        })

    outcome_assessment_matrix = {
        "rows": all_outcomes,
        "columns": all_programs,
        "data": matrix_data,
        "rows_with_data": rows_with_data
    }

    #table 4-2
    try:
        attainment_profile_rows = criterion_cont_improv_data.get_outcome_attainment_level(questionnaire)
    except Exception:
        attainment_profile_rows = []

    #outcomes are fixed at 7 for now probably will change it in the future
    attainment_all_outcomes = ["1", "2", "3", "4", "5", "6", "7"]
    attainment_all_programs = []
    attainment_all_course_names = []
    outcome_attainment = []
    attainment_data_map = {}

    for row in attainment_profile_rows:
        if isinstance(row, dict):
            out_num = str(row["outcome_number"]).strip()
            course_name = str(row["course_name"]).strip()
            attainment_level = str(row["attainment_level"]).strip()
            program_name = str(row["program_name"]).strip()

        if program_name and program_name not in attainment_all_programs:
            attainment_all_programs.append(program_name)

        if attainment_level:
            key = (out_num, program_name)
            if key in attainment_data_map:
                existing = []
                for m in attainment_data_map[key].split(","):
                    existing.append(m.strip())
                if attainment_level not in existing:
                    attainment_data_map[key] = attainment_data_map[key] + ", " + attainment_level
            else:
                attainment_data_map[key] = attainment_level

        if course_name and course_name not in attainment_all_course_names:
            attainment_all_course_names.append(course_name)
            outcome_attainment.append({
                "out_num": out_num,
                "course_name": course_name,
                "attainment_level": attainment_level,
                "program_name": program_name
            })

    #this section builds the 2d matrix for attainment level table
    attainment_matrix_data = []
    for outcome in attainment_all_outcomes:
        row_data = []
        for program in attainment_all_programs:
            row_data.append(attainment_data_map.get((outcome, program), ""))
        attainment_matrix_data.append(row_data)

    attainment_row_colors = ['D9D2E9','FFFFFF']
    attainment_rows_with_data = []
    for i in range(len(attainment_all_outcomes)):
        attainment_rows_with_data.append({
            "row_label": attainment_all_outcomes[i],
            "cells": attainment_matrix_data[i],
            "row_color": attainment_row_colors[i % 2]
        })

    outcome_attainment_matrix = {
        "rows": attainment_all_outcomes,
        "columns": attainment_all_programs,
        "data": attainment_matrix_data,
        "rows_with_data": attainment_rows_with_data
    }

    #table 4-3
    try:
        summary_profile_rows = criterion_cont_improv_data.get_assessment_summary(questionnaire)
    except Exception:
        summary_profile_rows = []

    #outcomes are fixed at 7 for now probably will change it in the future
    summary_all_outcomes = ["1", "2", "3", "4", "5", "6", "7"]
    summary_all_programs = []
    summary_all_semesters = []
    outcome_assessment_sum = []
    summary_data_map = {}

    for row in summary_profile_rows:
        if isinstance(row, dict):
            out_num = str(row["outcome_number"]).strip()
            semester = str(row["semester"]).strip()
            result = str(row["result"]).strip()
            

        if semester and semester not in summary_all_programs:
            summary_all_programs.append(semester)

        if result:
            key = (out_num, semester)
            if key in summary_data_map:
                existing = []
                for m in summary_data_map[key].split(","):
                    existing.append(m.strip())
                if result not in existing:
                    summary_data_map[key] = summary_data_map[key] + ", " + result
            else:
                summary_data_map[key] = result

        if semester and semester not in summary_all_semesters:
            summary_all_semesters.append(semester)
            outcome_assessment_sum.append({
                "out_num": out_num,
                "semester": semester,
                "result": result,
                
            })

    #sort semesters chronologically: S (Spring) before F (Fall) within same year
    def semester_sort_key(s):
        season = s[0].upper() if s else 'X'
        try:
            year = int(s[1:])
        except ValueError:
            year = 0
        season_order = 0 if season == 'S' else 1
        return (year, season_order)

    def outcome_sum_sort_key(x):
        return semester_sort_key(x["semester"])

    summary_all_semesters.sort(key=semester_sort_key)
    summary_all_programs.sort(key=semester_sort_key)
    outcome_assessment_sum.sort(key=outcome_sum_sort_key)

    #this section builds the 2d matrix for assessment summary table
    summary_matrix_data = []
    for outcome in summary_all_outcomes:
        row_data = []
        for program in summary_all_programs:
            row_data.append(summary_data_map.get((outcome, program), ""))
        summary_matrix_data.append(row_data)

    summary_rows_with_data = []
    for i in range(len(summary_all_outcomes)):
        summary_rows_with_data.append({
            "row_label": summary_all_outcomes[i],
            "cells": summary_matrix_data[i]
        })

    outcome_assessment_sum_matrix = {
        "rows": summary_all_outcomes,
        "columns": summary_all_programs,
        "data": summary_matrix_data,
        "rows_with_data": summary_rows_with_data
    }

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
        "outcome_attainment": outcome_attainment,
        "course_name": all_course_names,
        "outcome_attainment_matrix": outcome_attainment_matrix,
        "outcome_assessment_matrix": outcome_assessment_matrix,
        "outcome_assessment_sum": outcome_assessment_sum,
        "outcome_assessment_sum_matrix": outcome_assessment_sum_matrix,
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
        if isinstance(row, dict):
            outcome_assessment.append({
                "out_num": row["outcome_number"],
                "course_name": row["course_name"],
                "assess_method": row["assessment_method"]
            })
        else:
            # Handle tuple format
            outcome_assessment.append({
                "out_num": row[0] if len(row) > 0 else "",
                "course_name": row[1] if len(row) > 1 else "",
                "assess_method": row[2] if len(row) > 2 else ""
            })

    context["outcome_assessment"] = outcome_assessment