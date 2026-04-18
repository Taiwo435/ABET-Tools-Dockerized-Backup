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

final class AssignmentsGradesController extends AbstractController
{

    
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/tool/assignmentsgrades', name: 'app_assignments_grades')]
    public function tool_one(#[CurrentUser] User $user, Request $request) {

        //asurite
        $parts = explode('@', (string)$user->getEmail());
        $asurite = $parts[0] ?? 'user';

        // creates a task object and initializes some data for this example
        $task = new AccessTokenForm();
        $task->setToken('');

        $form = $this->createForm(AccessTokenType::class, $task);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // $form->getData() holds the submitted values
            // but, the original `$task` variable has also been updated
            $task = $form->getData();

            // ... perform some action, such as saving the task to the database

            return $this->redirectToRoute('app_assignments_grades_jobs');
        }

        return $this->render('tools/assignments_grades/index.html.twig', [
            'form' => $form,
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
            'asurite' => $asurite,
        ]);
    }

}
