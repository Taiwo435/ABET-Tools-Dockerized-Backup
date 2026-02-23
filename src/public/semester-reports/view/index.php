<?php
require_once $_ENV['ABET_PRIVATE_DIR'] . '/lib/templates/primary-header.php';
?>

<link rel="stylesheet" href="/assets/css/pdfviewer.css">

<main class="pdf-page">
    <div class="pdf-top-bar">
    <button type="button" onclick="goBack()" class="button-secondary">&larr; Back</button>
    <button type="button" onclick="downloadPDF()" class="button-primary">Download PDF</button>
    </div>

    <div class="pdf-container">
    <iframe id="pdf-frame" src="" class="pdf-frame"></iframe>
    </div>
</main>

<script>
    /*-----Buttons-----*/
    function goBack() {
    location.assign('/semester-reports');
    }



    /*-----PDF-----*/
    let pdfName = "";
    let pdfPath = "";

    function downloadPDF() {
    const link = document.createElement('a');
    link.href = pdfPath;
    link.download = pdfName;             
    //link.target = "_blank";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    }

    function setupPDF() {
    let urlParams = new URLSearchParams(window.location.search);
    let report = urlParams.get("report");

    pdfName = "TestPDF" + report + ".pdf";
    pdfPath = "/semester-reports/Test-PDFs/" + pdfName;

    // Adds the PDF to the iframe
    const pdfFrame = document.getElementById("pdf-frame");
    pdfFrame.src = pdfPath;
    }


    setupPDF()
</script>

<?php
require_once $_ENV['ABET_PRIVATE_DIR'] . '/lib/templates/primary-footer.php';
?>