<?php

namespace App\Controller\SyllabusTemplate;

use App\Entity\SyllabusTemplate\CommonCourse;
use App\Entity\SyllabusTemplate\ProposalOrigin;
use App\Entity\SyllabusTemplate\RevisionAuthorType;
use App\Entity\SyllabusTemplate\SubmissionStatus;
use App\Entity\SyllabusTemplate\TemplateSubmission;
use App\Entity\User;
use App\Form\Model\CoordinatorTemplateData;
use App\Form\SyllabusTemplate\CoordinatorTemplateType;
use App\Repository\SyllabusTemplate\TemplateSubmissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class FacultySyllabusTemplateController extends AbstractController
{
    #[IsGranted('ROLE_USER')]
    #[Route('/syllabus-templates', name: 'app_faculty_syllabus_templates', methods: ['GET'])]
    public function index(
        #[CurrentUser] User $user,
        EntityManagerInterface $entityManager,
        TemplateSubmissionRepository $submissions,
    ): Response
    {
        $courses = $entityManager->getRepository(CommonCourse::class)
            ->createQueryBuilder('course')
            ->addSelect('program', 'approvedRevision')
            ->innerJoin('course.program', 'program')
            ->innerJoin('course.currentApprovedRevision', 'approvedRevision')
            ->orderBy('course.courseSubject', 'ASC')
            ->addOrderBy('course.courseNumber', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('syllabus_template/faculty/index.html.twig', [
            'courses' => $courses,
            'drafts' => $submissions->findBy([
                'submittedBy' => $user,
                'origin' => ProposalOrigin::FacultySubmission,
                'status' => SubmissionStatus::Draft,
            ], ['updatedAt' => 'DESC']),
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/syllabus-templates/{id}/use', name: 'app_faculty_syllabus_templates_use', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function useTemplate(
        CommonCourse $course,
        Request $request,
        #[CurrentUser] User $user,
        TemplateSubmissionRepository $submissions,
        EntityManagerInterface $entityManager,
    ): Response
    {
        $existingDraft = $submissions->findOneBy([
            'commonCourse' => $course,
            'submittedBy' => $user,
            'origin' => ProposalOrigin::FacultySubmission,
            'status' => SubmissionStatus::Draft,
        ]);
        if ($existingDraft !== null) {
            $this->addFlash('info', 'Your existing draft for this course was opened.');

            return $this->redirectToRoute('app_faculty_syllabus_templates_edit', ['id' => $existingDraft->getId()]);
        }

        $approvedRevision = $course->getCurrentApprovedRevision();
        if ($approvedRevision === null) {
            throw $this->createNotFoundException('An approved shared template was not found for this course.');
        }

        $data = CoordinatorTemplateData::fromRevision($approvedRevision);
        $form = $this->createForm(CoordinatorTemplateType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $submission = new TemplateSubmission($course, $user, ProposalOrigin::FacultySubmission, $approvedRevision);
            $submission->addRevision($user, RevisionAuthorType::Faculty, $data->toContent());
            $entityManager->persist($submission);
            $entityManager->flush();

            $this->addFlash('success', 'Your independent faculty draft was created.');

            return $this->redirectToRoute('app_faculty_syllabus_templates_edit', ['id' => $submission->getId()]);
        }

        return $this->render('syllabus_template/faculty/form.html.twig', [
            'form' => $form,
            'submission' => null,
            'course' => $course,
            'basedOnRevision' => $approvedRevision,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/syllabus-templates/drafts/{id}/edit', name: 'app_faculty_syllabus_templates_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(
        TemplateSubmission $submission,
        Request $request,
        #[CurrentUser] User $user,
        EntityManagerInterface $entityManager,
    ): Response
    {
        $this->assertFacultyDraftOwner($submission, $user);

        $workingRevision = $submission->getWorkingRevision();
        if ($workingRevision === null) {
            throw $this->createNotFoundException('A faculty working revision was not found.');
        }

        $originalData = CoordinatorTemplateData::fromRevision($workingRevision);
        $data = CoordinatorTemplateData::fromRevision($workingRevision);
        $form = $this->createForm(CoordinatorTemplateType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($data->isEquivalentTo($originalData)) {
                $this->addFlash('info', 'No changes were detected.');
            } else {
                $submission->addRevision($user, RevisionAuthorType::Faculty, $data->toContent());
                $entityManager->flush();
                $this->addFlash('success', 'Your faculty working copy was saved.');
            }

            return $this->redirectToRoute('app_faculty_syllabus_templates_edit', ['id' => $submission->getId()]);
        }

        return $this->render('syllabus_template/faculty/form.html.twig', [
            'form' => $form,
            'submission' => $submission,
            'course' => $submission->getCommonCourse(),
            'basedOnRevision' => $submission->getBasedOnRevision(),
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/syllabus-templates/drafts/{id}/delete', name: 'app_faculty_syllabus_templates_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(
        TemplateSubmission $submission,
        Request $request,
        #[CurrentUser] User $user,
        EntityManagerInterface $entityManager,
    ): Response
    {
        $this->assertFacultyDraftOwner($submission, $user);

        if (!$this->isCsrfTokenValid('delete-faculty-syllabus-'.$submission->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid draft deletion token.');
        }

        $submission->prepareFacultyDraftDeletion($user);
        $entityManager->flush();
        $entityManager->remove($submission);
        $entityManager->flush();

        $this->addFlash('success', 'Your faculty syllabus draft was deleted.');

        return $this->redirectToRoute('app_faculty_syllabus_templates');
    }

    private function assertFacultyDraftOwner(TemplateSubmission $submission, User $user): void
    {
        if ($submission->getOrigin() !== ProposalOrigin::FacultySubmission
            || $submission->getStatus() !== SubmissionStatus::Draft
            || $submission->getSubmittedBy() !== $user) {
            throw $this->createNotFoundException('An editable faculty syllabus draft was not found.');
        }
    }
}
