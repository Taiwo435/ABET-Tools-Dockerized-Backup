<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\User;

final class AssignmentsGradesController extends AbstractController
{

    
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/tool/assignmentsgrades', name: 'app_assignments_grades')]
    public function tool_one(#[CurrentUser] User $user) {
        $parts = explode('@', (string)$user->getEmail());
        $asurite = $parts[0] ?? 'user';
        return $this->render('tools/assignments_grades/index.html.twig', [
            'asurite' => $asurite,
        ]);
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/tool/assignmentsgrades/jobs', name: 'app_assignments_grades_jobs')]
    public function jobs(#[CurrentUser] User $user) {
        $parts = explode('@', (string)$user->getEmail());
        $asurite = $parts[0] ?? 'user';
        return $this->render('tools/assignments_grades/jobs.html.twig', [
            'asurite' => $asurite,
        ]);
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/tool/assignmentsgrades/course_select', name: 'app_assignments_grades_course_select')]
    public function course_select(#[CurrentUser] User $user) {
        $parts = explode('@', (string)$user->getEmail());
        $asurite = $parts[0] ?? 'user';
        return $this->render('tools/assignments_grades/course_select.html.twig', [
            'asurite' => $asurite,
        ]);
    }

}
