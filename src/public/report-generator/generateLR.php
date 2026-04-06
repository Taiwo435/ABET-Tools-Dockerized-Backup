<?php
declare(strict_types=1);
require_once getenv('ABET_PRIVATE_DIR') . '/lib/security_headers.php';

if ($_SERVER['APP_DEBUG']) {
    umask(0000); // DON"T set file permissions :)
}

/**
 * report-generator/generate.php
 * Secure upload + isolated Python run + optional DOCX->PDF conversion
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

$privateLogDir = getenv('ABET_PRIVATE_DIR') . '/logs';
if (!is_dir($privateLogDir)) {
    @mkdir($privateLogDir, 0700, true);
}
ini_set('error_log', $privateLogDir . '/report-generator-php.log');

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log("FATAL: {$e['message']} @ {$e['file']}:{$e['line']}");
    }
});

require_once getenv('ABET_PRIVATE_DIR') . '/lib/auth.php';
require_login();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function json_response(int $code, array $payload): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function generate_suffix(int $bytes = 4): string
{
    return bin2hex(random_bytes($bytes));
}


 #basing this on the pattern from api-proxy.php, ultimately generates the url for api service using env variables
function api_base(string $service): string
{
    $hosts = [
        'reportgen' => ['REPORTGEN_HOSTNAME', 'REPORTGEN_PORT', 'reportgen', '8002'],
    ];
    [$hostEnv, $portEnv, $defaultHost, $defaultPort] = $hosts[$service];
    $host = getenv($hostEnv) ?: $defaultHost;
    $port = getenv($portEnv) ?: $defaultPort;
    return "http://{$host}:{$port}";
}

/**
 * this is the POST JSON that gets sent to an internal API and returns the response.
 * and this is returning the docx file as a binary instead of the JSON shenanigans going on in api-proxy.php fast api endpoint
 *
 * @return array{ok:bool, status:int, body:string|false, error:string|null}
 */
function curl_api_raw(string $url, string $jsonPayload, array $curlExtra = []): array
{
    $ch = curl_init($url);
    #this is the curl to send post request to the url and get the response from the api
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $jsonPayload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 300,
        CURLOPT_CONNECTTIMEOUT => 10,
    ] + $curlExtra);

    #bunch of error handling below if any issues occur during curl request
    $body     = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);
    
    
    if ($error) {
        return ['ok' => false, 'status' => 502, 'body' => $body, 'error' => 'CURL ERROR: ['.$error.']'];
    }

    $ok = $httpCode >= 200 && $httpCode < 300;
    return ['ok' => $ok, 'status' => $httpCode, 'body' => $body, 'error' => null];
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, ['ok' => false, 'error' => 'POST required']);
    }

    #CSRF token shenanigans for matching the session prevent cross site request attacks
    $csrf = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_report_token']) || !hash_equals((string)$_SESSION['csrf_report_token'], (string)$csrf)) {
        json_response(403, ['ok' => false, 'error' => 'Invalid CSRF token']);
    }


    #paths
    $jobsRoot = getenv('ABET_PRIVATE_DIR') . '/report_jobs';

    if (!is_dir($jobsRoot) && !mkdir($jobsRoot, 0700, true)) {
        json_response(500, ['ok' => false, 'error' => 'Cannot create jobs directory']);
    }

    $jobId   = date('Ymd_His') . '_' . generate_suffix(4);
    $jobDir  = $jobsRoot . '/' . $jobId;
    //$inputDir = $jobDir . '/input_jsons';
    $outDir   = $jobDir . '/generated_pdfs';
    $logDir   = $jobDir . '/logs';

    foreach ([$jobDir, $logDir] as $dir) {
        if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
            json_response(500, ['ok' => false, 'error' => 'Cannot create job folder']);
        }
    }


    #Ccll the fastapi report generation endpoint
    #hardcoded defaults for now adjust later maybe
    $reportgenUrl = api_base('reportgen') . '/generate-report';
    $payload = json_encode([
        'year'        => 2026,
        'department'  => 'CSE',
        'degree_type' => 'BS',
    ]);

    $result = curl_api_raw($reportgenUrl, $payload);

    #this creates and writes to a log entry for debugging pruposes
    $runLog = "URL: {$reportgenUrl}\n"
        . "HTTP: {$result['status']}\n"
        . "CURL_ERR: " . ($result['error'] ?? '') . "\n"
        . "RESPONSE_LEN: " . strlen((string)$result['body']) . "\n";
    @file_put_contents($logDir . '/run.log', $runLog);

    if (!$result['ok']) {
        #more error handling for api call if not successful
        $decoded = @json_decode((string)$result['body'], true);
        $detail  = $result['error'] ?? ($decoded['detail'] ?? "HTTP {$result['status']}");
        json_response(500, [
            'ok'    => false,
            'error' => 'Report API error: ' . $detail,
            'job_id' => $jobId
        ]);
    }

    #save the generated DOCX file to the output directory
    $finalDocxPath = $outDir . '/report.docx';
    if (!is_dir($outDir) && !mkdir($outDir, 0700, true)) {
        json_response(500, ['ok' => false, 'error' => 'Cannot create output directory']);
    }
    $written = @file_put_contents($finalDocxPath, $result['body']);

    if ($written === false || !file_exists($finalDocxPath)) {
        json_response(500, [
            'ok' => false,
            'error' => 'Failed to save generated DOCX file',
            'job_id' => $jobId
        ]);
    }

    // URLs served through authenticated stream endpoint
    $docxUrl = '/report-generator/view.php?job=' . urlencode($jobId) . '&file=report.docx';
    //$pdfUrl  = '/report-generator/view.php?job=' . urlencode($jobId) . '&file=report.pdf';

    json_response(200, [
        'ok' => true,
        'job_id' => $jobId,
        //'pdf_ready' => $pdfReady,
        //'pdf_url' => $pdfReady ? $pdfUrl : null,
        'docx_url' => $docxUrl
    ]);
} catch (Throwable $e) {
    error_log('generateLR.php exception: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    json_response(500, ['ok' => false, 'error' => 'Internal server error']);
}
