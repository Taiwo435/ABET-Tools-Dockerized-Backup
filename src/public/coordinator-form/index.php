<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/form-database/coordinator_form_load.php';

$formName = "coordinator-form";
$formDisplayTitle = "Coordinator Form";
$pageTitle = "Coordinator Form";
$formBasePath = "/coordinator-form";
$formCssPath = "/assets/css/faculty-form.css";

$completeMessage = "The form is complete. If necessary, you can edit your responses. Otherwise, you are done with this form and can safely navigate away from this page.";
$incompleteMessage = "Your form is not yet complete. Click \"Start / Continue\" or select a page to fill out the remaining sections.";

require_once getenv('ABET_PRIVATE_DIR') . '/lib/templates/form-page-select-template.php';
?>