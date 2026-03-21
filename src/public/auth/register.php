<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/db.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/auth.php';

$errors = [];
$success = false;

/**
 * Password policy:
 * - at least 10 chars
 * - at least 1 number
 * - at least 1 lowercase
 * - at least 1 uppercase
 * - at least 1 special (non-alphanumeric)
 */
function password_policy_check(string $password): array {
  $issues = [];

  if (strlen($password) < 10) {
    $issues[] = 'at least 10 characters';
  }
  if (!preg_match('/[0-9]/', $password)) {
    $issues[] = 'at least 1 number';
  }
  if (!preg_match('/[a-z]/', $password)) {
    $issues[] = 'at least 1 lowercase letter';
  }
  if (!preg_match('/[A-Z]/', $password)) {
    $issues[] = 'at least 1 uppercase letter';
  }
  if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
    $issues[] = 'at least 1 special character';
  }

  return [
    'ok' => count($issues) === 0,
    'issues' => $issues
  ];
}

/**
 * Returns the default permissions bitmask for a given role.
 * Must stay in sync with the Permissions enum in User.php.
 *
 * Permissions bit positions:
 *   AdminPanel           = 1 << 0 =  1
 *   GradeDataTool        = 1 << 1 =  2
 *   CanvasFormattingTool = 1 << 2 =  4
 *   ReportGenTool        = 1 << 3 =  8
 *   FacultyFormTool      = 1 << 4 = 16
 *   CoordinatorFormTool  = 1 << 5 = 32
 */
function default_permissions_for_role(string $role): int {
  if ($role === 'admin') {
    // Admin gets all permissions
    return (1 << 0) | (1 << 1) | (1 << 2) | (1 << 3) | (1 << 4) | (1 << 5);
  }
  // Faculty gets GradeDataTool + CanvasFormattingTool + FacultyFormTool
  return (1 << 1) | (1 << 2) | (1 << 4);
}

$email = '';
$role = 'faculty';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = strtolower(trim($_POST['email'] ?? ''));
  $password = (string)($_POST['password'] ?? '');
  $confirm = (string)($_POST['confirm_password'] ?? '');
  $role = in_array($_POST['role'] ?? '', ['admin', 'faculty']) ? $_POST['role'] : 'faculty';

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address.';
  }

  // Optional: restrict to ASU emails
  // if (!str_ends_with($email, '@asu.edu')) {
  //   $errors[] = 'Use your ASU email address.';
  // }

  $policy = password_policy_check($password);
  if (!$policy['ok']) {
    $errors[] = 'Password is too weak. It must include: ' . implode(', ', $policy['issues']) . '.';
  }

  if ($password !== $confirm) {
    $errors[] = 'Passwords do not match.';
  }

  if (!$errors) {
    $pdo = db();

    // Check if email exists
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);

    if ($stmt->fetch()) {
      $errors[] = 'An account with that email already exists.';
    } else {
      $hash = password_hash($password, PASSWORD_BCRYPT);
      $permissions = default_permissions_for_role($role);

      $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, role, is_active, permissions) VALUES (?, ?, ?, 1, ?)");
      $stmt->execute([$email, $hash, $role, $permissions]);

      $success = true;
    }
  }
} 
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>ASU ABET Tools | Create Account</title>
  <link rel="icon" type="image/svg" href="/assets/img/favicon.svg" />
  <link href="/assets/css/auth.css" rel="stylesheet">
