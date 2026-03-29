<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/csrf.php';
require_login();
$csrfToken = csrf_token('tool1_proxy');
require_once getenv('ABET_PRIVATE_DIR') . '/lib/security_headers.php'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manage Roster | ABET Tools</title>
  <link rel="stylesheet" href="/assets/css/roster.css">
</head>
<body>

  <header class="site-header">
    <div class="site-title">ABET Tools - Roster & Extraction</div>
    <div>
      <a href="tool1.php" class="nav-link pipeline-nav-link" style="margin-right: 15px;">← Back to Connection</a>
      <a href="/../index.php" class="nav-link pipeline-nav-link">← Dashboard</a>
    </div>

  </header>

  <div class="main-container">
    
    <div class="page-header">
      <div class="page-title">
        <h1>Upload Roster & Run Extraction</h1>
        <p id="classInfo">Upload your class roster, then run the extraction pipeline</p>
      </div>
    </div>

    <!-- Upload Section -->
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Upload Roster</h2>
      </div>
      <div class="card-body">
        

        <!-- Action Buttons -->
        <div class="btn-group">
          <!-- <button class="btn btn-primary" onclick="downloadFromCanvas()">
            Download from Canvas
          </button> -->
          <button class="btn btn-secondary" onclick="document.getElementById('rosterFile').click()">
             Upload CSV/XLS File
          </button>
          <button class="btn btn-secondary" onclick="clearRoster()" style="margin-left: auto;">
            Clear Roster
          </button>
        </div>

        <!-- File Upload Area -->
        <div class="file-upload" id="dropZone" onclick="document.getElementById('rosterFile').click()">
          <div class="file-upload-text">Click to upload or drag and drop</div>
          <div class="file-upload-hint">Accepts .csv or .xls roster files (Name, ID, Program will be extracted)</div>
        </div>
        <input type="file" id="rosterFile" accept=".csv,.xls" onchange="handleFileUpload(event)">

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
                  <div>No roster data loaded. Upload a CSV or XLS roster file to get started.</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

      <!-- <a href="assignments.php" class="btn btn-primary">
        Continue to Assignments
      </a>
    </div> -->

    <!-- Pipeline Trigger -->
    <div class="pipeline-section">
      <div class="card">
        <div class="card-header">
          <h2 class="card-title">Run Extraction & Formatting</h2>
        </div>
        <div class="card-body">
          <p style="color: var(--text-light); margin-top: 0;">
            This will extract assignment data from your source course, upload it to the
            destination shell, and create formatted Canvas modules. This process takes
            2–5 minutes.
          </p>
          <button class="btn btn-primary" id="runPipelineBtn"
                  onclick="runPipeline()" disabled>
            Run Extraction & Formatting
          </button>
        </div>
      </div>
      <div class="pipeline-status" id="pipelineStatus"></div>
      <div class="progress-container" id="progressContainer">
        <div class="progress-bar" id="progressBar"></div>
      </div>
    </div>

  <script>
    // Global variables
    let currentCsrfToken = '<?= htmlspecialchars($csrfToken, ENT_QUOTES, "UTF-8") ?>';
    let rosterData = [];
    // // Load saved roster on page load
    // window.addEventListener('DOMContentLoaded', function() {
    //   loadSelectedClass();
    //   loadSavedRoster();
    // });

    // // Load selected class information from localStorage
    // function loadSelectedClass() {
    //   const selectedClassStr = localStorage.getItem('selectedClass');
    //   if (selectedClassStr) {
    //     try {
    //       const classInfo = JSON.parse(selectedClassStr);
    //       // Update the page header with selected class info
    //       const classInfoElement = document.getElementById('classInfo');
    //       if (classInfoElement && classInfo.name && classInfo.semester) {
    //         classInfoElement.textContent = classInfo.name + ' • ' + classInfo.semester;
    //       }
    //     } catch (error) {
    //       console.error('Error loading selected class:', error);
    //     }
    //   }
    // }
    let uploadedFile = null;



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

      if (!file.name.match(/\.(csv|xls)$/i)) {
        alert('Please upload a .csv or .xls file');
        return;
      }

      uploadedFile = file;
      showFileSelected(file.name);

      if (file.name.match(/\.xls$/i)) {
        document.getElementById('runPipelineBtn').disabled = false;
        document.getElementById('dropZone').innerHTML +=
          '<div class="file-upload-hint" style="color:var(--text-light); margin-top:6px;">' +
          'XLS roster preview not available — file will be parsed server-side during extraction</div>';
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

        // // Save to localStorage
        // localStorage.setItem('rosterData', JSON.stringify(rosterData));
        // localStorage.setItem('rosterLastUpdated', new Date().toLocaleString());
        
        // Update display
        updateRosterDisplay();
        // alert('Roster uploaded successfully!\n\n' +
        //       '• Students loaded: ' + rosterData.length + '\n' +
        //       '• Data extracted: Name, ID, Program, Email\n\n' +
        //       'Roster will be saved until you upload a new file.');
        document.getElementById('runPipelineBtn').disabled = false;

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
      
      const lastUpdated = new Date().toLocaleDateString();
      document.getElementById('lastUpdated').textContent = lastUpdated;

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

    function showFileSelected(filename) {
      const dz = document.getElementById('dropZone');
      dz.innerHTML =
        '<div class="file-upload-text">✅ ' + escapeHtml(filename) + '</div>' +
        '<div class="file-upload-hint">File selected — click to change</div>';
      dz.style.borderColor = 'var(--success)';
      dz.style.background = '#e6f4ea';
    }

    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }
    
    // function loadSavedRoster() {
    //   const saved = localStorage.getItem('rosterData');
    //   if (saved) {
    //     try {
    //       rosterData = JSON.parse(saved);
    //       updateRosterDisplay();
    //     } catch (error) {
    //       console.error('Error loading saved roster:', error);
    //     }
    //   }
    // }

    let pollingTimeout = null;
    let pollingInFlight = false;
    let pipelineActive = false;

    // localStorage helpers 
    const _CLIENT_JOB_TTL_MS = 3600 * 1000; // 1 hour

    function getStoredJob() {
      const raw = localStorage.getItem('activeExtractionJob');
      if (!raw) return null;
      try {
        const parsed = JSON.parse(raw);
        if (parsed && parsed.jobId) return parsed;
      } catch (_) {  }

      localStorage.removeItem('activeExtractionJob');
      return null;
    }

    function saveJob(jobId) {
      const entry = JSON.stringify({ jobId, createdAt: Date.now() });
      localStorage.setItem('activeExtractionJob', entry);
    }

    function clearStoredJob() {
      localStorage.removeItem('activeExtractionJob');
    }

    // Navigation guard 
    function setPipelineActive(active) {
      pipelineActive = active;
      // Update nav link click guards
      document.querySelectorAll('.pipeline-nav-link').forEach(link => {
        if (active) {
          link.dataset.originalHref = link.href;
          link.addEventListener('click', navGuardHandler);
        } else {
          link.removeEventListener('click', navGuardHandler);
        }
      });
    }

    function navGuardHandler(e) {
      if (!pipelineActive) return;
      const leave = confirm(
        'An extraction is still running in the background.\n\n' +
        'If you leave now, you will lose track of this job\'s progress ' +
        'Leave anyway?'
      );
      if (!leave) {
        e.preventDefault();
      }
    }

    window.addEventListener('beforeunload', (e) => {
      if (pipelineActive) e.preventDefault();
    });

    // Check on page load if a job is already running
    window.addEventListener('DOMContentLoaded', () => {
      const stored = getStoredJob();
      if (stored) {
        const ageMs = Date.now() - stored.createdAt;
        if (ageMs > _CLIENT_JOB_TTL_MS) {
          clearStoredJob();
          return;
        }
        resumePolling(stored.jobId);
      } else {
        // No active job — idle
      }
    });

    async function runPipeline() {
      if (!uploadedFile) {
        alert('Please upload a roster file first.');
        return;
      }

      const btn = document.getElementById('runPipelineBtn');
      const uploadSection = document.querySelector('.card');

      btn.disabled = true;
      btn.textContent = 'Starting Extraction...';
      uploadSection.classList.add('pipeline-locked');

      setStatus('running',
        '<span class="spinner"></span>' +
        '<strong>Initializing:</strong> Starting data extraction on Canvas…'
      );

      try {
        const body = new FormData();
        body.append('action', 'start-extraction');
        body.append('roster_file', uploadedFile);
        body.append('csrf_token', currentCsrfToken);

        const res = await fetch('api-proxy.php', { method: 'POST', body });
        const data = await res.json();
        if (data.next_csrf) {
          currentCsrfToken = data.next_csrf;
        }

        if (!data.success || !data.job_id) {
          setStatus('error', '<strong>Failed to start extraction:</strong> ' + escapeHtml(data.message));
          btn.disabled = false;
          btn.textContent = 'Retry Extraction & Formatting';
          uploadSection.classList.remove('pipeline-locked');
          return;
        }
        
        // Clear any stale job before saving the new one to prevent a previously
        // completed job from retriggering formatting on an unexpected page refresh.
        clearStoredJob();
        saveJob(data.job_id);
        setPipelineActive(true);
        
        // Begin the polling cycle
        resumePolling(data.job_id);

      } catch (err) {
        setStatus('error', '<strong>Network error:</strong> ' + escapeHtml(err.message));
        btn.disabled = false;
        btn.textContent = 'Retry Extraction & Formatting';
        uploadSection.classList.remove('pipeline-locked');
      }
    }

    function resumePolling(jobId) {
      setPipelineActive(true);
      const btn = document.getElementById('runPipelineBtn');
      const uploadSection = document.querySelector('.card');
      
      btn.disabled = true;
      btn.textContent = 'Processing in Background...';
      uploadSection.classList.add('pipeline-locked');
      
      setStatus('running',
        '<span class="spinner"></span>' +
        '<strong>Step 1/2:</strong> Extracting data from Canvas and uploading to destination course… ' +
        '<br><span style="color:var(--text-light)">This may take 2–5 minutes.</span>'
      );

      if (pollingTimeout) {
        clearTimeout(pollingTimeout);
      }
      pollingInFlight = false;
      
      // Use setTimeout chain so the next poll only fires
      // after the previous one completes — prevents CSRF token race conditions.
      scheduleNextPoll(jobId);
    }

    function scheduleNextPoll(jobId) {
      pollingTimeout = setTimeout(() => {
        pollExtractionStatus(jobId);
      }, 5000);
    }

    async function pollExtractionStatus(jobId) {
      if (pollingInFlight) return;
      pollingInFlight = true;
      try {
        const body = new FormData();
        body.append('action', 'check-extraction-status');
        body.append('job_id', jobId);
        body.append('csrf_token', currentCsrfToken);

        const res = await fetch('api-proxy.php', { method: 'POST', body });
        const data = await res.json();
        if (data.next_csrf) {
          currentCsrfToken = data.next_csrf;
        }

        if (!data.success) {
          clearTimeout(pollingTimeout);
          clearStoredJob();
          setPipelineActive(false);
          const isStaleJob = (data.message || '').toLowerCase().includes('not found');
          if (isStaleJob) {
            setStatus('error',
              '<strong>Previous extraction was interrupted.</strong><br>' +
              'The server may have restarted. Please start the extraction again.'
            );
          } else {
            setStatus('error', '<strong>Status check failed:</strong> ' + escapeHtml(data.message));
          }
          unlockUI();
          return;
        }

        const statusStr = data.job_status.status;
        
        if (statusStr === 'processing') {
          const progContainer = document.getElementById('progressContainer');
          if (progContainer) {
            progContainer.style.display = 'block';
          }
          
          if (data.job_status.progress) {
            const progBar = document.getElementById('progressBar');
            if (progBar) {
              progBar.style.width = data.job_status.progress + '%';
            }
          }
          if (data.job_status.message) {
            setStatus('running', '<span class="spinner"></span> <strong>Working:</strong> ' + escapeHtml(data.job_status.message));
          }
          scheduleNextPoll(jobId);
        }
        else if (statusStr === 'failed') {
          clearTimeout(pollingTimeout);
          clearStoredJob();
          setPipelineActive(false);
          setStatus('error', '<strong>Extraction failed:</strong> ' + escapeHtml(data.job_status.error || 'Unknown error.'));
          unlockUI();
        } 
        else if (statusStr === 'completed') {
          clearTimeout(pollingTimeout);
          // NOTE: Do NOT clear localStorage here. Keep it until runFormatting
          // finishes so a page reload during formatting can re-trigger it.
          
          const progContainer = document.getElementById('progressContainer');
          if (progContainer) {
            progContainer.style.display = 'none';
          }
          
          runFormatting(data.job_status.course_folder_name, data.job_status.term_display);
        }
        else {
          scheduleNextPoll(jobId);
        }

      } catch (err) {
        console.error('Extraction status check failed:', err);
        scheduleNextPoll(jobId);
      } finally {
        pollingInFlight = false;
      }
    }

    const urlParams = new URLSearchParams(window.location.search);
    const overwriteFlag = urlParams.get('overwrite') === '1';

    async function runFormatting(folderName, termDisplay) {
      setStatus('running',
        '<span class="spinner"></span>' +
        '<strong>Step 2/2:</strong> Extraction complete! Generating Canvas modules… ' +
        '<br><span style="color:var(--text-light)">This will finish shortly.</span>'
      );

      try {
        const body = new FormData();
        body.append('action', 'run-formatting');
        body.append('course_folder_name', folderName || '');
        body.append('term_display', termDisplay || '');
        body.append('csrf_token', currentCsrfToken);
        if (overwriteFlag) {
          body.append('overwrite', '1');
        }

        const res = await fetch('api-proxy.php', { method: 'POST', body });
        const data = await res.json();
        if (data.next_csrf) {
          currentCsrfToken = data.next_csrf;
        }

        if (!data.success) {
          clearStoredJob();
          setPipelineActive(false);
          setStatus('error', '<strong>Formatting failed:</strong> ' + escapeHtml(data.message));
          unlockUI();
          return;
        }

        // Pipeline fully done — safe to clear localStorage now
        clearStoredJob();
        setPipelineActive(false);

        setStatus('success',
          '<strong>✅ Pipeline complete!</strong><br>' +
          'Data has been extracted, formatted, and uploaded to your Canvas destination course.'
        );
        
        const btn = document.getElementById('runPipelineBtn');
        btn.disabled = false;
        btn.textContent = 'Run Extraction & Formatting Again';
        document.querySelector('.card').classList.remove('pipeline-locked');

      } catch (err) {
        clearStoredJob();
        setPipelineActive(false);
        setStatus('error', '<strong>Formatting network error:</strong> ' + escapeHtml(err.message));
        unlockUI();
      }
    }

    function unlockUI() {
      const btn = document.getElementById('runPipelineBtn');
      btn.disabled = false;
      btn.textContent = 'Retry Extraction & Formatting';
      document.querySelector('.card').classList.remove('pipeline-locked');
    }

    function setStatus(type, html) {
      const el = document.getElementById('pipelineStatus');
      el.className = 'pipeline-status active status-' + type;
      el.innerHTML = html;
      el.scrollIntoView({ behavior: 'smooth' });
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
      
      // localStorage.setItem('rosterData', JSON.stringify(rosterData));
      // localStorage.setItem('rosterLastUpdated', new Date().toLocaleString());
      updateRosterDisplay();
    }

    function removeStudent(index) {
      if (confirm('Remove ' + rosterData[index].name + ' from roster?')) {
        rosterData.splice(index, 1);
        // localStorage.setItem('rosterData', JSON.stringify(rosterData));
        // localStorage.setItem('rosterLastUpdated', new Date().toLocaleString());
        updateRosterDisplay();
      }
    }

    function clearRoster() {
      if (confirm('Clear entire roster?\n\nThis will remove all ' + rosterData.length + ' students from the roster.')) {
        rosterData = [];
        // localStorage.removeItem('rosterData');
        // localStorage.removeItem('rosterLastUpdated');
        updateRosterDisplay();
        alert('Roster cleared successfully!');
      }
    }


  </script>

</body>
</html>