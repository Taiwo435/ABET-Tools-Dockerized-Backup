<<<<<<< HEAD
<<<<<<< HEAD
=======
>>>>>>> ff5ef5a (Added faculty form review page, faculty form select page)
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once getenv('ABET_PRIVATE_DIR') . '/lib/templates/auth-handler.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/form_functions.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/form-database/faculty_form_load.php';

require_once getenv('ABET_PRIVATE_DIR') . '/lib/templates/primary-header.php';

$formName = "faculty-form";


function decodeGridRows($v): array {
    if (is_array($v) && isset($v["rows"]) && is_array($v["rows"])) return $v["rows"];
    if (is_string($v) && trim($v) !== "") {
        $decoded = json_decode($v, true);
        if (is_array($decoded) && isset($decoded["rows"]) && is_array($decoded["rows"])) return $decoded["rows"];
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
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Faculty Form Review</title>
  <link rel="stylesheet" href="/assets/css/form.css">

  <style>
    :root{
    --asu-maroon:#8C1D40;
    --asu-gold:#FFC627;
    --asu-rich-black:#191919;
    --border-light: rgba(0,0,0,0.12);
    --card-bg: #FFFFFF;
    --page-bg: #F4F5F7;
    }

    body { background: var(--page-bg); }

    .form-holder {
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    padding: 0 !important;
    }
    .review-section {
    margin-top: 18px;
    padding: 18px 18px 8px;
    border: 1px solid rgba(0,0,0,0.10);
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 2px 12px rgba(0,0,0,0.05);
    position: relative;
    }
    .review-section:last-child {
    padding-bottom: 8px;
    }
    .review-section::before{
    content:"";
    position:absolute;
    left:0;
    right:0;
    top:0;
    height:5px;
    background: linear-gradient(90deg, #8C1D40 0%, #8C1D40 85%, #FFC627 85%, #FFC627 100%);
    border-radius: 8px 8px 0 0;
    }
    .review-section:last-child { 
    border-bottom: none; padding-bottom: 0; 
    }
    .review-section-title{
    margin: 0;
    font-size: 22px;
    font-weight: 700;
    letter-spacing: -0.3px;
    color: var(--asu-maroon);
    }
    .review-section-header{
    display:flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 14px;
    }
    .review-value-box{
    border: 1px solid rgba(0,0,0,0.10);
    background: #FAFAFA;
    border-radius: 6px;
    padding: 10px 12px;
    }
    .review-grid th{
    background: rgba(140, 29, 64, 0.06);
    color: var(--asu-rich-black);
    }
  </style>
</head>

<body>
<div class="center-div">
  <div class="form-holder">
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
            <h3 class="form-label"><?php echo htmlspecialchars($title); ?></label>
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
</body>
</html>

<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/templates/primary-footer.php';
?>
