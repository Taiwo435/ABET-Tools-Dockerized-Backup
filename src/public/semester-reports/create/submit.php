<?php
/*header('Location: /semester-reports');

require_once getenv('ABET_PRIVATE_DIR') . '/lib/form_functions.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/security_headers.php';

$form = loadForm('testform');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request.");
}

$errors = validateForm($form, $_POST);

if (!empty($errors)) {
    $old = $_POST;
    #include(getenv('ABET_PRIVATE_DIR') . '/lib/templates/form-template.php');
    header('Location: /semester-reports/create');
    exit;
}*/

// process data here

header('Location: /semester-reports');
?>
