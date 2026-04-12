<?php
require_once __DIR__ . '/db.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/auth.php';
require_login();

require_once getenv('ABET_PRIVATE_DIR') . '/lib/csrf.php';
require_once getenv('ABET_PRIVATE_DIR') . '/lib/security_headers.php'; 

header('Content-Type: application/json; charset=utf-8');

// Helpers 

function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    $data['next_csrf'] = csrf_token('tool1_proxy');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function post_str(string $key): string {
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : '';
}

function validate_course_id(string $id, string $label): ?string {
    if ($id === '' || !preg_match('/^\d+$/', $id)) {
        return "$label must be a numeric Canvas Course ID.";
    }
    return null;
}

function api_base(string $service): string {
    $hosts = [
        'extraction'  => ['EXTRACTION_HOSTNAME', 'EXTRACTION_PORT', 'extraction_api', '8000'],
        'formatting'  => ['CANVAS_FORMATTING_HOSTNAME', 'CANVAS_FORMATTING_PORT', 'canvas_formatting', '8001'],
    ];
    [$hostEnv, $portEnv, $defaultHost, $defaultPort] = $hosts[$service];
    $host = getenv($hostEnv) ?: $defaultHost;
    $port = getenv($portEnv) ?: $defaultPort;
    return "http://{$host}:{$port}";
}

function curl_api(string $url, string $method, string $token, array $curlExtra = []): array {
    $ch = curl_init($url);

    $headers = [];
    if ($token !== '') {
        $headers[] = 'canvas-access-token: ' . $token;
    }
    // Only set Content-Type for methods that send a body
    if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true)) {
        $headers[] = 'Content-Type: application/json';
    }

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT => 600,
    ] + $curlExtra);

    $body     = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['ok' => false, 'status' => 502, 'body' => null, 'error' => $error];
    }

    $decoded = json_decode((string) $body, true);
    $ok      = $httpCode >= 200 && $httpCode < 300;
    return ['ok' => $ok, 'status' => $httpCode, 'body' => $decoded, 'error' => null];
}

function getCoursesFromSemester(string $term, array $courses): array
{
    $filtered = array_filter($courses, function($course) use ($term) {
        // Always include Testing Ground course regardless of term
        if (($course['id'] ?? 0) === 240102) return true;
        return isset($course['term']['name']) && str_contains($course['term']['name'], $term);
    });

    // only return fields the frontend needs
    return array_values(array_map(function($c) {
        return [
            'id'           => $c['id'],
            'name'         => $c['name'] ?? '',
            'course_code'  => $c['course_code'] ?? '',
            'total_students' => $c['total_students'] ?? 0,
            'term'         => ['name' => $c['term']['name'] ?? ''],
            'teachers'     => $c['teachers'] ?? [],
        ];
    }, $filtered));
}
//  Gate 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

$action = post_str('action');
if ($action === '') {
    json_response(['success' => false, 'message' => 'Missing action parameter.'], 400);
}

$allowed_actions = [
    'verify-token',
    'store-credentials', 
    'verify-course', 
    'start-extraction', 
    'start-extraction-v2',
    'check-extraction-status', 
    'check-job-history',
    'run-formatting',
    'fetch-classes-from-semester',
    'store-class-data-from-grid'
];
if (!in_array($action, $allowed_actions, true)) {
    json_response(['success' => false, 'message' => 'Unknown action.'], 400);
}

// CSRF validation (uses shared csrf.php library)
$csrf = post_str('csrf_token');
if (!csrf_validate($csrf, 'tool1_proxy')) {
    json_response(['success' => false, 'message' => 'Invalid or missing CSRF token.'], 403);
}

// Actions 

if ($action === 'store-credentials') {
    $token = post_str('canvas_token');

    if ($token === '') {
        json_response(['success' => false, 'message' => 'Canvas token is required.'], 400);
    }

    if ($errors) {
        json_response(['success' => false, 'message' => 'Validation failed.', 'errors' => array_values($errors)], 422);
    }
 
    $_SESSION['canvas_token']     = $token;
    $_SESSION['token_stored_at']  = time();

    json_response(['success' => true, 'message' => 'Credentials stored.']);
}


