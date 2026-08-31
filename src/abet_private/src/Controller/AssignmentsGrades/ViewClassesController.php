<?php

namespace App\Controller\AssignmentsGrades;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

// #132: migrated from src/public/AssignmentsGrades/viewClasses.php.
//
// This page was entirely hardcoded mock data in the legacy version --
// two fake class cards with made-up instructors/schedules/student counts,
// no database query or API call anywhere in the file. Ported as an honest,
// authenticated placeholder with the same mock content, same pattern used
// for tools earlier in this migration, rather than inventing real data
// wiring that wasn't actually there to preserve.
#[IsGranted('ROLE_ASSIGNMENTS_GRADES')]
final class ViewClassesController extends AbstractController
{
    #[Route('/AssignmentsGrades/view-classes', name: 'app_assignments_grades_view_classes', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('tools/assignments_grades/view_classes.html.twig');
    }
}
