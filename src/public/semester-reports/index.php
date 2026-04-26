<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/templates/primary-header.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/security_headers.php';
?>

<section class="hero hero-small">
    <h1>Semester Reports</h1>
    <p>Produce a report suitable for faculty review and departmental records.</p>
</section>

<div class="body-main">

    <div class="create-elements">
        <div class="section-header">
            <h2>Create a New Report</h2>
        </div>

        <button type="button" onclick="openCreatePage()" class="button-primary">Create &#x279E;</a>
    </div>

    <div class="section-divider"></div>


    <div class="view-elements">
        <div class="section-header">
            <h2>Your Reports</h2>
        </div>

        <div class="report-container">
            <select id="report-list" size="6" class="report-select">
                <!--Reports are added by javascript-->
            </select>
        </div>

        <div id="select-report-error"></div>

        <button type="button" onclick="openViewPage()" class="button-primary">View Report</a>
    </div>

    <div class="section-divider"></div>

    <a href="/home" class="button-secondary">Back</a>

</div>


<script>
    function openCreatePage() {
        location.assign('/semester-reports/create');
    }

    function openViewPage() {
        const reportSelect = document.getElementById("report-list");

        if (reportSelect.selectedIndex == -1) {
            const errorDiv = document.getElementById("select-report-error");

            const errorMessage = document.createElement("p")
            errorMessage.classList.add("error-message")
            errorMessage.textContent = "Please select a report"

            errorDiv.replaceChildren(errorMessage);

            return;
        }

        const selectedReport = reportSelect.value;

        viewURL = '/semester-reports/view' + '?report=' + selectedReport;
        location.assign(viewURL);
    }

    function addReportsToSelect() {
        const reportSelect = document.getElementById("report-list");

        reportSelect.replaceChildren();

        reports = [["Report 1", 1], ["Report 2", 2], ["Report 3", 3]];

        for (let i = 0; i < reports.length; i++) {
            report = reports[i];

            const reportOption = document.createElement("option");
            reportOption.textContent = report[0];
            reportOption.value = report[1];
            reportOption.class = "report-option";

            reportSelect.appendChild(reportOption);
        }
    }

    addReportsToSelect();
</script>

<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/templates/primary-footer.php';
?>