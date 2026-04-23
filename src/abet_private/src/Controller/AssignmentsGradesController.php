<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\User;
use App\Entity\Task\AccessTokenForm;
use App\Form\AccessTokenType;
use App\Service\API;
use App\Service\ApiProxy;

final class AssignmentsGradesController extends AbstractController
{

    
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/tool/assignmentsgrades', name: 'app_assignments_grades')]
    public function tool_one(
        #[CurrentUser] User $user, 
        Request $request,
        ApiProxy $proxy,
        ) {

        /**
         * This creates a form using these docs:
         * @see https://symfony.com/doc/current/forms.html
         */
        // Initialize a AccessTokenForm that encapsulates the data returned
        $task = new AccessTokenForm();
        $task->setToken('');

        // Use the form builder that we defined
        $form = $this->createForm(AccessTokenType::class, $task);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // $form->getData() holds the submitted values
            // but, the original `$task` variable has also been updated
            $task = $form->getData();

            // use our API proxy to execute the task
            $response = $proxy->verifyToken($task->getToken());


            if ($response->getStatusCode() == 200) {
                // if successful, add to sessoin
                $session = $request->getSession();
                $session->set('canvas_token', $task->getToken());
                return $this->render('tools/assignments_grades/index.html.twig', [
                    'form' => $form,
                    'form_success' => true
                ]);
            }

            // The other case: the form has errored.
            // expects the response to be {'detail': error-msg}
            return $this->render('tools/assignments_grades/index.html.twig', [
                'form' => $form,
                'form_error' => $response->toArray(false)['detail'],
                'debug_error' => $response->getContent(false),
            ]);
        }

        return $this->render('tools/assignments_grades/index.html.twig', [
            'form' => $form,
        ]);
    } 

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

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/tool/assignmentsgrades/new_extraction', name: 'app_assignments_grades_new_extraction')]
    public function course_select(#[CurrentUser] User $user, ApiProxy $proxy) {
        return $this->render('tools/assignments_grades/new_extraction.html.twig', [
        ]);
    }

    #[Route('/tool/assignmentsgrades/testform', name: 'test_form')]
    public function new(Request $request, #[CurrentUser] User $user): Response
    {
        //asurite
        $parts = explode('@', (string)$user->getEmail());
        $asurite = $parts[0] ?? 'user';

        // creates a task object and initializes some data for this example
        $task = new AccessTokenForm();
        $task->setToken('');

        $form = $this->createForm(AccessTokenType::class, $task, [
            'row_attr' => ['a' => 'b']
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // $form->getData() holds the submitted values
            // but, the original `$task` variable has also been updated
            $task = $form->getData();

            // ... perform some action, such as saving the task to the database

            return $this->redirectToRoute('app_assignments_grades_jobs');
        }

        return $this->render('tools/assignments_grades/testform.twig', [
            'form' => $form,
        ]);
    }

}
