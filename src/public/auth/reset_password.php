<?php
declare(strict_types=1);

// require_once '/home/abet_private/config/config.php'; // local config path not available right now
require_once getenv('ABET_PRIVATE_DIR') . '/lib/db.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/auth.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/reset_password_lib.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function rp_page_csrf_token(): string {
    if (empty($_SESSION['csrf_token_reset_password'])) {
        $_SESSION['csrf_token_reset_password'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token_reset_password'];
}

function rp_page_verify_csrf(?string $token): bool {
    if (!isset($_SESSION['csrf_token_reset_password']) || !is_string($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token_reset_password'], $token);
}

$errors = [];
$success = false;

// Token can come from GET (email link) or hidden POST field on submit
$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$password = '';
$confirmPassword = '';

$tokenCheck = null;
if ($token !== '') {
    $tokenCheck = rp_validate_reset_token($token);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if (!rp_page_verify_csrf($csrf)) {
        $errors[] = 'Invalid request. Please refresh and try again.';
    }

    if ($token === '') {
        $errors[] = 'Missing reset token.';
    }

    if ($password === '' || $confirmPassword === '') {
        $errors[] = 'Please fill out both password fields.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    $policyIssues = rp_password_policy_check($password);
    foreach ($policyIssues as $issue) {
        $errors[] = $issue;
    }

    if (!$errors) {
        $result = rp_complete_password_reset($token, $password);

        if (!empty($result['ok'])) {
            header('Location: reset_password_success.php');
            exit;
        }

        $reason = $result['reason'] ?? 'unknown';
        if (in_array($reason, ['invalid_token', 'expired_token', 'used_token'], true)) {
            $errors[] = 'This reset link is invalid or has expired. Please request a new one.';
        } else {
            $errors[] = 'Unable to reset password right now. Please try again.';
        }
    }

    // Re-check token for page rendering after POST
    $tokenCheck = ($token !== '') ? rp_validate_reset_token($token) : null;
}

$isTokenValid = !empty($tokenCheck['ok']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Reset Password</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body { font-family: Arial, sans-serif; background:#f5f6fa; margin:0; }
    .wrap { max-width: 560px; margin: 60px auto; background:#fff; border-radius:12px; padding:24px; box-shadow:0 10px 30px rgba(0,0,0,.08); }
    h1 { margin-top:0; }
    .muted { color:#555; }
    .error { background:#fdecea; color:#8a1f11; padding:10px 12px; border-radius:8px; margin-bottom:10px; }
    .warning { background:#fff8e1; color:#6b5200; padding:12px; border-radius:8px; }
    label { display:block; margin:14px 0 6px; font-weight:600; }
    input[type="password"] { width:100%; padding:10px 12px; border:1px solid #ccc; border-radius:8px; box-sizing:border-box; }
    button { margin-top:16px; padding:10px 14px; border:none; border-radius:8px; cursor:pointer; background:#8C1D40; color:#fff; font-weight:600; }
    a { color:#8C1D40; text-decoration:none; }
    ul.policy { margin:10px 0 0 20px; color:#555; }
    .links { margin-top:16px; }
  </style>
</head>
<body>
  <div class="wrap">
    <h1>Reset Password</h1>

    <?php foreach ($errors as $err): ?>
      <div class="error"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endforeach; ?>

    <?php if (!$isTokenValid): ?>
      <div class="warning">
        This reset link is invalid, expired, or has already been used.
      </div>
      <div class="links">
        <a href="forgot_password.php">Request a New Reset Link</a><br>
        <a href="login.php">Back to Login</a>
      </div>
    <?php else: ?>
      <p class="muted">Enter a new password for your account.</p>

      <form method="post" action="">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(rp_page_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

        <label for="password">New Password</label>
        <input id="password" name="password" type="password" required autocomplete="new-password">

        <label for="confirm_password">Confirm New Password</label>
        <input id="confirm_password" name="confirm_password" type="password" required autocomplete="new-password">

        <ul class="policy">
          <li>At least 10 characters</li>
          <li>At least 1 uppercase letter</li>
          <li>At least 1 lowercase letter</li>
          <li>At least 1 number</li>
          <li>At least 1 special character</li>
        </ul>

        <button type="submit">Reset Password</button>
      </form>

      <div class="links">
        <a href="login.php">Back to Login</a>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>