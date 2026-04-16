<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/templates/auth-handler.php'; // Needed here so session vars can be set/accessed

require_once getenv('ABET_PRIVATE_DIR') . '/lib/form_functions.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/form-database/faculty_form_load.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/security_headers.php'; 


$pageNumber = $_GET['page']; // pageNumber is 1-indexed
$formName = "faculty-form";

// Check if the page number is a valid integer
if (filter_var($pageNumber, FILTER_VALIDATE_INT) === false) {
    header('Location: /faculty-form/edit?page=1');
}

$pageNumber = (int) $pageNumber;

// If the form is past the last page, it will redirect to the review page
if ($pageNumber > getPageCount($formName)) {
    header('Location: /faculty-form/review');
    die();
}

// Check if the page number is within the page count range
if ($pageNumber < 1) {
    header('Location: /faculty-form/edit?page=1');
    die();
}

// Loads the name of the JSON file corresponding to its page order in the index.json file
$pageName = getPageNameFromNumber($formName, $pageNumber);

$form = loadFormPage($formName, $pageName);


$old; // The JSON data that will be autofilled onto the elements
$backendErrorMessage = ''; // The error message that will be displayed to the user, if there is one
// If there was an error saving, it will reload what was on the page before the save attempt
// Otherwise, it will attempt to load data from the SQL db
if (array_key_exists('faculty_form_error_flag', $_SESSION) && $_SESSION['faculty_form_error_flag'] === true) {
    $_SESSION['faculty_form_error_flag'] = false;
    $old = $_SESSION['faculty_form_old'];
    $backendErrorMessage = $_SESSION['faculty_form_error_message'];
} else {
    $old = loadFormData($pageName);
}

//print_r($old); // For debugging
?>



<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/templates/primary-header.php';
?>

<?php
include(getenv('ABET_PRIVATE_DIR') . '/lib/templates/form-template.php');
?>

<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/templates/primary-footer.php';
?>