if ($action === 'fetch-classes-from-semester')
{   
    $term = post_str('term');
    $token = $_SESSION['canvas_token'] ?? '';

    if ($token === '') {
        json_response(['success' => false, 'message' => 'No token found. Please connect first.'], 401);
    }

    // Call Python extraction API instead of Canvas directly
    $url = api_base('extraction') . '/canvas/courses?' . http_build_query(['enrollment_type' => 'teacher']);
    $result = curl_api($url, 'GET', $token);

    if ($result['error']) {
        json_response(['success' => false, 'message' => 'cURL error: ' . $result['error']], 502);
    }

    if ($result['status'] === 401) {
        json_response(['success' => false, 'message' => 'Token is invalid or expired.'], 401);
    }

    if ($result['status'] !== 200) {
        $detail = $result['body']['detail'] ?? ('Unexpected response HTTP {' . $result['status'] . '}');
        json_response(['success' => false, 'message' => $detail], $result['status']);
    }

    $courses = getCoursesFromSemester($term, $result['body']);
    json_response(['success' => true, 'courses' => $courses]);
} 

if ($action === 'store-class-data-from-grid')
{   
    $_SESSION['class_data'] = json_decode(post_str('selected_courses_data'), true);
    json_response(['success' => true]);
} 

if ($action === 'verify-token'){

    if ($_SESSION['canvas_token'] === '')
    {
        json_response(['success' => false, 'message' => 'No token found']);
    }

    $canvas_endpoint = "https://canvas.asu.edu/api/v1/users/self";
    $ch = curl_init($canvas_endpoint);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer " . $_SESSION['canvas_token']],
        CURLOPT_TIMEOUT => 10
    ]);
    $body = curl_exec($ch);
    $httpcode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error)
    {
        json_response(['success' => false, 'message' => 'cURL error: ' . $error]);
    }

    if ($httpcode === 401)
    {
        json_response(['success' => false, 'message' => 'Token is invalid or expired']);
    }
    
    if ($httpcode !== 200)
    {
        json_response(['success' => false, 'message' => 'Unexpected reponse HTTP {$httpcode}']);
    }

     json_response(['success' => true]);

}

if ($action === 'verify-course') {
    $courseId = post_str('course_id');
    $token = $_SESSION['canvas_token'] ?? '';

    if ($token === '') {
        json_response(['success' => false, 'message' => 'No canvas token found.'], 401);
    }
    if ($courseId === '') {
        json_response(['success' => false, 'message' => 'Course ID is required.'], 400);
    }

    $url = api_base('extraction') . '/verify-course/' . urlencode($courseId);
    $checkDuplicate = post_str('check_duplicate') === '1';
    $destCourseId = post_str('dest_course_id');
    
    if ($checkDuplicate) {
        if ($destCourseId === '') {
            json_response(['success' => false, 'message' => 'Destination Course ID is required to verify duplicates.'], 400);
        }
        $url .= '?dest_course_id=' . urlencode($destCourseId);
    }
    $result = curl_api($url, 'GET', $token);

    if ($result['error']) {
        json_response(['success' => false, 'message' => 'cURL error: ' . $result['error']], 502);
    }
    if ($result['status'] === 401) {
        json_response(['success' => false, 'message' => 'Token is invalid or expired.'], 401);
    }
    if ($result['status'] === 404) {
        json_response(['success' => false, 'message' => 'Course not found. Check the ID and try again.'], 404);
    }
    if ($result['status'] !== 200) {
        $detail = $result['body']['detail'] ?? "Unexpected response HTTP {$result['status']}";
        json_response(['success' => false, 'message' => $detail], $result['status']);
    }

    json_response([
        'success' => true,
        'course' => [
            'id'          => $result['body']['course_id'] ?? $courseId,
            'name'        => $result['body']['name'] ?? '',
            'course_code' => $result['body']['course_code'] ?? '',
            'duplicate_status' => $result['body']['duplicate_status'] ?? false,
            'teachers'    => $result['body']['teachers'] ?? []
        ]
    ]);
}


