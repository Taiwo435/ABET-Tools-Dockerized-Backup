<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/auth.php';
require_login();
require_once getenv('ABET_PRIVATE_DIR') . '/lib/security_headers.php'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Assignments & Grades | ABET Tools</title>
  <style>
    :root {
      --asu-maroon: #8C1D40;
      --asu-gold: #FFC627;
      --text-dark: #222;
      --text-light: #555;
      --bg-color: #F9F9F9;
      --border-color: #E0E0E0;
      --success: #0a7d30;
      --warning: #b08d00;
      --info: #1976d2;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      background: var(--bg-color);
      color: var(--text-dark);
      margin: 0;
      padding: 0;
    }

    /* Top Navigation */
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

    .header-actions {
      display: flex;
      gap: 15px;
      align-items: center;
    }

    .nav-link {
      color: rgba(255,255,255,0.9);
      text-decoration: none;
      font-size: 0.9rem;
    }

    .btn-header {
      background: rgba(255,255,255,0.15);
      color: white;
      text-decoration: none;
      padding: 8px 16px;
      border-radius: 20px;
      font-weight: 600;
      font-size: 0.85rem;
      transition: background 0.2s;
      border: 1px solid rgba(255,255,255,0.3);
      white-space: nowrap;
    }

    .btn-header:hover {
      background: rgba(255,255,255,0.25);
    }

    /* Main Container */
    .main-container {
      max-width: 1200px;
      margin: 40px auto;
      padding: 0 20px;
    }

    /* Page Header area */
    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
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

    .btn-primary {
      background: var(--asu-maroon);
      color: white;
      text-decoration: none;
      padding: 10px 20px;
      border-radius: 30px;
      font-weight: 600;
      font-size: 0.9rem;
      transition: background 0.2s;
      border: none;
      cursor: pointer;
    }

    .btn-primary:hover {
      background: #5c132a;
    }

    /* Stats Grid */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }

    .stat-card {
      background: white;
      border-radius: 8px;
      padding: 20px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
      border-left: 4px solid var(--asu-maroon);
    }

    .stat-label {
      font-size: 0.75rem;
      text-transform: uppercase;
      color: var(--text-light);
      font-weight: 700;
      margin-bottom: 8px;
    }

    .stat-value {
      font-size: 2rem;
      font-weight: 700;
      color: var(--asu-maroon);
    }

    .stat-subtitle {
      font-size: 0.85rem;
      color: var(--text-light);
      margin-top: 5px;
    }

    /* Data Card */
    .data-card {
      background: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
      border: 1px solid var(--border-color);
      overflow: hidden;
      margin-bottom: 20px;
    }

    /* Tabs */
    .card-tabs {
      display: flex;
      border-bottom: 1px solid var(--border-color);
      background: #fafafa;
    }

    .tab {
      padding: 15px 25px;
      text-decoration: none;
      color: var(--text-light);
      font-weight: 600;
      border-right: 1px solid var(--border-color);
      font-size: 0.9rem;
      cursor: pointer;
    }

    .tab.active {
      background: white;
      color: var(--asu-maroon);
      border-bottom: 2px solid white;
      margin-bottom: -1px;
    }

    .tab:hover:not(.active) {
      background: #f0f0f0;
      color: var(--text-dark);
    }

    /* Table */
    .table-responsive {
      width: 100%;
      overflow-x: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
    }

    th {
      background: #fcfcfc;
      padding: 16px;
      font-size: 0.8rem;
      text-transform: uppercase;
      color: var(--text-light);
      font-weight: 700;
      border-bottom: 2px solid var(--border-color);
    }

    td {
      padding: 16px;
      border-bottom: 1px solid var(--border-color);
      font-size: 0.95rem;
    }

    tr:last-child td {
      border-bottom: none;
    }

    tr:hover {
      background: #fafafa;
    }

    .status-badge {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 12px;
      font-size: 0.75rem;
      font-weight: 700;
    }

    .status-graded { background: #e6f4ea; color: #0a7d30; }
    .status-pending { background: #fff8e1; color: #b08d00; }
    .status-scheduled { background: #e3f2fd; color: #1976d2; }

    .action-link {
      color: var(--asu-maroon);
      text-decoration: none;
      font-weight: 600;
      font-size: 0.85rem;
      margin-right: 12px;
    }

    .action-link:hover { text-decoration: underline; }

    /* Progress Bar */
    .progress-bar-container {
      width: 100%;
      height: 8px;
      background: #e0e0e0;
      border-radius: 4px;
      overflow: hidden;
      margin-top: 5px;
    }

    .progress-bar {
      height: 100%;
      background: var(--success);
      border-radius: 4px;
      transition: width 0.3s ease;
    }

    /* Filter Bar */
    .filter-bar {
      display: flex;
      gap: 15px;
      padding: 20px;
      background: #fafafa;
      border-bottom: 1px solid var(--border-color);
      flex-wrap: wrap;
    }

    .filter-group {
      display: flex;
      flex-direction: column;
      gap: 5px;
    }

    .filter-label {
      font-size: 0.75rem;
      font-weight: 700;
      text-transform: uppercase;
      color: var(--text-light);
    }

    .filter-select {
      padding: 8px 12px;
      border: 1px solid var(--border-color);
      border-radius: 4px;
      font-size: 0.9rem;
      background: white;
      cursor: pointer;
      transition: border-color 0.2s;
    }

    .filter-select:focus {
      outline: none;
      border-color: var(--asu-maroon);
    }

    /* Section Header */
    .section-header {
      padding: 20px;
      border-bottom: 1px solid var(--border-color);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .section-title {
      font-size: 1rem;
      font-weight: 700;
      color: var(--text-dark);
      margin: 0;
    }

    .section-action {
      color: var(--asu-maroon);
      text-decoration: none;
      font-size: 0.85rem;
      font-weight: 600;
    }

    /* Chart Container */
    .chart-container {
      padding: 30px;
      text-align: center;
    }

    .chart-placeholder {
      height: 300px;
      background: linear-gradient(135deg, #f5f5f5 0%, #e0e0e0 100%);
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--text-light);
      font-style: italic;
      border: 2px dashed var(--border-color);
    }

    /* Hidden row class */
    .hidden-row {
      display: none;
    }
  </style>
</head>
<body>

  <header class="site-header">
    <div class="site-title">ABET Tools</div>
    <div class="header-actions">
      <a href="/AssignmentsGrades/canvasTest.php" class="btn-header">+ Add Class</a>
      <a href="/AssignmentsGrades/viewClasses.php" class="btn-header">View Classes</a>
      <a href="/home" class="nav-link">← Back to Dashboard</a>
    </div>
  </header>

  <div class="main-container">

    <div class="page-header">
      <div class="page-title">
        <h1>Assignments & Grades</h1>
        <p>CSE 445: Software Security • Fall 2024</p>
      </div>
      <a href="#" class="btn-primary">+ New Assignment</a>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">Total Assignments</div>
        <div class="stat-value">8</div>
        <div class="stat-subtitle">3 pending review</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Completion Rate</div>
        <div class="stat-value">94%</div>
        <div class="stat-subtitle">45/48 students</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Average Grade</div>
        <div class="stat-value">85.2</div>
        <div class="stat-subtitle">Class performance</div>
      </div>
    </div>

    <!-- Assignments Table -->
    <div class="data-card">

      <div class="card-tabs">
        <a href="#" class="tab active">All Assignments</a>
        <a href="#" class="tab">Gradebook</a>
        <a href="#" class="tab">Analytics</a>
      </div>

      <!-- Filter Bar -->
      <div class="filter-bar">
        <div class="filter-group">
          <label class="filter-label">Status</label>
          <select class="filter-select" id="statusFilter">
            <option value="all">All Statuses</option>
            <option value="graded">Graded</option>
            <option value="pending">Pending</option>
            <option value="scheduled">Scheduled</option>
          </select>
        </div>
        <div class="filter-group">
          <label class="filter-label">Type</label>
          <select class="filter-select" id="typeFilter">
            <option value="all">All Types</option>
            <option value="homework">Homework</option>
            <option value="project">Project</option>
            <option value="exam">Exam</option>
            <option value="quiz">Quiz</option>
          </select>
        </div>
      </div>

      <div class="table-responsive">
        <table>
          <thead>
            <tr>
              <th>Assignment Name</th>
              <th>Due Date</th>
              <th>Type</th>
              <th>Submissions</th>
              <th>Status</th>
              <th style="text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody id="assignmentsTableBody">
            <tr data-status="graded" data-type="homework">
              <td>
                <strong>Homework 1: Threat Models</strong>
                <div class="progress-bar-container">
                  <div class="progress-bar" style="width: 94%;"></div>
                </div>
              </td>
              <td>Oct 12, 2024</td>
              <td>Homework</td>
              <td>45 / 48</td>
              <td><span class="status-badge status-graded">Graded</span></td>
              <td style="text-align: right;">
                <a href="#" class="action-link">Edit</a>
                <a href="#" class="action-link">View Stats</a>
                <a href="#" class="action-link">Export</a>
              </td>
            </tr>
            <tr data-status="pending" data-type="project">
              <td>
                <strong>Project: Secure Login</strong>
                <div class="progress-bar-container">
                  <div class="progress-bar" style="width: 25%;"></div>
                </div>
              </td>
              <td>Nov 01, 2024</td>
              <td>Project</td>
              <td>12 / 48</td>
              <td><span class="status-badge status-pending">Pending</span></td>
              <td style="text-align: right;">
                <a href="#" class="action-link">Edit</a>
                <a href="#" class="action-link">Grade</a>
              </td>
            </tr>
            <tr data-status="graded" data-type="quiz">
              <td>
                <strong>Quiz 2: Cryptography</strong>
                <div class="progress-bar-container">
                  <div class="progress-bar" style="width: 100%;"></div>
                </div>
              </td>
              <td>Oct 25, 2024</td>
              <td>Quiz</td>
              <td>48 / 48</td>
              <td><span class="status-badge status-graded">Graded</span></td>
              <td style="text-align: right;">
                <a href="#" class="action-link">Edit</a>
                <a href="#" class="action-link">View Stats</a>
                <a href="#" class="action-link">Export</a>
              </td>
            </tr>
            <tr data-status="scheduled" data-type="exam">
              <td>
                <strong>Midterm Exam</strong>
                <div class="progress-bar-container">
                  <div class="progress-bar" style="width: 0%;"></div>
                </div>
              </td>
              <td>Nov 15, 2024</td>
              <td>Exam</td>
              <td>--</td>
              <td><span class="status-badge status-scheduled">Scheduled</span></td>
              <td style="text-align: right;">
                <a href="#" class="action-link">Edit</a>
                <a href="#" class="action-link">Preview</a>
              </td>
            </tr>
            <tr data-status="pending" data-type="homework">
              <td>
                <strong>Homework 3: Access Control</strong>
                <div class="progress-bar-container">
                  <div class="progress-bar" style="width: 88%;"></div>
                </div>
              </td>
              <td>Nov 08, 2024</td>
              <td>Homework</td>
              <td>42 / 48</td>
              <td><span class="status-badge status-pending">Pending</span></td>
              <td style="text-align: right;">
                <a href="#" class="action-link">Edit</a>
                <a href="#" class="action-link">Grade</a>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>

  </div>

  <script>
    // Get filter elements
    const statusFilter = document.getElementById('statusFilter');
    const typeFilter = document.getElementById('typeFilter');
    const tableBody = document.getElementById('assignmentsTableBody');
    const rows = tableBody.getElementsByTagName('tr');

    // Function to filter table
    function filterTable() {
      const statusValue = statusFilter.value;
      const typeValue = typeFilter.value;

      for (let row of rows) {
        const rowStatus = row.getAttribute('data-status');
        const rowType = row.getAttribute('data-type');

        const statusMatch = statusValue === 'all' || rowStatus === statusValue;
        const typeMatch = typeValue === 'all' || rowType === typeValue;

        if (statusMatch && typeMatch) {
          row.classList.remove('hidden-row');
        } else {
          row.classList.add('hidden-row');
        }
      }
    }

    // Add event listeners
    statusFilter.addEventListener('change', filterTable);
    typeFilter.addEventListener('change', filterTable);
  </script>

</body>
</html>
