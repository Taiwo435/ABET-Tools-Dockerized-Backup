<?php
declare(strict_types=1);

require_once getenv('ABET_PRIVATE_DIR') . '/lib/db.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/auth.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/clerk.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/security_headers.php';

/**
 * Activates a pending (is_active = 0) account once the user proves, via a
 * Clerk-verified Google sign-in, that they own the email address they
 * registered with, then logs them straight in — having just proven their
 * identity via Google, making them immediately re-enter a password too
 * would be redundant. Populates the same raw $_SESSION keys that native
 * /login (via SecurityAuditSubscriber) sets, since that's what every
 * legacy-page permission check and LegacySessionAuthenticator read from.
 * Password login at /login is otherwise untouched.
 */

function clerk_verify_json(int $status, array $data): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    clerk_verify_json(405, ['error' => 'Method not allowed.']);
}

start_session();

try {
    $expectedOrigin = clerk_request_origin();

    $rawBody = file_get_contents('php://input');

    if (!is_string($rawBody) || $rawBody === '') {
        clerk_verify_json(400, ['error' => 'Missing request body.']);
    }

    try {
        $body = json_decode($rawBody, true, 8, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        clerk_verify_json(400, ['error' => 'Invalid JSON request body.']);
    }

    $token = $body['token'] ?? null;

    if (!is_string($token) || $token === '' || strlen($token) < 100 || strlen($token) > 10000) {
        clerk_verify_json(400, ['error' => 'Missing or invalid session token.']);
    }

    $claims = clerk_verify_session_token($token, $expectedOrigin);
    $verifiedEmail = clerk_verified_primary_email($claims->sub);

    $pdo = db();

    $stmt = $pdo->prepare('SELECT id, email, permissions, is_active FROM users WHERE LOWER(TRIM(email)) = :email LIMIT 1');
    $stmt->execute(['email' => $verifiedEmail]);
    $user = $stmt->fetch();

    if (!$user) {
        clerk_verify_json(404, [
            'error' => 'That Google account\'s email doesn\'t match a pending signup. Make sure you sign in with the same email address you registered with.',
        ]);
    }

    if ((int) $user['is_active'] !== 1) {
        $pdo->prepare('UPDATE users SET is_active = 1 WHERE id = :id')->execute(['id' => (int) $user['id']]);
    }

    $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = :id')->execute(['id' => (int) $user['id']]);

    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_email'] = (string) $user['email'];
    $_SESSION['user_permissions'] = (int) $user['permissions'];
    $_SESSION['created_at'] = time();
    $_SESSION['last_activity'] = time();

    clerk_verify_json(200, ['verified' => true, 'redirect' => '/home']);
} catch (Throwable $exception) {
    error_log('Clerk email verification failed: ' . $exception::class . ': ' . $exception->getMessage());
    clerk_verify_json(500, ['error' => 'The verification service encountered a server error.']);
}
