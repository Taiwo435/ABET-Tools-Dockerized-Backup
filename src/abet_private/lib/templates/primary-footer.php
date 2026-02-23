<footer>
    <p>&copy; 2026 Arizona State University. All rights reserved.</p>
    <p>Inspiring <span>Innovation</span> across the globe.</p>
</footer>

<!--Adds scripts for form validation if a form is on the page-->
<?php if (!empty($form)): ?>
    <script>
    // form-validation.js uses the formConfig variable
    const formConfig = <?= json_encode($form, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    </script>
    <script src="/assets/js/form-validation.js"></script>
<?php endif; ?>

</body>
</html>