<?php
declare(strict_types=1);

// require_once 'getenv('ABET_PRIVATE_DIR') . '/config/config.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/db.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/auth.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/reset_password_lib.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/security_headers.php'; 

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function fp_csrf_token(): string {
    if (empty($_SESSION['csrf_token_forgot_password'])) {
        $_SESSION['csrf_token_forgot_password'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token_forgot_password'];
}

function fp_verify_csrf(?string $token): bool {
    if (!isset($_SESSION['csrf_token_forgot_password']) || !is_string($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token_forgot_password'], $token);
}

$email = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $csrf = (string)($_POST['csrf_token'] ?? '');

    if (!fp_verify_csrf($csrf)) {
        $errors[] = 'Invalid request. Please try again.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (!$errors) {
        // Always returns a generic public message (prevents email enumeration)
        $result = rp_request_password_reset(
            $email,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );

        $_SESSION['forgot_password_notice'] = $result['public_message']
            ?? 'If an account exists for that email, a reset link has been sent.';

        // Helpful for local Docker testing (only shown if fallback logger was used)
        if (!empty($result['dev_reset_link'])) {
            $_SESSION['forgot_password_dev_reset_link'] = $result['dev_reset_link'];
        } else {
            unset($_SESSION['forgot_password_dev_reset_link']);
        }

        header('Location: forgot_password_sent.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Forgot Password</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body { font-family: Arial, sans-serif; background:#f5f6fa; margin:0; }
    .wrap { max-width: 520px; margin: 60px auto; background:#fff; border-radius:12px; padding:24px; box-shadow:0 10px 30px rgba(0,0,0,.08); }
    h1 { margin-top:0; }
    .muted { color:#555; }
    .error { background:#fdecea; color:#8a1f11; padding:10px 12px; border-radius:8px; margin-bottom:10px; }
    label { display:block; margin:14px 0 6px; font-weight:600; }
    input[type="email"] { width:100%; padding:10px 12px; border:1px solid #ccc; border-radius:8px; box-sizing:border-box; }
    button { margin-top:16px; padding:10px 14px; border:none; border-radius:8px; cursor:pointer; background:#8C1D40; color:#fff; font-weight:600; }
    a { color:#8C1D40; text-decoration:none; }
    .links { margin-top:14px; }
  </style>
</head>
<body>
  <div class="wrap">
    <h1>Forgot Password</h1>
    <p class="muted">
      Enter the email address for your account. If it exists, we’ll send a password reset link.
    </p>

    <?php foreach ($errors as $err): ?>
      <div class="error"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endforeach; ?>

    <form method="post" action="">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(fp_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

      <label for="email">Email</label>
      <input
        id="email"
        name="email"
        type="email"
        required
        autocomplete="email"
        value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
      >

      <button type="submit">Send Reset Link</button>
    </form>

    <div class="links">
      <a href="login.php">Back to Login</a>
    </div>
  </div>
</body>
</html>