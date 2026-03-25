<?php
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header(
    "Content-Security-Policy: "
    . "default-src 'self'; "
    . "script-src 'self' 'unsafe-inline'; "
    . "style-src 'self' 'unsafe-inline'; "
    . "img-src 'self' data: https://cms.asuonline.asu.edu;"
);

// HSTS should only be enabled over HTTPS (production)
// header("Strict-Transport-Security: max-age=31536000; includeSubDomains");

header("Permissions-Policy: camera=(), microphone=(), geolocation=()");