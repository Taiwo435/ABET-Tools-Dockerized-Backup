<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/form_functions.php';

$form = loadForm('testform');
$errors = [];
$old = [];


require_once getenv('ABET_PRIVATE_DIR') . '/lib/templates/primary-header.php';


include(getenv('ABET_PRIVATE_DIR') . '/lib/templates/form-template.php');



require_once getenv('ABET_PRIVATE_DIR') . '/lib/templates/primary-footer.php';
?>
