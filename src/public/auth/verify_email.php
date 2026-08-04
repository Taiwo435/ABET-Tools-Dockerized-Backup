<?php
declare(strict_types=1);

require_once getenv('ABET_PRIVATE_DIR') . '/lib/db.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/auth.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/security_headers.php';

start_session();

const MAX_VERIFICATION_ATTEMPTS = 5;

function e(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function verify_email_csrf_token(): string {
  if (empty($_SESSION['csrf_token_verify_email'])) {
    $_SESSION['csrf_token_verify_email'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token_verify_email'];
}

function verify_verify_email_csrf(?string $token): bool {
  if (!isset($_SESSION['csrf_token_verify_email']) || !is_string($token)) {
    return false;
  }

  return hash_equals($_SESSION['csrf_token_verify_email'], $token);
}

$email = strtolower(trim($_GET['email'] ?? $_POST['email'] ?? ''));
$error = '';

// Generic message for every failure case (wrong code, expired, already
// verified, unknown email) so this endpoint can't be used to enumerate
// accounts or distinguish "wrong code" from "no such pending signup".
$genericError = 'That code is invalid or has expired. Please check the code or request a new one.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!verify_verify_email_csrf($_POST['csrf_token'] ?? null)) {
    $error = 'Invalid or missing form token. Please refresh the page and try again.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Please enter a valid email address.';
  } else {
    $code = preg_replace('/\D/', '', (string) ($_POST['code'] ?? ''));

    $pdo = db();
    $stmt = $pdo->prepare(
      'SELECT id, email_verification_token, email_verification_expires_at, email_verification_attempts
       FROM users WHERE email = ? AND is_active = 0 LIMIT 1'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !$user['email_verification_token']) {
      $error = $genericError;
    } elseif ((int) $user['email_verification_attempts'] >= MAX_VERIFICATION_ATTEMPTS) {
      $error = 'Too many incorrect attempts. Please request a new code.';
    } elseif ($user['email_verification_expires_at'] === null || strtotime((string) $user['email_verification_expires_at']) < time()) {
      $error = $genericError;
    } elseif (!hash_equals((string) $user['email_verification_token'], $code)) {
      $pdo->prepare('UPDATE users SET email_verification_attempts = email_verification_attempts + 1 WHERE id = ?')
        ->execute([$user['id']]);
      $error = $genericError;
    } else {
      $pdo->prepare(
        'UPDATE users SET is_active = 1, email_verification_token = NULL,
         email_verification_expires_at = NULL, email_verification_attempts = 0 WHERE id = ?'
      )->execute([$user['id']]);

      header('Location: /login?verified=1');
      exit;
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>ASU ABET Tools | Verify Email</title>
  <link rel="icon" type="image/svg" href="/assets/img/favicon.svg" />
  <link href="/assets/css/auth.css" rel="stylesheet">
</head>
<body>

  <div class="register-container">

    <div class="brand-section">
      <div class="brand-content">
        <h2>Arizona State University</h2>
        <p>Enterprise Technology & ABET Accreditation Tools.</p>
        <div style="width: 60px; height: 4px; background: var(--asu-gold); margin-top: 20px;"></div>
      </div>
    </div>

    <div class="form-section">
      <div class="form-header">
        <h1>Verify Your Email</h1>
        <p>Enter the 6-digit code we sent to your email address.</p>
      </div>

      <?php if ($error): ?>
        <div class="msg error" id="error-box">
          <?php echo e($error); ?>
        </div>
      <?php endif; ?>

      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?php echo e(verify_email_csrf_token()); ?>">

        <div class="form-group">
          <label for="email">Email Address</label>
          <input
            id="email"
            name="email"
            type="email"
            placeholder="asurite@asu.edu"
            required
            value="<?php echo e($email); ?>"
          />
        </div>

        <div class="form-group">
          <label for="code">Verification Code</label>
          <input
            id="code"
            name="code"
            type="text"
            inputmode="numeric"
            pattern="[0-9]{6}"
            maxlength="6"
            placeholder="123456"
            required
            autofocus
            style="letter-spacing: 4px; font-size: 1.3rem; text-align: center;"
          />
        </div>

        <button class="btn-submit" type="submit">Verify Account</button>

        <div class="footer-links">
          <span>Didn't get a code?</span>
          <a href="/resend-verification">Resend code</a>
        </div>
      </form>
    </div>

  </div>

</body>
</html>
