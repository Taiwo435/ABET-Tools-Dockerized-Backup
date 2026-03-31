<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/security_headers.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manage Roster | ABET Tools</title>
  <style>
    :root {
      --asu-maroon: #8C1D40;
      --asu-gold: #FFC627;
      --text-dark: #222;
      --text-light: #555;
      --bg-color: #F9F9F9;
      --border-color: #E0E0E0;
      --success: #0a7d30;
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
      max-width: 1400px;
      margin: 40px auto;
      padding: 0 20px;
    }

    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
    }

    .page-title h1 {
      margin: 0;
      font-size: 1.75rem;
      color: var(--asu-maroon);
    }

    .page-title p {
      margin: 5px 0 0;
      color: var(--text-light);
    }

    .card {
      background: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
      border: 1px solid var(--border-color);
      margin-bottom: 20px;
    }

    .card-header {
      padding: 20px;
      border-bottom: 1px solid var(--border-color);
      background: #fafafa;
    }

    .card-title {
      font-size: 1.1rem;
      font-weight: 700;
      margin: 0;
    }

    .card-body {
      padding: 30px;
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
    }

    .instructions ol {
      margin: 10px 0 0 0;
      padding-left: 20px;
    }

    .instructions li {
      margin-bottom: 8px;
      line-height: 1.5;
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

    .btn-group {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-bottom: 20px;
    }

    .file-upload {
      border: 2px dashed var(--border-color);
      border-radius: 8px;
      padding: 40px;
      text-align: center;
      background: #fafafa;
      cursor: pointer;
      transition: all 0.2s;
    }

    .file-upload:hover {
      border-color: var(--asu-maroon);
      background: #f5f5f5;
    }

    .file-upload-icon {
      font-size: 3rem;
      color: var(--asu-maroon);
      margin-bottom: 15px;
    }

    .file-upload-text {
      font-size: 1.1rem;
      color: var(--text-dark);
      margin-bottom: 5px;
    }

    .file-upload-hint {
      font-size: 0.9rem;
      color: var(--text-light);
    }

    input[type="file"] {
      display: none;
    }

    .stats-mini {
      display: flex;
      gap: 20px;
      margin: 20px 0;
      flex-wrap: wrap;
    }

    .stat-mini {
      background: white;
      padding: 15px 20px;
      border-radius: 6px;
      border-left: 3px solid var(--asu-maroon);
      box-shadow: 0 1px 5px rgba(0,0,0,0.05);
    }

    .stat-mini-label {
      font-size: 0.7rem;
      text-transform: uppercase;
      color: var(--text-light);
      font-weight: 700;
    }

    .stat-mini-value {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--asu-maroon);
      margin-top: 5px;
    }

    .table-responsive {
      overflow-x: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th {
      background: #fcfcfc;
      padding: 12px;
      font-size: 0.75rem;
      text-transform: uppercase;
      color: var(--text-light);
      font-weight: 700;
      border-bottom: 2px solid var(--border-color);
      text-align: left;
    }

    td {
      padding: 12px;
      border-bottom: 1px solid var(--border-color);
      font-size: 0.9rem;
    }

    tr:hover {
      background: #fafafa;
    }

    .action-link {
      color: var(--asu-maroon);
      text-decoration: none;
      font-weight: 600;
      margin-right: 12px;
    }

    .action-link:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>

  <header class="site-header">
    <div class="site-title">ABET Tools - Roster Management</div>
    <a href="roster-dashboard.html" class="nav-link">← Back to Dashboard</a>
  </header>

  <div class="main-container">
    
    <div class="page-header">
      <div class="page-title">
        <h1>Student Roster Management</h1>
        <p>CSE 445: Software Security • Fall 2024</p>
      </div>
    </div>

    <!-- Upload Section -->
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Upload Roster</h2>
      </div>
      <div class="card-body">

        <!-- File Upload Area -->
        <div class="file-upload" id="dropZone" onclick="document.getElementById('rosterFile').click()">
          <div class="file-upload-icon">📄</div>
          <div class="file-upload-text">Click to upload or drag and drop</div>
          <div class="file-upload-hint">Accepts .csv files from Canvas export (Name, ID, Program will be extracted)</div>
        </div>
        <input type="file" id="rosterFile" accept=".csv" onchange="handleFileUpload(event)">

      </div>
    </div>

    <!-- Roster Statistics -->
    <div class="stats-mini" id="rosterStats">
      <div class="stat-mini">
        <div class="stat-mini-label">Total Students</div>
        <div class="stat-mini-value" id="totalStudents">48</div>
      </div>
      <div class="stat-mini">
        <div class="stat-mini-label">Unique Programs</div>
        <div class="stat-mini-value" id="totalPrograms">3</div>
      </div>
      <div class="stat-mini">
        <div class="stat-mini-label">Last Updated</div>
        <div class="stat-mini-value" style="font-size: 1rem;">Today</div>
      </div>
    </div>

    <!-- Roster Table -->
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Current Roster (48 Students)</h2>
      </div>
      <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
          <table>
            <thead>
              <tr>
                <th>Student Name</th>
                <th>Student ID</th>
                <th>Program Title</th>
                <th>Email</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><strong>Smith, John</strong></td>
                <td>1234567890</td>
                <td>Computer Science BS</td>
                <td>jsmith@asu.edu</td>
                <td>
                  <a href="#" class="action-link">Edit</a>
                  <a href="#" class="action-link">Remove</a>
                </td>
              </tr>
              <tr>
                <td><strong>Johnson, Emily</strong></td>
                <td>1234567891</td>
                <td>Software Engineering BS</td>
                <td>ejohnson@asu.edu</td>
                <td>
                  <a href="#" class="action-link">Edit</a>
                  <a href="#" class="action-link">Remove</a>
                </td>
              </tr>
              <tr>
                <td><strong>Chen, Michael</strong></td>
                <td>1234567892</td>
                <td>Computer Science BS</td>
                <td>mchen@asu.edu</td>
                <td>
                  <a href="#" class="action-link">Edit</a>
                  <a href="#" class="action-link">Remove</a>
                </td>
              </tr>
              <tr>
                <td><strong>Garcia, Maria</strong></td>
                <td>1234567893</td>
                <td>Computer Systems Engineering BS</td>
                <td>mgarcia@asu.edu</td>
                <td>
                  <a href="#" class="action-link">Edit</a>
                  <a href="#" class="action-link">Remove</a>
                </td>
              </tr>
              <tr>
                <td><strong>Williams, David</strong></td>
                <td>1234567894</td>
                <td>Software Engineering BS</td>
                <td>dwilliams@asu.edu</td>
                <td>
                  <a href="#" class="action-link">Edit</a>
                  <a href="#" class="action-link">Remove</a>
                </td>
              </tr>
              <tr>
                <td colspan="5" style="text-align: center; padding: 20px; color: var(--text-light);">
                  <em>+ 43 more students</em>
                  <a href="#" style="color: var(--asu-maroon); margin-left: 15px;">View All</a>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Export Options -->
    <div style="margin-top: 20px; text-align: right;">
      <button class="btn btn-secondary" onclick="exportRoster()">
        <span class="btn-icon">💾</span> Export Roster
      </button>
      <a href="roster-assignments.html" class="btn btn-primary">
        Continue to Assignments →
      </a>
    </div>

  </div>

  <script>
    // Drag and drop functionality
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('rosterFile');

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
      dropZone.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
      e.preventDefault();
      e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
      dropZone.addEventListener(eventName, () => {
        dropZone.style.borderColor = 'var(--asu-maroon)';
        dropZone.style.background = '#fff3f5';
      });
    });

    ['dragleave', 'drop'].forEach(eventName => {
      dropZone.addEventListener(eventName, () => {
        dropZone.style.borderColor = 'var(--border-color)';
        dropZone.style.background = '#fafafa';
      });
    });

    dropZone.addEventListener('drop', handleDrop);

    function handleDrop(e) {
      const dt = e.dataTransfer;
      const files = dt.files;
      fileInput.files = files;
      handleFileUpload({ target: { files: files } });
    }

    function handleFileUpload(event) {
      const file = event.target.files[0];
      if (file) {
        alert('File uploaded: ' + file.name + '\n\nProcessing roster data...\n✓ Extracted Name\n✓ Extracted Student ID\n✓ Extracted Program Title\n\nRoster updated successfully!');
        
        document.getElementById('totalStudents').textContent = '48';
        document.getElementById('totalPrograms').textContent = '3';
      }
    }

  </script>

</body>
</html>