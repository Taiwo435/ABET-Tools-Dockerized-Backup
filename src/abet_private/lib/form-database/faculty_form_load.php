<?php
function loadFormData ($pageName) {

$path = getenv('ABET_PRIVATE_DIR') . "/" . "testData" . "/" . $pageName . "_data.json";
if (!file_exists($path)) {
    //echo "Debug info: FILE $path NOT FOUND";
    return null;
}

$jsonData = json_decode(file_get_contents($path), true);

$formData = $jsonData;
switch ($pageName) {
case "canvasSyllabus":
    $formData = $jsonData;
    break;
case "canvasTokens":
    $formData = $jsonData;
    break;
case "qualifications":
    $formData = $jsonData;
    break;
case "vitae":
    $formData = $jsonData;
    break;
case "workload":
    $formData = $jsonData;
    break;
}

return $formData;

}
?>