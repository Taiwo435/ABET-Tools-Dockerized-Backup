<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/db.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/auth.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/security_headers.php';
require_once getenv('ABET_PRIVATE_DIR') . '/vendor/autoload.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/mailer.php';

start_session();

$submitted = false;

function e(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function resend_csrf_token(): string {
  if (empty($_SESSION['csrf_token_resend'])) {
    $_SESSION['csrf_token_resend'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token_resend'];
}

function verify_resend_csrf(?string $token): bool {
  if (!isset($_SESSION['csrf_token_resend']) || !is_string($token)) {
    return false;
  }

  return hash_equals($_SESSION['csrf_token_resend'], $token);
}

$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = strtolower(trim($_POST['email'] ?? ''));

  if (verify_resend_csrf($_POST['csrf_token'] ?? null) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    // Only accounts that exist AND are still unverified get a new token.
    // The response is identical either way, so this can't be used to enumerate accounts.
    $stmt = db()->prepare('SELECT id FROM users WHERE email = ? AND is_active = 0 LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
      $verificationCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
      $expiresAt = (new DateTime('+15 minutes'))->format('Y-m-d H:i:s');

      db()->prepare(
        'UPDATE users SET email_verification_token = ?, email_verification_expires_at = ?,
         email_verification_attempts = 0 WHERE id = ?'
      )->execute([$verificationCode, $expiresAt, $user['id']]);

      send_verification_email($email, $verificationCode);
    }
  }

  $submitted = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>ASU ABET Tools | Resend Verification</title>
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
        <h1>Resend Verification Email</h1>
        <p>Enter the email you signed up with and we'll send a new verification link.</p>
      </div>

      <?php if ($submitted): ?>
        <div class="msg success">
          <strong>Check your email.</strong> If an account with that email is awaiting verification, a new code has been sent.
        </div>
        <a href="/verify-email?email=<?php echo urlencode($email); ?>" class="btn-submit" style="display:block; text-align:center; text-decoration:none;">Enter Verification Code</a>
      <?php else: ?>

        <form method="post" autocomplete="off">
          <input type="hidden" name="csrf_token" value="<?php echo e(resend_csrf_token()); ?>">
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

          <button class="btn-submit" type="submit">Resend Verification Code</button>

          <div class="footer-links">
            <span>Already verified?</span>
            <a href="/login">Sign In</a>
          </div>
        </form>

      <?php endif; ?>
    </div>

  </div>

</body>
</html>
