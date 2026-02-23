<?php

function loadForm($formName) {
    $path = __DIR__ . "/../forms/$formName.json";

    if (!file_exists($path)) {
        throw new Exception("Form not found.");
    }

    return json_decode(file_get_contents($path), true);
}

function validateForm($form, $data) {
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
