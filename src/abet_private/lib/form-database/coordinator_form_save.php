<?php
// Need to implement DB tables still
function handleSaveError($message) {
    $_SESSION['coordinator_form_error_flag'] = true;
    $_SESSION['coordinator_form_old'] = $_POST;
    $_SESSION['coordinator_form_error_message'] = $message;

    $pageNumber = $_POST['current_page_number'] ?? 1;
    $url = '/coordinator-form/edit/?page=' . $pageNumber;
    header('Location: ' . $url);
    die();
}

$jsonData = $_POST;

unset($jsonData['current_page_number']);
unset($jsonData['next_page_number']);

$pageName = $_POST['page_name'] ?? 'form_not_found';

switch ($pageName) {
case 'programSelect':
case 'background':
case 'educationalObjectives':
case 'generalCriteria':
case 'studentOutcomes':
case 'assessmentPlan':
case 'continuousImprovement':
case 'curriculum':
case 'equipment':
case 'facilities':
case 'faculty':
case 'institutionalSummary':
case 'staffing':
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