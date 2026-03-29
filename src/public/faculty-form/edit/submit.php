<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/templates/auth-handler.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/security_headers.php'; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /faculty-form');
    die("Invalid request.");
}

require_once getenv('ABET_PRIVATE_DIR') . '/lib/form-database/faculty_form_save.php';

// Moves form to the specified next page after saving
header('Location: /faculty-form/edit/?page=' . $_POST['next_page_number']);

?>
