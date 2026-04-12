<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/csrf.php';
require_login();
$csrfToken = csrf_token('tool1_proxy');
$role = $_SESSION['user_role'] ?? 'faculty';

error_log("user role: " . $role);
if (empty($_SESSION['class_data'])) {
    header('Location: select-courses.php');
    exit;
}


//-----------------------------------------------------
/*
This part of the php is for the form handling of the syllabus information.
*/

$host = getenv('MYSQL_HOSTNAME');
$dbname = getenv('MYSQL_DATABASE');
$username = getenv('MYSQL_USER');
$password = getenv('MYSQL_PASS');

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$courses_json = json_encode($_SESSION['class_data'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

$program_year = $_SESSION['class_data'][0]['term']["name"];
$program_year = substr($program_year, 0, 4);


?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Course Setup | ABET Tools</title>
  <link rel="stylesheet" href="/assets/css/tool1.css">
  <link rel="stylesheet" href="/assets/css/course-setup.css">
  <script src="/assets/scripts/course_step.js"></script>
  <script>
    console.log("class_data:", <?= $courses_json ?>);
  </script>
</head>
<body>

  <header class="site-header">
    <div class="site-title">ABET Tools - Course Setup</div>
    <a href="select-courses.php" class="nav-link">← Back to Course Selection</a>
  </header>

  <div class="main-container">
    <div class="page-header">
      <div class="page-title">
        <h1>Course Setup</h1>
        <p>Upload a roster for each selected course, then start extraction</p>
      </div>
    </div>

    <div class="setup-layout">
      <!-- Sidebar -->
      <div class="setup-sidebar">
        <div class="sidebar-header">Selected Courses</div>
        <div id="sidebarItems"></div>
      </div>

      <!-- Main content area -->
      <div class="setup-main" id="setupMain">
        <div class="course-heading">
          <div>
            <div class="course-code" id="courseCode"></div>
            <div class="course-name" id="courseName"></div>
          </div>
          <span class="course-progress" id="courseProgress"></span>
        </div>

        <div class="card">
          <div class="card-title">Destination Course ID</div>
          <p style="font-size:0.85rem;color:#555;margin:0 0 12px">Enter the Canvas course ID where extracted data will be uploaded.</p>
          <div style="display:flex;gap:10px;align-items:center" id="locationDestCourse">
            <input type="text" id="destCourseInput" placeholder="e.g. 240102" style="padding:8px 12px;border:1px solid #e0e0e0;border-radius:4px;font-size:0.9rem;flex:1">
            <button class="btn btn-outline" id="verifyDestBtn" onclick="verifyDestCourse()">Verify</button>
          </div>
          <div id="destStatus" style="margin-top:8px;font-size:0.85rem"></div>
        </div>

        <div class="card">
          <div class="card-title">Upload Class Roster</div>
          <div class="upload-zone" id="uploadZone">
            <div class="upload-icon" id="uploadIcon">📄</div>
            <div class="upload-text" id="uploadText">Click or drag to upload CSV / XLS file</div>
            <div class="upload-hint">Accepted: .csv, .xls</div>
          </div>
          <input type="file" id="rosterInput" accept=".csv,.xls" style="display:none">
        </div>

        <div class="card">

          <div class="card-title">Syllabus Information</div>
          

          <div class="card-body">
            <form id="syllabusForm" method="POST" action="">

              <!-- BASIC INFO -->
              <div class="form-group">
                <label class="form-label">Course Subject<span class="required">* (e.g., CSE)</span></label>
                <input type="text" name="course_subject" class="form-input" required>
                <label class="form-label">Course Number<span class="required">*</span></label>
                <input type="text" name="course_number" class="form-input" required>
                <label class="form-label">Course Name<span class="required">*</span></label>
                <input type="text" name="syllabus_course_name" class="form-input" required>
              </div>

              <div class="form-grid">
                <div class="form-group">
                  <label class="form-label">Credits Hours<span class="required">*</span></label>
                  <input type="text" name="credits_hours" class="form-input" required>
                  <label class="form-label">Contact Hours</label>
                  <input type="text" name="contact_hours" class="form-input">
                  <label class="form-label">Categorization of credits<span class="required">*</span></label>
                    <select name="category" class="form-select" required>
                      <option value="">Select category</option>
                      <option value="math">Math</option>
                      <option value="science">Science</option>
                      <option value="engineering">Engineering</option>
                      <option value="other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                  <label class="form-label">Course Coordinators<span class="required">*</span></label>

                  <div id="coordinator-container">
                    <div class="form-group">
                      <input
                        type="text"
                        name="course_coordinators[]"
                        class="form-input"
                        placeholder="Enter instructor name"
                        required
                      >
                      <button class = "btn btn-remove" style = "margin-top: 3%" type="button" onclick="removeRow(this)" class="btn btn-secondary">
                        Remove
                      </button>
                    </div>
                  </div>

                  <button class = "btn btn-outline" type="button" onclick="addCoordinator()" class="btn btn-secondary">
                    + Add Instructor
                  </button>
                </div>

              </div>

              <!-- TEXTBOOKS -->
              <div class="divider"><span class="divider-text">Textbooks</span></div>

              <div id="textbook-container">
                <div class="form-group textbook-row">
                  <input type="text" name="textbooks[]" class="form-input" placeholder="Enter textbook">
                  <button class = "btn btn-remove" style = "margin-top: 3%" type="button" onclick="removeRow(this)" class="btn btn-secondary">Remove</button>
                </div>
              </div>
              <div>
                <button class = "btn btn-outline" type="button" onclick="addTextbook()" class="btn btn-secondary">
                  + Add Textbook
                </button>
              </div>
              
              <!-- COURSE INFO -->
              <div class="divider"><span class="divider-text">Course Information</span></div>

              <div class="form-group">
                <label class="form-label">a. Catalog Description</label>
                <textarea name="catalog_description" class="form-input"></textarea>
              </div>

              <div class="form-group">
                <label class="form-label">b. Prerequisites</label>
                <textarea name="prerequisites" class="form-input"></textarea>
              </div>

              <div class="form-group">
                <label class="form-label">c. Elective</label>
                <select name="course_type" class="form-select" required>
                  <option value="">Select type</option>
                  <option value="R">Required (R)</option>
                  <option value="E">Elective (E)</option>
                  <option value="SE">Senior Elective (SE)</option>
                </select>
              </div>

              <!-- COURSE GOALS -->
              <div class="divider"><span class="divider-text">Course Goals</span></div>

              <label class="form-label">a. Course Outcomes</label>
              <div id="course-outcomes-container">
                <div class="form-group">
                  <input type="text" name="course_outcomes[]" class="form-input" placeholder="Enter course outcome">
                  <button class = "btn btn-remove" style = "margin-top: 3%" type="button" onclick="removeRow(this)" class="btn btn-secondary">Remove</button>
                </div>
              </div>

              <button class = "btn btn-outline" type="button" onclick="addCourseOutcome()" class="btn btn-secondary">+ Add Course Outcome</button>

              <br><br>

              <label class="form-label">
                b. Explicitly indicate which of the student outcomes listed in Criterion 3 or any other outcomes are addressed by the course
              </label>
              <div id="student-outcomes-container">
                <div class="form-group">
                  <input type="text" name="student_outcomes_addressed[]" class="form-input" placeholder="Enter student outcome addressed">
                  <button class = "btn btn-remove" style = "margin-top: 3%" type="button" onclick="removeRow(this)" class="btn btn-secondary">Remove</button>
                </div>
              </div>

              <button class = "btn btn-outline" type="button" onclick="addStudentOutcome()" class="btn btn-secondary">+ Add Student Outcome</button>

              <!-- TOPICS -->
              <div class="divider"><span class="divider-text">Topics</span></div>

              <div id="topics-container">
                <div class="form-group topic-row">
                  <input type="text" name="topics[]" class="form-input" placeholder="Enter topic">
                  <button class = "btn btn-remove" style = "margin-top: 3%" type="button" onclick="removeRow(this)" class="btn btn-secondary">Remove</button>
                </div>
              </div>
              <div>
                <button class = "btn btn-outline" type="button" onclick="addTopic()" class="btn btn-secondary">
                  + Add Topic
                </button>
              </div>
              <br><br>
              <button type="submit" class="btn btn-primary">Save</button>

            </form>
          </div>
        </div>

        <div class="alert alert-error" id="errorAlert" style="display:none">
          <div class="alert-content">
            <strong>Error:</strong> <span id="errorMessage"></span>
          </div>
        </div>

        <div class="btn-row">
          <button type="button" class="btn btn-outline" id="saveNextBtn" onclick="saveAndNext()">Save & Next Course →</button>
          <button type="button" class="btn btn-primary" id="startBtn" style="display:none" onclick="startAllExtractions()">
            🚀 Start All Extractions
          </button>
        </div>
      </div>
    </div>
  </div>

  <script>
    let csrfToken = '<?= htmlspecialchars($csrfToken, ENT_QUOTES, "UTF-8") ?>';
    let courses = <?= $courses_json ?>;
    let activeIdx = 0;
    // Per-course roster files: { courseId: File }
    const rosterFiles = {};
    let verifiedDestCourse = null; // { id, name, course_code }
    const role = <?php echo json_encode($role); ?>;
    console.log(role)

    //Hide Dest ID Input initially
    function destCourseInputAbility()
    {
      const destCourseInput = document.getElementById('destCourseInput');
      destCourseInput.readOnly = true;
    }

    // Build sidebar
    function buildSidebar() {
      const container = document.getElementById('sidebarItems');
      container.innerHTML = '';
      courses.forEach((c, i) => {
        const item = document.createElement('div');
        item.className = 'sidebar-item' + (i === activeIdx ? ' active' : '');
        item.onclick = () => selectCourse(i);
        item.id = 'sidebar-' + i;
        const hasRoster = !!rosterFiles[c.id];
        item.innerHTML = `
          <div class="sidebar-icon ${hasRoster ? 'done' : 'pending'}">${hasRoster ? '✓' : '!'}</div>
          <div>
            <div class="sidebar-name">${esc(c.name)}</div>
            <div class="sidebar-code">${esc(c.course_code)}</div>
          </div>`;
        container.appendChild(item);
      });
    }

    function selectCourse(idx) {
      activeIdx = idx;
      const c = courses[idx];
      document.getElementById('courseCode').textContent = c.course_code;
      document.getElementById('courseName').textContent = c.name;
      document.getElementById('courseProgress').textContent = `Course ${idx + 1} of ${courses.length}`;

      // Update upload zone state
      const hasFile = !!rosterFiles[c.id];
      const zone = document.getElementById('uploadZone');
      const icon = document.getElementById('uploadIcon');
      const text = document.getElementById('uploadText');
      if (hasFile) {
        zone.classList.add('has-file');
        icon.textContent = '✅';
        text.textContent = rosterFiles[c.id].name + ' uploaded';
      } else {
        zone.classList.remove('has-file');
        icon.textContent = '📄';
        text.textContent = 'Click or drag to upload CSV / XLS file';
      }

      // Show/hide buttons
      const isLast = idx >= courses.length - 1;
      document.getElementById('saveNextBtn').style.display = isLast ? 'none' : 'inline-flex';

      // Show start button only if all courses have rosters AND dest verified
      const allReady = courses.every(c => !!rosterFiles[c.id]) && verifiedDestCourse;
      document.getElementById('startBtn').style.display = (isLast || allReady) ? 'inline-flex' : 'none';
      document.getElementById('startBtn').disabled = !allReady;

      // Restore dest course input state
      if (verifiedDestCourse) {
        document.getElementById('destCourseInput').value = verifiedDestCourse.id;
        document.getElementById('destStatus').innerHTML = '✅ <strong>' + esc(verifiedDestCourse.name) + '</strong>';
        document.getElementById('destStatus').style.color = '#0a7d30';
      }

      // Update sidebar highlights
      buildSidebar();
      hideError();
    }

    // File upload handling
    const uploadZone = document.getElementById('uploadZone');
    const rosterInput = document.getElementById('rosterInput');

    uploadZone.addEventListener('click', () => rosterInput.click());
    uploadZone.addEventListener('dragover', e => { e.preventDefault(); uploadZone.classList.add('dragover'); });
    uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('dragover'));
    uploadZone.addEventListener('drop', e => {
      e.preventDefault();
      uploadZone.classList.remove('dragover');
      if (e.dataTransfer.files.length > 0) handleFile(e.dataTransfer.files[0]);
    });
    rosterInput.addEventListener('change', () => {
      if (rosterInput.files.length > 0) handleFile(rosterInput.files[0]);
    });

    function handleFile(file) {
      const ext = file.name.split('.').pop().toLowerCase();
      if (!['csv', 'xls'].includes(ext)) {
        showError('Only .csv and .xls files are accepted.');
        return;
      }
      rosterFiles[courses[activeIdx].id] = file;
      selectCourse(activeIdx);
    }

    function saveAndNext() {
      if (!rosterFiles[courses[activeIdx].id]) {
        showError('Please upload a roster file first.');
        return;
      }
      if (activeIdx < courses.length - 1) {
        selectCourse(activeIdx + 1);
      }
    }

    async function startAllExtractions() {
      const btn = document.getElementById('startBtn');
      //Gets the syllabus form data. 
      const form = document.getElementById('syllabusForm');
      btn.disabled = true;
      btn.textContent = 'Starting…';
      hideError();

      let successCount = 0;

      for (const course of courses) {
        try {
          const body = new FormData(form);
          console.log("Form data entries:", body.entries());
          body.append('program_year', '<?= $program_year ?>');
          body.append('action', 'start-extraction-v2');
          body.append('csrf_token', csrfToken);
          body.append('source_course_id', String(course.id));
          body.append('dest_course_id', String(verifiedDestCourse.id));
          body.append('course_name', String(course.name));
          body.append('overwrite', course.overwrite ? '1' : '0');
          body.append('roster_file', rosterFiles[course.id]);

          const res = await fetch('api-proxy.php', { method: 'POST', body });
          const data = await res.json();
          if (data.next_csrf) csrfToken = data.next_csrf;

          if (data.success) {
            successCount++;
          } else {
            showError(`Failed for ${course.name}: ${data.message}`);
          }
        } catch (err) {
          showError(`Network error for ${course.name}: ${err.message}`);
        }
      }

      if (successCount === courses.length) {
        // All dispatched — redirect to jobs page
        window.location.href = 'jobs.php';
      } else {
        btn.disabled = false;
        btn.textContent = '🚀 Start All Extractions';
      }
    }

    function showError(msg) {
      document.getElementById('errorAlert').style.display = 'block';
      document.getElementById('errorMessage').textContent = msg;
    }
    function hideError() {
      document.getElementById('errorAlert').style.display = 'none';
    }
    function esc(str) {
      if (!str) return '';
      const d = document.createElement('div');
      d.textContent = str;
      return d.innerHTML;
    }

    // Dest course verification
    async function verifyDestCourse() {
      const input = document.getElementById('destCourseInput');
      const status = document.getElementById('destStatus');
      const courseId = input.value.trim();
      if (!courseId) { showError('Please enter a course ID.'); return; }

      status.textContent = 'Verifying…';
      status.style.color = '#555';
      hideError();

      try {
        const body = new FormData();
        body.append('action', 'verify-course');
        body.append('csrf_token', csrfToken);
        body.append('course_id', courseId);
        const res = await fetch('api-proxy.php', { method: 'POST', body });


        const data = await res.json();
        if (data.next_csrf) csrfToken = data.next_csrf;

        if (data.success) {
          verifiedDestCourse = data.course;
          status.innerHTML = '<strong>' + esc(data.course.name) + '</strong> (' + esc(data.course.course_code) + ')';
          status.style.color = '#0a7d30';
          selectCourse(activeIdx); // refresh button state
        } else {
          verifiedDestCourse = null;
          console.log(data.message)
          status.textContent = '' + data.message;
          status.style.color = '#c00';
          
          //If the destination course id is not verified, admin can re-edit it but faculty cannot
          if (role === 'admin')
          {
            input.readOnly = false;
          }
          else
          {
            status.textContent = "Course not found; check with coordinator";
          }
          selectCourse(activeIdx);
        }
      } catch (err) {
        status.textContent = 'Network error: ' + err.message;
        status.style.color = '#c00';
      }
    }

    // Clear verification if user edits the ID
    document.getElementById('destCourseInput').addEventListener('input', function() {
      if (verifiedDestCourse && this.value.trim() !== String(verifiedDestCourse.id)) {
        verifiedDestCourse = null;
        document.getElementById('destStatus').textContent = '';
        selectCourse(activeIdx);
      }
    });

    // Init
    selectCourse(0);

    const sessionDestId = sessionStorage.getItem('abet_dest_course_id');
    console.log(sessionDestId)
    if (sessionDestId) {
      document.getElementById('destCourseInput').value = sessionDestId;
      verifyDestCourse().then(() => {
        if (!verifiedDestCourse) selectCourse(0); // fallback if verification fails
      });
    } else {
      selectCourse(0);
    }
    destCourseInputAbility();
  </script>

</body>
</html>
