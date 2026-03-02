<?php

function getPageNameFromNumber($formName, $pageNumber){

    $path = getenv('ABET_PRIVATE_DIR') . "/" . "forms" . "/" . $formName . "/" . "index.json";
    if (!file_exists($path)) {
        throw new Exception("Form not found.");
    }
    
    $form = json_decode(file_get_contents($path), true);

    if ($pageNumber < 1 || $pageNumber > getPageCount($formName)) {
        $pageNumber = 1;
    }

    return ($form['pages'])[$pageNumber - 1]['fileName'];
}

function allPagesDone($formName) {
    $path = getenv('ABET_PRIVATE_DIR') . "/" . "forms" . "/" . $formName . "/" . "index.json";
    if (!file_exists($path)) {
        throw new Exception("Form not found.");
    }
    
    $form = json_decode(file_get_contents($path), true);

    foreach ($form['pages'] as $page) {
        $path = getenv('ABET_PRIVATE_DIR') . "/" . "testData" . "/" . $page['fileName'] . "_data.json";
        if (!file_exists($path)) {
            return false;
        }
    }
    return true;
}

function getAllPageNames($formName) {
    $path = getenv('ABET_PRIVATE_DIR') . "/" . "forms" . "/" . $formName . "/" . "index.json";
    if (!file_exists($path)) {
        throw new Exception("Form not found.");
    }
    
    $form = json_decode(file_get_contents($path), true);

    $pageNames = [];
    foreach ($form['pages'] as $page) {
        array_push($pageNames, $page['fileName']);
    }
    return $pageNames;
}

function loadFormPage($formName, $pageName) {
    $path = getenv('ABET_PRIVATE_DIR') . "/" . "forms" . "/" . $formName . "/" . $pageName . ".json";

    if (!file_exists($path)) {
        throw new Exception("Form not found.");
    }

    return json_decode(file_get_contents($path), true);
}


function getPageCount($formName) {
    $path = getenv('ABET_PRIVATE_DIR') . "/" . "forms" . "/" . $formName . "/" . "index.json";
    if (!file_exists($path)) {
        throw new Exception("Form not found.");
    }
    $form = json_decode(file_get_contents($path), true);
    
    return count($form['pages']);
}

function validateFormPage($form, $data) {
    $errors = [];

    foreach ($form['fields'] as $field) {

        $name = $field['name'];
        $value = trim($data[$name] ?? '');

        if (!empty($field['required']) && $value === '') {
            $errors[$name] = $field['label'] . " is required.";
            continue;
        }

        if (!empty($field['minLength']) && strlen($value) < $field['minLength']) {
            $errors[$name] = $field['label'] . " must be at least " . $field['minLength'] . " characters.";
        }

        if ($field['type'] === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $errors[$name] = "Invalid email format.";
        }
    }

    return $errors;
}

?>