<?php
declare(strict_types=1);

require_once getenv('ABET_PRIVATE_DIR') . '/lib/auth.php';
require_login();
require_once getenv('ABET_PRIVATE_DIR') . '/lib/security_headers.php'; 

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function post_str(string $key): string {
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : '';
}

function post_bool(string $key): bool {
    return isset($_POST[$key]) && in_array(strtolower((string)$_POST[$key]), ['1', 'true', 'on', 'yes'], true);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

// CSRF check
$csrfPosted = post_str('csrf_canvas_token');
$csrfSession = $_SESSION['csrf_canvas_token'] ?? '';
if ($csrfPosted === '' || $csrfSession === '' || !hash_equals($csrfSession, $csrfPosted)) {
    json_response(['success' => false, 'message' => 'Invalid CSRF token'], 403);
}

// Inputs
$sourceCourse = post_str('sourceCourse');
$destCourse   = post_str('destCourse');
$semester     = post_str('semester');
$year         = post_str('year');
$canvasToken  = post_str('canvasToken');
$genCoursePage = post_bool('genCoursePage');
$genAbetPage   = post_bool('genAbetPage');

// Validation
$errors = [];

if ($sourceCourse === '' || !preg_match('/^\d+$/', $sourceCourse)) {
    $errors[] = 'Source Canvas Course ID must be numeric.';
}
if ($destCourse === '' || !preg_match('/^\d+$/', $destCourse)) {
    $errors[] = 'Destination Canvas Course ID must be numeric.';
}
if (!in_array($semester, ['Fall', 'Spring', 'Summer'], true)) {
    $errors[] = 'Semester must be Fall, Spring, or Summer.';
}
if ($year === '' || !preg_match('/^\d{4}$/', $year)) {
    $errors[] = 'Year must be a 4-digit year.';
}
if ($canvasToken === '') {
    $errors[] = 'Canvas token is required for testing.';
}
if (!$genCoursePage && !$genAbetPage) {
    $errors[] = 'Select at least one generation option (Course Page or ABET Page).';
}

if ($errors) {
    json_response([
        'success' => false,
        'message' => 'Validation failed.',
        'errors' => $errors
    ], 422);
}

// Call the Canvas Formatting API (Docker container on the same server)
$apiUrl = "http://localhost:8001/format-and-upload/" . urlencode($sourceCourse);
$apiUrl .= "?" . http_build_query([
    'destination_course_id' => $destCourse,
    'semester' => strtolower($semester),
    'year' => $year,
]);

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'canvas_access_token: ' . $canvasToken,
        'Content-Type: application/json',
    ],
    CURLOPT_TIMEOUT => 120,
]);

$responseBody = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    json_response([
        'success' => false,
        'message' => 'Failed to reach Canvas Formatting API: ' . $curlError,
    ], 502);
}

$data = json_decode($responseBody, true) ?: [];
$success = ($httpCode >= 200 && $httpCode < 300);

// Redact token if somehow echoed
$safeBody = str_replace($canvasToken, '[REDACTED_TOKEN]', (string)$responseBody);

json_response([
    'success' => $success,
    'message' => $success
        ? ($data['message'] ?? 'Canvas formatting completed successfully.')
        : ($data['detail'] ?? 'Canvas formatting API returned an error.'),
    'exitCode' => $httpCode,
    'stdout' => $safeBody,
    'stderr' => '',
], $success ? 200 : 500);