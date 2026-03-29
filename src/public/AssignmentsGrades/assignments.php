<?php
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
      border: 1px solid rgba(255,255,255,0.3);
      white-space: nowrap;
    }

    .btn-header:hover {
      background: rgba(255,255,255,0.25);
    }

    .main-container {
      max-width: 1200px;
      margin: 40px auto;
      padding: 0 20px;
    }

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
      border: none;
      cursor: pointer;
    }

    .btn-primary:hover {
      background: #5c132a;
    }

    .btn-success {
      background: var(--success);
      color: white;
    }

    .btn-success:hover {
      background: #085d20;
    }

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

    .data-card {
      background: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
      border: 1px solid var(--border-color);
      overflow: hidden;
      margin-bottom: 20px;
    }

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
    .status-scheduled { background: #e3f2fd; color: #1976d2; }

    .action-link {
      color: var(--asu-maroon);
      text-decoration: none;
      font-weight: 600;
      font-size: 0.85rem;
      margin-right: 12px;
      cursor: pointer;
    }
    
    .action-link:hover { text-decoration: underline; }

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

    .hidden-row {
      display: none;
    }

    /* Modal Styles */
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0,0,0,0.5);
      animation: fadeIn 0.3s;
    }

    .modal.show {
      display: block;
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    .modal-content {
      background-color: white;
      margin: 5% auto;
      padding: 0;
      border-radius: 8px;
      width: 90%;
      max-width: 600px;
      max-height: 80vh;
      overflow-y: auto;
      animation: slideDown 0.3s;
    }

    @keyframes slideDown {
      from {
        transform: translateY(-50px);
        opacity: 0;
      }
      to {
        transform: translateY(0);
        opacity: 1;
      }
    }

    .modal-header {
      padding: 20px;
      background: var(--asu-maroon);
      color: white;
      border-radius: 8px 8px 0 0;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .modal-title {
      margin: 0;
      font-size: 1.3rem;
    }

    .close {
      color: white;
      font-size: 28px;
      font-weight: bold;
      cursor: pointer;
      background: none;
      border: none;
      padding: 0;
      width: 30px;
      height: 30px;
      line-height: 1;
    }

    .close:hover {
      opacity: 0.7;
    }

    .modal-body {
      padding: 30px;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-label {
      display: block;
      font-weight: 600;
      margin-bottom: 8px;
      color: var(--text-dark);
    }

    .form-input,
    .form-select,
    .form-textarea {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid var(--border-color);
      border-radius: 4px;
      font-size: 0.95rem;
      box-sizing: border-box;
    }

    .form-textarea {
      resize: vertical;
      min-height: 100px;
    }

    .modal-footer {
      padding: 20px;
      background: #f9f9f9;
      border-top: 1px solid var(--border-color);
      display: flex;
      gap: 10px;
      justify-content: flex-end;
    }

    .btn-secondary {
      background: white;
      color: var(--asu-maroon);
      border: 1px solid var(--asu-maroon);
      padding: 10px 20px;
      border-radius: 30px;
      font-weight: 600;
      cursor: pointer;
    }

    .stats-display {
      background: #f9f9f9;
      padding: 20px;
      border-radius: 6px;
      margin-bottom: 15px;
    }

    .stats-row {
      display: flex;
      justify-content: space-between;
      padding: 10px 0;
      border-bottom: 1px solid var(--border-color);
    }

    .stats-row:last-child {
      border-bottom: none;
    }

    .stat-name {
      font-weight: 600;
      color: var(--text-dark);
    }

    .stat-number {
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--asu-maroon);
    }

    .upload-area {
      border: 2px dashed var(--border-color);
      border-radius: 8px;
      padding: 30px;
      text-align: center;
      background: #fafafa;
      cursor: pointer;
      transition: all 0.2s;
    }

    .upload-area:hover {
      border-color: var(--asu-maroon);
      background: #f5f5f5;
    }

    .upload-icon {
      font-size: 1.2rem;
      color: var(--asu-maroon);
      margin-bottom: 10px;
      font-weight: 700;
      text-transform: uppercase;
    }

    .file-list {
      margin-top: 15px;
      padding: 15px;
      background: white;
      border-radius: 4px;
      border: 1px solid var(--border-color);
    }

    .file-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 8px 0;
      border-bottom: 1px solid #f0f0f0;
    }

    .file-item:last-child {
      border-bottom: none;
    }

    .file-remove {
      color: #d32f2f;
      cursor: pointer;
      font-size: 0.85rem;
      font-weight: 600;
    }

    input[type="file"] {
      display: none;
    }

    .import-section {
      background: #e3f2fd;
      border: 2px solid var(--info);
      border-radius: 8px;
      padding: 20px;
      margin: 20px 0;
    }

    .import-title {
      font-weight: 700;
      color: var(--info);
      margin-bottom: 10px;
    }

    .checkbox-group {
      margin: 15px 0;
    }

    .checkbox-item {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 10px;
    }

    .checkbox-item input {
      width: 18px;
      height: 18px;
    }
  </style>
</head>
<body>

  <header class="site-header">
    <div class="site-title">ABET Tools</div>
    <div class="header-actions">
      <button class="btn-header" onclick="showImportDialog()">Import to Canvas</button>
      <a href="/../index.php" class="nav-link">← Back to Dashboard</a>
      <a href="roster-upload.php" class="nav-link">← Back </a>
    </div>
  </header>

  <div class="main-container">
    
    <div class="page-header">
      <div class="page-title">
        <h1>Assignments & Grades</h1>
        <p>CSE 445: Software Security • Fall 2024</p>
      </div>
      <button class="btn-primary" onclick="showNewAssignmentDialog()">+ New Assignment</button>
    </div>

    <!-- Button Guide Section -->
    <div class="data-card" style="margin-bottom: 30px;">
      <div class="section-header">
        <h2 class="section-title">Button Functions Guide</h2>
        <a href="#" class="section-action" onclick="toggleGuide(); return false;">Hide Help</a>
      </div>
      <div id="guideContent" style="display: block;">
        <div style="padding: 30px;">
          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
            
            <!-- Edit Button -->
            <div>
              <h3 style="color: var(--asu-maroon); margin: 0 0 10px 0;">EDIT Button</h3>
              <ul style="margin: 0; padding-left: 20px; color: var(--text-dark);">
                <li>Modify assignment details</li>
                <li>Upload PDF files</li>
                <li>Change grading scale</li>
              </ul>
            </div>

            <!-- View Stats Button -->
            <div>
              <h3 style="color: var(--asu-maroon); margin: 0 0 10px 0;">VIEW STATS Button</h3>
              <ul style="margin: 0; padding-left: 20px; color: var(--text-dark);">
                <li>High, Low, Median, Mean</li>
                <li>Grade distribution</li>
                <li>Export statistics</li>
              </ul>
            </div>

            <!-- Export Button -->
            <div>
              <h3 style="color: var(--asu-maroon); margin: 0 0 10px 0;">EXPORT Button</h3>
              <ul style="margin: 0; padding-left: 20px; color: var(--text-dark);">
                <li>Download as CSV</li>
                <li>Includes all grades</li>
                <li>Submission data</li>
              </ul>
            </div>

            <!-- Preview Button -->
            <div>
              <h3 style="color: var(--asu-maroon); margin: 0 0 10px 0;">PREVIEW Button</h3>
              <ul style="margin: 0; padding-left: 20px; color: var(--text-dark);">
                <li>Review before publishing</li>
                <li>Check requirements</li>
                <li>Publish to students</li>
              </ul>
            </div>

            <!-- Remove Button -->
            <div>
              <h3 style="color: var(--asu-maroon); margin: 0 0 10px 0;">REMOVE Button</h3>
              <ul style="margin: 0; padding-left: 20px; color: var(--text-dark);">
                <li>Delete assignment</li>
                <li>Requires confirmation</li>
                <li>Cannot be undone</li>
              </ul>
            </div>

            <!-- Import to Canvas Button -->
            <div>
              <h3 style="color: var(--asu-maroon); margin: 0 0 10px 0;">IMPORT TO CANVAS</h3>
              <ul style="margin: 0; padding-left: 20px; color: var(--text-dark);">
                <li>Copy to Canvas shell</li>
                <li>Duplicate detection</li>
                <li>Batch import</li>
              </ul>
            </div>

          </div>
        </div>
      </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">Total Assignments</div>
        <div class="stat-value">5</div>
        <div class="stat-subtitle">4 graded, 1 scheduled</div>
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
        <a href="#" class="tab">Analytics</a>
      </div>

      <!-- Filter Bar -->
      <div class="filter-bar">
        <div class="filter-group">
          <label class="filter-label">Status</label>
          <select class="filter-select" id="statusFilter">
            <option value="all">All Statuses</option>
            <option value="graded">Graded</option>
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
            <tr data-status="graded" data-type="homework" data-id="hw1">
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
                <a class="action-link" onclick="editAssignment('hw1')">Edit</a>
                <a class="action-link" onclick="viewStats('hw1')">View Stats</a>
                <a class="action-link" onclick="exportAssignment('hw1')">Export</a>
                <a class="action-link" onclick="removeAssignment('hw1')" style="color: #d32f2f;">Remove</a>
              </td>
            </tr>
            <tr data-status="graded" data-type="project" data-id="proj1">
              <td>
                <strong>Project: Secure Login</strong>
                <div class="progress-bar-container">
                  <div class="progress-bar" style="width: 100%;"></div>
                </div>
              </td>
              <td>Nov 01, 2024</td>
              <td>Project</td>
              <td>48 / 48</td>
              <td><span class="status-badge status-graded">Graded</span></td>
              <td style="text-align: right;">
                <a class="action-link" onclick="editAssignment('proj1')">Edit</a>
                <a class="action-link" onclick="viewStats('proj1')">View Stats</a>
                <a class="action-link" onclick="exportAssignment('proj1')">Export</a>
                <a class="action-link" onclick="removeAssignment('proj1')" style="color: #d32f2f;">Remove</a>
              </td>
            </tr>
            <tr data-status="graded" data-type="quiz" data-id="quiz2">
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
                <a class="action-link" onclick="editAssignment('quiz2')">Edit</a>
                <a class="action-link" onclick="viewStats('quiz2')">View Stats</a>
                <a class="action-link" onclick="exportAssignment('quiz2')">Export</a>
                <a class="action-link" onclick="removeAssignment('quiz2')" style="color: #d32f2f;">Remove</a>
              </td>
            </tr>
            <tr data-status="scheduled" data-type="exam" data-id="midterm">
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
                <a class="action-link" onclick="editAssignment('midterm')">Edit</a>
                <a class="action-link" onclick="previewAssignment('midterm')">Preview</a>
                <a class="action-link" onclick="removeAssignment('midterm')" style="color: #d32f2f;">Remove</a>
              </td>
            </tr>
            <tr data-status="graded" data-type="homework" data-id="hw3">
              <td>
                <strong>Homework 3: Access Control</strong>
                <div class="progress-bar-container">
                  <div class="progress-bar" style="width: 88%;"></div>
                </div>
              </td>
              <td>Nov 08, 2024</td>
              <td>Homework</td>
              <td>42 / 48</td>
              <td><span class="status-badge status-graded">Graded</span></td>
              <td style="text-align: right;">
                <a class="action-link" onclick="editAssignment('hw3')">Edit</a>
                <a class="action-link" onclick="viewStats('hw3')">View Stats</a>
                <a class="action-link" onclick="exportAssignment('hw3')">Export</a>
                <a class="action-link" onclick="removeAssignment('hw3')" style="color: #d32f2f;">Remove</a>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    
    </div>

  </div>

  <!-- Edit Assignment Modal -->
  <div id="editModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title">Edit Assignment</h2>
        <button class="close" onclick="closeModal('editModal')">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Assignment Name</label>
          <input type="text" class="form-input" id="editName" value="Homework 1: Threat Models">
        </div>
        <div class="form-group">
          <label class="form-label">Due Date</label>
          <input type="date" class="form-input" id="editDueDate">
        </div>
        <div class="form-group">
          <label class="form-label">Total Points</label>
          <input type="number" class="form-input" id="editPoints" value="100">
        </div>
        <div class="form-group">
          <label class="form-label">Grading Scale</label>
          <select class="form-select" id="editScale">
            <option value="standard">Standard (90/80/70/60)</option>
            <option value="high">High Performance (95/90/85/80)</option>
            <option value="low">Low Threshold (85/75/65/55)</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Upload PDFs/Materials</label>
          <div class="upload-area" onclick="document.getElementById('editFileInput').click()">
            <div class="upload-icon">Upload</div>
            <div>Click to upload assignment files</div>
            <input type="file" id="editFileInput" multiple accept=".pdf,.doc,.docx" onchange="handleFileSelect(this)">
          </div>
          <div id="fileList" class="file-list" style="display: none;"></div>
        </div>
        <div class="form-group">
          <label class="form-label">Instructions/Description</label>
          <textarea class="form-textarea" id="editDescription">Complete the threat modeling exercise for the given scenario...</textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-secondary" onclick="closeModal('editModal')">Cancel</button>
        <button class="btn-primary" onclick="saveChanges()">Save Changes</button>
      </div>
    </div>
  </div>

  <!-- View Stats Modal -->
  <div id="statsModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title">Assignment Statistics</h2>
        <button class="close" onclick="closeModal('statsModal')">&times;</button>
      </div>
      <div class="modal-body">
        <h3 style="margin-top: 0;">Grade Distribution</h3>
        <div class="stats-display">
          <div class="stats-row">
            <span class="stat-name">Highest Score</span>
            <span class="stat-number">98</span>
          </div>
          <div class="stats-row">
            <span class="stat-name">Lowest Score</span>
            <span class="stat-number">62</span>
          </div>
          <div class="stats-row">
            <span class="stat-name">Median Score</span>
            <span class="stat-number">85</span>
          </div>
          <div class="stats-row">
            <span class="stat-name">Mean (Average)</span>
            <span class="stat-number">84.3</span>
          </div>
          <div class="stats-row">
            <span class="stat-name">Standard Deviation</span>
            <span class="stat-number">12.4</span>
          </div>
        </div>
        
        <h3>Grade Breakdown</h3>
        <div class="stats-display">
          <div class="stats-row">
            <span class="stat-name">A (90-100)</span>
            <span class="stat-number">18 students (40%)</span>
          </div>
          <div class="stats-row">
            <span class="stat-name">B (80-89)</span>
            <span class="stat-number">15 students (33%)</span>
          </div>
          <div class="stats-row">
            <span class="stat-name">C (70-79)</span>
            <span class="stat-number">8 students (18%)</span>
          </div>
          <div class="stats-row">
            <span class="stat-name">D (60-69)</span>
            <span class="stat-number">3 students (7%)</span>
          </div>
          <div class="stats-row">
            <span class="stat-name">F (0-59)</span>
            <span class="stat-number">1 student (2%)</span>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-secondary" onclick="closeModal('statsModal')">Close</button>
        <button class="btn-primary" onclick="exportStats()">Export Stats</button>
      </div>
    </div>
  </div>

  <!-- Preview Assignment Modal -->
  <div id="previewModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title">Preview Assignment</h2>
        <button class="close" onclick="closeModal('previewModal')">&times;</button>
      </div>
      <div class="modal-body">
        <h3 style="margin-top: 0;">Midterm Exam</h3>
        <div class="stats-display">
          <div class="stats-row">
            <span class="stat-name">Due Date</span>
            <span>Nov 15, 2024 at 11:59 PM</span>
          </div>
          <div class="stats-row">
            <span class="stat-name">Total Points</span>
            <span>150</span>
          </div>
          <div class="stats-row">
            <span class="stat-name">Type</span>
            <span>Exam</span>
          </div>
          <div class="stats-row">
            <span class="stat-name">Status</span>
            <span>Scheduled - Not yet available to students</span>
          </div>
        </div>
        
        <h4>Instructions:</h4>
        <p>This is a comprehensive midterm exam covering weeks 1-7. Students will have 90 minutes to complete the exam once started. The exam includes:</p>
        <ul>
          <li>Multiple choice questions (40 points)</li>
          <li>Short answer questions (60 points)</li>
          <li>Problem-solving exercises (50 points)</li>
        </ul>
        
        <p><strong>Materials Allowed:</strong> One page of handwritten notes (both sides)</p>
        <p><strong>Format:</strong> In-person, proctored exam</p>
      </div>
      <div class="modal-footer">
        <button class="btn-secondary" onclick="closeModal('previewModal')">Close</button>
        <button class="btn-primary" onclick="publishAssignment()">Publish to Students</button>
      </div>
    </div>
  </div>

  <!-- Import to Canvas Modal -->
  <div id="importModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title">Import to Canvas</h2>
        <button class="close" onclick="closeModal('importModal')">&times;</button>
      </div>
      <div class="modal-body">
        <div class="import-section">
          <div class="import-title">Ready to Import Assignments to Canvas</div>
          <p>This will automatically copy all assignment information into your Canvas course where all data is stored.</p>
        </div>

        <h4>Select Assignments to Import:</h4>
        <div class="checkbox-group">
          <div class="checkbox-item">
            <input type="checkbox" id="import_hw1" checked>
            <label for="import_hw1">Homework 1: Threat Models</label>
          </div>
          <div class="checkbox-item">
            <input type="checkbox" id="import_proj1" checked>
            <label for="import_proj1">Project: Secure Login</label>
          </div>
          <div class="checkbox-item">
            <input type="checkbox" id="import_quiz2" checked>
            <label for="import_quiz2">Quiz 2: Cryptography</label>
          </div>
          <div class="checkbox-item">
            <input type="checkbox" id="import_midterm" checked>
            <label for="import_midterm">Midterm Exam</label>
          </div>
          <div class="checkbox-item">
            <input type="checkbox" id="import_hw3" checked>
            <label for="import_hw3">Homework 3: Access Control</label>
          </div>
        </div>

        <div style="margin-top: 20px; padding: 15px; background: #fff8e1; border-radius: 6px;">
          <strong>Duplicate Detection:</strong> System will check for duplicates and prevent copying multiple files with the same name.
        </div>

        <div style="margin-top: 15px;">
          <label class="checkbox-item">
            <input type="checkbox" id="removeAfterImport">
            <span>Remove assignments from ABET Tools after successful import</span>
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-secondary" onclick="closeModal('importModal')">Cancel</button>
        <button class="btn-success btn-primary" onclick="startImportProcess()">Start Import Process</button>
      </div>
    </div>
  </div>

  <script>
    // Toggle Guide Section
    function toggleGuide() {
      const guideContent = document.getElementById('guideContent');
      const toggleLink = document.querySelector('.section-action');
      if (guideContent.style.display === 'none') {
        guideContent.style.display = 'block';
        toggleLink.textContent = 'Hide Help';
      } else {
        guideContent.style.display = 'none';
        toggleLink.textContent = 'Show Help';
      }
    }

    // Filter functionality
    const statusFilter = document.getElementById('statusFilter');
    const typeFilter = document.getElementById('typeFilter');
    const tableBody = document.getElementById('assignmentsTableBody');
    const rows = tableBody.getElementsByTagName('tr');

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

    statusFilter.addEventListener('change', filterTable);
    typeFilter.addEventListener('change', filterTable);

    // Modal functions
    function showModal(modalId) {
      document.getElementById(modalId).classList.add('show');
    }

    function closeModal(modalId) {
      document.getElementById(modalId).classList.remove('show');
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
      if (event.target.classList.contains('modal')) {
        event.target.classList.remove('show');
      }
    }

    // EDIT BUTTON: Edit assignment details (PDF, grading scale, changes)
    function editAssignment(assignmentId) {
      console.log('Editing assignment:', assignmentId);
      showModal('editModal');
    }

    // VIEW STATS BUTTON: Show high, low, median, mean statistics
    function viewStats(assignmentId) {
      console.log('Viewing stats for:', assignmentId);
      showModal('statsModal');
    }

    // EXPORT BUTTON: Export assignment data to CSV/PDF
    function exportAssignment(assignmentId) {
      console.log('Exporting assignment:', assignmentId);
      alert('Exporting Assignment Data...\n\n' +
            'Format: CSV\n' +
            'Includes:\n' +
            '• Student names and IDs\n' +
            '• Grades and scores\n' +
            '• Submission timestamps\n' +
            '• Assignment statistics\n\n' +
            'Export complete!');
    }

    // PREVIEW BUTTON: Preview scheduled assignment before publishing
    function previewAssignment(assignmentId) {
      console.log('Previewing assignment:', assignmentId);
      showModal('previewModal');
    }

    // REMOVE BUTTON: Delete assignment from system
    function removeAssignment(assignmentId) {
      if (confirm('Are you sure you want to remove this assignment?\n\nThis action cannot be undone and will delete:\n• Assignment details\n• All student submissions\n• Grade data\n• Associated files')) {
        // Find and remove the row
        const row = document.querySelector(`tr[data-id="${assignmentId}"]`);
        if (row) {
          row.remove();
          alert('Assignment removed successfully!');
          
          // Update statistics
          updateStats();
        }
      }
    }

    // Update statistics after removing assignment
    function updateStats() {
      const totalAssignments = document.querySelectorAll('#assignmentsTableBody tr').length;
      const gradedCount = document.querySelectorAll('#assignmentsTableBody tr[data-status="graded"]').length;
      const scheduledCount = document.querySelectorAll('#assignmentsTableBody tr[data-status="scheduled"]').length;
      
      document.querySelector('.stat-value').textContent = totalAssignments;
      document.querySelector('.stat-subtitle').textContent = `${gradedCount} graded, ${scheduledCount} scheduled`;
    }

    // Save changes from edit modal
    function saveChanges() {
      alert('Assignment updated successfully!\n\nChanges saved:\n' +
            '• Assignment details\n' +
            '• Grading scale\n' +
            '• Uploaded materials\n' +
            '• Description/instructions');
      closeModal('editModal');
    }

    // Publish assignment to students
    function publishAssignment() {
      alert('Assignment published!\n\nStudents can now:\n' +
            '• View the assignment\n' +
            '• See due date and requirements\n' +
            '• Submit their work');
      closeModal('previewModal');
    }

    // Export statistics
    function exportStats() {
      alert('Statistics exported!\n\nFile includes:\n' +
            '• High, low, median, mean scores\n' +
            '• Grade distribution\n' +
            '• Standard deviation\n' +
            '• Student performance data');
    }

    // File upload handling
    let uploadedFiles = [];
    
    function handleFileSelect(input) {
      const fileList = document.getElementById('fileList');
      fileList.style.display = 'block';
      fileList.innerHTML = '';
      
      uploadedFiles = Array.from(input.files);
      
      uploadedFiles.forEach((file, index) => {
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item';
        fileItem.innerHTML = `
          <span>${file.name}</span>
          <span class="file-remove" onclick="removeFile(${index})">Remove</span>
        `;
        fileList.appendChild(fileItem);
      });
    }

    function removeFile(index) {
      uploadedFiles.splice(index, 1);
      document.getElementById('editFileInput').value = '';
      handleFileSelect({ files: uploadedFiles });
    }

    // New assignment dialog
    function showNewAssignmentDialog() {
      alert('Opening new assignment creator...\n\n' +
            'Create assignments with:\n' +
            '• Name and description\n' +
            '• Due date\n' +
            '• Point values\n' +
            '• Grading rubrics\n' +
            '• File attachments');
    }

    // IMPORT TO CANVAS: Main import functionality
    function showImportDialog() {
      showModal('importModal');
    }

    function startImportProcess() {
      // Get selected assignments
      const hw1 = document.getElementById('import_hw1').checked;
      const proj1 = document.getElementById('import_proj1').checked;
      const quiz2 = document.getElementById('import_quiz2').checked;
      const midterm = document.getElementById('import_midterm').checked;
      const hw3 = document.getElementById('import_hw3').checked;
      
      const removeAfter = document.getElementById('removeAfterImport').checked;
      
      let selectedCount = [hw1, proj1, quiz2, midterm, hw3].filter(x => x).length;
      
      if (selectedCount === 0) {
        alert('Please select at least one assignment to import');
        return;
      }
      
      closeModal('importModal');
      
      // Simulate import process
      alert('Starting import process to Canvas...\n\n' +
            '1. Checking for duplicates...\n' +
            '2. Preparing assignment data...\n' +
            '3. Uploading to Canvas shell...\n\n' +
            'Processing ' + selectedCount + ' assignments...');
      
      setTimeout(() => {
        alert('Import Complete!\n\n' +
              '• ' + selectedCount + ' assignments imported to Canvas\n' +
              '• All information stored in Canvas database\n' +
              '• No duplicates created\n' +
              '• Students can now access assignments\n\n' +
              (removeAfter ? '• Assignments removed from ABET Tools' : '• Assignments retained in ABET Tools'));
      }, 1500);
    }
  </script>

</body>
</html>