if ($action === 'start-extraction') {
    set_time_limit(60);

    $token    = $_SESSION['canvas_token']     ?? '';
    $sourceId = $_SESSION['source_course_id'] ?? '';
    $destId   = $_SESSION['dest_course_id']   ?? '';
    $userId = (int)($_SESSION['user_id'] ?? 0);

    if ($token === '' || $sourceId === '' || $destId === '') {
        json_response(['success' => false, 'message' => 'No credentials in session. Please connect first.'], 401);
    }

    // Token TTL — 30 minutes
    if (time() - ($_SESSION['token_stored_at'] ?? 0) > 1800) {
        unset($_SESSION['canvas_token'], $_SESSION['source_course_id'], $_SESSION['dest_course_id'], $_SESSION['token_stored_at']);
        json_response(['success' => false, 'message' => 'Session credentials expired. Please reconnect.'], 401);
    }

    if (!isset($_FILES['roster_file']) || $_FILES['roster_file']['error'] !== UPLOAD_ERR_OK) {
        json_response(['success' => false, 'message' => 'Roster file is required.'], 400);
    }

    // Server-side file extension validation
    $ext = strtolower(pathinfo($_FILES['roster_file']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'xls'], true)) {
        json_response(['success' => false, 'message' => 'Only .csv and .xls roster files are accepted.'], 400);
    }

    // Trigger Extraction
    $extractionUrl = api_base('extraction')
        . '/move-data-between-courses/' . urlencode($destId)
        . '?' . http_build_query(['course_ids_to_pull' => $sourceId]);

    $rosterCurl = new CURLFile(
        $_FILES['roster_file']['tmp_name'],
        $_FILES['roster_file']['type'] ?: 'text/csv',
        $_FILES['roster_file']['name']
    );

    $headers = ['canvas-access-token: ' . $token];
    if ($userId <= 0) {
        json_response(['success' => false, 'message' => 'Session is missing user identity. Please log in again.'], 401);
    }

    $headers[] = 'submitted-by-user-id: ' . $userId;
    $headers[] = 'user-id: ' . $userId;

    $ch = curl_init($extractionUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30, // Background tasks return immediately
        CURLOPT_POSTFIELDS     => ['roster_file' => $rosterCurl],
    ]);

    $extractBody  = curl_exec($ch);
    $extractCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $extractError = curl_error($ch);
    curl_close($ch);

    if ($extractError || $extractCode < 200 || $extractCode >= 300) {
        $decoded = json_decode((string) $extractBody, true);
        $msg = $extractError ?: ($decoded['detail'] ?? 'Failed to start extraction.');
        json_response(['success' => false, 'message' => $msg], $extractCode ?: 502);
    }

    // Parse extraction response metadata
    $extractData = json_decode((string) $extractBody, true) ?: [];
    
    // Return job_id immediately
    json_response([
        'success' => true,
        'message' => 'Extraction started.',
        'job_id'  => $extractData['job_id'] ?? null
    ]);
}


if ($action === 'check-extraction-status') {
    $jobId = post_str('job_id');
    if ($jobId === '') {
        json_response(['success' => false, 'message' => 'Missing job_id parameter.'], 400);
    }

    // Extraction API status endpoint
    $statusUrl = api_base('extraction') . '/job-status/' . urlencode($jobId);
    
    // Call extraction API status endpoint. No canvas token needed for
    // internal status checks, so we pass an empty string to avoid leaking it.
    $result = curl_api($statusUrl, 'GET', '');

    if (!$result['ok']) {
        $detail = $result['error'] ?? ($result['body']['detail'] ?? 'Status check failed.');
        json_response(['success' => false, 'message' => $detail], $result['status']);
    }

    json_response(['success' => true, 'job_status' => $result['body']]);
}


