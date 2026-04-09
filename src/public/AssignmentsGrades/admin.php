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
            $response = ['status' => true, 'courses' => $merged];
            echo json_encode($response);
            exit;
        } else {
            $error = 'Failed to save config. Check file permissions.';
            $response = ['status' => false, 'error' => $error];
            echo json_encode($response);
            exit;
        }
    }
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
    #popup-form { display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border: 1px solid #ccc;  padding: 20px; z-index: 9999; box-shadow: 0 0 0 9999px rgba(0,0,0,0.5); max-height: 100vh; overflow-y: auto;}
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
    <br>
    <div> <p id="success-msg" style="display:none; color:green; font-weight: bold; margin-top: 90px;"> </p> </div>
  </form>
</div>

<div id="overlay">
    <div class="form-popup" id="popup-form">
      <input type = "checkbox" id="request-verification" style="width: 16px; height: 16px; cursor: pointer;"> Would you like to check if the IDs you plan to overwrite exist?</input>
      <br>
      <label id="check-for-overwritten-dest-id" style="display: none;">Select at least 1 ID to overwrite</label>
      <label style="display: none; font-size: 14px; font-weight: 600; color: #333; margin-bottom: 6px;" id="label-enter-token" >Enter your Canvas Access Token</label>
      <input type = "text" style="display: none; padding: 8px 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; width: 260px; outline: none;" id="input-canvas-token">
      <button class="btn btn-primary" id="button-submit-token" style="display: none;"> Submit Token </button>
      <label id="canvas_access_token_warning" style="display: none;"></label>
      <label id="label-verified-courses" style="display: none;"> Courses Found: </label>
      <label id="label-unverified-courses" style="display: none;"> Courses not Found: </label>
      <br><br>
      <label id="ask-user-confirmation" style = "display: none;">Continue with unverified classes? </label>
      <button id="confirm-unverified-class" style = "display: none;">Yes</button>
      <button id="do-not-confirm-unverified-class" style = "display: none;">No</button>
      <br>
      <button class="btn btn-primary" id="finish-popup-form"> Finish </button>
  </div> 


