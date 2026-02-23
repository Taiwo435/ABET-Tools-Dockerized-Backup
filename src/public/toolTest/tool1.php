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
      display: flex;
      align-items: flex-start;
      gap: 15px;
      border-left: 4px solid;
    }

    

    .alert-success {
      background: #e6f4ea;
      border-color: var(--success);
      color: #0a5020;
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
    <a href="roster-dashboard.html" class="nav-link">← Back to Dashboard</a>
  </header>

  <div class="main-container">
    
    <div class="page-header">
      <div class="page-title">
        <h1>Connect to Canvas Class</h1>
        <p>Enter your Canvas credentials to access class data</p>
      </div>
    </div>

      

        <form id="canvasConnectionForm">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">
                Canvas Class Token <span class="required">*</span>
              </label>
              <input type="text" class="form-input" id="classToken" placeholder="Enter Class token" required>
            </div>
            <div class="form-group">
              <label class="form-label">
                Canvas Class ID <span class="required">*</span>
              </label>
              <input type="text" class="form-input" id="classId" placeholder="e.g., 12345" required>
            </div>
          </div>

          <button type="submit" class="btn btn-primary">
            <span class="btn-icon">🔗</span> Connect
          </button>
        </form>

        <div class="divider">
          <span class="divider-text">OR</span>
        </div>

        <!-- Existing Classes -->
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

    <!-- Connection Status (hidden initially) -->
    <div class="alert alert-success" id="successAlert" style="display: none;">
      <div class="alert-icon">✅</div>
      <div class="alert-content">
        <strong>Successfully connected!</strong>
        <div style="margin-top: 5px;">
          <strong>Class:</strong> <span id="connectedClass"></span><br>
          <strong>Students:</strong> <span id="studentCount"></span>
        </div>
        <div style="margin-top: 15px;">
          <a href="roster-upload.html" class="btn btn-primary">Continue to Roster Management →</a>
        </div>
      </div>
    </div>

  </div>

  <script>
    // Handle form submission
    document.getElementById('canvasConnectionForm').addEventListener('submit', function(e) {
      e.preventDefault();
      
      const token = document.getElementById('classToken').value;
      const classId = document.getElementById('classId').value;
      
      if (!token || !classId) {
        alert('Please enter both Canvas Token and Class ID');
        return;
      }
      
      // Simulate connection
      setTimeout(() => {
        document.getElementById('connectedClass').textContent = 'CSE 445 - Software Security (Fall 2024)';
        document.getElementById('studentCount').textContent = '48';
        document.getElementById('successAlert').style.display = 'flex';
        
        // Scroll to success message
        document.getElementById('successAlert').scrollIntoView({ behavior: 'smooth' });
      }, 1000);
    });

    function selectExistingClass() {
      const select = document.getElementById('existingClass');
      const value = select.value;
      
      if (value) {
        const text = select.options[select.selectedIndex].text;
        
        // Simulate loading existing class
        setTimeout(() => {
          document.getElementById('connectedClass').textContent = text;
          document.getElementById('studentCount').textContent = '48';
          document.getElementById('successAlert').style.display = 'flex';
          
          // Scroll to success message
          document.getElementById('successAlert').scrollIntoView({ behavior: 'smooth' });
        }, 500);
      }
    }
  </script>

</body>
</html>

