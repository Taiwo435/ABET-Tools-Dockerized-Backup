<?php
declare(strict_types=1);

use Symfony\Component\HttpFoundation\Session\Session;
use App\Entity\User;

/**
 * Auth/session helpers
 * Path: getenv('ABET_PRIVATE_DIR') . '/lib/auth.php'
 */

function safe_redirect(string $redirectTo = '/login'): void {
  $redirectTo = trim($redirectTo);
  $redirectTo = str_replace(array("\r", "\n"), '', $redirectTo);

  if ($redirectTo === '') {
    $redirectTo = '/home';
  }

  // Block external/full URLs
  if (preg_match('#^(https?:)?//#i', $redirectTo)) {
    $redirectTo = '/home';
  }

  // Block filesystem-path leakage
  // TODO: remove hardcoded paths
  $lower = strtolower($redirectTo);
  if (
    strpos($lower, 'public_html') !== false ||
    strpos($lower, 'home/osburn') !== false ||
    strpos($lower, '/home/osburn') !== false
  ) {
    $redirectTo = '/home';
  }

  // Force absolute site path
  if ($redirectTo[0] !== '/') {
    $redirectTo = '/' . ltrim($redirectTo, '/');
  }

  header('Location: ' . $redirectTo, true, 302);
  exit;
}

function start_session_basic(): void {
  if (session_status() === PHP_SESSION_ACTIVE) return;

  ini_set('session.use_strict_mode', '1');
  ini_set('session.use_only_cookies', '1');
  ini_set('session.use_trans_sid', '0');
  ini_set('session.cookie_httponly', '1');

  $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

  // Fixes ZAP Issue: Cookie without SameSite Attribute
  $params = session_get_cookie_params();
  session_set_cookie_params([
    'lifetime' => 0,
    'path' => $params['path'] ?? '/',
    'domain' => $params['domain'] ?? '',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax'
  ]);

  session_start();
}

function start_session(): void {
  start_session_basic();
  enforce_session_timeouts();
}

function enforce_session_timeouts(): void {
  // Do not call start_session() here (prevents recursion)
  if (session_status() !== PHP_SESSION_ACTIVE) return;

  $now = time();
  $idle = defined('SESSION_IDLE_TIMEOUT') ? (int)SESSION_IDLE_TIMEOUT : (30 * 60);
  $absolute = defined('SESSION_ABSOLUTE_TIMEOUT') ? (int)SESSION_ABSOLUTE_TIMEOUT : (8 * 60 * 60);

  if (!isset($_SESSION['created_at'])) $_SESSION['created_at'] = $now;
  if (!isset($_SESSION['last_activity'])) $_SESSION['last_activity'] = $now;

  // Absolute timeout
  if (($now - (int)$_SESSION['created_at']) > $absolute) {
    logout('/login?reason=timeout');
  }
  // Idle timeout
  if (($now - (int)$_SESSION['last_activity']) > $idle) {
    logout('/login?reason=idle');
  }

  $_SESSION['last_activity'] = $now;
}

function is_logged_in(): bool {
  start_session();
  return !empty($_SESSION['user_id']);
}

function require_login(string $redirectTo = '/login'): void {
  start_session();

  if (empty($_SESSION['user_id'])) {
    safe_redirect($redirectTo);
  }

  // Verify the user still exists AND refresh cached permissions/active
  // status from the database on every request. $_SESSION['user_permissions']
  // is only ever set at login time, so without this, any permission change
  // made while a user is already logged in (very common while testing/
  // administering) would silently go unrecognized by every legacy page
  // gated with require_role() until that user logs out and back in again —
  // even though Symfony-native pages (which load the User entity fresh
  // each request) would immediately reflect the change.
  require_once getenv('ABET_PRIVATE_DIR') . '/lib/db.php';
  $stmt = db()->prepare('SELECT permissions, is_active FROM users WHERE id = :id LIMIT 1');
  $stmt->execute(['id' => (int)$_SESSION['user_id']]);
  $row = $stmt->fetch();

  if (!$row) {
    logout('/login?reason=deleted');
  }

  if ((int)$row['is_active'] !== 1) {
    logout('/login?reason=deactivated');
  }

  $_SESSION['user_permissions'] = (int)$row['permissions'];
}

/**
 * Requires the logged-in user to hold the given permission, identified by
 * the Permissions enum case name (e.g. 'ROLE_FACULTY_FORM', 'ROLE_ADMIN').
 * An admin always passes, mirroring User::hasPermission()'s short-circuit.
 * Fails closed (403) on an unrecognized role name.
 */
function require_role(string $role): void {
  require_login();

  $permissions = (int)($_SESSION['user_permissions'] ?? 0);
  $isAdmin = ($permissions & \App\Entity\Permissions::ROLE_ADMIN->value) !== 0;

  if ($isAdmin) {
    return;
  }

  $permission = null;
  foreach (\App\Entity\Permissions::cases() as $case) {
    if ($case->name === $role) {
      $permission = $case;
      break;
    }
  }

  if ($permission === null || !($permissions & $permission->value)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
  }
}

function logout(string $redirectTo = '/login'): void {

  $_SESSION = array();
  session_destroy();

  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
      session_name(),
      '',
      [
        'expires' => time() - 42000,
        'path' => $params['path'] ?? '/',
        'domain' => $params['domain'] ?? '',
        'secure' => (bool)($params['secure'] ?? false),
        'httponly' => (bool)($params['httponly'] ?? true),
        'samesite' => 'Lax'
      ]
    );
  }

  start_session();

  safe_redirect($redirectTo);
}