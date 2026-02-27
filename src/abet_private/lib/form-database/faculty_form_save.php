<?php
function handleSaveError($message) {
    $_SESSION['faculty_form_error_flag'] = true;
    $_SESSION['faculty_form_old'] = $_POST;
    $_SESSION['faculty_form_error_message'] = $message;
    
    $url = '/faculty-form/edit/?page=' . $_POST['current_page_number'];
    header('Location: ' . $url);
    die();
}

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
case 'qualifications':
    $pageName = 'qualifications';
    break;
case 'vitae':
    $pageName = 'vitae';
    break;
case 'workload':
    $pageName = 'workload';
    break;
}

$filePath = getenv('ABET_PRIVATE_DIR') . '/' . 'testData' . '/' . $pageName . "_data.json";
if (file_put_contents($filePath, $jsonString) !== false) {
    echo "Data has been successfully written to $filePath.";
} else {
    $errorMessage = "Error occurred while trying to write to the file: " . $filePath;
    handleSaveError($errorMessage);
}

?>