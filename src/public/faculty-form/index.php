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
    if (is_array($v) && isset($v["rows"]) && is_array($v["rows"])) return $v["rows"];
    if (is_string($v) && trim($v) !== "") {
        $decoded = json_decode($v, true);
        if (is_array($decoded) && isset($decoded["rows"]) && is_array($decoded["rows"])) return $decoded["rows"];
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

  </div>
</div>

<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/templates/primary-footer.php';
?>
