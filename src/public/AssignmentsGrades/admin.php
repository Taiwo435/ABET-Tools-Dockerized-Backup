<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/csrf.php';
require_login();

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
        $export = "<?php\nreturn [\n    'dest_courses' => [\n";
        foreach ($newCourses as $course) {
            $label = addslashes($course['label']);
            $id    = addslashes($course['id']);
            $export .= "        ['label' => '{$label}', 'id' => '{$id}'],\n";
        }
        $export .= "    ]\n];\n";

        if (file_put_contents($configPath, $export) !== false) {
            $destCourses = $newCourses;
            $success = true;
        } else {
            $error = 'Failed to save config. Check file permissions.';
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

  <form method="POST">
    <div class="row-header">
      <span style="width:60px; flex:none;"></span>
      <span>Program Label</span>
      <span>Course ID</span>
    </div>

    <?php foreach ($destCourses as $i => $course): ?>
    <div class="course-row">
      <label><?= htmlspecialchars($course['label'], ENT_QUOTES, 'UTF-8') ?></label>
      <input type="text" name="labels[]" value="<?= htmlspecialchars($course['label'], ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. CS" required>
      <input type="text" name="ids[]" value="<?= htmlspecialchars($course['id'], ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. 240102" required pattern="\d+" title="Numeric only">
    </div>
    <?php endforeach; ?>

    <button type="submit" class="btn btn-primary">Save Changes</button>
  </form>
</div>

</body>
</html>