</div>

    <script>
        let csrfToken = '<?= htmlspecialchars($csrfToken, ENT_QUOTES, "UTF-8") ?>'
        let current_dest_courses = <?php echo json_encode($destCourses); ?>; 
        console.log(current_dest_courses)

        //
        document.getElementById("request-verification").addEventListener("change", async (e) =>
        {
          let label_enter_token = document.getElementById("label-enter-token");
          let input_canvas_access_token = document.getElementById("input-canvas-token");
          let submit_canvas_token_button = document.getElementById("button-submit-token");

          if(e.target.checked){

            //Fetch list of IDS to overwrite current IDS
            const overwrittenIDs = checkOverwrite();
            let warning = document.getElementById("check-for-overwritten-dest-id");

            //If no IDS are found, tell the user they must check at least one box
            if (overwrittenIDs[0].length === 0) {
              let checkbox_question = document.getElementById("request-verification")
              checkbox_question.checked = false; 
              warning.style.display = "flex"; 
              submit_canvas_token_button.style.display = "none";
              return; 
            }

            //If the user decides to overwrite the ID, add the UI to request an access token
            warning.style.display = "none";
            label_enter_token.style.display = "flex";
            input_canvas_access_token.style.display = "flex";
            submit_canvas_token_button.style.display = "flex";
          }
          else
          {
            label_enter_token.style.display = "none";
            input_canvas_access_token.style.display = "none";
          }
        });

        //When Pressed, it will Verify access token + checking if classes exist
        document.getElementById("button-submit-token").addEventListener("click", async (e) =>
        {
          //Fetch canvas token from input
          let input_canvas_access_token = document.getElementById("input-canvas-token");
          const canvas_token = input_canvas_access_token.value.trim();
          
          //If the token is an empty string, give the user a warning text
          const warning = document.getElementById("canvas_access_token_warning");
          if (canvas_token === "")
          {
            warning.textContent = "Please add your access token here";
            warning.style.display = "flex";
            return;
          } 

          try {
            //Store Canvas Token Before Validating it
            const storeCredentials = new FormData();
            storeCredentials.append('action', 'store-credentials');
            storeCredentials.append('canvas_token', canvas_token);
            storeCredentials.append('csrf_token', csrfToken);

            let storeRes = await fetch('api-proxy.php', {method: 'POST', body: storeCredentials});
            let storeJson = await storeRes.json()
            if (storeJson.next_csrf)  csrfToken = storeJson.next_csrf;
            if (!storeJson.success)
            {
              warning.textContent = "Failed to store token";
              warning.style.display = "flex";
              return;
            }

            //Validate Token Here
            const storeToken = new FormData();
            storeToken.append('action', 'verify-token');
            storeToken.append('csrf_token', csrfToken);
            storeRes = await fetch('api-proxy.php', {method: 'POST', body: storeToken});
            storeJson = await storeRes.json();

            if (storeJson.next_csrf)  csrfToken = storeJson.next_csrf;
            if (!storeJson.success)
            {
              warning.textContent = "Token not Verified";
              warning.style.display = "flex";
              return;
            }
            else{
              warning.textContent = "Token Verified";
              
              //Fetch list of IDS to overwrite current IDS
              const overwrittenIDs = checkOverwrite();
              let string_verified_courses = "";
              let string_unverified_courses = "";
              for (let i = 0; i < overwrittenIDs[1].length; i++)
              {
                  const storeID = new FormData();
                  storeID.append('action', 'verify-course');
                  storeID.append('csrf_token', csrfToken);
                  storeID.append('course_id', overwrittenIDs[1][i])
                  storeRes = await fetch('api-proxy.php', {method: 'POST', body: storeID});
                  storeJson = await storeRes.json()
                  console.log(storeJson);
                  if (storeJson.next_csrf) { csrfToken = storeJson.next_csrf;}
                  if (storeJson.success)
                  {
                      string_verified_courses += "\n" + overwrittenIDs[0][i] + ": " + overwrittenIDs[1][i] ;
                  }
                  else { 
                      string_unverified_courses += "\n" + overwrittenIDs[0][i] + ": " + overwrittenIDs[1][i];
                  } 
              }
              let label_verified_courses = document.getElementById("label-verified-courses");
              
              if (string_verified_courses !== "")
              {
                label_verified_courses.style.display = "flex";
                label_verified_courses.textContent += string_verified_courses;
              }
              
              let label_unverified_courses = document.getElementById("label-unverified-courses");
              if (string_unverified_courses !== "")
              {
                console.log(string_unverified_courses)
                label_unverified_courses.style.display = "flex";
                label_unverified_courses.textContent += string_unverified_courses;
              }
              

              

            }
            

          } catch (err)
          {

          } finally {

          }

        });

        //If admin decides to overwrite the current IDs, then add the newly submitted ID (and respective label) to a list
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
          console.log(labels)
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
              <input type = "checkbox" style="width: 16px; height: 16px; cursor: pointer;"> OverWrite - 
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
          const label_unverified_courses = document.getElementById("label-unverified-courses")
          console.log(label_unverified_courses.textContent.trim())
          if (label_unverified_courses.textContent.trim() !== "Courses not Found:")
          {
            const label_unconfirmed_classes = document.getElementById("ask-user-confirmation");
            label_unconfirmed_classes.style.display = "flex";
      <button id="confirm-unverified-class" style = "display: none;">Yes</button>
      <button id="do-not-confirm-unverified-class" style = "display: none;">No</button>

            return;
          }

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
            if (storeData.status)
            {
              current_dest_courses = storeData.courses;
              const success_msg = document.getElementById('success-msg');
              success_msg.textContent = "Changes added successfully";
              success_msg.style.display = "inline";
            }
          } catch(err)
          {

          } finally {

          }

        });


        //Update Destination Course Ids
        const btn_save_changes = document.getElementById('course-form-container');
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
            <input type="text" name="ids[]" value="${escapeHtml(course.id)}" placeholder="e.g. 240102" pattern="\\d+" title="Numeric only" oninput="this.nextElementSibling.style.display = /\D/.test(this.value) ? 'inline' : 'none'" required>
            <span style="color: red; font-size: 0.85em; display: none;"> Numeric Only </span>
            </div>`
          ).join('')
        }
        fetchInitialCourseIds()

      </script>
      

</body>
</html>