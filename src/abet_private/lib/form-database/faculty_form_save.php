<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/db.php';


/*
    Sets up an error message to be shown to the user, and logs an error message on the server.
    For unforseen errors such as an sql error, messages shown to the user should be generic.
    Stops execution of this file and redirects back to the edit page that was attempting to save.
    Args:
        $message (String): The error message that will be shown to the user.
        $loggedMessage (int): The error message that will be logged onto the backend.
    Returns:
        None
*/
function handleSaveError($message, $loggedMessage) {
    $_SESSION['faculty_form_error_flag'] = true;
    $_SESSION['faculty_form_old'] = $_POST;
    $_SESSION['faculty_form_error_message'] = $message;
    
    $url = '/faculty-form/edit/?page=' . $_POST['current_page_number'];
    header('Location: ' . $url);

    $loggedMessageFull = "FACULTY FORM SAVE ERROR FOR USER WITH ID " . $_SESSION['user_id'] . ": " . $loggedMessage;
    error_log($loggedMessageFull);
    die();
}



$pdo = db();

// A generic message to show to the user in case of unforseen errors.
$genericErrorMessage = "Something went wrong while saving the form. Please contact sdosburn@asu.edu if the problem persists.";

$jsonData = $_POST;
$jsonString = json_encode($jsonData, JSON_PRETTY_PRINT);

