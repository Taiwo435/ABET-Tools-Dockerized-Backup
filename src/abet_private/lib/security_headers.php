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

// Add Cross-Origin headers for enhanced isolation
// (ZAP: Cross-Origin-Opener-Policy, Cross-Origin-Resource-Policy Header Missing)
// NOTE: Cross-Origin-Embedder-Policy removed as it commonly breaks external CDNs/images unless they send CORP headers
header("Cross-Origin-Opener-Policy: same-origin");
header("Cross-Origin-Resource-Policy: same-site");