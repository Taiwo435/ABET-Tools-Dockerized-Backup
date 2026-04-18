<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/security_headers.php';
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