</head>
<body>

  <div class="register-container">

    <div class="brand-section">
      <div class="brand-content">
        <h2>Join the Community</h2>
        <p>Create your account to start managing ABET accreditation data and tools.</p>
        <div style="width: 60px; height: 4px; background: var(--asu-gold); margin-top: 20px;"></div>
      </div>
    </div>

    <div class="form-section">
      <a href="#" class="help-link">Need Help?</a>

      <div class="form-header">
        <h1>Create Account</h1>
        <p>Please fill in your details below.</p>
      </div>

      <?php if ($success): ?>
        <div class="msg success">
          <strong>Success!</strong> Account created. You can now sign in.
        </div>
        <a href="/login" class="btn-submit" style="display:block; text-align:center; text-decoration:none;">Go to Sign In</a>
      <?php else: ?>

        <?php if ($errors): ?>
          <div class="msg error">
            <?php foreach ($errors as $e): ?>
              <?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?><br>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form id="registerForm" method="post" autocomplete="off" novalidate>
          <div class="form-group">
            <label for="email">Email Address</label>
            <input
              id="email"
              name="email"
              type="email"
              placeholder="asurite@asu.edu"
              required
              value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"
            />
          </div>

          <div class="form-group">
            <label for="role">Role</label>
            <select id="role" name="role">
              <option value="faculty" <?php echo $role === 'faculty' ? 'selected' : ''; ?>>Faculty</option>
              <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Admin</option>
            </select>
          </div>

          <div class="form-group">
            <label for="password">Password</label>
            <div class="password-wrapper">
              <input id="password" name="password" type="password" placeholder="At least 10 characters" required />
              <button type="button" class="toggle-password" onclick="togglePasswordVisibility()" aria-label="Show or hide password">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                  <circle cx="12" cy="12" r="3"></circle>
                </svg>
              </button>
            </div>
          </div>

          <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <input id="confirm_password" name="confirm_password" type="password" placeholder="Re-enter password" required />
          </div>

          <div class="strength-meter-container" id="strengthContainer" style="display:none;">
            <div class="strength-bar">
              <div class="strength-fill" id="strengthFill"></div>
            </div>

            <div class="strength-title" id="strengthTitle">Weak password. Must contain:</div>

            <ul class="strength-list">
              <li id="req-length"><span class="icon"></span> At least 10 characters</li>
              <li id="req-number"><span class="icon"></span> At least 1 number</li>
              <li id="req-lower"><span class="icon"></span> At least 1 lowercase letter</li>
              <li id="req-upper"><span class="icon"></span> At least 1 uppercase letter</li>
              <li id="req-special"><span class="icon"></span> At least 1 special character</li>
            </ul>
          </div>

          <button id="submitBtn" class="btn-submit" type="submit">Create Account</button>

          <div class="footer-links">
            <span>Already have an account?</span>
            <a href="/login">Sign In</a>
          </div>
        </form>

      <?php endif; ?>
    </div>

  </div>

<script>
  function togglePasswordVisibility() {
    const passwordInput = document.getElementById('password');
    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordInput.setAttribute('type', type);
  }

  document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('registerForm');
    const passwordInput = document.getElementById('password');
    const container = document.getElementById('strengthContainer');
    const fill = document.getElementById('strengthFill');
    const title = document.getElementById('strengthTitle');

    if (!form || !passwordInput) return;

    const reqs = {
      length: document.getElementById('req-length'),
      number: document.getElementById('req-number'),
      lower: document.getElementById('req-lower'),
      upper: document.getElementById('req-upper'),
      special: document.getElementById('req-special')
    };

    function evaluatePassword(val) {
      const checks = {
        length: val.length >= 10,
        number: /[0-9]/.test(val),
        lower: /[a-z]/.test(val),
        upper: /[A-Z]/.test(val),
        special: /[^A-Za-z0-9]/.test(val)
      };

      let passedCount = 0;
      for (const [key, element] of Object.entries(reqs)) {
        if (checks[key]) {
          element.classList.add('valid');
          passedCount++;
        } else {
          element.classList.remove('valid');
        }
      }

      const strengthPercent = (passedCount / 5) * 100;
      fill.style.width = strengthPercent + '%';

      if (passedCount <= 2) {
        fill.style.backgroundColor = '#d32f2f';
        title.textContent = 'Weak password. Must contain:';
      } else if (passedCount < 5) {
        fill.style.backgroundColor = '#FFC627';
        title.textContent = 'Medium password. Must contain:';
      } else {
        fill.style.backgroundColor = '#2e7d32';
        title.textContent = 'Strong password.';
      }

      return passedCount === 5;
    }

    passwordInput.addEventListener('input', function() {
      const val = passwordInput.value;
      if (val.length > 0) {
        container.style.display = 'block';
      } else {
        container.style.display = 'none';
      }
      evaluatePassword(val);
    });

    form.addEventListener('submit', function(e) {
      const isStrong = evaluatePassword(passwordInput.value);
      if (!isStrong) {
        e.preventDefault();
        container.style.display = 'block';
        passwordInput.focus();
      }
    });
  });
</script>

</body>
</html>