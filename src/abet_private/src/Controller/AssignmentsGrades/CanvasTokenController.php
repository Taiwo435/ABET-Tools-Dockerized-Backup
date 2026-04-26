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
use App\Form\AssignmentsGrades\AccessTokenType;
use App\Service\ApiProxy;

/**
 * Controller that handles stuff relating to the submit canvas token page
 */
final class CanvasTokenController extends AbstractController
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

    /**
     * Route that only exists to remove the canvas token from the user
     */
    #[Route('/tool/assignmentsgrades/remove_token', name: 'app_assignments_grades_remove_token')]
    public function unregister_token(
        Request $request
    ) {
        $session = $request->getSession();
        $session->remove('canvas_token');
        return $this->redirectToRoute('app_assignments_grades');
    }

}
