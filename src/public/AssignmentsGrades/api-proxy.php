<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/auth.php';
require_login();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once getenv('ABET_PRIVATE_DIR') . '/lib/csrf.php';

header('Content-Type: application/json; charset=utf-8');

// Helpers 

function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    $data['next_csrf'] = csrf_token('tool1_proxy');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function post_str(string $key): string {
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : '';
}

function validate_course_id(string $id, string $label): ?string {
    if ($id === '' || !preg_match('/^\d+$/', $id)) {
        return "$label must be a numeric Canvas Course ID.";
    }
    return null;
}

function api_base(string $service): string {
    $hosts = [
        'extraction'  => ['EXTRACTION_HOSTNAME', 'EXTRACTION_PORT', 'extraction_api', '8000'],
        'formatting'  => ['CANVAS_FORMATTING_HOSTNAME', 'CANVAS_FORMATTING_PORT', 'canvas_formatting', '8001'],
    ];
    [$hostEnv, $portEnv, $defaultHost, $defaultPort] = $hosts[$service];
    $host = getenv($hostEnv) ?: $defaultHost;
    $port = getenv($portEnv) ?: $defaultPort;
    return "http://{$host}:{$port}";
}

function curl_api(string $url, string $method, string $token, array $curlExtra = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'canvas-access-token: ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 600,
    ] + $curlExtra);

    $body     = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['ok' => false, 'status' => 502, 'body' => null, 'error' => $error];
    }

    $decoded = json_decode((string) $body, true);
    $ok      = $httpCode >= 200 && $httpCode < 300;
    return ['ok' => $ok, 'status' => $httpCode, 'body' => $decoded, 'error' => null];
}

//  Gate 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

$action = post_str('action');
if ($action === '') {
    json_response(['success' => false, 'message' => 'Missing action parameter.'], 400);
}

$allowed_actions = ['store-credentials', 'verify-course', 'run-pipeline'];
if (!in_array($action, $allowed_actions, true)) {
    json_response(['success' => false, 'message' => 'Unknown action.'], 400);
}

// CSRF validation (uses shared csrf.php library)
$csrf = post_str('csrf_token');
if (!csrf_validate($csrf, 'tool1_proxy')) {
    json_response(['success' => false, 'message' => 'Invalid or missing CSRF token.'], 403);
}

// Actions 

if ($action === 'store-credentials') {
    $token    = post_str('canvas_token');
    $sourceId = post_str('source_course_id');
    $destId   = post_str('dest_course_id');

    if ($token === '') {
        json_response(['success' => false, 'message' => 'Canvas token is required.'], 400);
    }

    $errors = array_filter([
        validate_course_id($sourceId, 'Source Course ID'),
        validate_course_id($destId, 'Destination Course ID'),
    ]);
    if ($errors) {
        json_response(['success' => false, 'message' => 'Validation failed.', 'errors' => array_values($errors)], 422);
    }

    $_SESSION['canvas_token']     = $token;
    $_SESSION['source_course_id'] = $sourceId;
    $_SESSION['dest_course_id']   = $destId;
    $_SESSION['token_stored_at']  = time();

    json_response(['success' => true, 'message' => 'Credentials stored.']);
}


if ($action === 'verify-course') {
    $token    = $_SESSION['canvas_token']     ?? '';
    $courseId = $_SESSION['source_course_id'] ?? '';

    if ($token === '' || $courseId === '') {
        json_response(['success' => false, 'message' => 'No credentials in session. Please connect first.'], 401);
    }

    // Token TTL — 30 minutes
    if (time() - ($_SESSION['token_stored_at'] ?? 0) > 1800) {
        unset($_SESSION['canvas_token'], $_SESSION['source_course_id'], $_SESSION['dest_course_id'], $_SESSION['token_stored_at']);
        json_response(['success' => false, 'message' => 'Session credentials expired. Please reconnect.'], 401);
    }

    $url    = api_base('extraction') . '/verify-course/' . urlencode($courseId);
    $result = curl_api($url, 'GET', $token);

    if (!$result['ok']) {
        $detail = $result['error'] ?? ($result['body']['detail'] ?? 'Verification failed.');
        json_response(['success' => false, 'message' => $detail], $result['status']);
    }

    json_response(['success' => true, 'course' => $result['body']]);
}


