<?php
declare(strict_types=1);

require_once $_ENV['ABET_PRIVATE_DIR'] . '/lib/auth.php';
require_login();

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

$wrapper = $_ENV['ABET_PRIVATE_DIR'] . '/bin/run_canvas_generator.sh';
if (!is_file($wrapper) || !is_executable($wrapper)) {
    json_response([
        'success' => false,
        'message' => 'Backend wrapper script is missing or not executable.'
    ], 500);
}

// Build environment vars for Python script.
// IMPORTANT: pass token via env, NOT command line args.
$env = $_ENV;
$env['canvas_access_token'] = $canvasToken;
$env['CANVAS_SOURCE_COURSE_ID'] = $sourceCourse;
$env['CANVAS_DEST_COURSE_ID'] = $destCourse;
$env['CANVAS_SEMESTER'] = $semester;
$env['CANVAS_YEAR'] = $year;
$env['CANVAS_DO_COURSE_PAGE'] = $genCoursePage ? '1' : '0';
$env['CANVAS_DO_ABET_PAGE'] = $genAbetPage ? '1' : '0';
$env['CANVAS_DOMAIN'] = 'canvas.asu.edu';

// Run process
$descriptorspec = [
    0 => ['pipe', 'r'], // stdin
    1 => ['pipe', 'w'], // stdout
    2 => ['pipe', 'w'], // stderr
];

$process = @proc_open($wrapper, $descriptorspec, $pipes, null, $env);

if (!is_resource($process)) {
    json_response([
        'success' => false,
        'message' => 'Failed to start backend process (proc_open unavailable or blocked).'
    ], 500);
}

// No stdin needed
fclose($pipes[0]);

$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);

fclose($pipes[1]);
fclose($pipes[2]);

$exitCode = proc_close($process);

// Redact token if somehow echoed by downstream code
if ($canvasToken !== '') {
    $stdout = str_replace($canvasToken, '[REDACTED_TOKEN]', (string)$stdout);
    $stderr = str_replace($canvasToken, '[REDACTED_TOKEN]', (string)$stderr);
}

$success = ($exitCode === 0);

json_response([
    'success' => $success,
    'message' => $success ? 'Canvas generator completed successfully.' : 'Canvas generator failed.',
    'exitCode' => $exitCode,
    'stdout' => (string)$stdout,
    'stderr' => (string)$stderr,
], $success ? 200 : 500);