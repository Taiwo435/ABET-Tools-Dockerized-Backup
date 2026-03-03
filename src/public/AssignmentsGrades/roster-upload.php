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

    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: var(--text-light);
    }

    .empty-state-icon {
      font-size: 3rem;
      margin-bottom: 15px;
      opacity: 0.3;
    }
  </style>
</head>
<body>

  <header class="site-header">
    <div class="site-title">ABET Tools - Roster Management</div>
    <a href="/../index.php" class="nav-link">← Back to Dashboard</a>
    <a href="tool1.php" class="nav-link">← Back </a>

  </header>

  <div class="main-container">
    
    <div class="page-header">
      <div class="page-title">
        <h1>Student Roster Management</h1>
        <p id="classInfo">CSE 445: Software Security • Fall 2024</p>
      </div>
    </div>

    <!-- Upload Section -->
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Upload or Download Roster</h2>
      </div>
      <div class="card-body">
        

        <!-- Action Buttons -->
        <div class="btn-group">
          <button class="btn btn-primary" onclick="downloadFromCanvas()">
            Download from Canvas
          </button>
          <button class="btn btn-secondary" onclick="document.getElementById('rosterFile').click()">
             Upload CSV File
          </button>
          <button class="btn btn-secondary" onclick="clearRoster()" style="margin-left: auto;">
            Clear Roster
          </button>
        </div>

        <!-- File Upload Area -->
        <div class="file-upload" id="dropZone" onclick="document.getElementById('rosterFile').click()">
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
        <div class="stat-mini-value" id="totalStudents">0</div>
      </div>
      <div class="stat-mini">
        <div class="stat-mini-label">Unique Programs</div>
        <div class="stat-mini-value" id="totalPrograms">0</div>
      </div>
      <div class="stat-mini">
        <div class="stat-mini-label">Last Updated</div>
        <div class="stat-mini-value" style="font-size: 1rem;" id="lastUpdated">Never</div>
      </div>
    </div>

    <!-- Roster Table -->
    <div class="card">
      <div class="card-header">
        <h2 class="card-title" id="rosterTableTitle">Current Roster (0 Students)</h2>
      </div>
      <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
          <table id="rosterTable">
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
                <td colspan="5" class="empty-state"> 
                  <div>No roster data loaded. Upload a CSV file or download from Canvas to get started.</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

      <a href="assignments.php" class="btn btn-primary">
        Continue to Assignments
      </a>
    </div>

  </div>

  <script>
    // Global variables
    let rosterData = [];

    // Load saved roster on page load
    window.addEventListener('DOMContentLoaded', function() {
      loadSelectedClass();
      loadSavedRoster();
    });

    // Load selected class information from localStorage
    function loadSelectedClass() {
      const selectedClassStr = localStorage.getItem('selectedClass');
      if (selectedClassStr) {
        try {
          const classInfo = JSON.parse(selectedClassStr);
          // Update the page header with selected class info
          const classInfoElement = document.getElementById('classInfo');
          if (classInfoElement && classInfo.name && classInfo.semester) {
            classInfoElement.textContent = classInfo.name + ' • ' + classInfo.semester;
          }
        } catch (error) {
          console.error('Error loading selected class:', error);
        }
      }
    }

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
      if (!file) return;

      if (!file.name.endsWith('.csv')) {
        alert('Please upload a CSV file');
        return;
      }

      const reader = new FileReader();
      
      reader.onload = function(e) {
        const text = e.target.result;
        parseCSV(text);
      };

      reader.onerror = function() {
        alert('Error reading file. Please try again.');
      };

      reader.readAsText(file);
    }

    function parseCSV(text) {
      try {
        const lines = text.split('\n').filter(line => line.trim());
        if (lines.length < 2) {
          alert('CSV file appears to be empty');
          return;
        }

        // Get headers
        const headers = parseCSVLine(lines[0]);
        
        // Find column indices
        const nameIndex = findColumnIndex(headers, ['student', 'name', 'student name']);
        const idIndex = findColumnIndex(headers, ['id', 'student id', 'sis user id', 'sis login id']);
        const emailIndex = findColumnIndex(headers, ['email', 'sis login id', 'login id']);
        const programIndex = findColumnIndex(headers, ['program', 'major', 'program title']);

        if (nameIndex === -1 || idIndex === -1) {
          alert('Could not find required columns (Student Name and ID).\n\nPlease ensure your CSV has columns for:\n• Student Name\n• Student ID\n• Program (optional)\n• Email (optional)');
          return;
        }

        // Parse data rows
        rosterData = [];
        for (let i = 1; i < lines.length; i++) {
          const values = parseCSVLine(lines[i]);
          if (values.length > 1) {
            const student = {
              name: values[nameIndex] || '',
              id: values[idIndex] || '',
              program: programIndex !== -1 ? (values[programIndex] || 'Not specified') : 'Not specified',
              email: emailIndex !== -1 ? (values[emailIndex] || generateEmail(values[nameIndex])) : generateEmail(values[nameIndex])
            };
            
            // Only add if we have at least name and ID
            if (student.name && student.id) {
              rosterData.push(student);
            }
          }
        }

        if (rosterData.length === 0) {
          alert('No valid student records found in CSV file');
          return;
        }

        // Save to localStorage
        localStorage.setItem('rosterData', JSON.stringify(rosterData));
        localStorage.setItem('rosterLastUpdated', new Date().toLocaleString());
        
        // Update display
        updateRosterDisplay();
        
        alert('Roster uploaded successfully!\n\n' +
              '• Students loaded: ' + rosterData.length + '\n' +
              '• Data extracted: Name, ID, Program, Email\n\n' +
              'Roster will be saved until you upload a new file.');

      } catch (error) {
        console.error('Error parsing CSV:', error);
        alert('Error parsing CSV file. Please check the file format.');
      }
    }

    function parseCSVLine(line) {
      const result = [];
      let current = '';
      let inQuotes = false;

      for (let i = 0; i < line.length; i++) {
        const char = line[i];
        const nextChar = line[i + 1];

        if (char === '"') {
          if (inQuotes && nextChar === '"') {
            current += '"';
            i++;
          } else {
            inQuotes = !inQuotes;
          }
        } else if (char === ',' && !inQuotes) {
          result.push(current.trim());
          current = '';
        } else {
          current += char;
        }
      }
      result.push(current.trim());
      return result;
    }

    function findColumnIndex(headers, possibleNames) {
      for (let name of possibleNames) {
        const index = headers.findIndex(h => 
          h.toLowerCase().includes(name.toLowerCase())
        );
        if (index !== -1) return index;
      }
      return -1;
    }

    function generateEmail(name) {
      if (!name) return 'unknown@asu.edu';
      const parts = name.split(/[\s,]+/).filter(p => p);
      if (parts.length >= 2) {
        const first = parts[0].toLowerCase();
        const last = parts[parts.length - 1].toLowerCase();
        return first.charAt(0) + last + '@asu.edu';
      }
      return name.toLowerCase().replace(/\s/g, '') + '@asu.edu';
    }

    function updateRosterDisplay() {
      // Update statistics
      const uniquePrograms = [...new Set(rosterData.map(s => s.program))].length;
      document.getElementById('totalStudents').textContent = rosterData.length;
      document.getElementById('totalPrograms').textContent = uniquePrograms;
      
      const lastUpdated = localStorage.getItem('rosterLastUpdated') || 'Just now';
      document.getElementById('lastUpdated').textContent = lastUpdated.split(',')[0]; // Just show date

      // Update title
      document.getElementById('rosterTableTitle').textContent = `Current Roster (${rosterData.length} Students)`;

      // Update table
      const tbody = document.querySelector('#rosterTable tbody');
      tbody.innerHTML = '';

      if (rosterData.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="5" class="empty-state">
              <div class="empty-state-icon"></div>
              <div>No roster data loaded. Upload a CSV file or download from Canvas to get started.</div>
            </td>
          </tr>
        `;
        return;
      }

      rosterData.forEach((student, index) => {
        const row = tbody.insertRow();
        row.innerHTML = `
          <td><strong>${escapeHtml(student.name)}</strong></td>
          <td>${escapeHtml(student.id)}</td>
          <td>${escapeHtml(student.program)}</td>
          <td>${escapeHtml(student.email)}</td>
          <td>
            <a href="#" class="action-link" onclick="editStudent(${index}); return false;">Edit</a>
            <a href="#" class="action-link" onclick="removeStudent(${index}); return false;">Remove</a>
          </td>
        `;
      });
    }

    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    function loadSavedRoster() {
      const saved = localStorage.getItem('rosterData');
      if (saved) {
        try {
          rosterData = JSON.parse(saved);
          updateRosterDisplay();
        } catch (error) {
          console.error('Error loading saved roster:', error);
        }
      }
    }

    function editStudent(index) {
      const student = rosterData[index];
      const newName = prompt('Edit Student Name:', student.name);
      
      if (newName === null) return; // User cancelled
      
      const newId = prompt('Edit Student ID:', student.id);
      if (newId === null) return;
      
      const newProgram = prompt('Edit Program:', student.program);
      if (newProgram === null) return;
      
      rosterData[index].name = newName || student.name;
      rosterData[index].id = newId || student.id;
      rosterData[index].program = newProgram || student.program;
      
      localStorage.setItem('rosterData', JSON.stringify(rosterData));
      localStorage.setItem('rosterLastUpdated', new Date().toLocaleString());
      updateRosterDisplay();
    }

    function removeStudent(index) {
      if (confirm('Remove ' + rosterData[index].name + ' from roster?')) {
        rosterData.splice(index, 1);
        localStorage.setItem('rosterData', JSON.stringify(rosterData));
        localStorage.setItem('rosterLastUpdated', new Date().toLocaleString());
        updateRosterDisplay();
      }
    }

    function clearRoster() {
      if (confirm('Clear entire roster?\n\nThis will remove all ' + rosterData.length + ' students from the roster.')) {
        rosterData = [];
        localStorage.removeItem('rosterData');
        localStorage.removeItem('rosterLastUpdated');
        updateRosterDisplay();
        alert('Roster cleared successfully!');
      }
    }


  </script>

</body>
</html>