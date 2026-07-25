<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/csrf.php';
require_login();

require_role('ROLE_ADMIN');

$csrfToken = csrf_token('syllabus_templates');

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

function post_value(string $key): string {
    return trim((string)($_POST[$key] ?? ''));
}

function post_array(string $key): array {
    $values = $_POST[$key] ?? [];
    if (!is_array($values)) {
        return [];
    }

    return array_values(array_filter(array_map('trim', $values)));
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? '', 'syllabus_templates')) {
        $error = 'Invalid CSRF token.';
    } else {
        $courseSubject = strtoupper(post_value('course_subject'));
        $courseNumber = post_value('course_number');
        $courseName = post_value('course_name');
        $deliveryType = post_value('delivery_type');
        $creditHours = post_value('credit_hours') !== '' ? (float) post_value('credit_hours') : null;
        $contactHours = post_value('contact_hours') !== '' ? (float) post_value('contact_hours') : null;
        $courseType = post_value('course_type') ?: null;

        if ($courseSubject === '' || $courseNumber === '' || $courseName === '' || $deliveryType === '') {
            $error = 'Course subject, number, name, and delivery type are required.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO syllabus_templates (
                        course_subject,
                        course_number,
                        course_name,
                        delivery_type,
                        credit_hours,
                        contact_hours,
                        course_type,
                        course_coordinators,
                        textbooks,
                        catalog_description,
                        prerequisites,
                        course_outcomes,
                        student_outcomes,
                        topics,
                        created_by
                    ) VALUES (
                        :course_subject,
                        :course_number,
                        :course_name,
                        :delivery_type,
                        :credit_hours,
                        :contact_hours,
                        :course_type,
                        :course_coordinators,
                        :textbooks,
                        :catalog_description,
                        :prerequisites,
                        :course_outcomes,
                        :student_outcomes,
                        :topics,
                        :created_by
                    )
                    ON DUPLICATE KEY UPDATE
                        course_name = VALUES(course_name),
                        delivery_type = VALUES(delivery_type),
                        credit_hours = VALUES(credit_hours),
                        contact_hours = VALUES(contact_hours),
                        course_type = VALUES(course_type),
                        course_coordinators = VALUES(course_coordinators),
                        textbooks = VALUES(textbooks),
                        catalog_description = VALUES(catalog_description),
                        prerequisites = VALUES(prerequisites),
                        course_outcomes = VALUES(course_outcomes),
                        student_outcomes = VALUES(student_outcomes),
                        topics = VALUES(topics),
                        updated_at = CURRENT_TIMESTAMP
                ");

                $stmt->execute([
                    ':course_subject' => $courseSubject,
                    ':course_number' => $courseNumber,
                    ':course_name' => $courseName,
                    ':delivery_type' => $deliveryType,
                    ':credit_hours' => $creditHours,
                    ':contact_hours' => $contactHours,
                    ':course_type' => $courseType,
                    ':course_coordinators' => json_encode(post_array('course_coordinators')),
                    ':textbooks' => json_encode(post_array('textbooks')),
                    ':catalog_description' => post_value('catalog_description'),
                    ':prerequisites' => post_value('prerequisites'),
                    ':course_outcomes' => json_encode(post_array('course_outcomes')),
                    ':student_outcomes' => json_encode(post_array('student_outcomes')),
                    ':topics' => json_encode(post_array('topics')),
                    ':created_by' => $_SESSION['user_id'] ?? null,
                ]);

                $message = 'Template saved successfully.';
            } catch (PDOException $e) {
                $error = 'Failed to save template: ' . $e->getMessage();
            }
        }
    }
}

$templates = [];
try {
    $stmt = $pdo->query("
        SELECT template_id, course_subject, course_number, course_name, delivery_type, credit_hours, contact_hours, updated_at
        FROM syllabus_templates
        ORDER BY updated_at DESC
    ");
    $templates = $stmt->fetchAll();
} catch (PDOException $e) {
    $templates = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Syllabus Templates | ABET Tools</title>
  <link rel="stylesheet" href="/assets/css/tool1.css">
  <link rel="stylesheet" href="/assets/css/course-setup.css">

  <style>
    .template-container {
      max-width: 1100px;
      margin: 40px auto;
    }

    .template-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
    }

    .template-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 16px;
      font-size: 0.9rem;
    }

    .template-table th,
    .template-table td {
      border: 1px solid #ddd;
      padding: 10px;
      text-align: left;
    }

    .template-table th {
      background: #f3f3f3;
    }

    .message {
      padding: 12px;
      border-radius: 6px;
      margin-bottom: 16px;
      background: #e7f7e7;
      color: #146b14;
    }

    .error {
      padding: 12px;
      border-radius: 6px;
      margin-bottom: 16px;
      background: #fde8e8;
      color: #9b1c1c;
    }

    .required-mark {
      color: red;
      font-weight: bold;
    }
  </style>
