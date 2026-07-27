<?php
declare(strict_types=1);

require_once getenv('ABET_PRIVATE_DIR') . '/lib/db.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/auth.php';

/**
 * Test-only password login, used exclusively by the Selenium suite
 * (src/test/utils/backend_login.py) to establish an authenticated session
 * without driving the UI. /login itself is Google/email(Clerk)-driven now,
 * which can't be scripted end-to-end by Selenium (no way to automate a
 * real Google sign-in or read a Clerk email code in CI) — this endpoint is
 * NOT a substitute for it and is completely inert outside APP_ENV=test.
 */

// Deliberately its own flag rather than APP_ENV=test: the web container
// Selenium tests actually run against uses APP_ENV=dev (see demo.env), and
// APP_ENV=test triggers Symfony's mock session storage (a different cookie
// entirely, MOCKSESSID) which this endpoint's real PHP session wouldn't be
// visible through anyway. Defaults to off in every environment unless
// explicitly enabled for a test run.
if (getenv('ENABLE_TEST_LOGIN_ENDPOINT') !== '1') {
    http_response_code(404);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

start_session();

$rawBody = file_get_contents('php://input');
$body = is_string($rawBody) && $rawBody !== '' ? json_decode($rawBody, true) : null;

$email = strtolower(trim((string) ($body['email'] ?? '')));
$password = (string) ($body['password'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    http_response_code(400);
    exit;
}

$stmt = db()->prepare('SELECT id, email, password_hash, is_active, permissions FROM users WHERE email = :email LIMIT 1');
$stmt->execute(['email' => $email]);
$user = $stmt->fetch();

if (!$user || (int) $user['is_active'] !== 1 || !password_verify($password, (string) $user['password_hash'])) {
    http_response_code(401);
    exit;
}

session_regenerate_id(true);

$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['user_email'] = (string) $user['email'];
$_SESSION['user_permissions'] = (int) $user['permissions'];
$_SESSION['created_at'] = time();
$_SESSION['last_activity'] = time();

http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['ok' => true]);