if ($action === 'run-formatting') {
    set_time_limit(600); 

    $token    = $_SESSION['canvas_token']     ?? '';
    $destId   = $_SESSION['dest_course_id']   ?? '';
    
    $courseFolderName = post_str('course_folder_name');
    $termDisplay      = post_str('term_display');
    $overwrite        = post_str('overwrite') === '1';

    if ($token === '' || $destId === '') {
        json_response(['success' => false, 'message' => 'No credentials in session. Please connect first.'], 401);
    }

    if (time() - ($_SESSION['token_stored_at'] ?? 0) > 1800) {
        unset($_SESSION['canvas_token'], $_SESSION['source_course_id'], $_SESSION['dest_course_id'], $_SESSION['token_stored_at']);
        json_response(['success' => false, 'message' => 'Session credentials expired. Please reconnect.'], 401);
    }

    $formatQuery = [];
    if (!empty($courseFolderName)) {
        $formatQuery['course_folder_name'] = $courseFolderName;
    }
    if (!empty($termDisplay)) {
        $formatQuery['term_display'] = $termDisplay;
    }
    if ($overwrite) {
        $formatQuery['overwrite'] = 'true';
    }

    $formattingUrl = api_base('formatting')
        . '/format-and-upload/' . urlencode($destId)
        . '?' . http_build_query($formatQuery);

    $formatResult = curl_api($formattingUrl, 'POST', $token);

    if (!$formatResult['ok']) {
        $detail = $formatResult['error'] ?? ($formatResult['body']['detail'] ?? 'Formatting failed.');
        json_response(['success' => false, 'step' => 'formatting', 'message' => $detail], $formatResult['status']);
    }

    json_response([
        'success' => true,
        'message' => 'Pipeline complete. Data extracted, formatted, and uploaded to Canvas.',
        'formatting' => $formatResult['body'],
    ]);
}

