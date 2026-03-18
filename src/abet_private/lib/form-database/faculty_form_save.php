<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/db.php';

function handleSaveError($message) {
    $_SESSION['faculty_form_error_flag'] = true;
    $_SESSION['faculty_form_old'] = $_POST;
    $_SESSION['faculty_form_error_message'] = $message;
    
    $url = '/faculty-form/edit/?page=' . $_POST['current_page_number'];
    header('Location: ' . $url);
    die();
}

$pdo = db();


$jsonData = $_POST;
$jsonString = json_encode($jsonData, JSON_PRETTY_PRINT);

$formName = "";
$pageName = "form_not_found";

switch ($_POST['page_name']) {
case 'canvasSyllabus':
    $pageName = 'canvasSyllabus';
    break;
case 'canvasTokens':
    $pageName = 'canvasTokens';
    break;
case 'info':
    // If the program doesn't exist in the table, add it and get the id. If it does exist, just get the id.
    $program_id = null;
    try {
        //[0] => program_name
        //[1] => program_code
        $department = explode('-', $_POST['department']);
        if (count($department) != 2) {
            handleSaveError("Department field must be in the format '[ProgramName]-[ProgramCode]'. Currently, it is '" . $_POST['department'] . "'.");
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
        handleSaveError($e->getMessage());
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
        handleSaveError($e->getMessage());
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
        handleSaveError($e->getMessage());
        die();
    }
    
    break;
case 'workload':
    $teaching_pct = str_replace('%', '', $_POST['teaching_pct']);
    $research_or_scholarship_pct = str_replace('%', '', $_POST['research_or_scholarship_pct']);
    $other_pct = str_replace('%', '', $_POST['other_pct']);
    $pct_time_devoted_to_program = str_replace('%', '', $_POST['pct_time_devoted_to_program']);

    $percentSum = $teaching_pct + $research_or_scholarship_pct + $other_pct;
    if ($percentSum != 100) {
        handleSaveError("Teaching, Research/Service, and Other Work Percentages should sum to 100%. Currently, they sum to " . $percentSum . "%.");
        die();
    }

    if ($pct_time_devoted_to_program > 100) {
        handleSaveError("Percentage of time devoted to program cannot exceed 100. Currently, it is " . $pct_time_devoted_to_program . "%.");
        die();
    }

    $fields = [
        'user_id' => $_SESSION['user_id'],
        'academic_year' => "S26", // Fix
        'pt_or_ft' => ($_POST['pt_or_ft'] !== '') ? $_POST['pt_or_ft'] : 'FT',
        'classes_taught' => $_POST['classes_taught'],
        'teaching_pct' => $teaching_pct,
        'research_or_scholarship_pct' => $research_or_scholarship_pct,
        'other_pct' => $other_pct,
        'pct_time_devoted_to_program' => $pct_time_devoted_to_program
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
            INSERT INTO faculty_workload ($columns) VALUES ($placeholders)
            ON DUPLICATE KEY UPDATE $updateClause
        ");
        $stmt->execute($fields);
    } catch (PDOException $e) {
        handleSaveError($e->getMessage());
        die();
    }
    break;
    
default:
    handleSaveError("Page " . $_POST['page_name'] . " not recognized.");
    break;
}

/*$filePath = getenv('ABET_PRIVATE_DIR') . '/' . 'testData' . '/' . $pageName . "_data.json";
if (file_put_contents($filePath, $jsonString) !== false) {
    echo "Data has been successfully written to $filePath.";
} else {
    $errorMessage = "Error occurred while trying to write to the file: " . $filePath;
    handleSaveError($errorMessage);
}*/

?>