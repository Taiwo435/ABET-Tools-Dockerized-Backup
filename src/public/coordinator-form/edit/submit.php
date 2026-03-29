<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/templates/auth-handler.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /coordinator-form');
    die("Invalid request.");
}

require_once getenv('ABET_PRIVATE_DIR') . '/lib/form-database/coordinator_form_save.php';

header('Location: /coordinator-form/edit/?page=' . $_POST['next_page_number']);
die();
?>