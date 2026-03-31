<?php
declare(strict_types=1);
require_once __DIR__ . '/../_guard.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/security_headers.php'; 

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method Not Allowed');
}

http_response_code(501);
exit('Not implemented yet');