if ($action === 'start-extraction-v2') {
    set_time_limit(60);

    $token     = $_SESSION['canvas_token'] ?? '';
    $sourceId  = post_str('source_course_id');
    $destId    = post_str('dest_course_id');
    $overwrite = post_str('overwrite') === '1';
    $userId    = (int)($_SESSION['user_id'] ?? 0);

    //----------------Infomation from the syllabus form----------------//
    $courseSubject       = post_str('course_subject');
    $courseNumber        = post_str('course_number');
    $syllabusCourseName  = post_str('syllabus_course_name');
    $creditsHours        = post_str('credits_hours');
    $contactHours        = post_str('contact_hours');
    $category            = post_str('category');
    $catalogDescription  = post_str('catalog_description');
    $prerequisites       = post_str('prerequisites');
    $courseType          = post_str('course_type');
    //***This will need to be updated dynamically eventully.***
    $program_name = "Computer Systems Engineering";
    $program_year = post_str('program_year');
    //----------------------------------------------------------------//

    $courseCoordinators = array_values(array_filter(array_map('trim', $_POST['course_coordinators'] ?? [])));
    $textbooks = array_values(array_filter(array_map('trim', $_POST['textbooks'] ?? [])));
    $courseOutcomes = array_values(array_filter(array_map('trim', $_POST['course_outcomes'] ?? [])));
    $studentOutcomesAddressed = array_values(array_filter(array_map('trim', $_POST['student_outcomes_addressed'] ?? [])));
    $topics = array_values(array_filter(array_map('trim', $_POST['topics'] ?? [])));
    
    //----------------Infomation from the syllabus form end------------//

    if ($token === '') {
        json_response(['success' => false, 'message' => 'No token in session.'], 401);
    }
    if ($sourceId === '' || $destId === '') {
        json_response(['success' => false, 'message' => 'Course IDs are required.'], 400);
    }
    if (!isset($_FILES['roster_file']) || $_FILES['roster_file']['error'] !== UPLOAD_ERR_OK) {
        json_response(['success' => false, 'message' => 'Roster file is required.'], 400);
    }

    $ext = strtolower(pathinfo($_FILES['roster_file']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'xls'], true)) {
        json_response(['success' => false, 'message' => 'Only .csv and .xls roster files are accepted.'], 400);
    }

    // Build extraction API URL — single course
    $courseName = post_str('course_name');
    $queryArgs  = ['course_ids_to_pull' => $sourceId];
    if ($courseName !== '') {
        $queryArgs['course_name'] = $courseName;
    }
    if ($overwrite) {
        $queryArgs['overwrite'] = 'true';
    }

    $extractionUrl = api_base('extraction')
        . '/move-data-between-courses/' . urlencode($destId)
        . '?' . http_build_query($queryArgs);

    $rosterCurl = new CURLFile(
        $_FILES['roster_file']['tmp_name'],
        $_FILES['roster_file']['type'] ?: 'text/csv',
        $_FILES['roster_file']['name']
    );

    $headers = ['canvas-access-token: ' . $token];
    if ($userId > 0) {
        $headers[] = 'submitted-by-user-id: ' . $userId;
        $headers[] = 'user-id: ' . $userId;
    }

    $ch = curl_init($extractionUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_POSTFIELDS     => ['roster_file' => $rosterCurl],
    ]);

    $extractBody  = curl_exec($ch);
    $extractCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $extractError = curl_error($ch);
    curl_close($ch);

    if ($extractError || $extractCode < 200 || $extractCode >= 300) {
        $decoded = json_decode((string) $extractBody, true);
        $msg = $extractError ?: ($decoded['detail'] ?? 'Failed to start extraction.');
        json_response(['success' => false, 'message' => $msg], $extractCode ?: 502);
    }


    $stmt = $pdo->prepare("SELECT program_id FROM programs WHERE program_name = ? AND program_year = ?");
    $stmt->execute([$program_name, $program_year]);
    $program = $stmt->fetch();
    $program = $program ? $program['program_id'] : null;

    if($program === null) {
        json_response(['success' => false, 'message' => 'Program not found for course name: ' . $program_name. ' and year: ' . $program_year], 404);
    }

    $stmt = $pdo->prepare("
        INSERT INTO course_syllabi (
            program_id,
            course_subject,
            course_number,
            course_name,
            credits,
            contact_hours,
            credit_categorization,
            instructor_name,
            textbook,
            catalog_description,
            prerequisites,
            course_type,
            specific_goals,
            student_outcomes,
            topics_covered
        ) VALUES (
            :program_id,
            :course_subject,
            :course_number,
            :course_name,
            :credits,
            :contact_hours,
            :credit_categorization,
            :instructor_name,
            :textbook,
            :catalog_description,
            :prerequisites,
            :course_type,
            :specific_goals,
            :student_outcomes,
            :topics_covered
        )
    ");

    $stmt->execute([
        ':program_id' => $program,
        ':course_subject' => $courseSubject,
        ':course_number' => $courseNumber,
        ':course_name' => $syllabusCourseName,
        ':credits' => $creditsHours,
        ':contact_hours' => $contactHours,
        ':credit_categorization' => $category,
        ':instructor_name' => json_encode($courseCoordinators),
        ':textbook' => json_encode($textbooks),
        ':catalog_description' => $catalogDescription,
        ':prerequisites' => $prerequisites,
        ':course_type' => $courseType,
        ':specific_goals' => json_encode($courseOutcomes),
        ':student_outcomes' => json_encode($studentOutcomesAddressed),
        ':topics_covered' => json_encode($topics),
    ]);

    


    $extractData = json_decode((string) $extractBody, true) ?: [];
    json_response([
        'success' => true,
        'message' => 'Extraction started.',
        'job_id'  => $extractData['job_id'] ?? null
    ]);
    

}

if ($action === 'check-job-history') {
    $token  = $_SESSION['canvas_token'] ?? '';
    $userId = (int)($_SESSION['user_id'] ?? 0);

    if ($token === '') {
        json_response(['success' => false, 'message' => 'No token in session.'], 401);
    }

    $url = api_base('extraction') . '/jobs?limit=50';
    $headers = ['canvas-access-token: ' . $token];
    if ($userId > 0) {
        $headers[] = 'submitted-by-user-id: ' . $userId;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 10,
    ]);

    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err || $code !== 200) {
        $decoded = json_decode((string) $body, true) ?: [];
        $msg = $err ?: ($decoded['detail'] ?? 'Failed to fetch jobs.');
        json_response(['success' => false, 'message' => $msg], $code ?: 502);
    }

    $data = json_decode((string) $body, true) ?: [];
    json_response(['success' => true, 'jobs' => $data['jobs'] ?? []]);
}

json_response(['success' => false, 'message' => 'Unknown action.'], 400);