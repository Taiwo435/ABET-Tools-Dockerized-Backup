<?php

require_once getenv('ABET_PRIVATE_DIR') . '/lib/csrf.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/db.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/security_headers.php'; 

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
$openaiSuccess = false;
$openaiError = '';

// AES-256-CBC encryption using MYSQL_PASS as the secret key
function encrypt_value(string $value): string {
    $key = substr(hash('sha256', getenv('MYSQL_PASS')), 0, 32);
    $iv  = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv);
    return base64_encode($iv . base64_decode($encrypted));
}

function decrypt_value(string $encrypted): string {
    $key     = substr(hash('sha256', getenv('MYSQL_PASS')), 0, 32);
    $decoded = base64_decode($encrypted);
    $iv      = substr($decoded, 0, 16);
    $data    = substr($decoded, 16);
    return openssl_decrypt(base64_encode($data), 'AES-256-CBC', $key, 0, $iv) ?: '';
}

function post_str(string $key): string {
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : '';
}


// CSRF validation (uses shared csrf.php library)
if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $csrf = post_str('csrf_token');
    if (!csrf_validate($csrf, 'tool1_proxy')) {
        json_response(['success' => false, 'message' => 'Invalid or missing CSRF token.'], 403);
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['save_openai'])) {
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
        sleep(5);
        if (file_put_contents($configPath, $export, LOCK_EX) !== false) {
            $destCourses = $merged;
            $success = true;
            $csrfToken = csrf_token('tool1_proxy');
            $response = ['status' => true, 'courses' => $merged, 'csrf_token' => csrf_token('tool1_proxy')];
            echo json_encode($response);
            exit;
        } else {
            $error = 'Failed to save config. Check file permissions.';
            $response = ['status' => false, 'error' => $error, 'csrf_token' => csrf_token('tool1_proxy')];
            echo json_encode($response);
            exit;
        }
    }
}

