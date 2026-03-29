<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/form-database/coordinator_form_load.php';

$formName = "coordinator-form";
$formBasePath = "/coordinator-form";
$reviewTitle = "Coordinator Form Review";
$reviewCssPath = "/assets/css/faculty-form-review.css";

require_once getenv('ABET_PRIVATE_DIR') . '/lib/templates/form-review-template.php';
?>