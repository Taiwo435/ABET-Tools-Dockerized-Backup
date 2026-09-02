<?php

namespace App\Controller\AssignmentsGrades;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
}
