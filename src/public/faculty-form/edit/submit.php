<?php

// This line is needed so the php session can start and session variables can be set/accessed
require_once getenv('ABET_PRIVATE_DIR') . '/lib/templates/auth-handler.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /faculty-form');
    die("Invalid request.");
}

// Saves the POST data to the sql database
// If there is an error while saving, the file may itself quit and redirect rather than continuing through this file.
require_once getenv('ABET_PRIVATE_DIR') . '/lib/form-database/faculty_form_save.php';

// Moves form to the specified next page after saving
header('Location: /faculty-form/edit/?page=' . $_POST['next_page_number']);

