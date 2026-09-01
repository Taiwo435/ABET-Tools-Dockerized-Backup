<?php
declare(strict_types=1);

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use App\Entity\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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

function start_session_basic(SessionInterface $session): void
{
    if ($session->isStarted()) {
        return;
    }

    $session->start();
}


function start_session(SessionInterface $session): void {
  start_session_basic($session);
  enforce_session_timeouts($session);
}

function enforce_session_timeouts(SessionInterface $session): void
{
    // Do not start the session here (prevents recursion)
    if (!$session->isStarted()) {
        return;
    }

    $now = time();

    $idle = defined('SESSION_IDLE_TIMEOUT')
        ? (int) SESSION_IDLE_TIMEOUT
        : (30 * 60);

    $absolute = defined('SESSION_ABSOLUTE_TIMEOUT')
        ? (int) SESSION_ABSOLUTE_TIMEOUT
        : (8 * 60 * 60);

    $createdAt = $session->get('created_at');
    $lastActivity = $session->get('last_activity');

    if ($createdAt === null) {
        $createdAt = $now;
        $session->set('created_at', $createdAt);
    }

    if ($lastActivity === null) {
        $lastActivity = $now;
        $session->set('last_activity', $lastActivity);
    }

    // Absolute timeout
    if (($now - (int) $createdAt) > $absolute) {
        logout($session, '/login?reason=timeout');
        return;
    }

    // Idle timeout
    if (($now - (int) $lastActivity) > $idle) {
        logout($session, '/login?reason=idle');
        return;
    }

    $session->set('last_activity', $now);
}

function is_logged_in(SessionInterface $session): bool {
  start_session($session);
  return !empty($_SESSION['user_id']);
}

function require_login(
    SessionInterface $session,
    string $redirectTo = '/login'
): void {
    start_session($session);

    $userId = $session->get('user_id');

    if (empty($userId)) {
        safe_redirect($redirectTo);
        return;
    }

    // Verify the user still exists AND refresh cached permissions/active
    // status from the database on every request.
    //
    // user_permissions is only set at login time, so without refreshing it,
    // permission changes made while a user is already logged in would not
    // be recognized by legacy pages using require_role() until logout/login.

    require_once getenv('ABET_PRIVATE_DIR') . '/lib/db.php';

    $stmt = db()->prepare(
        'SELECT permissions, is_active
         FROM users
         WHERE id = :id
         LIMIT 1'
    );

    $stmt->execute([
        'id' => (int) $userId,
    ]);

    $row = $stmt->fetch();

    if (!$row) {
        logout($session, '/login?reason=deleted');
        return;
    }

    if ((int) $row['is_active'] !== 1) {
        logout($session, '/login?reason=deactivated');
        return;
    }

    // Refresh cached permissions from the database.
    $session->set('user_permissions', (int) $row['permissions']);
}


/**
 * Requires the logged-in user to hold the given permission, identified by
 * the Permissions enum case name (e.g. 'ROLE_FACULTY_FORM', 'ROLE_ADMIN').
 * An admin always passes, mirroring User::hasPermission()'s short-circuit.
 * Fails closed (403) on an unrecognized role name.
 */
function require_role(SessionInterface $session, string $role): void {
  require_login($session);

  $permissions = (int)$session->get('user_permissions', 0);
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
    throw new AccessDeniedHttpException("Forbidden");
  }
}


function logout(SessionInterface $session, string $redirectTo = '/login'): void
{
    // Clear all session data
    $session->clear();

    // Invalidate the session
    $session->invalidate();

    safe_redirect($redirectTo);
}
