<?php

require_once getenv('ABET_PRIVATE_DIR') . '/lib/db.php';

/*
    Loads data from the SQL db for a specific page.
    Args:
        $pageName (String): The name of the page.
    Returns:
        (Object): The data loaded from the table.
*/
function loadFormData ($pageName) {

$pdo = db();

$formData = [];
switch ($pageName) {
case "info":
    try {
        // Checks if the data exists, if not returns null
        $stmt = $pdo->prepare("SELECT * FROM faculty_info WHERE user_id = :user_id LIMIT 1");
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
        $faculty_info = $stmt->fetch();

        if (!$faculty_info) {
            return null;
        }

        // Loads the data from the table into a JSON
        $formData['first_name'] = $faculty_info['first_name'];
        $formData['last_name'] = $faculty_info['last_name'];
        $formData['highest_degree'] = $faculty_info['highest_degree'];
        $formData['asurite'] = $faculty_info['asurite'];
        $formData['department_other'] = "*PLACEHOLDER- department functionality to be added*"; // Fix
        $formData['areas_of_interest'] = $faculty_info['areas_of_interest'];
        $formData['faculty_rank'] = $faculty_info['faculty_rank'];
        $formData['academic_appointment'] = $faculty_info['academic_appointment'];
        $formData['years_experience_gov_industry'] = (string) $faculty_info['years_experience_gov_industry'];
        $formData['years_experience_teaching'] = (string) $faculty_info['years_experience_teaching'];
        $formData['years_experience_institution'] = (string) $faculty_info['years_experience_institution'];
        $formData['activity_prof_orgs'] = $faculty_info['activity_prof_orgs'];
        $formData['activity_prof_dev'] = $faculty_info['activity_prof_dev'];
        $formData['activity_consulting'] = $faculty_info['activity_consulting'];

        $program_id = $faculty_info['program_id'];
        $stmt = $pdo->prepare("SELECT program_name, program_code FROM programs WHERE program_id = :program_id");
        $stmt->execute([':program_id' => $program_id]);
        $program = $stmt->fetch();
        if ($program) {
            $formData['department'] = $program['program_name'] . "-" . $program['program_code'];
        } else {
            $formData['department'] = null;
        }

    } catch (PDOException $e) {
        print_r($e);
        return null;
    }

    break;
case "vitae":
    try {
        // Checks if the data exists, if not returns null
        $stmt = $pdo->prepare("SELECT * FROM faculty_vitae WHERE user_id = :user_id LIMIT 1");
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
        $faculty_vitae = $stmt->fetch();

        if (!$faculty_vitae) {
            return null;
        }

        // Loads the data from the table into a JSON
        $formData['education'] = $faculty_vitae['education'];
        $formData['academic_experience'] = $faculty_vitae['academic_experience'];
        $formData['non_academic_experience'] = $faculty_vitae['non_academic_experience'];
        $formData['certifications'] = $faculty_vitae['certifications'];
        $formData['professional_memberships'] = $faculty_vitae['professional_memberships'];
        $formData['honors_and_awards'] = $faculty_vitae['honors_and_awards'];
        $formData['service_activities'] = $faculty_vitae['service_activities'];
        $formData['publications_presentations'] = $faculty_vitae['publications_presentations'];
        $formData['professional_development'] = $faculty_vitae['professional_development'];

    } catch (PDOException $e) {
        print_r($e);
        return null;
    }
    break;
case "workload":

    try {
        // Checks if the data exists, if not returns null
        $stmt = $pdo->prepare("SELECT * FROM faculty_workload WHERE user_id = :user_id LIMIT 1");
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
        $faculty_workload = $stmt->fetch();

        if (!$faculty_workload) {
            return null;
        }

        // Loads the data from the table into a JSON
        $formData['academic_year'] = $faculty_workload['academic_year'];
        $formData['pt_or_ft'] = $faculty_workload['pt_or_ft'];
        $formData['classes_taught'] = $faculty_workload['classes_taught'];
        $formData['teaching_pct'] = (string) $faculty_workload['teaching_pct'];
        $formData['research_or_scholarship_pct'] = (string) $faculty_workload['research_or_scholarship_pct'];
        $formData['other_pct'] = (string) $faculty_workload['other_pct'];
        $formData['pct_time_devoted_to_program'] = (string) $faculty_workload['pct_time_devoted_to_program'];
    } catch (PDOException $e) {
        print_r($e);
        return null;
    }

    break;
}

return $formData;

}

