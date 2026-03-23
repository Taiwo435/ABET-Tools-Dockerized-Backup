<?php

function loadFormData($pageName) {
    $path = getenv('ABET_PRIVATE_DIR') . '/testData/' . $pageName . '_data.json';

    if (!file_exists($path)) {
        return null;
    }

    $jsonData = json_decode(file_get_contents($path), true);

    if (!is_array($jsonData)) {
        return null;
    }

    return $jsonData;
}
?>