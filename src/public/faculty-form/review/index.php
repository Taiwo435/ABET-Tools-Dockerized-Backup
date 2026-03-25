<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once getenv('ABET_PRIVATE_DIR') . '/lib/templates/auth-handler.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/form_functions.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/form-database/faculty_form_load.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/security_headers.php'; 


$formName = "faculty-form";

function decodeGridRows($v): array {
    if (is_array($v)) return $v;
    if (is_string($v) && trim($v) !== "") {
        $decoded = json_decode($v, true);
        if (is_array($decoded)) return $decoded;
    }
    return [];
}

function format($v): string {
    if ($v === null) return "";
    if (is_bool($v)) return $v ? "Yes" : "No";
    if (is_numeric($v)) return (string)$v;
    if (is_string($v)) return $v;
    return "";
}

function loadValues(string $pageName): array {
    $data = loadFormData($pageName);
    return is_array($data) ? $data : [];
}

$pageNames = getAllPageNames($formName);

require_once getenv('ABET_PRIVATE_DIR') . '/lib/templates/primary-header.php';
?>

<link rel="stylesheet" href="/assets/css/form.css">
<link rel="stylesheet" href="/assets/css/faculty-form-review.css">

<div class="center-div">
  <div class="form-holder review-form-holder">
    <div class="top-bar">
      <h2 class="form-title">Faculty Form Review</h2>
      <div class="page-select-actions">
        <button class="form-button form-button-return"
                type="button"
                onclick="window.location.assign('/faculty-form')">
          Return to Page Select
        </button>
      </div>
    </div>

    <?php foreach ($pageNames as $idx => $pageName): ?>
      <?php
        $pageNumber = $idx + 1;
        $form = loadFormPage($formName, $pageName);
        $saved = loadValues($pageName);

        $title = $form["title"] ?? $pageName;
        $fields = $form["fields"] ?? [];
        $editLink = "/faculty-form/edit/?page=" . urlencode((string)$pageNumber);
      ?>

      <div class="review-section">
        <div class="review-section-header">
          <div>
            <h3 class="form-label"><?php echo htmlspecialchars($title); ?></h3>
          </div>
          <button class="form-button form-button-save"
                  type="button"
                  onclick="window.location.assign('<?php echo htmlspecialchars($editLink); ?>')">
            Edit Section
          </button>
        </div>
        <?php foreach ($fields as $field): ?>
          <?php
            $type = $field["type"] ?? "";
            if ($type === "section-break" || $type === "section-label") continue;

            $name = $field["name"] ?? "";
            if ($name === "") continue;

            $label = $field["label"] ?? $name;
            $required = (bool)($field["required"] ?? false);
            $description = $field["description"] ?? "";
            $rawVal = $saved[$name] ?? null;

            if ($type === "select") {
              $key = is_string($rawVal) ? $rawVal : "";
              $opts = $field["options"] ?? [];
              $displayVal = ($key !== "" && isset($opts[$key])) ? $opts[$key] : $key;
            } else {
              $displayVal = format($rawVal);
            }
          ?>

          <div class="form-group">
            <label class="form-label">
              <?php echo htmlspecialchars($label); ?><?php if ($required) echo "*"; ?>
            </label>

            <?php if (!empty($description)): ?>
              <p class="form-field-description">
                <?php echo htmlspecialchars($description); ?>
              </p>
            <?php endif; ?>

            <?php if ($type === "expandable-grid"): ?>
              <?php
                $rows = decodeGridRows($rawVal);
                $cols = $field["columns"] ?? [];
              ?>

              <table class="review-grid">
                <thead>
                  <tr>
                    <?php foreach ($cols as $c): ?>
                      <th><?php echo htmlspecialchars($c["label"] ?? ($c["name"] ?? "Column")); ?></th>
                    <?php endforeach; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php if (count($rows) === 0): ?>
                    <tr>
                      <td colspan="<?php echo htmlspecialchars((string)max(1, count($cols))); ?>">
                        <span class="review-empty">(empty)</span>
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                      <tr>
                        <?php foreach ($cols as $c): ?>
                          <?php $cn = $c["name"] ?? ""; ?>
                          <td><?php echo htmlspecialchars(format($r[$cn] ?? "")); ?></td>
                        <?php endforeach; ?>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>

            <?php else: ?>
              <div class="review-value-box">
                <?php if ($displayVal !== ""): ?>
                  <?php echo htmlspecialchars($displayVal); ?>
                <?php else: ?>
                  <span class="review-empty">(empty)</span>
                <?php endif; ?>
              </div>
            <?php endif; ?>

          </div>
        <?php endforeach; ?>

      </div>
    <?php endforeach; ?>
    <div style="text-align: right;">
        <button class="form-button form-button-return"
            type="button"
            onclick="window.location.assign('/faculty-form')">
          Return to Page Select
        </button>
    </div>

  </div>
</div>

<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/templates/primary-footer.php';
?>
