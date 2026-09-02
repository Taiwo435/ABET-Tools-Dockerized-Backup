<?php

namespace App\Controller\AssignmentsGrades;

use App\Service\ApiProxy;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

// #132: migrated from src/public/AssignmentsGrades/assignments.php.
//
// This page was entirely hardcoded mock data in the legacy version --
// five fake assignments with made-up submission counts/grades, four
// modals pre-filled with static demo values, and every interactive
// action (Edit, Export, Remove, Import to Canvas) simulated purely
// client-side via alert()/console.log(), with zero real backend calls
// anywhere in the file.
//

#[IsGranted('ROLE_ASSIGNMENTS_GRADES')]
final class AssignmentsController extends AbstractController
{
    #[Route('/AssignmentsGrades/assignments', name: 'app_assignments_grades_assignments', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->render('tools/assignments_grades/assignments.html.twig', [
            'courseId' => $request->query->get('courseId'),
        ]);
    }

    #[Route('/AssignmentsGrades/api/assignments', name: 'app_assignments_grades_assignments_api', methods: ['GET'])]
    public function assignments(Request $request, ApiProxy $proxy): JsonResponse
    {
        $courseId = trim((string) $request->query->get('courseId', ''));
        if ($courseId === '' || !ctype_digit($courseId)) {
            return $this->json(['success' => false, 'message' => 'Invalid course ID.'], 400);
        }

        $token = (string) $request->getSession()->get('canvas_token', '');
        if ($token === '') {
            return $this->json([
                'success' => false,
                'message' => 'Canvas token is missing or expired. Please reconnect.',
            ], 401);
        }

        try {
            $response = $proxy->getAssignments($token, $courseId);
            $status = $response->getStatusCode();
            $body = $response->toArray(false);
        } catch (\Throwable) {
            return $this->json([
                'success' => false,
                'message' => 'The assignments service is unavailable. Please try again.',
            ], 502);
        }

        if ($status === 401 || $status === 403) {
            return $this->json([
                'success' => false,
                'message' => 'Canvas token is invalid or expired. Please reconnect.',
            ], 401);
        }
        if ($status < 200 || $status >= 300) {
            return $this->json([
                'success' => false,
                'message' => $body['detail'] ?? 'Canvas could not load assignments.',
            ], $status >= 400 && $status <= 599 ? $status : 502);
        }

        return $this->json(['success' => true, 'assignments' => $body]);
    }
}
