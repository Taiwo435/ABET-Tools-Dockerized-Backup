<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/form-database/faculty_form_load.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/security_headers.php'; 


$formName = "faculty-form";
$formBasePath = "/faculty-form";
$reviewTitle = "Faculty Form Review";
$reviewCssPath = "/assets/css/faculty-form-review.css";

require_once getenv('ABET_PRIVATE_DIR') . '/lib/templates/form-review-template.php';
?>