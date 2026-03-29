<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/form_functions.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/templates/primary-header.php';

// Permission bit positions - must match Permissions enum in User.php
const PERM_ADMIN_PANEL            = 1 << 0; // 1
const PERM_GRADE_DATA_TOOL        = 1 << 1; // 2
const PERM_CANVAS_FORMATTING_TOOL = 1 << 2; // 4
const PERM_REPORT_GEN_TOOL        = 1 << 3; // 8
const PERM_FACULTY_FORM_TOOL      = 1 << 4; // 16
const PERM_COORDINATOR_FORM_TOOL  = 1 << 5; // 32

$permissions = (int)($_SESSION['user_permissions'] ?? 0);

function hasPermission(int $permissions, int $bit): bool {
    return ($permissions & $bit) !== 0;
}

if (empty($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}
?>

<link rel="stylesheet" href="assets/css/toolcards.css">
<link rel="stylesheet" href="assets/css/checklist.css">

<section class="hero">
    <h1>ABET Tools </h1>
    <p>Access the core tools designed to empower the Sun Devil faculty. Select a tool below to learn more about its capabilities.</p>
</section>

<div class="container">
    <div class="checklist">
        <label class="checklist-title">Your To-Do List</label>

        <div class="checklist-item" onclick="window.location.href='#';">
            <?php if (false): ?>
                <label class="checklist-check">&#10004;</label>
            <?php else: ?>
                <label class="checklist-x">&#10006;</label>
            <?php endif; ?>
            <label class="checklist-label">Upload your canvas token &#x279E;</label>
        </div>

        <?php if (hasPermission($permissions, PERM_FACULTY_FORM_TOOL)): ?>
        <div class="checklist-item" onclick="window.location.href='/faculty-form';">
            <?php if (allPagesDone('faculty-form')): ?>
                <label class="checklist-check">&#10004;</label>
            <?php else: ?>
                <label class="checklist-x">&#10006;</label>
            <?php endif; ?>
            <label class="checklist-label">Complete the faculty information form &#x279E;</label>
        </div>
        <?php endif; ?>

        <?php if (hasPermission($permissions, PERM_GRADE_DATA_TOOL)): ?>
        <div class="checklist-item" onclick="window.location.href='/AssignmentsGrades/tool1.php';">
            <?php if (false): ?>
                <label class="checklist-check">&#10004;</label>
            <?php else: ?>
                <label class="checklist-x">&#10006;</label>
            <?php endif; ?>
            <label class="checklist-label">Link all of your canvas courses (<?= 0 ?> added so far) &#x279E;</label>
        </div>
        <?php endif; ?>

        <div class="checklist-item" onclick="window.location.href='#';">
            <?php if (false): ?>
                <label class="checklist-check">&#10004;</label>
            <?php else: ?>
                <label class="checklist-x">&#10006;</label>
            <?php endif; ?>
            <label class="checklist-label">Upload and manage your roster and assignments &#x279E;</label>
        </div>

    </div>

    <div class="tools-grid">

        <?php if (hasPermission($permissions, PERM_GRADE_DATA_TOOL)): ?>
        <div class="tool-card">
            <div class="card-header" onclick="triggerToggle(this)">
                <div class="card-title">Assigment & Grade Data</div>
                <button class="toggle-btn" aria-label="Toggle Description" type="button">+</button>
            </div>
            <div class="card-body">
                <div class="card-body-inner">
                    <p>Manage course performance data efficiently with a clear, structured view of assignments and outcomes-related grading. Ensure records are complete and ready for review when needed.</p>
                    <a href="/AssignmentsGrades/tool1.php" class="action-link">Launch Tool 1 &rarr;</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (hasPermission($permissions, PERM_FACULTY_FORM_TOOL)): ?>
        <div class="tool-card">
            <div class="card-header" onclick="triggerToggle(this)">
                <div class="card-title">Faculty Form</div>
                <button class="toggle-btn" aria-label="Toggle Description" type="button">+</button>
            </div>
            <div class="card-body">
                <div class="card-body-inner">
                    <p>Fill out information about yourself that will be used on the ABET accreditation reports.</p>
                    <a href="/faculty-form" class="action-link">Open Faculty Form &rarr;</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (hasPermission($permissions, PERM_COORDINATOR_FORM_TOOL)): ?>
        <div class="tool-card">
            <div class="card-header" onclick="triggerToggle(this)">
                <div class="card-title">Coordinator Form</div>
                <button class="toggle-btn" aria-label="Toggle Description" type="button">+</button>
            </div>
            <div class="card-body">
                <div class="card-body-inner">
                    <p>Description text.</p>
                    <a href="/coordinator-form/edit" class="action-link">Open Coordinator Form &rarr;</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (hasPermission($permissions, PERM_REPORT_GEN_TOOL)): ?>
        <div class="tool-card">
            <div class="card-header" onclick="triggerToggle(this)">
                <div class="card-title">Generate ABET Report</div>
                <button class="toggle-btn" aria-label="Toggle Description" type="button">+</button>
            </div>
            <div class="card-body">
                <div class="card-body-inner">
                    <p>Generate a comprehensive ABET report that summarizes assessment results and supporting materials. Present information clearly to streamline internal review and accreditation preparation.</p>
                    <a href="/report-generator/index.php" class="action-link">Launch Tool 3 &rarr;</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (hasPermission($permissions, PERM_ADMIN_PANEL)): ?>
        <div class="tool-card">
            <div class="card-header" onclick="triggerToggle(this)">
                <div class="card-title">Admin Panel</div>
                <button class="toggle-btn" aria-label="Toggle Description" type="button">+</button>
            </div>
            <div class="card-body">
                <div class="card-body-inner">
                    <p>Manage destination course IDs, user permissions, and other admin settings.</p>
                    <a href="/AssignmentsGrades/admin.php" class="action-link">Open Admin Panel &rarr;</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
    function triggerToggle(headerElement) {
        const button = headerElement.querySelector('.toggle-btn');
        const card = headerElement.closest('.tool-card');
        const content = card.querySelector('.card-body');

        const isAlreadyOpen = !!content.style.maxHeight;

        document.querySelectorAll('.card-body').forEach(el => {
            el.style.maxHeight = null;
        });
        document.querySelectorAll('.toggle-btn').forEach(btn => {
            btn.classList.remove('active');
        });

        if (!isAlreadyOpen) {
            content.style.maxHeight = content.scrollHeight + "px";
            button.classList.add('active');
        }
    }
</script>

<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/templates/primary-footer.php';
?>