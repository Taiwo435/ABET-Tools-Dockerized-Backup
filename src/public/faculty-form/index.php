<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/auth.php';
require_role('ROLE_FACULTY_FORM');

require_once getenv('ABET_PRIVATE_DIR') . '/lib/form-database/faculty_form_load.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/security_headers.php';


$formName = "faculty-form";
$formDisplayTitle = "Faculty Form";
$pageTitle = "Faculty Form";
$formBasePath = "/faculty-form";
$formCssPath = "/assets/css/faculty-form.css";

$completeMessage = "The form is complete. If necessary, you can edit your responses. Otherwise, you are done with this form and can safely navigate away from this page.";
$incompleteMessage = "Your form is not yet complete. Click \"Start / Continue\" or select a page to fill out the remaining sections.";

require_once getenv('ABET_PRIVATE_DIR') . '/lib/templates/form-page-select-template.php';
?>