// Handle OpenAI key form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_openai'])) {

    $newKey = trim($_POST['openai_api_key'] ?? '');
    $csrfToken = csrf_token('tool1_proxy');           //Make new token again
    if (!$newKey) {
        $openaiError = 'OpenAI API key cannot be empty.';
    } else {
        
        //Check if API Key exists
        $openai_url = "https://api.openai.com/v1/models"; 
        $ch = curl_init($openai_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
          "Authorization: Bearer " . $newKey,
          "Content-Type: application/json"
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if($http_code === 401)
        {
          $openaiError = "API Key is not valid";
        } elseif($http_code === 429) {
          $openaiError = "Rate Limit exceeded";
        } 
        
        //If key exists, encrypt + update DB
        elseif($http_code === 200) {
            try {
              $encrypted = encrypt_value($newKey);
              $stmt = db()->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('openai_api_key', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
              $stmt->execute([$encrypted, $encrypted]);
              $openaiSuccess = true;
            } catch (Exception $e) {
                $openaiError = 'Failed to save OpenAI key to database.';
            }
        }
        else {
          $openaiError = "Unexpected Response. HTTP Code: " . $http_code;
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
    .section-title { font-size: 16px; font-weight: bold; margin-bottom: 16px; border-bottom: 2px solid #eee; padding-bottom: 8px; margin-top: 40px; }
    .validation-title { font-size: 15px; margin-bottom: 16px; padding-bottom: 8px;}
    .course-row { display: flex; gap: 12px; align-items: center; margin-bottom: 12px; }
    .course-row input { flex: 1; padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
    .course-row label { font-size: 13px; color: #555; width: 60px; flex-shrink: 0; }
    .btn { padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: bold; }
    .btn-primary { background: #8B0000; color: white; }
    .btn-primary:hover { background: #a00000; }
    .btn-secondary {  background: #fdf7f7;  color: var(--asu-maroon);  border: 2px solid black;}
    .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
    .alert-success { background: #e6f4ea; color: #2e7d32; border: 1px solid #a5d6a7; }
    .alert-error { background: #fdecea; color: #c62828; border: 1px solid #ef9a9a; }
    .row-header { display: flex; gap: 12px; margin-bottom: 4px; }
    .row-header span { flex: 1; font-size: 12px; color: #888; font-weight: bold; text-transform: uppercase; }
    .row-header span:first-child { width: 60px; flex: none; }
    #overlay {display: none; top: 0; left: 0; position: fixed; width: 100%; height: 100%; background: black; z-index: 9998;}
    #popup-form { display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border: 1px solid #ccc;  padding: 20px; z-index: 9999; box-shadow: 0 0 0 9999px rgba(0,0,0,0.5); max-height: 100vh; overflow-y: auto;}
    .openai-row { display: flex; gap: 12px; align-items: center; margin-bottom: 8px; }
    .openai-row input { flex: 1; padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
    .form-help { font-size: 12px; color: #888; margin-bottom: 12px; }
    .loading-overlay {  grid-column: 1 / -1;  text-align: center;  padding: 10px;  font-size: 1.1rem;  color: #666;}

  </style>
</head>
<body>

<header class="site-header">
  <div class="site-title">ABET Tools - Admin Panel</div>
  <a href="/home" class="nav-link">← Back to Dashboard</a>
</header>

<div class="main-container">
  <h1>Admin Panel</h1>
  <p class="subtitle">Manage destination course IDs and application settings.</p>

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
      <label class = "validation-title"><input type = "checkbox" id="request-verification"> Would you like to verify the above course IDs?</label><br>
      <label style="display: none; font-size: 14px; font-weight: 600; color: #333; margin-top: 12px; margin-bottom: 6px;" id="label-enter-token" >Enter your Canvas Access Token</label><br>
      <input type = "password" style="display: none; padding: 8px 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; width: 260px; outline: none;" id="input-canvas-token"><br>
      <button class="btn btn-secondary " id="button-submit-token" style="display: none; margin-bottom: 15px;" type="button"> Submit Token </button>
      <label id="canvas_access_token_warning" style="display: none;"></label>
      <div class="loading-overlay" style = "display: none;"><i class='fas fa-spinner fa-spin'></i> Verifying classes, please wait...</div>
      <label id="label-verified-courses" style="display: none;" class="alert alert-success"> Courses Found: </label>
      <label id="label-unverified-courses" style="display: none;" class="alert alert-error"> Courses not Found: </label>
      <button class="btn btn-primary" id="finish-popup-form" style="display: none;"> Finish </button>
    <button type="submit" class="btn btn-primary" id="save_changes">Save Changes</button>
    <br><br>
    <div class="alert alert-success" style = "display: none;" id="alert-success-destination-id">Course IDs updated successfully. </div>
    <div class="alert alert-error" style="display: none;" id = "alert-error-destination-id">Failed to update Course IDs. Try Again. </div>
  </form>

  <div class="section-title">OpenAI API Key</div>

  <?php if ($openaiSuccess): ?>
    <div class="alert alert-success">OpenAI API key updated successfully.</div>
  <?php endif; ?>
  <?php if ($openaiError): ?>
    <div class="alert alert-error"><?= htmlspecialchars($openaiError, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, "UTF-8"), ENT_QUOTES, 'UTF-8' ?>">
    <div class="openai-row">
      <input type="password" name="openai_api_key" value="" placeholder="Paste your new OpenAI API key here" required>
    </div>
    <div class="form-help">Enter a new key above to replace the existing one. The key is encrypted before being stored.</div>
    <button type="submit" name="save_openai" class="btn btn-primary">Save OpenAI Key</button>
  </form>

</div>

    <script>
        let csrfToken = '<?= htmlspecialchars($csrfToken, ENT_QUOTES, "UTF-8") ?>'
        let current_dest_courses = <?php echo json_encode($destCourses); ?>; 

        /*
          Description: Checkbox for ID - request-verification

          If the checkbox is marked, open a form asking the user for a canvas token
          If the checkbox is unchecked, close the form that asks to submit a canvas token
        */
        document.getElementById("request-verification").addEventListener("change", async (e) =>
        {
          let label_enter_token = document.getElementById("label-enter-token");
          let input_canvas_access_token = document.getElementById("input-canvas-token");
          let submit_canvas_token_button = document.getElementById("button-submit-token");

          if(e.target.checked){
            
            label_enter_token.style.display = "flex";
            input_canvas_access_token.style.display = "flex";
            submit_canvas_token_button.style.display = "flex";
          }
          else
          {
            const success_msg = document.getElementById('alert-success-destination-id');
            const errors_msg = document.getElementById('alert-error-destination-id');
            const label_verified_courses = document.getElementById('label-verified-courses');
            const label_unverified_courses = document.getElementById('label-unverified-courses');
            success_msg.style.display = 
            label_enter_token.style.display = "none";
            input_canvas_access_token.style.display = "none";
            submit_canvas_token_button.style.display = "none";
            label_verified_courses.style.display = "none";
            label_unverified_courses.style.display = "none";
          }
        });

        /*
            Handles user's Canvas token submission when button is called
        */
        document.getElementById("button-submit-token").addEventListener("click", async (e) =>
        {
          //Fetch canvas token from input
          e.preventDefault();
          let input_canvas_access_token = document.getElementById("input-canvas-token");
          let label_verified_courses = document.getElementById("label-verified-courses");
          let label_unverified_courses = document.getElementById("label-unverified-courses");
          const canvas_token = input_canvas_access_token.value.trim(); // Fetch Canvas Token
          console.log(canvas_token)
          //If token DNE, return an error
          if (canvas_token === "")
          {
            label_unverified_courses.innerHTML = "Please add your access token here";
            label_unverified_courses.style.display = "flex";
            return;
          } 

          try {
            //Store Canvas Token in Session before Validating it
            const storeCredentials = new FormData();
            storeCredentials.append('action', 'store-credentials');
            storeCredentials.append('canvas_token', canvas_token);
            storeCredentials.append('csrf_token', csrfToken);

            let storeRes = await fetch('api-proxy.php', {method: 'POST', body: storeCredentials});
            let storeJson = await storeRes.json()
            if (storeJson.next_csrf)  csrfToken = storeJson.next_csrf;
            if (!storeJson.success)
            {
              if (storeRes.status === 403)
              {
                label_unverified_courses.innerHTML = storeJson.message;
                label_unverified_courses.style.display = "flex";
              } else {
                label_unverified_courses.innerHTML = "Failed to store token; Try Again";
                label_unverified_courses.style.display = "flex";
              }
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
              label_unverified_courses.innerHTML = "Token not Verified";
              label_unverified_courses.style.display = "flex";
              return;
            }
            else{
              const loadingOverlay = document.querySelector(".loading-overlay");
              label_unverified_courses.innerHTML = "Courses not Found: ";
              label_verified_courses.innerHTML = "Courses Found: ";
              
              //Fetch list of IDS to overwrite current IDS
              const overwrittenIDs = checkOverwrite();
              let string_verified_courses = "";
              let string_unverified_courses = "";
              loadingOverlay.style.display = "flex";

              //Verify each course ID that the user plans to overwrite
              for (let i = 0; i < overwrittenIDs[1].length; i++)
              {
                  //Call POST request
                  const storeID = new FormData();
                  storeID.append('action', 'verify-course');
                  storeID.append('csrf_token', csrfToken);
                  storeID.append('course_id', overwrittenIDs[1][i])
                  storeRes = await fetch('api-proxy.php', {method: 'POST', body: storeID});
                  storeJson = await storeRes.json()
                  if (storeJson.next_csrf) { csrfToken = storeJson.next_csrf;}
                  if (storeJson.success) {
                      string_verified_courses += "<br>" + overwrittenIDs[0][i] + ": " + overwrittenIDs[1][i] ;
                  }
                  else { 
                      string_unverified_courses += "<br>" + overwrittenIDs[0][i] + ": " + overwrittenIDs[1][i];
                  } 
              } 

              //Remove Loading Screen
              loadingOverlay.style.display = "none";

              //Add Message Button showing Successfully verified IDs and non-successful course IDs
              if (string_verified_courses !== "")
              {
                label_verified_courses.style.display = "flex";
                label_verified_courses.innerHTML += string_verified_courses + "<br>";
              }
              if (string_unverified_courses !== "")
              {
                label_unverified_courses.style.display = "flex";
                label_unverified_courses.innerHTML += string_unverified_courses + "<br>";
              }
            }
          } catch (err)
          {
              label_unverified_courses.innerHTML = "Failed to store token; Try Again";
              label_unverified_courses.style.display = "flex";
          } 
        });

        //If admin decides to overwrite the current IDs, then add the newly submitted ID (and respective label) to a list
        function checkOverwrite()
        {
          const labels = [];
          const ids = [];
          const rows = document.querySelectorAll('.course-row');

          rows.forEach(row => {
            const span = row.querySelector('span').textContent;
            const label = row.querySelector('input[name="labels[]"]')?.value.trim();
            const submittedId = row.querySelector('input[name="ids[]"]')?.value.trim();
            labels.push(label);
            ids.push(submittedId);
          });
          return [labels, ids];
        }

        /*
          Once the user has pressed the button "Save Changes"; Update the course IDs into destination-courses.php.
        */
        const btn_save_changes = document.getElementById('course-form-container');
        btn_save_changes.addEventListener("submit", async (e) => {
          e.preventDefault();

          let majorToDestinationCourseId = {};
          let list_of_submitted_ids = [];
          const labels = document.querySelectorAll('[name="labels[]"]');
          const ids = document.querySelectorAll('[name="ids[]"]');

          //Fetch the current Label and ID from the UI. Assign values into a Object and a List. 
          for(let i = 0; i < labels.length; i++)
          {
            majorToDestinationCourseId[labels[i].value] = ids[i].value;  
            list_of_submitted_ids.push({'label': labels[i].value, 'id': ids[i].value});
          }

          //If Submitted IDs are the same as the currently stored IDs, simply update the UI with a successful message
          if (JSON.stringify(current_dest_courses) === JSON.stringify(list_of_submitted_ids))
          { 
            //IDs already exist, do nothing
            const success_msg = document.getElementById('alert-success-destination-id');
            success_msg.style.display = "inline";
          }
          else
          {
            //Fetch list of Labels & IDs from text input
            const [labels, ids] = checkOverwrite();
            const storeBody = new FormData();
            labels.forEach(label => storeBody.append('labels[]', label));
            ids.forEach(id => storeBody.append('ids[]', id));
            storeBody.append('csrf_token', csrfToken);

            const success_msg = document.getElementById('alert-success-destination-id');
            const errors_msg = document.getElementById('alert-error-destination-id');
            try{
              
              //Update ID into destination-courses.php; if successful, show success response otherwise show error response
              const storeRes = await fetch('admin.php', {method: 'POST', body: storeBody});
              const storeData = await storeRes.json();
              console.log(storeData)
              if (storeData.status)
              {
                current_dest_courses = storeData.courses;
                errors_msg.style.display = "none";
                success_msg.style.display = "inline";
              }
              else {
                success_msg.style.display = "none";
                errors_msg.style.display = "inline";
              }
            } catch(err) {
              console.log("here");
                success_msg.style.display = "none";
                errors_msg.style.display = "inline";
            } 
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
        fetchInitialCourseIds();
      </script>
      

</body>
</html>