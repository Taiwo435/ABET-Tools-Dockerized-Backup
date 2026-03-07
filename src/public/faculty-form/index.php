<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/templates/auth-handler.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/form_functions.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/form-database/faculty_form_load.php';

$formName = "faculty-form";

function isEmptyValue($v): bool {
    if ($v === null) return true;
    if (is_string($v)) return trim($v) === "";
    if (is_array($v)) return count($v) === 0;
    return false;
}

function decodeGridRows($v): array {
    if (is_array($v)) return $v;
    if (is_string($v) && trim($v) !== "") {
        $decoded = json_decode($v, true);
        if (is_array($decoded)) return $decoded;
    }
    return [];
}

function loadValues(string $pageName): array {
    $data = loadFormData($pageName);
    return is_array($data) ? $data : [];
}

function normalizeFields(array $formJson): array {
    $fields = $formJson["fields"] ?? [];
    $out = [];
    foreach ($fields as $field) {
        $type = $field["type"] ?? "";
        if ($type === "section-break" || $type === "section-label") continue;

        $name = $field["name"] ?? null;
        if (!$name) continue;

        $out[] = [
            "name" => $name,
            "type" => $type,
            "label" => $field["label"] ?? $name,
            "required" => (bool)($field["required"] ?? false),
        ];
    }
    return $out;
}

$sections = [];
$totalRequired = 0;
$totalFilled = 0;

$pageNames = getAllPageNames($formName);

foreach ($pageNames as $i => $pageName) {
    $form = loadFormPage($formName, $pageName);
    $title = $form["title"] ?? $pageName;

    $fields = normalizeFields($form);
    $saved = loadValues($pageName);

    $reqCount = 0;
    $reqFilled = 0;
    $anyFilled = false;

    foreach ($fields as $f) {
        $fname = $f["name"];
        $type = $f["type"];
        $val = $saved[$fname] ?? null;

        if ($type === "expandable-grid") {
            if (count(decodeGridRows($val)) > 0) $anyFilled = true;
        } else {
            if (!isEmptyValue($val)) $anyFilled = true;
        }

        if ($f["required"]) {
            $reqCount++;
            if ($type === "expandable-grid") {
                if (count(decodeGridRows($val)) > 0) $reqFilled++;
            } else {
                if (!isEmptyValue($val)) $reqFilled++;
            }
        }
    }

    if ($reqCount === 0) {
        $status = $anyFilled ? "Completed" : "Not Started";
        $percent = $anyFilled ? 100 : 0;
    } else if ($reqFilled >= $reqCount) {
        $status = "Completed";
        $percent = (int)floor(($reqFilled / $reqCount) * 100);
    } else if ($anyFilled || $reqFilled > 0) {
        $status = "In Progress";
        $percent = (int)floor(($reqFilled / $reqCount) * 100);
    } else {
        $status = "Not Started";
        $percent = 0;
    }

    $totalRequired += $reqCount;
    $totalFilled += $reqFilled;

    $sections[] = [
        "pageNumber" => $i + 1,
        "name" => $pageName,
        "title" => $title,
        "status" => $status,
        "percent" => $percent,
        "requiredCount" => $reqCount,
        "requiredFilled" => $reqFilled,
    ];
}

$overallPercent = ($totalRequired > 0) ? (int)floor(($totalFilled / $totalRequired) * 100) : 0;

require_once getenv('ABET_PRIVATE_DIR') . '/lib/templates/primary-header.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Faculty Form</title>
  <link rel="stylesheet" href="/assets/css/form.css">
  <style>
    .status-row { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
    .status-pill { font-size:0.85rem; padding:6px 10px; border-radius:999px; border:1px solid rgba(0,0,0,0.12); background:rgba(0,0,0,0.04); white-space:nowrap; }
    .status-pill.completed { background: rgba(46, 204, 113, 0.16); }
    .status-pill.progress  { background: rgba(241, 196, 15, 0.22); }
    .status-pill.notstarted{ background: rgba(231, 76, 60, 0.16); }
    .progress-bar { height:10px; background:rgba(0,0,0,0.10); border-radius:999px; overflow:hidden; margin-top:8px; }
    .progress-bar > div { height:100%; background:rgba(27, 102, 255, 0.85); }
    .page-select-actions { display:flex; gap:10px; flex-wrap:wrap; }
    .page-card-link { text-decoration:none; color:inherit; display:block; }
    .small-muted { opacity:0.75; font-size:0.9rem; margin-top:4px; }
    .form-group.page-card { cursor:pointer; transition: transform 0.06s ease, background 0.1s ease; }
    .form-group.page-card:hover { transform: translateY(-1px); background: rgba(0,0,0,0.02); }
    .right-allign-div { display:flex; justify-content:flex-end; margin-top:18px; width: 100%;}
  </style>
</head>
<body>

<link rel="stylesheet" href="/assets/css/form.css">
<link rel="stylesheet" href="/assets/css/faculty-form.css">

<div class="center-div">
  <div class="form-holder">

    <div class="top-bar">
      <h2 class="form-title">Faculty Form</h2>
      <div class="page-select-actions">
        <button class="form-button form-button-save-continue"
                type="button"
                onclick="window.location.assign('/faculty-form/review')">
          Review All Information
        </button>
        <button class="form-button form-button-save"
                type="button"
                onclick="window.location.assign('/faculty-form/edit/?page=1')">
          Start / Continue
        </button>
      </div>
    </div>

    <div class="form-group">
      <div class="status-row">
        <div>
          <label class="form-label">Overall completion</label>
          <div class="small-muted">
            <strong><?php echo htmlspecialchars((string)$overallPercent); ?>%</strong>
          </div>
        </div>
      </div>
      <div class="progress-bar">
        <div style="width: <?php echo htmlspecialchars((string)$overallPercent); ?>%"></div>
      </div>
    </div>

    <?php foreach ($sections as $s): ?>
      <?php
        $statusClass = "notstarted";
        if ($s["status"] === "Completed") $statusClass = "completed";
        else if ($s["status"] === "In Progress") $statusClass = "progress";

        $editLink = "/faculty-form/edit/?page=" . urlencode((string)$s["pageNumber"]);
      ?>
      <a class="page-card-link" href="<?php echo htmlspecialchars($editLink); ?>">
        <div class="form-group page-card">
          <div class="status-row">
            <div>
              <label class="form-label"><?php echo htmlspecialchars($s["title"]); ?></label>
              <div class="small-muted">
                <?php if ($s["requiredCount"] > 0): ?>
                  Required filled: <?php echo htmlspecialchars((string)$s["requiredFilled"]); ?> /
                  <?php echo htmlspecialchars((string)$s["requiredCount"]); ?>
                  • <?php echo htmlspecialchars((string)$s["percent"]); ?>%
                <?php else: ?>
                  No required fields
                <?php endif; ?>
              </div>
            </div>
            <div class="status-pill <?php echo htmlspecialchars($statusClass); ?>">
              <?php echo htmlspecialchars($s["status"]); ?>
            </div>
          </div>
        </div>
      </a>
    <?php endforeach; ?>

    <div class="right-allign-div">
    <button class="form-button form-button-save"
            type="button"
            onclick="window.location.assign('/home')">
      Return Home
    </button>
    </div>

  </div>
</div>

<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/templates/primary-footer.php';
?>
