<?php
declare(strict_types=1);

require_once getenv('ABET_PRIVATE_DIR') . '/lib/db.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/security_headers.php';

$token = $_GET['token'] ?? '';

if (!$token || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(400);
    echo 'Invalid verification link.';
    exit;
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id FROM users WHERE email_verification_token = ? LIMIT 1');
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(400);
    echo 'This verification link is invalid or has already been used.';
    exit;
}

$pdo->prepare('UPDATE users SET is_active = 1, email_verification_token = NULL WHERE id = ?')
    ->execute([$user['id']]);

header('Location: /login?verified=1');
exit;
