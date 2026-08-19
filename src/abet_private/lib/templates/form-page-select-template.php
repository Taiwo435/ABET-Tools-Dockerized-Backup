<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/templates/auth-handler.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/form_functions.php';

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
$totalForms = 0;
$totalCompleted = 0;

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

    $totalForms++;
    if ($anyFilled) {
        $totalCompleted++;
    }

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

$overallPercent = ($totalForms > 0) ? (int)floor(($totalCompleted / $totalForms) * 100) : 0;

require_once getenv('ABET_PRIVATE_DIR') . '/lib/templates/primary-header.php';
?>

<link rel="stylesheet" href="/assets/css/form.css">
<link rel="stylesheet" href="<?php echo htmlspecialchars($formCssPath); ?>">
<style>
    .status-row { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
    .status-pill { font-size:0.85rem; padding:6px 10px; border-radius:999px; border:1px solid rgba(0,0,0,0.12); background:rgba(0,0,0,0.04); white-space:nowrap; }
    .status-pill.completed { background: rgba(46, 204, 113, 0.16); }
    .status-pill.progress  { background: rgba(241, 196, 15, 0.22); }
    .status-pill.notstarted{ background: rgba(231, 76, 60, 0.16); }
    .progress-bar { height:10px; background:rgba(0,0,0,0.10); border-radius:999px; overflow:hidden; margin-top:8px; }
    .progress-bar > div { height:100%; background:rgba(27, 102, 255, 0.85); }
    .form-status-message {
      margin-top: 24px;
      margin-bottom: 18px;
      font-size: 1.35rem;
      font-weight: 700;
      color: #1a237e;
      background: linear-gradient(90deg, #e3f2fd 0%, #bbdefb 100%);
      border-left: 6px solid #1976d2;
      padding: 16px 20px;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(25, 118, 210, 0.07);
      letter-spacing: 0.02em;
    }
    .section-divider { border:0; border-top:1px solid #ccc; margin:24px 0; }
    .page-select-actions { display:flex; gap:10px; flex-wrap:wrap; }
    .page-card-link { text-decoration:none; color:inherit; display:block; }
    .small-muted { opacity:0.75; font-size:0.9rem; margin-top:4px; }
    .form-group.page-card {
      background: white;
      padding: 16px 20px;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
      margin-bottom: 16px;
      cursor: pointer;
      transition: transform 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease;
    }
    .form-group.page-card:hover {
      transform: translateY(-2px);
      background-color: #fafafa;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    .right-allign-div { display:flex; justify-content:flex-end; margin-top:18px; width: 100%; }
</style>

<div class="center-div">
  <div class="form-holder">

    <div class="top-bar">
      <h2 class="form-title"><?php echo htmlspecialchars($formDisplayTitle); ?></h2>
      <div class="page-select-actions">
        <button class="form-button form-button-save-continue"
                type="button"
                onclick="window.location.assign('<?php echo htmlspecialchars($formBasePath . '/review'); ?>')">
          Review All Information
        </button>
        <button class="form-button form-button-save"
                type="button"
                onclick="window.location.assign('<?php echo htmlspecialchars($formBasePath . '/edit/?page=1'); ?>')">
          Start / Continue
        </button>
      </div>
    </div>

    <?php if ($overallPercent >= 100): ?>
      <p class="form-status-message"><?php echo htmlspecialchars($completeMessage); ?></p>
    <?php else: ?>
      <p class="form-status-message"><?php echo htmlspecialchars($incompleteMessage); ?></p>
    <?php endif; ?>

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

    <hr class="section-divider">

    <?php foreach ($sections as $s): ?>
      <?php
        $statusClass = "notstarted";
        if ($s["status"] === "Completed") $statusClass = "completed";
        else if ($s["status"] === "In Progress") $statusClass = "progress";

        $editLink = $formBasePath . "/edit/?page=" . urlencode((string)$s["pageNumber"]);
      ?>
      <a class="page-card-link" href="<?php echo htmlspecialchars($editLink); ?>">
        <div class="form-group page-card">
          <div class="status-row">
            <div>
              <label class="form-label"><?php echo htmlspecialchars($s["title"]); ?></label>
              <div class="small-muted">
                Go to page &rarr;
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