<?php
require_once $_ENV['ABET_PRIVATE_DIR'] . '/lib/form_functions.php';

$form = loadForm('testform');
$errors = [];
$old = [];


require_once $_ENV['ABET_PRIVATE_DIR'] . '/lib/templates/primary-header.php';


include($_ENV['ABET_PRIVATE_DIR'] . '/lib/templates/form-template.php');



require_once $_ENV['ABET_PRIVATE_DIR'] . '/lib/templates/primary-footer.php';
?>
