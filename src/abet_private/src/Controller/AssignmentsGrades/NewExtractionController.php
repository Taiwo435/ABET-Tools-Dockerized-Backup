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
 * Controller that deals with loading and displaying the courses a
 * connected Canvas token has access to.
 *
 * Courses are loaded all at once
 * (no term/semester selection step), identified by their Canvas course
 * ID. Any filtering by that ID happens later, downstream, after data has
 * been imported into the final compiled report shell -- not on this page.
 * The previous Symfony Form (NewExtractionType/NewExtractionForm) is no
 * longer used here: its only real field (Term) had no choices ever
 * defined, and is no longer needed under the new workflow.
 */
final class NewExtractionController extends AbstractController
{
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/tool/assignmentsgrades/new_extraction', name: 'app_assignments_grades_new_extraction')]
    public function course_select(
        #[CurrentUser] User $user,
        ApiProxy $proxy,
        Request $request
    ) {
        $session = $request->getSession();
        $token = $session->get('canvas_token');

        if (!$token) {
            // Template already handles this case (checks
            // app.session.has('canvas_token')), but without a token there
            // is nothing to fetch, so skip straight to rendering it empty.
            return $this->render('tools/assignments_grades/new_extraction.html.twig', [
                'courses' => [],
            ]);
        }

        $response = $proxy->getAllCourses($token);

        if ($response->getStatusCode() !== 200) {
            return $this->render('tools/assignments_grades/new_extraction.html.twig', [
                'courses' => [],
                'error' => $response->toArray(false)['detail'] ?? 'Failed to load courses from Canvas.',
            ]);
        }

        $courses = $response->toArray(false);

        return $this->render('tools/assignments_grades/new_extraction.html.twig', [
            'courses' => $courses,
        ]);
    }

    #[Route('/tool/assignmentsgrades/testform', name: 'test_form')]
    public function new(Request $request, #[CurrentUser] User $user): Response
    {
        $parts = explode('@', (string) $user->getEmail());
        $asurite = $parts[0] ?? 'user';

        $task = new AccessTokenForm();
        $task->setToken('');

        $form = $this->createForm(AccessTokenType::class, $task, [
            'row_attr' => ['a' => 'b']
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $task = $form->getData();
            return $this->redirectToRoute('app_assignments_grades_jobs');
        }

        return $this->render('tools/assignments_grades/testform.twig', [
            'form' => $form,
        ]);
    }
}
