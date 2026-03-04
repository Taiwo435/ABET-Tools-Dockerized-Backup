<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/csrf.php';
require_login();
$csrfToken = csrf_token('tool1_proxy');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Connect to Class | ABET Tools</title>
  <style>
    :root {
      --asu-maroon: #8C1D40;
      --asu-gold: #FFC627;
      --text-dark: #222;
      --text-light: #555;
      --bg-color: #F9F9F9;
      --border-color: #E0E0E0;
      --success: #0a7d30;
      --error: #d32f2f;
      --info: #1976d2;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      background: var(--bg-color);
      color: var(--text-dark);
      margin: 0;
      padding: 0;
    }

    .site-header {
      background: var(--asu-maroon);
      color: white;
      padding: 1rem 2rem;
      border-bottom: 4px solid var(--asu-gold);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .site-title {
      font-weight: 700;
      font-size: 1.25rem;
    }

    .nav-link {
      color: rgba(255,255,255,0.9);
      text-decoration: none;
      font-size: 0.9rem;
    }

    .main-container {
      max-width: 1000px;
      margin: 40px auto;
      padding: 0 20px;
    }

    .page-header {
      margin-bottom: 30px;
    }

    .page-title h1 {
      margin: 0;
      font-size: 1.75rem;
      color: var(--asu-maroon);
    }

    .page-title p {
      margin: 10px 0 0;
      color: var(--text-light);
    }

    .card {
      background: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
      border: 1px solid var(--border-color);
      margin-bottom: 20px;
      overflow: hidden;
    }

    .card-header {
      padding: 20px;
      border-bottom: 1px solid var(--border-color);
      background: #fafafa;
    }

    .card-title {
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--text-dark);
      margin: 0;
    }

    .card-subtitle {
      font-size: 0.85rem;
      color: var(--text-light);
      margin-top: 5px;
    }

    .card-body {
      padding: 30px;
    }

    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      margin-bottom: 20px;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-label {
      display: block;
      font-size: 0.85rem;
      font-weight: 700;
      text-transform: uppercase;
      color: var(--text-light);
      margin-bottom: 8px;
    }

    .required {
      color: #d32f2f;
    }

    .form-input,
    .form-select {
      width: 100%;
      padding: 12px 15px;
      border: 1px solid var(--border-color);
      border-radius: 6px;
      font-size: 0.95rem;
      transition: border-color 0.2s;
      box-sizing: border-box;
    }

    .form-input:focus,
    .form-select:focus {
      outline: none;
      border-color: var(--asu-maroon);
      box-shadow: 0 0 0 3px rgba(140, 29, 64, 0.1);
    }

    .form-help {
      font-size: 0.8rem;
      color: var(--text-light);
      margin-top: 5px;
    }

    .btn {
      padding: 12px 24px;
      border-radius: 6px;
      font-weight: 600;
      font-size: 0.9rem;
      border: none;
      cursor: pointer;
      transition: all 0.2s;
      text-decoration: none;
      display: inline-block;
    }

    .btn-primary {
      background: var(--asu-maroon);
      color: white;
    }

    .btn-primary:hover {
      background: #5c132a;
    }

    .btn-secondary {
      background: white;
      color: var(--asu-maroon);
      border: 1px solid var(--asu-maroon);
    }

    .btn-icon {
      margin-right: 8px;
    }

    .alert {
      padding: 15px 20px;
      border-radius: 6px;
      margin-bottom: 20px;
      display: none;
      align-items: flex-start;
      gap: 15px;
      border-left: 4px solid;
    }

    .alert-success {
      background: #e6f4ea;
      border-color: var(--success);
      color: #0a5020;
    }

    .alert-error {
      background: #fdecea;
      border-color: var(--error);
      color: #5f1412;
    }

    .alert-icon {
      font-size: 1.5rem;
    }

    .alert-content {
      flex: 1;
    }

    .instructions {
      background: #f9f9f9;
      border-left: 4px solid var(--asu-maroon);
      padding: 20px;
      border-radius: 6px;
      margin-bottom: 20px;
    }

    .instructions h4 {
      margin-top: 0;
      color: var(--asu-maroon);
      font-size: 1rem;
    }

    .instructions ol {
      margin: 10px 0 0 0;
      padding-left: 20px;
    }

    .instructions li {
      margin-bottom: 8px;
      line-height: 1.5;
    }

    .divider {
      text-align: center;
      margin: 30px 0;
      position: relative;
    }

    .divider::before {
      content: '';
      position: absolute;
      left: 0;
      right: 0;
      top: 50%;
      height: 1px;
      background: var(--border-color);
    }

    .divider-text {
      background: white;
      padding: 0 15px;
      position: relative;
      color: var(--text-light);
      font-weight: 600;
      font-size: 0.9rem;
    }

    @media (max-width: 768px) {
      .form-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>

  <header class="site-header">
    <div class="site-title">ABET Tools - Connect to Class</div>
    <a href="/../index.php" class="nav-link">← Back to Dashboard</a>
  </header>

  <div class="main-container">
    
    <div class="page-header">
      <div class="page-title">
        <h1>Connect to Canvas Class</h1>
        <p>Enter your Canvas Access Token</p>
      </div>
    </div>

      

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
              <input type="text" class="form-input" id="destCourseId" placeholder="e.g., 240102" required
                     pattern="\d+" title="Numeric Course ID only">
              <div class="form-help">The canvas course to push data into</div>
            </div>
          </div>

          <button type="submit" class="btn btn-primary" id="connectBtn">
            <span class="btn-icon">🔗</span> Verify & Connect
          </button>
        <!-- </form>
                <div class="divider">
          <span class="divider-text">OR</span>
        </div>

        Existing Classes
        <div class="form-group">
          <label class="form-label">Select from Previously Connected Classes</label>
          <select class="form-select" id="existingClass" onchange="selectExistingClass()">
            <option value="">-- Choose a class --</option>
            <option value="cse445-fall24">CSE 445 - Software Security (Fall 2024)</option>
            <option value="cse310-fall24">CSE 310 - Data Structures (Fall 2024)</option>
          </select>
          <div class="form-help">Previously connected classes in this semester</div>
        </div>

      </div>
    </div>

    Connection Status (hidden initially)
    <div class="alert alert-success" id="successAlert" style="display: none;">

        </form> -->

    <div class="alert alert-success" id="successAlert">
      <div class="alert-content">
        <strong>Successfully connected!</strong>
        <div style="margin-top: 5px;">
          <strong>Course:</strong> <span id="connectedCourse"></span><br>
          <strong>Term:</strong> <span id="connectedTerm"></span>
        </div>
        <div style="margin-top: 15px;">
          <a href="roster-upload.php" class="btn btn-primary">Continue to Roster Upload →</a>
        </div>
      </div>
    </div>

    <div class="alert alert-error" id="errorAlert">
      <div class="alert-content">
        <strong>Connection failed</strong>
        <span id="errorMessage"></span>
      </div>
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

    function showSuccess(course) {
      errorAlert.style.display = 'none';
      document.getElementById('connectedCourse').textContent =
        course.name + ' (' + course.course_code + ')';
      document.getElementById('connectedTerm').textContent =
        course.term || 'N/A';
      successAlert.style.display = 'block';
      successAlert.scrollIntoView({ behavior: 'smooth' });
    }

    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      const token    = document.getElementById('classToken').value.trim();
      const sourceId = document.getElementById('sourceCourseId').value.trim();
      const destId   = document.getElementById('destCourseId').value.trim();

      if (!token || !sourceId || !destId) {
        showError('All fields are required.');
        return;
      }

    //   // Simulate connection
    //   setTimeout(() => {
    //     const classInfo = {
    //       name: 'CSE 445 - Software Security',
    //       semester: 'Fall 2024',
    //       studentCount: '48',
    //       token: token,
    //       classId: classId
    //     };
        
    //     // Save to localStorage
    //     localStorage.setItem('selectedClass', JSON.stringify(classInfo));
        
    //     document.getElementById('connectedClass').textContent = classInfo.name + ' (' + classInfo.semester + ')';
    //     document.getElementById('studentCount').textContent = classInfo.studentCount;
    //     document.getElementById('successAlert').style.display = 'flex';
        
    //     // Scroll to success message
    //     document.getElementById('successAlert').scrollIntoView({ behavior: 'smooth' });
    //   }, 1000);
    // });

    // function selectExistingClass() {
    //   const select = document.getElementById('existingClass');
    //   const value = select.value;
      
    //   if (value) {
    //     const text = select.options[select.selectedIndex].text;
        
    //     // Parse class info from dropdown text
    //     const match = text.match(/^(.+?)\s*\((.+?)\)$/);
    //     let className = text;
    //     let semester = 'Fall 2024';
        
    //     if (match) {
    //       className = match[1].trim();
    //       semester = match[2].trim();
    //     }
        
    //     // Simulate loading existing class
    //     setTimeout(() => {
    //       const classInfo = {
    //         name: className,
    //         semester: semester,
    //         studentCount: '48',
    //         token: 'existing_token',
    //         classId: value
    //       };
          
    //       // Save to localStorage
    //       localStorage.setItem('selectedClass', JSON.stringify(classInfo));
          
    //       document.getElementById('connectedClass').textContent = text;
    //       document.getElementById('studentCount').textContent = classInfo.studentCount;
    //       document.getElementById('successAlert').style.display = 'flex';
          
    //       // Scroll to success message
    //       document.getElementById('successAlert').scrollIntoView({ behavior: 'smooth' });
    //     }, 500);
    //   }
    // }
      let csrfToken = '<?= htmlspecialchars($csrfToken, ENT_QUOTES, "UTF-8") ?>';

      connectBtn.disabled = true;
      connectBtn.textContent = 'Verifying…';
      successAlert.style.display = 'none';
      errorAlert.style.display = 'none';

      try {
        const storeBody = new FormData();
        storeBody.append('action', 'store-credentials');
        storeBody.append('canvas_token', token);
        storeBody.append('source_course_id', sourceId);
        storeBody.append('dest_course_id', destId);
        storeBody.append('csrf_token', csrfToken);

        const storeRes = await fetch('api-proxy.php', { method: 'POST', body: storeBody });
        const storeData = await storeRes.json();
        if (storeData.next_csrf) csrfToken = storeData.next_csrf;
        if (!storeData.success) {
          showError(storeData.message || 'Failed to store credentials.');
          return;
        }

        const verifyBody = new FormData();
        verifyBody.append('action', 'verify-course');
        verifyBody.append('csrf_token', csrfToken);

        const verifyRes = await fetch('api-proxy.php', { method: 'POST', body: verifyBody });
        const verifyData = await verifyRes.json();
        if (verifyData.next_csrf) csrfToken = verifyData.next_csrf;
        if (!verifyData.success) {
          showError(verifyData.message || 'Course verification failed.');
          return;
        }

        showSuccess(verifyData.course);

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