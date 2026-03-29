<?php
// Need to implement DB tables still
function handleSaveError($message) {
    $_SESSION['coordinator_form_error_flag'] = true;
    $_SESSION['coordinator_form_old'] = $_POST;
    $_SESSION['coordinator_form_error_message'] = $message;
    
    $url = '/coordinator-form/edit/?page=' . $_POST['current_page_number'];
    header('Location: ' . $url);
    die();
}

$jsonData = $_POST;

unset($jsonData['current_page_number']);
unset($jsonData['next_page_number']);

$pageName = $_POST['page_name'] ?? 'form_not_found';

switch ($pageName) {
case 'background':
case 'educationalObjectives':
case 'generalCriteria':
case 'studentOutcomes':
case 'student_outcomes':
case 'assessment_plan':
case 'continuous_improvement':
case 'cse_elective_courses':
case 'appendix_c_equipment':
case 'computer_resources':
case 'faculty_qualifications':
case 'program_enrollment_and_degree_data':
    break;

default:
    handleSaveError("Page " . $pageName . " not recognized.");
    break;
}

$dir = getenv('ABET_PRIVATE_DIR') . '/testData';
if (!is_dir($dir)) {
    if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
        handleSaveError("Could not create test data directory.");
    }
}

$filePath = $dir . '/' . $pageName . '_data.json';

if (file_put_contents($filePath, json_encode($jsonData, JSON_PRETTY_PRINT)) === false) {
    handleSaveError("Error occurred while trying to write to the file: " . $filePath);
}
?>