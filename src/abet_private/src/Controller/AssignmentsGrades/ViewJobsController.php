<?php

namespace App\Controller\AssignmentsGrades;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\User;
use App\Entity\Task\AssignmentsGrades\AccessTokenForm;
use App\Entity\Task\AssignmentsGrades\NewExtractionForm;
use App\Form\AssignmentsGrades\AccessTokenType;
use App\Form\AssignmentsGrades\NewExtractionType;
use App\Form\NewExtractionType as FormNewExtractionType;
use App\Service\API;
use App\Service\ApiProxy;

/**
 * Controller that deals with viewing the job history
 */
final class ViewJobsController extends AbstractController
{

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/tool/assignmentsgrades/jobs', name: 'app_assignments_grades_jobs')]
    public function jobs(
        #[CurrentUser] User $user, 
        ApiProxy $proxy
    ) {
        $response = $proxy->getJobHistory($user);
        $decoded_response = $response->toArray(false);

        // normal flow, response is valid
        if ($response->getStatusCode() == 200) {
            return $this->render('tools/assignments_grades/jobs.html.twig', [
                'jobs' => $decoded_response['jobs'],
            ]);
        }

        // else, return and render the error message
        // WARNING: if this happens on prod, it could expose sensitive information! 
        if ($response->getStatusCode() == 200) {
            return $this->render('tools/assignments_grades/jobs.html.twig', [
                'jobs' => [],
                'error' => $response->getContent(false)
            ]);
        }
    }

}
