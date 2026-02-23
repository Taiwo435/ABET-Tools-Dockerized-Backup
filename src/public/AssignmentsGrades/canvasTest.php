<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/auth.php';
require_login();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Generate CSRF token if needed for the form submission
if (empty($_SESSION['csrf_canvas_token'])) {
    $_SESSION['csrf_canvas_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_canvas_token'];

$displayName = $_SESSION['display_name'] ?? $_SESSION['name'] ?? $_SESSION['email'] ?? 'Account';

function e(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Canvas Course</title>
  <style>
    :root {
      --asu-maroon: #8C1D40;
      --asu-gold: #FFC627;
      --asu-rich-black: #191919;
      --asu-dark-gray: #484848;

      --bg-body: #F4F5F7;
      --bg-card: #FFFFFF;
      --text-main: #191919;
      --text-muted: #5C6670;
      --border-light: #E0E0E0;
      --border-focus: #8C1D40;

      --state-success: #1f8f4e;
      --state-success-bg: #e8f5e9;
      --state-error: #b42318;
      --state-error-bg: #fdf2f2;
      --state-info: #00558C;
      --state-info-bg: #eef7fc;
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      color: var(--text-main);
      background-color: var(--bg-body);
      min-height: 100vh;
      line-height: 1.5;
    }

    .topbar {
      height: 72px;
      border-bottom: 4px solid var(--asu-gold);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 24px;
      background-color: var(--asu-maroon);
      color: white;
      position: sticky;
      top: 0;
      z-index: 50;
      box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }

    .back-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
      color: #fff;
      font-size: 14px;
      font-weight: 500;
      opacity: 0.9;
      transition: opacity 0.2s;
    }
    .back-btn:hover { opacity: 1; text-decoration: underline; }

    .profile-wrap { position: relative; }

    .profile-btn {
      border: 1px solid rgba(255,255,255,0.3);
      background: rgba(0,0,0,0.2);
      color: #fff;
      padding: 8px 16px;
      border-radius: 4px;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 10px;
      font-weight: 500;
      font-size: 14px;
      transition: background 0.2s;
    }
    .profile-btn:hover { background: rgba(0,0,0,0.4); }

    .avatar {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      background-color: var(--asu-gold);
      color: var(--asu-maroon);
      display: grid;
      place-items: center;
      font-size: 13px;
      font-weight: 700;
    }

    .container {
      max-width: 800px;
      margin: 48px auto;
      padding: 0 20px;
    }

    .card {
      background: var(--bg-card);
      border: 1px solid var(--border-light);
      border-radius: 6px;
      padding: 40px;
      box-shadow: 0 2px 15px rgba(0,0,0,0.05);
      position: relative;
    }
    .card::before {
      content: "";
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 6px;
      background: linear-gradient(90deg, var(--asu-maroon) 0%, var(--asu-maroon) 85%, var(--asu-gold) 85%, var(--asu-gold) 100%);
      border-radius: 6px 6px 0 0;
    }

    .header-section {
      text-align: center;
      margin-bottom: 32px;
    }

    h1 {
      margin: 0 0 8px;
      font-size: 28px;
      color: var(--asu-maroon);
      font-weight: 700;
      letter-spacing: -0.5px;
    }

    .sub {
      margin: 0 auto;
      color: var(--text-muted);
      font-size: 15px;
      max-width: 650px;
      line-height: 1.6;
    }

    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      margin-bottom: 24px;
    }

    .form-group {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .form-group label {
      font-size: 14px;
      font-weight: 600;
      color: var(--text-main);
    }

    .form-control {
      padding: 10px 14px;
      border: 1px solid var(--border-light);
      border-radius: 4px;
      font-size: 15px;
      font-family: inherit;
      transition: border-color 0.2s;
      background-color: #FAFAFA;
    }
    .form-control:focus {
      outline: none;
      border-color: var(--asu-maroon);
      background-color: #FFF;
      box-shadow: 0 0 0 3px rgba(140, 29, 64, 0.1);
    }

    .token-row {
      grid-column: 1 / -1;
    }

    .token-note {
      margin-top: 4px;
      font-size: 12px;
      color: var(--text-muted);
      line-height: 1.4;
    }

    .checkbox-group {
      display: flex;
      gap: 24px;
      margin-bottom: 32px;
      padding: 16px;
      background: #FAFAFA;
      border: 1px solid var(--border-light);
      border-radius: 4px;
    }

    .checkbox-item {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 14px;
      font-weight: 500;
      color: var(--text-main);
      cursor: pointer;
    }

    .checkbox-item input[type="checkbox"] {
      width: 18px;
      height: 18px;
      accent-color: var(--asu-maroon);
      cursor: pointer;
    }

    .btn {
      width: 100%;
      border: 1px solid transparent;
      padding: 14px 24px;
      border-radius: 4px;
      font-weight: 600;
      font-size: 16px;
      text-decoration: none;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      transition: all 0.2s;
    }
    .btn:disabled { opacity: 0.6; cursor: not-allowed; filter: grayscale(1); }

    .btn.primary {
      background: var(--asu-maroon);
      color: #fff;
      box-shadow: 0 2px 4px rgba(140, 29, 64, 0.2);
    }
    .btn.primary:hover:not(:disabled) { background: #60132C; transform: translateY(-1px); }

    .results-section {
      margin-top: 40px;
      display: none;
      border-top: 1px solid var(--border-light);
      padding-top: 32px;
    }
    .results-section.show {
      display: block;
    }

    .status-banner {
      padding: 12px 16px;
      border-radius: 4px;
      font-size: 15px;
      font-weight: 600;
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .status-banner.success {
      background: var(--state-success-bg);
      color: #0e4e2a;
      border: 1px solid #c8e6c9;
    }
    .status-banner.error {
      background: var(--state-error-bg);
      color: #7a160e;
      border: 1px solid #ffcdd2;
    }
    .status-banner.running {
      background: var(--state-info-bg);
      color: #0c3d5d;
      border: 1px solid #b3e5fc;
    }

    .log-output {
      background: #1e1e1e;
      color: #d4d4d4;
      font-family: 'Courier New', Courier, monospace;
      font-size: 13px;
      padding: 16px;
      border-radius: 4px;
      max-height: 350px;
      overflow-y: auto;
      white-space: pre-wrap;
      line-height: 1.4;
      border: 1px solid #000;
    }

    @media (max-width: 600px) {
      .card { padding: 24px; }
      .form-grid { grid-template-columns: 1fr; }
      .checkbox-group { flex-direction: column; gap: 12px; }
      .token-row { grid-column: auto; }
    }
  </style>
</head>
<body>
  <header class="topbar">
    <a class="back-btn" href="/index.php">
      <span>&larr;</span> Back to Home
    </a>

    <div class="profile-wrap">
      <button class="profile-btn" id="profileBtn" type="button">
        <span class="avatar"><?php echo strtoupper(substr(e($displayName), 0, 1)); ?></span>
        <span><?php echo e($displayName); ?></span>
        <span>&#9662;</span>
      </button>
    </div>
  </header>

  <main class="container">
    <section class="card">
      <div class="header-section">
        <h1>Canvas Course</h1>
        <p class="sub">Generates and uploads standardized Canvas course pages and modules for ABET accreditation demonstration.</p>
      </div>

      <form id="generatorForm" method="post" novalidate>
        <input type="hidden" name="csrf_canvas_token" id="csrf_canvas_token" value="<?php echo e($csrfToken); ?>">

        <div class="form-grid">
          <div class="form-group">
            <label for="sourceCourse">Source Canvas Course ID</label>
            <input type="text" id="sourceCourse" name="sourceCourse" class="form-control" value="12345" placeholder="e.g. 12345" required>
          </div>

          <div class="form-group">
            <label for="destCourse">Destination Canvas Course ID (Sandbox)</label>
            <input type="text" id="destCourse" name="destCourse" class="form-control" value="98765" placeholder="e.g. 98765" required>
          </div>

          <div class="form-group token-row">
            <label for="canvasToken">Canvas Access Token (Testing Only)</label>
            <input type="password" id="canvasToken" name="canvasToken" class="form-control" placeholder="Paste Canvas Personal Access Token" autocomplete="off" spellcheck="false" required>
            <div class="token-note">Testing only. Token is masked in the UI and should never be displayed in logs.</div>
          </div>

          <div class="form-group">
            <label for="semester">Semester</label>
            <select id="semester" name="semester" class="form-control" required>
              <option value="Fall">Fall</option>
              <option value="Spring" selected>Spring</option>
              <option value="Summer">Summer</option>
            </select>
          </div>

          <div class="form-group">
            <label for="year">Year</label>
            <input type="number" id="year" name="year" class="form-control" value="2025" required>
          </div>
        </div>

        <div class="checkbox-group">
          <label class="checkbox-item">
            <input type="checkbox" id="genCoursePage" name="genCoursePage" checked>
            Generate Course Page
          </label>
          <label class="checkbox-item">
            <input type="checkbox" id="genAbetPage" name="genAbetPage" checked>
            Generate ABET Page
          </label>
        </div>

        <button type="submit" id="generateBtn" class="btn primary">Generate and Upload to Canvas</button>
      </form>

      <div id="resultsSection" class="results-section">
        <div id="statusBanner" class="status-banner running">
          Initializing process...
        </div>
        <div id="logOutput" class="log-output"></div>
      </div>
    </section>
  </main>

<script>
  const form = document.getElementById('generatorForm');
  const generateBtn = document.getElementById('generateBtn');
  const resultsSection = document.getElementById('resultsSection');
  const statusBanner = document.getElementById('statusBanner');
  const logOutput = document.getElementById('logOutput');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    generateBtn.disabled = true;
    generateBtn.textContent = 'Processing...';
    resultsSection.classList.add('show');
    logOutput.textContent = '';

    setStatus('running', 'Process running...');
    appendLog('> Starting Canvas & ABET Page Generation...\n');

    try {
      const formData = new FormData(form);

      // Basic client-side check (token field is required for testing)
      const token = (document.getElementById('canvasToken')?.value || '').trim();
      if (!token) {
        throw new Error('Canvas token is required for testing.');
      }

      appendLog('> Submitting request to backend...\n');
      appendLog('> Canvas token received (hidden).\n');

      const response = await fetch('/AssignmentsGrades/runCanvasGenerator.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      let data;
      try {
        data = await response.json();
      } catch (jsonErr) {
        throw new Error('Server returned a non-JSON response.');
      }

      if (data.stdout) {
        appendLog(data.stdout.endsWith('\n') ? data.stdout : data.stdout + '\n');
      }

      if (data.stderr) {
        appendLog('\n[stderr]\n');
        appendLog(data.stderr.endsWith('\n') ? data.stderr : data.stderr + '\n');
      }

      if (!response.ok || !data.success) {
        if (Array.isArray(data.errors) && data.errors.length) {
          appendLog('\nValidation errors:\n- ' + data.errors.join('\n- ') + '\n');
        }
        throw new Error(data.message || 'Process failed.');
      }

      setStatus('success', 'Completed Successfully');
      appendLog('\n> Ready.\n');

    } catch (err) {
      setStatus('error', 'Failed');
      appendLog(`\n> Error: ${err.message}\n`);
    } finally {
      generateBtn.disabled = false;
      generateBtn.textContent = 'Generate and Upload to Canvas';
    }
  });

  function setStatus(type, message) {
    statusBanner.className = 'status-banner ' + type;
    let icon = '';
    if (type === 'success') icon = '✓ ';
    if (type === 'error') icon = '⚠ ';
    if (type === 'running') icon = '↻ ';
    statusBanner.textContent = icon + message;
  }

  function appendLog(text) {
    logOutput.textContent += text;
    logOutput.scrollTop = logOutput.scrollHeight;
  }
</script>
</body>
</html>