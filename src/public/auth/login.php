<?php
declare(strict_types=1);

require_once getenv('ABET_PRIVATE_DIR') . '/lib/db.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/auth.php';

start_session();

function e(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function client_ip(): ?string {
  return $_SERVER['REMOTE_ADDR'] ?? null;
}

function user_agent(): ?string {
  return $_SERVER['HTTP_USER_AGENT'] ?? null;
}

function log_login_event(?int $userId, ?string $emailAttempted, string $result, ?string $reason = null): void {
  try {
    db()->prepare(
      'INSERT INTO login_events (user_id, email_attempted, result, reason, ip_address, user_agent)
       VALUES (:user_id, :email_attempted, :result, :reason, :ip, :ua)'
    )->execute([
      ':user_id' => $userId,
      ':email_attempted' => $emailAttempted,
      ':result' => $result,
      ':reason' => $reason,
      ':ip' => client_ip(),
      ':ua' => user_agent(),
    ]);
  } catch (Throwable $e) {
    // Fail-open: never break login flow if logging table/insert fails.
  }
}

function log_audit(?int $actorUserId, string $action, ?string $targetType = null, ?string $targetId = null, ?array $metadata = null): void {
  try {
    db()->prepare(
      'INSERT INTO audit_log (actor_user_id, action, target_type, target_id, metadata, ip_address)
       VALUES (:actor, :action, :target_type, :target_id, :metadata, :ip)'
    )->execute([
      ':actor' => $actorUserId,
      ':action' => $action,
      ':target_type' => $targetType,
      ':target_id' => $targetId,
      ':metadata' => $metadata ? json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
      ':ip' => client_ip(),
    ]);
  } catch (Throwable $e) {
    // Fail-open
  }
}

$error = '';

if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $csrf = $_POST['csrf'] ?? '';
  if (!hash_equals($_SESSION['csrf'], $csrf)) {
    $error = 'Invalid session. Please refresh and try again.';
    log_login_event(null, $_POST['email'] ?? null, 'failed_password', 'csrf_invalid');
  } else {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $error = 'Please enter a valid email.';
      log_login_event(null, $email, 'failed_password', 'invalid_email_format');
    } else {
      $stmt = db()->prepare('SELECT id, email, password_hash, role, is_active, permissions FROM users WHERE email = :email LIMIT 1');
      $stmt->execute([':email' => $email]);
      $user = $stmt->fetch();

      if (!$user || (int)$user['is_active'] !== 1 || !password_verify($password, (string)$user['password_hash'])) {
        $error = 'Invalid email or password.';
        log_login_event(isset($user['id']) ? (int)$user['id'] : null, $email, 'failed_password', 'bad_credentials_or_inactive');
      } else {
        session_regenerate_id(true);

        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_email'] = (string)$user['email'];
        $_SESSION['user_role'] = (string)$user['role'];
        $_SESSION['user_permissions'] = (int)$user['permissions'];
        $_SESSION['created_at'] = time();
        $_SESSION['last_activity'] = time();

        db()->prepare('UPDATE users SET last_login = NOW() WHERE id = :id')->execute([':id' => (int)$user['id']]);

        log_login_event((int)$user['id'], (string)$user['email'], 'success', null);
        log_audit((int)$user['id'], 'login_success', 'user', (string)$user['id'], [
          'role' => (string)$user['role']
        ]);

        header('Location: /home');
        exit;
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>ASU ABET Tools | Login</title>
  <link rel="stylesheet" href="/assets/css/auth.css" />
</head>
<body>

  <div class="login-container">
    
    <div class="brand-section">
      <div class="brand-content">
        <h2>Arizona State University</h2>
        <p>Enterprise Technology & ABET Accreditation Tools.</p>
        <div style="width: 60px; height: 4px; background: var(--asu-gold); margin-top: 20px;"></div>
      </div>
    </div>

    <div class="form-section">
      <a href="https://www.asu.edu/about/contact" class="help-link">Need Help?</a>

      <div class="form-header">
        <h1>Welcome Back</h1>
        <p>Please sign in to access your dashboard.</p>
      </div>

      <?php if ($error): ?>
        <div class="error-box">
          <strong>Error:</strong> <?php echo e($error); ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="/login" autocomplete="on">
        <input type="hidden" name="csrf" value="<?php echo e($_SESSION['csrf']); ?>">

        <div class="form-group">
          <label for="email">Email Address</label>
          <input type="email" id="email" name="email" placeholder="asurite@asu.edu" required />
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" placeholder="••••••••" required />
        </div>

        <button type="submit" class="btn-submit">Sign In</button>

        <div class="footer-links">
          <div>
            <span>Don't have an account?</span>
            <a href="/auth/register.php">Create Account</a>
          </div>
          <div style="margin-top: 15px;">
            <a href="/auth/forgot_password.php" style="margin-left: 0;">Forgot Password?</a>
          </div>
        </div>
      </form>
    </div>

  </div>

</body>
</html>