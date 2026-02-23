<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/auth.php';
require_login();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Classes | ABET Tools</title>
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
      transition: background 0.2s;
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
      transition: background 0.2s;
      border: none;
      cursor: pointer;
    }

    .btn-primary:hover {
      background: #5c132a;
    }

    .filter-bar {
      background: white;
      border-radius: 8px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
      display: flex;
      gap: 15px;
      flex-wrap: wrap;
      align-items: flex-end;
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

    .classes-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
      gap: 20px;
    }

    .class-card {
      background: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
      border: 1px solid var(--border-color);
      overflow: hidden;
      transition: transform 0.2s, box-shadow 0.2s;
      cursor: pointer;
    }

    .class-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }

    .class-header {
      background: linear-gradient(135deg, var(--asu-maroon) 0%, #5c132a 100%);
      color: white;
      padding: 20px;
      position: relative;
    }

    .class-code {
      font-size: 0.75rem;
      text-transform: uppercase;
      font-weight: 700;
      opacity: 0.9;
      margin-bottom: 5px;
    }

    .class-title {
      font-size: 1.2rem;
      font-weight: 700;
      margin: 0 0 10px 0;
    }

    .class-meta {
      font-size: 0.85rem;
      opacity: 0.9;
    }

    .class-body {
      padding: 20px;
    }

    .class-info-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 15px;
      margin-bottom: 15px;
    }

    .info-item {
      display: flex;
      flex-direction: column;
      gap: 3px;
    }

    .info-label {
      font-size: 0.7rem;
      text-transform: uppercase;
      font-weight: 700;
      color: var(--text-light);
    }

    .info-value {
      font-size: 0.95rem;
      color: var(--text-dark);
      font-weight: 600;
    }

    .class-actions {
      display: flex;
      gap: 10px;
      padding-top: 15px;
      border-top: 1px solid var(--border-color);
    }

    .action-btn {
      flex: 1;
      padding: 10px;
      border: 1px solid var(--border-color);
      background: white;
      border-radius: 6px;
      font-size: 0.85rem;
      font-weight: 600;
      color: var(--asu-maroon);
      cursor: pointer;
      transition: background 0.2s;
      text-align: center;
      text-decoration: none;
      display: block;
    }

    .action-btn:hover {
      background: #f5f5f5;
    }

    .semester-badge {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 12px;
      font-size: 0.7rem;
      font-weight: 700;
      background: rgba(255,255,255,0.2);
    }

    .status-active {
      background: #e6f4ea;
      color: #0a7d30;
    }

    .status-upcoming {
      background: #e3f2fd;
      color: #1976d2;
    }

    .status-archived {
      background: #f5f5f5;
      color: #666;
    }

    .empty-state {
      text-align: center;
      padding: 60px 20px;
      background: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }

    .empty-state-icon {
      font-size: 4rem;
      margin-bottom: 20px;
      opacity: 0.3;
    }

    .empty-state-title {
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--text-dark);
      margin-bottom: 10px;
    }

    .empty-state-text {
      color: var(--text-light);
      margin-bottom: 25px;
    }

    .hidden-class {
      display: none;
    }

    @media (max-width: 768px) {
      .classes-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>

  <header class="site-header">
    <div class="site-title">ABET Tools</div>
    <div class="header-actions">
      <a href="/AssignmentsGrades/canvasTest.php" class="btn-header">+ Add Class</a>
      <a href="/home" class="nav-link">← Back to Dashboard</a>
    </div>
  </header>

  <div class="main-container">
    
    <div class="page-header">
      <div class="page-title">
        <h1>My Classes</h1>
        <p>Manage all your courses and student outcomes</p>
      </div>
      <a href="/AssignmentsGrades/canvasTest.php" class="btn-primary">+ Add New Class</a>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
      <div class="filter-group">
        <label class="filter-label">Semester</label>
        <select class="filter-select" id="semesterFilter">
          <option value="all">All Semesters</option>
          <option value="fall-2024" selected>Fall 2024</option>
          <option value="summer-2024">Summer 2024</option>
          <option value="spring-2024">Spring 2024</option>
        </select>
      </div>
      <div class="filter-group">
        <label class="filter-label">Status</label>
        <select class="filter-select" id="statusFilter">
          <option value="all">All Statuses</option>
          <option value="active">Active</option>
          <option value="upcoming">Upcoming</option>
        </select>
      </div>
      <div class="filter-group">
        <label class="filter-label">Department</label>
        <select class="filter-select" id="deptFilter">
          <option value="all">All Departments</option>
          <option value="cse">Computer Science (CS)</option>
          <option value="ece">Computer Systems (CSE)</option>
        </select>
      </div>
    </div>

    <!-- Classes Grid -->
    <div class="classes-grid" id="classesGrid">
      
      <!-- Class Card 1 -->
      <div class="class-card" data-semester="fall-2024" data-status="active" data-dept="cse" data-href="index.php" tabindex="0" role="link" aria-label="Open Software Security assignments">
        <div class="class-header">
          <div class="class-code">CSE 445 - Section 001</div>
          <h3 class="class-title">Software Security</h3>
          <div class="class-meta">
            <span class="semester-badge">Fall 2024</span> • 
            <span>48 Students</span>
          </div>
        </div>
        <div class="class-body">
          <div class="class-info-grid">
            <div class="info-item">
              <div class="info-label">Schedule</div>
              <div class="info-value">MW 2:00-3:15 PM</div>
            </div>
            <div class="info-item">
              <div class="info-label">Location</div>
              <div class="info-value">BYENG 210</div>
            </div>
            <div class="info-item">
              <div class="info-label">Instructor</div>
              <div class="info-value">Dr. Sarah Smith</div>
            </div>
            <div class="info-item">
              <div class="info-label">Status</div>
              <div class="info-value">
                <span class="semester-badge status-active">Active</span>
              </div>
            </div>
          </div>
          <div class="class-actions">
            <a href="index.php" class="action-btn">View Assignments</a>
            <a href="#" class="action-btn">Gradebook</a>
            <a href="#" class="action-btn">Analytics</a>
          </div>
        </div>
      </div>

      <!-- Class Card 2 -->
      <div class="class-card" data-semester="fall-2024" data-status="active" data-dept="cse">
        <div class="class-header">
          <div class="class-code">CSE 310 - Section 002</div>
          <h3 class="class-title">Data Structures & Algorithms</h3>
          <div class="class-meta">
            <span class="semester-badge">Fall 2024</span> • 
            <span>52 Students</span>
          </div>
        </div>
        <div class="class-body">
          <div class="class-info-grid">
            <div class="info-item">
              <div class="info-label">Schedule</div>
              <div class="info-value">TTh 10:30-11:45 AM</div>
            </div>
            <div class="info-item">
              <div class="info-label">Location</div>
              <div class="info-value">BYENG 105</div>
            </div>
            <div class="info-item">
              <div class="info-label">Instructor</div>
              <div class="info-value">Dr. Michael Johnson</div>
            </div>
            <div class="info-item">
              <div class="info-label">Status</div>
              <div class="info-value">
                <span class="semester-badge status-active">Active</span>
              </div>
            </div>
          </div>
          <div class="class-actions">
            <a href="#" class="action-btn">View Assignments</a>
            <a href="#" class="action-btn">Gradebook</a>
            <a href="#" class="action-btn">Analytics</a>
          </div>
        </div>
      </div>

    </div>

  </div>

  <script>
    // Filter functionality
    const semesterFilter = document.getElementById('semesterFilter');
    const statusFilter = document.getElementById('statusFilter');
    const deptFilter = document.getElementById('deptFilter');
    const classCards = document.querySelectorAll('.class-card');

    function filterClasses() {
      const semesterValue = semesterFilter.value;
      const statusValue = statusFilter.value;
      const deptValue = deptFilter.value;

      classCards.forEach(card => {
        const cardSemester = card.getAttribute('data-semester');
        const cardStatus = card.getAttribute('data-status');
        const cardDept = card.getAttribute('data-dept');

        const semesterMatch = semesterValue === 'all' || cardSemester === semesterValue;
        const statusMatch = statusValue === 'all' || cardStatus === statusValue;
        const deptMatch = deptValue === 'all' || cardDept === deptValue;

        if (semesterMatch && statusMatch && deptMatch) {
          card.classList.remove('hidden-class');
        } else {
          card.classList.add('hidden-class');
        }
      });
    }

    semesterFilter.addEventListener('change', filterClasses);
    statusFilter.addEventListener('change', filterClasses);
    deptFilter.addEventListener('change', filterClasses);

    // Make entire card clickable (for cards that define data-href)
    document.querySelectorAll('.class-card[data-href]').forEach(card => {
      card.addEventListener('click', (e) => {
        if (e.target.closest('a, button, input, select, textarea, label')) return;
        window.location.href = card.dataset.href;
      });

      card.addEventListener('keydown', (e) => {
        if ((e.key === 'Enter' || e.key === ' ') &&
            !e.target.closest('a, button, input, select, textarea, label')) {
          e.preventDefault();
          window.location.href = card.dataset.href;
        }
      });
    });
  </script>

</body>
</html>