</head>

<body>

<header class="site-header">
  <div class="site-title">ABET Tools - Syllabus Templates</div>
  <a href="admin.php" class="nav-link">← Back to Admin</a>
  <a href="/../home.php" class="nav-link">← Back to Dashboard</a>
</header>

<div class="template-container">
  <div class="template-header">
    <div>
      <h1>Syllabus Templates</h1>
      <p>Create admin-managed syllabus templates that faculty can later review and edit.</p>
    </div>
  </div>

  <?php if ($message): ?>
    <div class="message"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-title">Create / Update Template</div>

    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

      <div class="form-group">
        <label class="form-label">
          Course Subject <span class="required-mark">*</span>
        </label>
        <input
          type="text"
          name="course_subject"
          class="form-input"
          placeholder="Enter course subject"
          value="<?= htmlspecialchars($_POST['course_subject'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
          required
        >

        <label class="form-label">
          Course Number <span class="required-mark">*</span>
        </label>
        <input
          type="text"
          name="course_number"
          class="form-input"
          placeholder="Enter course number"
          value="<?= htmlspecialchars($_POST['course_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
          required
        >

        <label class="form-label">
          Course Name <span class="required-mark">*</span>
        </label>
        <input
          type="text"
          name="course_name"
          class="form-input"
          placeholder="Enter course name"
          value="<?= htmlspecialchars($_POST['course_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
          required
        >
      </div>

      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">
            Delivery Type <span class="required-mark">*</span>
          </label>
          <select name="delivery_type" class="form-select" required>
            <option value="">Select delivery type</option>
            <option value="in_person" <?= (($_POST['delivery_type'] ?? '') === 'in_person') ? 'selected' : '' ?>>In-person</option>
            <option value="hybrid" <?= (($_POST['delivery_type'] ?? '') === 'hybrid') ? 'selected' : '' ?>>Hybrid</option>
            <option value="online" <?= (($_POST['delivery_type'] ?? '') === 'online') ? 'selected' : '' ?>>Online</option>
          </select>

          <label class="form-label">Credit Hours</label>
          <input
            type="number"
            step="0.5"
            name="credit_hours"
            class="form-input"
            placeholder="Enter credit hours"
            value="<?= htmlspecialchars($_POST['credit_hours'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
          >

          <label class="form-label">Contact Hours</label>
          <input
            type="number"
            step="0.5"
            name="contact_hours"
            class="form-input"
            placeholder="Enter contact hours"
            value="<?= htmlspecialchars($_POST['contact_hours'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
          >

          <label class="form-label">Course Type</label>
          <select name="course_type" class="form-select">
            <option value="">Select type</option>
            <option value="R" <?= (($_POST['course_type'] ?? '') === 'R') ? 'selected' : '' ?>>Required (R)</option>
            <option value="E" <?= (($_POST['course_type'] ?? '') === 'E') ? 'selected' : '' ?>>Elective (E)</option>
            <option value="SE" <?= (($_POST['course_type'] ?? '') === 'SE') ? 'selected' : '' ?>>Selected Elective (SE)</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Course Coordinators</label>
          <div id="coordinator-container">
            <div class="form-group">
              <input
                type="text"
                name="course_coordinators[]"
                class="form-input"
                placeholder="Enter instructor name"
              >
              <button class="btn btn-remove" style="margin-top:3%" type="button" onclick="removeRow(this)">Remove</button>
            </div>
          </div>
          <button
            class="btn btn-outline"
            type="button"
            onclick="addRow('coordinator-container', 'course_coordinators[]', 'Enter instructor name')"
          >
            + Add Instructor
          </button>
        </div>
      </div>

      <div class="divider">
        <span class="divider-text">Textbooks</span>
      </div>

      <div id="textbook-container">
        <div class="form-group">
          <input
            type="text"
            name="textbooks[]"
            class="form-input"
            placeholder="Enter textbook"
          >
          <button class="btn btn-remove" style="margin-top:3%" type="button" onclick="removeRow(this)">Remove</button>
        </div>
      </div>
      <button
        class="btn btn-outline"
        type="button"
        onclick="addRow('textbook-container', 'textbooks[]', 'Enter textbook')"
      >
        + Add Textbook
      </button>

      <div class="divider">
        <span class="divider-text">Course Information</span>
      </div>

      <div class="form-group">
        <label class="form-label">Catalog Description</label>
        <textarea name="catalog_description" class="form-input"><?= htmlspecialchars($_POST['catalog_description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">Prerequisites</label>
        <textarea name="prerequisites" class="form-input"><?= htmlspecialchars($_POST['prerequisites'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
      </div>

      <div class="divider">
        <span class="divider-text">Course Goals</span>
      </div>

      <label class="form-label">Course Outcomes</label>
      <div id="course-outcomes-container">
        <div class="form-group">
          <input
            type="text"
            name="course_outcomes[]"
            class="form-input"
            placeholder="Enter course outcome"
          >
          <button class="btn btn-remove" style="margin-top:3%" type="button" onclick="removeRow(this)">Remove</button>
        </div>
      </div>
      <button
        class="btn btn-outline"
        type="button"
        onclick="addRow('course-outcomes-container', 'course_outcomes[]', 'Enter course outcome')"
      >
        + Add Course Outcome
      </button>

      <br><br>

      <label class="form-label">Student Outcomes Addressed</label>
      <div id="student-outcomes-container">
        <div class="form-group">
          <input
            type="text"
            name="student_outcomes[]"
            class="form-input"
            placeholder="Enter student outcome"
          >
          <button class="btn btn-remove" style="margin-top:3%" type="button" onclick="removeRow(this)">Remove</button>
        </div>
      </div>
      <button
        class="btn btn-outline"
        type="button"
        onclick="addRow('student-outcomes-container', 'student_outcomes[]', 'Enter student outcome')"
      >
        + Add Student Outcome
      </button>

      <div class="divider">
        <span class="divider-text">Topics</span>
      </div>

      <div id="topics-container">
        <div class="form-group">
          <input
            type="text"
            name="topics[]"
            class="form-input"
            placeholder="Enter topic"
          >
          <button class="btn btn-remove" style="margin-top:3%" type="button" onclick="removeRow(this)">Remove</button>
        </div>
      </div>
      <button
        class="btn btn-outline"
        type="button"
        onclick="addRow('topics-container', 'topics[]', 'Enter topic')"
      >
        + Add Topic
      </button>

      <br><br>

      <button type="submit" class="btn btn-primary">Save Template</button>
    </form>
  </div>

  <div class="card" style="margin-top:24px">
    <div class="card-title">Existing Templates</div>

    <?php if (empty($templates)): ?>
      <p>No templates have been created yet.</p>
    <?php else: ?>
      <table class="template-table">
        <thead>
          <tr>
            <th>Course</th>
            <th>Name</th>
            <th>Delivery</th>
            <th>Credits</th>
            <th>Contact Hours</th>
            <th>Updated</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($templates as $template): ?>
            <tr>
              <td>
                <?= htmlspecialchars($template['course_subject'] . ' ' . $template['course_number']) ?>
              </td>
              <td><?= htmlspecialchars($template['course_name']) ?></td>
              <td><?= htmlspecialchars($template['delivery_type']) ?></td>
              <td><?= htmlspecialchars($template['credit_hours']) ?></td>
              <td><?= htmlspecialchars($template['contact_hours']) ?></td>
              <td><?= htmlspecialchars($template['updated_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<script>
function addRow(containerId, inputName, placeholder) {
  const container = document.getElementById(containerId);

  const wrapper = document.createElement("div");
  wrapper.className = "form-group";

  const input = document.createElement("input");
  input.type = "text";
  input.name = inputName;
  input.className = "form-input";
  input.placeholder = placeholder;

  const button = document.createElement("button");
  button.type = "button";
  button.className = "btn btn-remove";
  button.style.marginTop = "3%";
  button.textContent = "Remove";
  button.onclick = function () {
    removeRow(this);
  };

  wrapper.appendChild(input);
  wrapper.appendChild(button);
  container.appendChild(wrapper);
}

function removeRow(button) {
  const row = button.closest(".form-group");
  if (row) row.remove();
}
</script>

</body>
</html>