switch ($_POST['page_name']) {
case 'info':
    // If the program doesn't exist in the table, add it and get the id. If it does exist, just get the id.
    $program_id = null;
    try {
        //[0] => program_name
        //[1] => program_code
        $department = explode('-', $_POST['department']);
        if (count($department) != 2) {
            $message = "Department field must be in the format '[ProgramName]-[ProgramCode]'. Currently, it is '" . $_POST['department'] . "'.";
            handleSaveError($message, $message);
            die();
        }

        // Checks if there are any existing programs with the same name and code, if not returns null
        $stmt = $pdo->prepare("SELECT program_id FROM programs 
            WHERE program_name = :program_name AND program_code = :program_code");
        $stmt->execute([
            'program_name' => $department[0],
            'program_code' => $department[1]
        ]);
        $result = $stmt->fetch();

        if (!$result) {
            $stmt = $pdo->prepare("INSERT INTO programs (program_name, program_code)
            VALUES (:program_name, :program_code)");
            $stmt->execute([
                'program_name' => $department[0],
                'program_code' => $department[1]
            ]);
            $program_id = $pdo->lastInsertId();
        } else {
            $program_id = $result['program_id'];
        }
    } catch(PDOException $e) {
        handleSaveError($genericErrorMessage, $e->getMessage());
        die();
    }

    
    $fields = [
        'user_id' => $_SESSION['user_id'],
        'program_id' => $program_id,
        'first_name' => $_POST['first_name'],
        'last_name' => $_POST['last_name'],
        'highest_degree' => $_POST['highest_degree'],
        'asurite' => strtolower($_POST['asurite']),
        'areas_of_interest' => $_POST['areas_of_interest'],
        'faculty_rank' => ($_POST['faculty_rank'] !== '') ? $_POST['faculty_rank'] : 'O',
        'academic_appointment' => ($_POST['academic_appointment'] !== '') ? $_POST['academic_appointment'] : 'NTT',
        'years_experience_gov_industry' => $_POST['years_experience_gov_industry'],
        'years_experience_teaching' => $_POST['years_experience_teaching'],
        'years_experience_institution' => $_POST['years_experience_institution'],
        'activity_prof_orgs' => ($_POST['activity_prof_orgs'] !== '') ? $_POST['activity_prof_orgs'] : 'NA',
        'activity_prof_dev' => ($_POST['activity_prof_dev'] !== '') ? $_POST['activity_prof_dev'] : 'NA',
        'activity_consulting' => ($_POST['activity_consulting'] !== '') ? $_POST['activity_consulting'] : 'NA'
    ];

    $columns = implode(", ", array_keys($fields));
    $placeholders = ':' . implode(", :", array_keys($fields));

    $updateFields = array_filter(
        array_keys($fields),
        fn($field) => $field !== 'user_id'
    );

    $updateClause = implode(", ", array_map(
        fn($field) => "$field = VALUES($field)", 
        $updateFields)
    );

    try {
        $stmt = $pdo->prepare("
            INSERT INTO faculty_info ($columns) VALUES ($placeholders)
            ON DUPLICATE KEY UPDATE $updateClause
        ");
        $stmt->execute($fields);
    } catch (PDOException $e) {
        handleSaveError($genericErrorMessage, $e->getMessage());
        die();
    }
    
    break;
case 'vitae':
    $fields = [
        'user_id' => $_SESSION['user_id'],
        'education' => $_POST['education'],
        'academic_experience' => $_POST['academic_experience'],
        'non_academic_experience' => $_POST['non_academic_experience'],
        'certifications' => $_POST['certifications'],
        'professional_memberships' => $_POST['professional_memberships'],
        'honors_and_awards' => $_POST['honors_and_awards'],
        'service_activities' => $_POST['service_activities'],
        'publications_presentations' => $_POST['publications_presentations'],
        'professional_development' => $_POST['professional_development']
    ];

    $columns = implode(", ", array_keys($fields));
    $placeholders = ':' . implode(", :", array_keys($fields));

    $updateFields = array_filter(
        array_keys($fields),
        fn($field) => $field !== 'user_id'
    );

    $updateClause = implode(", ", array_map(
        fn($field) => "$field = VALUES($field)", 
        $updateFields)
    );

    try {
        $stmt = $pdo->prepare("
            INSERT INTO faculty_vitae ($columns) VALUES ($placeholders)
            ON DUPLICATE KEY UPDATE $updateClause
        ");
        $stmt->execute($fields);
    } catch (PDOException $e) {
        handleSaveError($genericErrorMessage, $e->getMessage());
        die();
    }
    
    break;
case 'workload':
    $current_academic_year = 'S26';

    $teaching_pct = str_replace('%', '', $_POST['teaching_pct']);
    $research_or_scholarship_pct = str_replace('%', '', $_POST['research_or_scholarship_pct']);
    $other_pct = str_replace('%', '', $_POST['other_pct']);
    $pct_time_devoted_to_program = str_replace('%', '', $_POST['pct_time_devoted_to_program']);

    $percentSum = $teaching_pct + $research_or_scholarship_pct + $other_pct;
    if ($percentSum != 100) {
        $message = "Teaching, Research/Service, and Other Work Percentages should sum to 100%. Currently, they sum to " . $percentSum . "%.";
        handleSaveError($message, $message);
        die();
    }

    if ($pct_time_devoted_to_program > 100) {
        $message = "Percentage of time devoted to program cannot exceed 100. Currently, it is " . $pct_time_devoted_to_program . "%.";
        handleSaveError($message, $message);
        die();
    }

    $fields = [
        'user_id' => $_SESSION['user_id'],
        'academic_year' => $current_academic_year,
        'pt_or_ft' => ($_POST['pt_or_ft'] !== '') ? $_POST['pt_or_ft'] : 'FT',
        'classes_taught' => json_encode([]), // Placeholder value, Information will be pulled automatically from a script instead
        'teaching_pct' => $teaching_pct,
        'research_or_scholarship_pct' => $research_or_scholarship_pct,
        'other_pct' => $other_pct,
        'pct_time_devoted_to_program' => $pct_time_devoted_to_program
    ];

    $columns = implode(", ", array_keys($fields));
    $placeholders = ':' . implode(", :", array_keys($fields));

    $updateFields = array_filter(
        array_keys($fields),
        fn($field) => $field !== 'user_id' && $field !== 'classes_taught'
    );

    $updateClause = implode(", ", array_map(
        fn($field) => "$field = VALUES($field)", 
        $updateFields)
    );

    try {
        $stmt = $pdo->prepare("
            INSERT INTO faculty_workload ($columns) VALUES ($placeholders)
            ON DUPLICATE KEY UPDATE $updateClause
        ");
        $stmt->execute($fields);
    } catch (PDOException $e) {
        handleSaveError($e->getMessage(), $e->getMessage());
        die();
    }
    break;
    
default:
    $message = "Page " . $_POST['page_name'] . " not recognized.";
    handleSaveError($genericErrorMessage, $message);
    break;
}
