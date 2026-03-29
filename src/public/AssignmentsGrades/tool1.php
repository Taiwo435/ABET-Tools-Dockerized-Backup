<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/csrf.php';
require_login();
$csrfToken = csrf_token('tool1_proxy');
$role = $_SESSION['user_role'] ?? 'faculty';
$config = require getenv('ABET_PRIVATE_DIR') . '/config.php';
$destCourses = $config['dest_courses'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Connect to Class | ABET Tools</title>
  <link rel="stylesheet" href="/assets/css/tool1.css">

</head>
<body>

  <header class="site-header">
    <div class="site-title">ABET Tools - Connect to Class</div>
    <div class="header-actions">
      <a href="jobs.php" class="nav-link">View Jobs</a>
      <a href="/../index.php" class="nav-link">← Back to Dashboard</a>
    </div>
  </header>

  <div class="main-container">
    
    <div class="page-header">
      <div class="page-title">
        <h1>Connect to Canvas Class</h1>
        <p>Enter your Canvas Access Token</p>
      </div>
    </div>

      <div>
        <form id="canvasConnectionForm">
          <div class="form-grid">
            <div class="form-group" style="grid-column: 1 / -1;">
              <label class="form-label">
                Canvas Access Token <span class="required">*</span>
              </label>
              <input type="password" class="form-input" id="classToken" placeholder="Paste your Canvas access token" required>
              <div class="form-help">Generate at Canvas → Account → Settings → Approved Integrations → New Access Token</div>
            </div>
            <div class="form-group">
              <label class="form-label">
                Source Course ID <span class="required">*</span>
              </label>
              <input type="text" class="form-input" id="sourceCourseId" placeholder="e.g., 240102" required
                     pattern="\d+" title="Numeric Course ID only">
              <div class="form-help">The canvas course to extract data from.</div>
              <div class="form-help">Look at url for the id, eg.: https://canvas.asu.edu/courses/<span style="font-weight: bold;">240102</span></div>
            </div>
            <div class="form-group">
              <label class="form-label">
                Destination Course ID <span class="required">*</span>
              </label>

              <?php if ($role === 'admin'): ?>
                <input type="text" class="form-input" id="destCourseId" placeholder="e.g., 240102" required
                       pattern="\d+" title="Numeric Course ID only">
                <div class="form-help">The canvas course to push data into</div>
              <?php else: ?>
                <select class="form-input" id="destCourseId" required>
                  <option value="" disabled selected>Select the destination course</option>
                  <?php foreach ($destCourses as $course): ?>
                    <option value="<?= htmlspecialchars($course['id'], ENT_QUOTES, 'UTF-8') ?>">
                      <?= htmlspecialchars($course['label'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars($course['id'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <div class="form-help">Select your program's destination course</div>
              <?php endif; ?>

            </div>
          </div>
        </div>

          <button type="submit" class="btn btn-primary" id="connectBtn">
            <span class="btn-icon">🔗</span> Validate Token
          </button>

    <div class="alert alert-success" id="successAlert">
      <div class="alert-content">
        <strong>Successfully connected!</strong>
        <div style="margin-top: 15px;">
          <a href="select-courses.php" class="btn btn-primary">Continue to Select Courses →</a>
        </div>
      </div>
    </div>

    <div class="alert alert-error" id="errorAlert">
      <div class="alert-content">
        <strong>Connection failed</strong>
        <span id="errorMessage"></span>
      </div>
    </div>

    <div class = "info-banner">
       <span>Your token is used only to fetch your courses from Canvas. It is not stored permanently.</span>
    </div>
  </div>

  <script>
    const form = document.getElementById('canvasConnectionForm');
    const connectBtn = document.getElementById('connectBtn');
    const successAlert = document.getElementById('successAlert');
    const errorAlert = document.getElementById('errorAlert');

    function showError(msg) {
      successAlert.style.display = 'none';
      errorAlert.style.display = 'block';
      document.getElementById('errorMessage').textContent =
        typeof msg === 'string' ? msg : JSON.stringify(msg);
      errorAlert.scrollIntoView({ behavior: 'smooth' });
    }

    function showSuccess() {
      errorAlert.style.display = 'none';
      successAlert.style.display = 'block';
      successAlert.scrollIntoView({ behavior: 'smooth' });
    }

    form.addEventListener('submit', async (e) => {

      e.preventDefault();
      const token    = document.getElementById('classToken').value.trim();
      if (!token) {
        showError('All fields are required.');
        return;
      }

      let csrfToken = '<?= htmlspecialchars($csrfToken, ENT_QUOTES, "UTF-8") ?>';

      connectBtn.disabled = true;
      connectBtn.textContent = 'Verifying…';
      successAlert.style.display = 'none';
      errorAlert.style.display = 'none';

      try {
        const storeBody = new FormData();
        storeBody.append('action', 'store-credentials');
        storeBody.append('canvas_token', token);
        storeBody.append('csrf_token', csrfToken);

        const storeRes = await fetch('api-proxy.php', { method: 'POST', body: storeBody });
        const storeData = await storeRes.json();
        if (storeData.next_csrf) csrfToken = storeData.next_csrf;
        if (!storeData.success) {
          showError(storeData.message || 'Failed to store credentials.');
          return;
        }

        const verifyToken = new FormData();
        verifyToken.append('action', 'verify-token');
        verifyToken.append('csrf_token', csrfToken);
        const res = await fetch('api-proxy.php', { method: 'POST', body: verifyToken});
        const data = await res.json();
        if (data.next_csrf) csrfToken = data.next_csrf;
        if (!data.success) {
          showError(data.message || 'Failed to connect to endpoint given token');
          return;
        }
        showSuccess();

      } catch (err) {
        showError('Network error: ' + err.message);
      } finally {
        connectBtn.disabled = false;
        connectBtn.textContent = '🔗 Verify & Connect';
      }
    });
  </script>

</body>
</html>