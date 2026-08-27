<?php

namespace App\Controller\AssignmentsGrades;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

// #132: migrated from src/public/AssignmentsGrades/{canvasTest,runCanvasGenerator}.php.
//
// Fixed here: the legacy generator called the Canvas
// Formatting API at a hardcoded "http://localhost:8001/...", which only
// works if the caller happens to run inside the same container as that
// service. Every other caller in this codebase (see ApiProxy::api_base())
// resolves the API host via CANVAS_FORMATTING_HOSTNAME/PORT, defaulting to
// the real Docker service name, this now does the same, matching the
// exact env-var/hostname pattern used elsewhere.
//
// CSRF: uses Symfony's native token system instead of the legacy
// session-based csrf_canvas_token.
#[IsGranted('ROLE_ASSIGNMENTS_GRADES')]
final class CanvasCourseGeneratorController extends AbstractController
{
    #[Route('/AssignmentsGrades/canvas-course', name: 'app_assignments_grades_canvas_course', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('tools/assignments_grades/canvas_course.html.twig');
    }

    #[Route('/AssignmentsGrades/canvas-course/generate', name: 'app_assignments_grades_canvas_course_generate', methods: ['POST'])]
    public function generate(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('canvas_course_generate', (string) $request->request->get('_csrf_token'))) {
            return $this->json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
        }

        $sourceCourse = trim((string) $request->request->get('sourceCourse', ''));
        $destCourse = trim((string) $request->request->get('destCourse', ''));
        $semester = trim((string) $request->request->get('semester', ''));
        $year = trim((string) $request->request->get('year', ''));
        $canvasToken = trim((string) $request->request->get('canvasToken', ''));
        $genCoursePage = $request->request->getBoolean('genCoursePage');
        $genAbetPage = $request->request->getBoolean('genAbetPage');

        $errors = [];
        if ($sourceCourse === '' || !preg_match('/^\d+$/', $sourceCourse)) {
            $errors[] = 'Source Canvas Course ID must be numeric.';
        }
        if ($destCourse === '' || !preg_match('/^\d+$/', $destCourse)) {
            $errors[] = 'Destination Canvas Course ID must be numeric.';
        }
        if (!in_array($semester, ['Fall', 'Spring', 'Summer'], true)) {
            $errors[] = 'Semester must be Fall, Spring, or Summer.';
        }
        if ($year === '' || !preg_match('/^\d{4}$/', $year)) {
            $errors[] = 'Year must be a 4-digit year.';
        }
        if ($canvasToken === '') {
            $errors[] = 'Canvas token is required for testing.';
        }
        if (!$genCoursePage && !$genAbetPage) {
            $errors[] = 'Select at least one generation option (Course Page or ABET Page).';
        }

        if ($errors) {
            return $this->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $errors,
            ], 422);
        }

        $host = getenv('CANVAS_FORMATTING_HOSTNAME') ?: 'canvas_formatting';
        $port = getenv('CANVAS_FORMATTING_PORT') ?: '8001';
        $apiUrl = "http://{$host}:{$port}/format-and-upload/" . urlencode($sourceCourse);
        $apiUrl .= '?' . http_build_query([
            'destination_course_id' => $destCourse,
            'semester' => strtolower($semester),
            'year' => $year,
        ]);

        $client = HttpClient::create();

        try {
            $response = $client->request('POST', $apiUrl, [
                'headers' => [
                    'canvas_access_token' => $canvasToken,
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 120,
            ]);
            $httpCode = $response->getStatusCode();
            $responseBody = $response->getContent(false);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to reach Canvas Formatting API: ' . $e->getMessage(),
            ], 502);
        }

        $data = json_decode($responseBody, true) ?: [];
        $success = ($httpCode >= 200 && $httpCode < 300);
        $safeBody = str_replace($canvasToken, '[REDACTED_TOKEN]', (string) $responseBody);

        return $this->json([
            'success' => $success,
            'message' => $success
                ? ($data['message'] ?? 'Canvas formatting completed successfully.')
                : ($data['detail'] ?? 'Canvas formatting API returned an error.'),
            'exitCode' => $httpCode,
            'stdout' => $safeBody,
            'stderr' => '',
        ], $success ? 200 : 500);
    }
}
