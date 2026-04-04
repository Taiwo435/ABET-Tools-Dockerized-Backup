<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/csrf.php';
require_login();
$csrfToken = csrf_token('tool1_proxy');
// Redirect non-admins away
if (($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: /home');
    exit;
}

$configPath = getenv('ABET_PRIVATE_DIR') . '/destination_courses.php';
$config = require $configPath;
$destCourses = $config['dest_courses'];

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $labels = $_POST['labels'] ?? [];
    $ids    = $_POST['ids'] ?? [];

    // Validate
    $newCourses = [];
    $valid = true;
    foreach ($labels as $i => $label) {
        $label = trim($label);
        $id    = trim($ids[$i] ?? '');
        if (!$label || !$id) {
            $error = 'All fields are required.';
            $valid = false;
            break;
        }
        if (!preg_match('/^\d+$/', $id)) {
            $error = 'Course IDs must be numeric.';
            $valid = false;
            break;
        }
        $newCourses[] = ['label' => $label, 'id' => $id];
    }

    if ($valid && !empty($newCourses)) {
        // Write back to destination_courses.php
        $overwriteMap = [];
        foreach ($newCourses as $course) {
            $overwriteMap[$course['label']] = $course['id'];
        }
        $merged = [];
        foreach($destCourses as $existing)
          {
            if (isset($overwriteMap[$existing['label']])) {
              $merged[] = ['label' => $existing['label'], 'id' => $overwriteMap[$existing['label']]];
            } else {
              $merged[] = $existing;
            }
          }
        
        //Write merged result
        $export = "<?php\nreturn [\n    'dest_courses' => [\n";
        foreach($merged as $course)
          {
            $label = addslashes($course['label']);
            $id = addslashes($course['id']);
            $export .= "        ['label' => '{$label}', 'id' => '{$id}'], \n";
          }
        $export .= "    ]\n];\n";

        if (file_put_contents($configPath, $export) !== false) {
            $destCourses = $merged;
            $success = true;
            $response = ['status' => true];
            echo json_encode($response);
            exit;
        } else {
            $error = 'Failed to save config. Check file permissions.';
            $response = ['status' => false, 'error' => $error];
            echo json_encode($response);
            exit;
        }
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Panel | ABET Tools</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 0; }
    .site-header { background:  #8C1D40; color: white; padding: 14px 24px; display: flex; justify-content: space-between; align-items: center; }
    .site-title { font-size: 18px; font-weight: bold; }
    .nav-link { color: #aaa; text-decoration: none; font-size: 14px; }
    .nav-link:hover { color: white; }
    .main-container { max-width: 700px; margin: 40px auto; background: white; border-radius: 8px; padding: 32px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    h1 { font-size: 22px; margin-bottom: 6px; }
    p.subtitle { color: #666; margin-bottom: 24px; font-size: 14px; }
    .section-title { font-size: 16px; font-weight: bold; margin-bottom: 16px; border-bottom: 2px solid #eee; padding-bottom: 8px; }
    .course-row { display: flex; gap: 12px; align-items: center; margin-bottom: 12px; }
    .course-row input { flex: 1; padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
    .course-row label { font-size: 13px; color: #555; width: 60px; flex-shrink: 0; }
    .btn { padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: bold; }
    .btn-primary { background: #8B0000; color: white; }
    .btn-primary:hover { background: #a00000; }
    .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
    .alert-success { background: #e6f4ea; color: #2e7d32; border: 1px solid #a5d6a7; }
    .alert-error { background: #fdecea; color: #c62828; border: 1px solid #ef9a9a; }
    .row-header { display: flex; gap: 12px; margin-bottom: 4px; }
    .row-header span { flex: 1; font-size: 12px; color: #888; font-weight: bold; text-transform: uppercase; }
    .row-header span:first-child { width: 60px; flex: none; }
    #overlay {display: none; top: 0; left: 0; position: fixed; width: 100%; height: 100%; background: black; z-index: 9998;}
    #popup-form { display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border: 1px solid #ccc;  padding: 20px; z-index: 9999; box-shadow: 0 0 0 9999px rgba(0,0,0,0.5); max-height: 80vh; overflow-y: auto;}
  </style>
</head>
<body>

<header class="site-header">
  <div class="site-title">ABET Tools - Admin Panel</div>
  <a href="/home" class="nav-link">← Back to Dashboard</a>
</header>

<div class="main-container">
  <h1>Admin Panel</h1>
  <p class="subtitle">Manage destination course IDs for faculty.</p>

  <?php if ($success): ?>
    <div class="alert alert-success">Destination courses updated successfully.</div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <div class="section-title">Destination Course IDs</div>

  <form id="course-form-container">
    <div class="row-header">
      <span style="width:60px; flex:none;"></span>
      <span>Program Label</span>
      <span>Course ID</span>
    </div>
    <div id="course-rows">
  </div>

    <button type="submit" class="btn btn-primary" id="save_changes">Save Changes</button>
    <div> <p id="success-msg" style="display:none; color:green; font-weight: bold; margin-top: 90px;"> </p> </div>
  </form>
</div>

<div id="overlay">
    <div class="form-popup" id="popup-form">
      <button class="btn btn-primary" id="finish-popup-form"> Finish </button>
  </div> 
</div>

    <script>
        let csrfToken = '<?= htmlspecialchars($csrfToken, ENT_QUOTES, "UTF-8") ?>'
        const current_dest_courses = <?php echo json_encode($destCourses); ?>; 

        //If admin decides to overwrite the current IDs, then add the new IDs and labels into a list
        function checkOverwrite()
        {
          const labels = [];
          const ids = [];
          const rows = document.querySelectorAll('.form-course-row');

          rows.forEach(row => {
            const checkbox = row.querySelector('input[type="checkbox"]');
            if(checkbox.checked)
            {
              const span = row.querySelector('span').textContent;
              const label = span.split(':')[0].trim();
              const submittedId = span.split('Submitted ID:')[1].split('|')[0].trim();
              labels.push(label);
              ids.push(submittedId);
            }
          });
          console.log(labels, ids);
          return [labels, ids];
        }

        //Create a pop-up window to ensure the user wants to overwrite current id
        function popUpWindow(majorToDestinationCourseId)
        {
          const popup = document.getElementById('popup-form');
          for (const course of current_dest_courses)
          {
            const label = course['label'];
            const destinationCourseId = majorToDestinationCourseId[label].trim();
            if (destinationCourseId !== course['id'])
            {
              const form = document.createElement('div');
              form.className = 'form-course-row';
              form.style = "display: flex; align-items: center; gap: 24px; padding: 8px 0; border-bottom:1px solid #eee;"
              const row = document.createElement('div');
              row.innerHTML = `
              <input type = "checkbox" style="width: 16px; height: 16px; cursor: pointer;"> OverWrite - </button>
              <span style="flex:1";>
                <strong>${label}: </strong>
                <span style=" font-size:1em; margin-left: 6px;">
                  Submitted ID: ${destinationCourseId} | <span style="color: #c0392b;"> Current ID: ${course.id} </span>
                </span>
              </span>
              `;
              form.appendChild(row);
              popup.insertBefore(form, popup.firstChild);
            }
          }
          document.getElementById('overlay').style.display = 'block';
          popup.style.display = 'block';
          
        }

        //Once the popup form is submitted, overwrite IDs
        document.getElementById('finish-popup-form').addEventListener("click", async(e) => {
          const [labels, ids] = checkOverwrite();  
          
          //Remove Popup & Delete elements inside
          document.getElementById('overlay').style.display = 'none';
          const popup = document.getElementById('popup-form');
          popup.style.display = 'none';
          popup.querySelectorAll('.form-course-row').forEach(i => i.remove());

          const storeBody = new FormData();
          labels.forEach(label => storeBody.append('labels[]', label));
          ids.forEach(id => storeBody.append('ids[]', id));
          storeBody.append('csrf_token', csrfToken);

          try{
            const storeRes = await fetch('admin.php', {method: 'POST', body: storeBody});
            const storeData = await storeRes.json();
            const success_msg = document.getElementById('success-msg');
            success_msg.textContent = "Changes added successfully";
            success_msg.style.display = "inline";

          } catch(err)
          {

          } finally {

          }

        });


        //Update Destination Course Ids
        const btn_save_changes = document.getElementById('save_changes');
        btn_save_changes.addEventListener("submit", async (e) => {
          e.preventDefault();
          let majorToDestinationCourseId = {};
          let list_of_submitted_ids = [];

          const labels = document.querySelectorAll('[name="labels[]"]');
          const ids = document.querySelectorAll('[name="ids[]"]');

          for(let i = 0; i < labels.length; i++)
          {
            //Create a dictionary to match the major to its respective course id
            majorToDestinationCourseId[labels[i].value] = ids[i].value;  
            list_of_submitted_ids.push({'label': labels[i].value, 'id': ids[i].value});
          }

          //Check if the submitted IDs already exist in destination-courses.php; if it does, do nothing
          if (JSON.stringify(current_dest_courses) === JSON.stringify(list_of_submitted_ids))
          { 
            //IDs already exist, do nothing
            const success_msg = document.getElementById('success-msg');
            success_msg.textContent = "Changes added successfully";
            success_msg.style.display = "inline";
          }
          else
          {
            //Create pop-window asking if they want to replace current course-id
            popUpWindow(majorToDestinationCourseId)
          }
        });

        //Convert into safe string
        function escapeHtml(str)
        {
          const div = document.createElement('div');
          div.textContent = str;
          return div.innerHTML;
        }

        //Update Admin Panel with Current Destination Course Ids
        function fetchInitialCourseIds()
        {
          const container = document.getElementById('course-rows');

          container.innerHTML = current_dest_courses.map(course =>
            `<div class = "course-row">
            <label>${escapeHtml(course.label)}</label>
            <input type="text" name="labels[]" value="${escapeHtml(course.label)}" placeholder="e.g. CS" required>
            <input type="text" name="ids[]" value="${escapeHtml(course.id)}" placeholder="e.g. 240102" pattern="\\d+" title="Numeric only" oninput="this.nextElementSibling.style.display = /\D/.test(this.value) ? 'inline' : 'none'">
            <span style="color: red; font-size: 0.85em; display: none;"> Numeric Only </span>
            </div>`
          ).join('')
        }
        fetchInitialCourseIds()

      </script>
      

</body>
</html>