if ($action === 'run-pipeline') {
    set_time_limit(0); 

    $token    = $_SESSION['canvas_token']     ?? '';
    $sourceId = $_SESSION['source_course_id'] ?? '';
    $destId   = $_SESSION['dest_course_id']   ?? '';

    if ($token === '' || $sourceId === '' || $destId === '') {
        json_response(['success' => false, 'message' => 'No credentials in session. Please connect first.'], 401);
    }

    // Token TTL — 30 minutes
    if (time() - ($_SESSION['token_stored_at'] ?? 0) > 1800) {
        unset($_SESSION['canvas_token'], $_SESSION['source_course_id'], $_SESSION['dest_course_id'], $_SESSION['token_stored_at']);
        json_response(['success' => false, 'message' => 'Session credentials expired. Please reconnect.'], 401);
    }

    if (!empty($_SESSION['pipeline_running'])) {
        json_response(['success' => false, 'message' => 'A pipeline is already running. Please wait.'], 409);
    }

    if (!isset($_FILES['roster_file']) || $_FILES['roster_file']['error'] !== UPLOAD_ERR_OK) {
        json_response(['success' => false, 'message' => 'Roster file is required.'], 400);
    }

    // Server-side file extension validation
    $ext = strtolower(pathinfo($_FILES['roster_file']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'xls'], true)) {
        json_response(['success' => false, 'message' => 'Only .csv and .xls roster files are accepted.'], 400);
    }

    $_SESSION['pipeline_running'] = true;
    session_write_close();

    // Step 1: Extraction — move-data-between-courses
    $extractionUrl = api_base('extraction')
        . '/move-data-between-courses/' . urlencode($destId)
        . '?' . http_build_query(['course_ids_to_pull' => $sourceId]);

    $rosterCurl = new CURLFile(
        $_FILES['roster_file']['tmp_name'],
        $_FILES['roster_file']['type'] ?: 'text/csv',
        $_FILES['roster_file']['name']
    );

    $ch = curl_init($extractionUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['canvas-access-token: ' . $token],
        CURLOPT_TIMEOUT        => 600,
        CURLOPT_POSTFIELDS     => ['roster_file' => $rosterCurl],
    ]);

    $extractBody  = curl_exec($ch);
    $extractCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $extractError = curl_error($ch);
    curl_close($ch);

    if ($extractError || $extractCode < 200 || $extractCode >= 300) {
        session_start();
        $_SESSION['pipeline_running'] = false;
        session_write_close();
        $decoded = json_decode((string) $extractBody, true);
        $msg = $extractError ?: ($decoded['detail'] ?? 'Extraction failed.');
        json_response(['success' => false, 'step' => 'extraction', 'message' => $msg], $extractCode ?: 502);
    }

    // Parse extraction response metadata
    $extractData = json_decode((string) $extractBody, true) ?: [];

    // Step 2: Formatting — format-and-upload
    // Reads from DEST course (where extraction uploaded files)
    $formatQuery = [];
    if (!empty($extractData['course_folder_name'])) {
        $formatQuery['course_folder_name'] = $extractData['course_folder_name'];
    }
    if (!empty($extractData['term_display'])) {
        $formatQuery['term_display'] = $extractData['term_display'];
    }
    // $formattingUrl = api_base('formatting')
    //     . '/format-and-upload/' . urlencode($sourceId)
    //     . '?' . http_build_query(['destination_course_id' => $destId]);
    $formattingUrl = api_base('formatting')
        . '/format-and-upload/' . urlencode($destId)
        . '?' . http_build_query($formatQuery);

    $formatResult = curl_api($formattingUrl, 'POST', $token);

    session_start();
    $_SESSION['pipeline_running'] = false;
    session_write_close();

    if (!$formatResult['ok']) {
        $detail = $formatResult['error'] ?? ($formatResult['body']['detail'] ?? 'Formatting failed.');
        json_response(['success' => false, 'step' => 'formatting', 'message' => $detail], $formatResult['status']);
    }

    json_response([
        'success' => true,
        'message' => 'Pipeline complete. Data extracted, formatted, and uploaded to Canvas.',
        'formatting' => $formatResult['body'],
    ]);
}

json_response(['success' => false, 'message' => 'Unknown action.'], 400);
