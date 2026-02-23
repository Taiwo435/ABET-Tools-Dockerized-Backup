<?php
declare(strict_types=1);

require_once __DIR__ . '/../_guard.php';

if (!function_exists('db') && !empty(getenv('ABET_PRIVATE_DIR'))) {
  require_once getenv('ABET_PRIVATE_DIR') . '/lib/db.php';
}

function e(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function ensure_csrf(): void {
  if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
  }
}

function current_user_id(): ?int {
  return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function redirect_self(): void {
  header('Location: /account/settings/password/');
  exit;
}

function logout_and_redirect_to_login(string $message): void {
  $_SESSION = [];

  if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(
      session_name(),
      '',
      time() - 42000,
      $p['path'],
      $p['domain'],
      (bool)$p['secure'],
      (bool)$p['httponly']
    );
  }

  session_destroy();

  session_start();
  $_SESSION['flash_success'] = $message;

  header('Location: /login');
  exit;
}

ensure_csrf();

$userId = current_user_id();
if (!$userId) {
  header('Location: /login');
  exit;
}

$flash_error = $_SESSION['flash_error'] ?? '';
$flash_success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $csrf = (string)($_POST['csrf'] ?? '');
  if (!hash_equals((string)($_SESSION['csrf'] ?? ''), $csrf)) {
    $_SESSION['flash_error'] = 'Invalid session. Please refresh and try again.';
    redirect_self();
  }

  $currentPassword = (string)($_POST['current_password'] ?? '');
  $newPassword     = (string)($_POST['new_password'] ?? '');
  $confirmPassword = (string)($_POST['confirm_password'] ?? '');

  if ($newPassword !== $confirmPassword) {
    $_SESSION['flash_error'] = 'New passwords do not match.';
    redirect_self();
  }

  if (strlen($newPassword) < 8) {
    $_SESSION['flash_error'] = 'New password must be at least 8 characters.';
    redirect_self();
  }

  if ($newPassword === $currentPassword) {
    $_SESSION['flash_error'] = 'New password must be different from the current password.';
    redirect_self();
  }

  $stmt = db()->prepare('SELECT password_hash FROM users WHERE user_id = :uid LIMIT 1');
  $stmt->execute([':uid' => $userId]);
  $row = $stmt->fetch();

  if (!$row) {
    $_SESSION['flash_error'] = 'Account not found.';
    redirect_self();
  }

  if (!password_verify($currentPassword, (string)$row['password_hash'])) {
    $_SESSION['flash_error'] = 'Current password is incorrect.';
    redirect_self();
  }

  $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

  db()->prepare('UPDATE users SET password_hash = :hash WHERE user_id = :uid')
    ->execute([':hash' => $newHash, ':uid' => $userId]);

  logout_and_redirect_to_login('Password updated. Please sign in again.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Reset Password | ABET Tools</title>
  <style>
    :root {
      --asu-maroon: #8C1D40;
      --asu-dark-maroon: #5c132a;
      --gray-bg: #F5F5F5;
      --text-dark: #222;
      --text-light: #666;
      --white: #fff;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
      background: var(--gray-bg);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }

    .card {
      width: 100%;
      max-width: 720px;
      background: var(--white);
      border-radius: 12px;
      box-shadow: 0 15px 35px rgba(0,0,0,0.12);
      overflow: hidden;
    }

    .header {
      padding: 22px 24px;
      background: linear-gradient(135deg, var(--asu-maroon), var(--asu-dark-maroon));
      color: var(--white);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .header h1 {
      font-size: 1.25rem;
      font-weight: 800;
    }

    .back-btn {
      background: rgba(255,255,255,0.12);
      color: var(--white);
      text-decoration: none;
      padding: 10px 14px;
      border-radius: 8px;
      font-weight: 700;
      font-size: 0.9rem;
    }

    .back-btn:hover { background: rgba(255,255,255,0.18); }

    .body { padding: 24px; }

    .hint {
      color: var(--text-light);
      margin-bottom: 18px;
      line-height: 1.45;
    }

    .msg {
      padding: 12px 14px;
      border-radius: 8px;
      margin-bottom: 16px;
      font-size: 0.95rem;
    }

    .msg.error {
      background: #fdedf0;
      color: #8C1D40;
      border-left: 4px solid var(--asu-maroon);
    }

    .msg.success {
      background: #ecfdf3;
      color: #0f5132;
      border-left: 4px solid #198754;
    }

    label {
      display: block;
      font-weight: 700;
      color: var(--text-dark);
      margin-bottom: 8px;
      margin-top: 14px;
      font-size: 0.95rem;
    }

    input[type="password"] {
      width: 100%;
      padding: 14px;
      border: 1px solid #ddd;
      border-radius: 10px;
      font-size: 1rem;
      background: #fafafa;
    }

    input[type="password"]:focus {
      outline: none;
      border-color: var(--asu-maroon);
      background: var(--white);
      box-shadow: 0 0 0 3px rgba(140, 29, 64, 0.10);
    }

    .actions {
      margin-top: 18px;
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
    }

    .btn {
      border: none;
      border-radius: 10px;
      padding: 12px 16px;
      font-weight: 800;
      cursor: pointer;
      font-size: 1rem;
    }

    .btn-primary {
      background: var(--asu-maroon);
      color: var(--white);
    }

    .btn-primary:hover { background: var(--asu-dark-maroon); }

    .btn-secondary {
      background: #eee;
      color: #333;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    .btn-secondary:hover { background: #e4e4e4; }
  </style>
</head>
<body>
  <div class="card">
    <div class="header">
      <h1>Reset Password</h1>
      <a class="back-btn" href="/account/settings/">← Back to Account Settings</a>
    </div>

    <div class="body">
      <p class="hint">Enter the current password, then choose a new one.</p>

      <?php if ($flash_error): ?>
        <div class="msg error"><strong>Error:</strong> <?php echo e((string)$flash_error); ?></div>
      <?php endif; ?>

      <?php if ($flash_success): ?>
        <div class="msg success"><?php echo e((string)$flash_success); ?></div>
      <?php endif; ?>

      <form method="POST" action="/account/settings/password/" autocomplete="off">
        <input type="hidden" name="csrf" value="<?php echo e((string)($_SESSION['csrf'] ?? '')); ?>">

        <label for="current_password">Current Password</label>
        <input id="current_password" name="current_password" type="password" required>

        <label for="new_password">New Password</label>
        <input id="new_password" name="new_password" type="password" required>

        <label for="confirm_password">Confirm New Password</label>
        <input id="confirm_password" name="confirm_password" type="password" required>

        <div class="actions">
          <button class="btn btn-primary" type="submit">Update Password</button>
          <a class="btn btn-secondary" href="/account/settings/">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</body>
</html>