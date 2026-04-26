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
 * Controller that deals with the form that starts a new extraction
 */
final class NewExtractionController extends AbstractController
{

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/tool/assignmentsgrades/new_extraction', name: 'app_assignments_grades_new_extraction')]
    public function course_select(
        #[CurrentUser] User $user, 
        ApiProxy $proxy,
        Request $request) {

        /**
         * This creates a form using these docs:
         * @see https://symfony.com/doc/current/forms.html
         */
        // Initialize a AccessTokenForm that encapsulates the data returned
        $task = new NewExtractionForm();
        $task->setTerm('');
        $task->setDegree('');

        // Use the form builder that we defined
        $form = $this->createForm(NewExtractionType::class, $task);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // $form->getData() holds the submitted values
            // but, the original `$task` variable has also been updated
            $task = $form->getData();
        }

        return $this->render('tools/assignments_grades/new_extraction.html.twig', [
            'form' => $form
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
