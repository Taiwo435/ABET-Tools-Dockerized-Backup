<?php
declare(strict_types=1);

require_once getenv('ABET_PRIVATE_DIR') . '/lib/db.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/auth.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/clerk.php';
require_once getenv('ABET_PRIVATE_DIR') . '/vendor/autoload.php';
require_once getenv('ABET_PRIVATE_DIR') . '/src/Entity/User.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/security_headers.php';

/**
 * "Continue with Google" on the login page. Auto-creates an account on
 * first sign-in (a Clerk-verified Google email is at least as strong proof
 * of ownership as our own code/link verification would have been), so
 * Google users never have to separately fill out the password register
 * form first. Existing password-based accounts also work here as long as
 * they're active. Logs the user in the same way native password login
 * does: populates the raw $_SESSION keys legacy pages and
 * LegacySessionAuthenticator read from.
 */

function clerk_login_json(int $status, array $data): never
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
    clerk_login_json(405, ['error' => 'Method not allowed.']);
}

start_session();

try {
    $expectedOrigin = clerk_request_origin();

    $rawBody = file_get_contents('php://input');

    if (!is_string($rawBody) || $rawBody === '') {
        clerk_login_json(400, ['error' => 'Missing request body.']);
    }

    try {
        $body = json_decode($rawBody, true, 8, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        clerk_login_json(400, ['error' => 'Invalid JSON request body.']);
    }

    $token = $body['token'] ?? null;

    if (!is_string($token) || $token === '' || strlen($token) < 100 || strlen($token) > 10000) {
        clerk_login_json(400, ['error' => 'Missing or invalid session token.']);
    }

    $claims = clerk_verify_session_token($token, $expectedOrigin);
    $verifiedEmail = clerk_verified_primary_email($claims->sub);

    $pdo = db();

    $stmt = $pdo->prepare('SELECT id, email, permissions, is_active FROM users WHERE LOWER(TRIM(email)) = :email LIMIT 1');
    $stmt->execute(['email' => $verifiedEmail]);
    $user = $stmt->fetch();

    if (!$user) {
        // Google already proved they own this email — no separate password
        // registration or verification step needed. Unusable random
        // password hash: the password_hash column is NOT NULL, but this
        // account can only ever be signed into via Google unless the user
        // later sets a real password themselves.
        $placeholderHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT);
        $defaultPermissions = \App\Entity\Permissions::ROLE_FACULTY_FORM->value;

        $pdo->prepare(
            'INSERT INTO users (email, password_hash, is_active, permissions) VALUES (?, ?, 1, ?)'
        )->execute([$verifiedEmail, $placeholderHash, $defaultPermissions]);

        $stmt->execute(['email' => $verifiedEmail]);
        $user = $stmt->fetch();
    }

    if ((int) $user['is_active'] !== 1) {
        clerk_login_json(403, [
            'error' => 'This account is not active yet. Verify your email first from the link on the registration page.',
        ]);
    }

    $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = :id')->execute(['id' => (int) $user['id']]);

    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_email'] = (string) $user['email'];
    $_SESSION['user_permissions'] = (int) $user['permissions'];
    $_SESSION['created_at'] = time();
    $_SESSION['last_activity'] = time();

    clerk_login_json(200, ['redirect' => '/home']);
} catch (Throwable $exception) {
    error_log('Clerk login failed: ' . $exception::class . ': ' . $exception->getMessage());
    clerk_login_json(500, ['error' => 'The sign-in service encountered a server error.']);
}
