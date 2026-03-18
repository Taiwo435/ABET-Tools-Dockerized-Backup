<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/templates/auth-handler.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/form_functions.php';
//require_once getenv('ABET_PRIVATE_DIR') . '/lib/form-database/coordinator_form_load.php';

$pageNumber = $_GET['page']; // pageNumber is 1-indexed
$formName = "coordinator-form";

//print_r(getAllPageNames($formName));

// Check if the page number is a valid integer
if (filter_var($pageNumber, FILTER_VALIDATE_INT) === false) {
    header('Location: /coordinator-form/edit?page=1');
}

$pageNumber = (int) $pageNumber;

// If the form is past the last page, it will redirect to the review page
if ($pageNumber > getPageCount($formName)) {
    header('Location: /coordinator-form/review');
    die();
}

// Check if the page number is within the page count range
if ($pageNumber < 1) {
    header('Location: /coordinator-form/edit?page=1');
    die();
}

// Loads the name of the JSON file corresponding to its page order in the index.json file
$pageName = getPageNameFromNumber($formName, $pageNumber);

$form = loadFormPage($formName, $pageName);


$old; // The JSON data that will be autofilled onto the elements
$backendErrorMessage = '';
if ($_SESSION['coordinator_form_error_flag'] === true) {
    $_SESSION['coordinator_form_error_flag'] = false;
    $old = $_SESSION['coordinator_form_old'];
    $backendErrorMessage = $_SESSION['coordinator_form_error_message'];
} else {
    //$old = loadFormData($pageName);
}

//print_r($old);
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