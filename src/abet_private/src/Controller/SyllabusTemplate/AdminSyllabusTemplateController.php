<?php

namespace App\Controller\SyllabusTemplate;

use App\Entity\Program;
use App\Entity\SyllabusTemplate\CompletenessStatus;
use App\Entity\SyllabusTemplate\CommonCourse;
use App\Entity\SyllabusTemplate\ProposalOrigin;
use App\Entity\SyllabusTemplate\ReviewDecision;
use App\Entity\SyllabusTemplate\RevisionAuthorType;
use App\Entity\SyllabusTemplate\SubmissionStatus;
use App\Entity\SyllabusTemplate\TemplateSubmission;
use App\Entity\SyllabusTemplate\TemplateReview;
use App\Entity\User;
use App\Form\Model\CoordinatorTemplateData;
use App\Form\SyllabusTemplate\CoordinatorTemplateType;
use App\Repository\SyllabusTemplate\TemplateSubmissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormError;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AdminSyllabusTemplateController extends AbstractController
{
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/syllabus-templates', name: 'app_admin_syllabus_templates', methods: ['GET'])]
    public function index(Request $request, TemplateSubmissionRepository $submissions): Response
    {
        $filter = CompletenessStatus::tryFrom($request->query->getString('completeness'));

        return $this->render('syllabus_template/admin/index.html.twig', [
            'templates' => $submissions->findManagedTemplates($filter),
            'completenessFilter' => $filter?->value ?? '',
            'pendingReviewCount' => $submissions->countPendingFacultyReviews(),
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/syllabus-template-reviews', name: 'app_admin_syllabus_template_reviews', methods: ['GET'])]
    public function reviewQueue(
        Request $request,
        TemplateSubmissionRepository $submissions,
        EntityManagerInterface $entityManager,
    ): Response
    {
        $programId = $request->query->getInt('program');
        $selectedProgram = $programId > 0
            ? $entityManager->getRepository(Program::class)->find($programId)
            : null;

        return $this->render('syllabus_template/admin/review_queue.html.twig', [
            'pendingSubmissions' => $submissions->findPendingFacultyReviews($selectedProgram),
            'pendingReviewCount' => $submissions->countPendingFacultyReviews($selectedProgram),
            'programs' => $submissions->findPendingFacultyReviewPrograms(),
            'selectedProgram' => $selectedProgram,
            'selectedProgramId' => $selectedProgram?->getId() ?? 0,
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/syllabus-template-reviews/{id}', name: 'app_admin_syllabus_template_review', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function reviewDetail(int $id, TemplateSubmissionRepository $submissions): Response
    {
        $submission = $submissions->findPendingFacultyReview($id);
        if ($submission === null) {
            throw $this->createNotFoundException('A pending faculty syllabus submission was not found.');
        }

        return $this->render('syllabus_template/admin/review_detail.html.twig', [
            'submission' => $submission,
            'sharedTemplateChanged' => $submission->hasSharedTemplateChanged(),
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/syllabus-template-reviews/{id}/approve-unchanged', name: 'app_admin_syllabus_template_review_approve', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function approveUnchanged(
        int $id,
        Request $request,
        #[CurrentUser] User $user,
        TemplateSubmissionRepository $submissions,
        EntityManagerInterface $entityManager,
    ): Response
    {
        $submission = $submissions->findPendingFacultyReview($id);
        if ($submission === null) {
            throw $this->createNotFoundException('A pending faculty syllabus submission was not found.');
        }

        if (!$this->isCsrfTokenValid('approve-syllabus-submission-'.$submission->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid syllabus approval token.');
        }

        if ($submission->hasSharedTemplateChanged()) {
            $this->addFlash('error', 'This proposal is based on an older shared template and must be reconciled before approval.');

            return $this->redirectToRoute('app_admin_syllabus_template_review', ['id' => $submission->getId()]);
        }

        $submittedRevision = $submission->getSubmittedRevision();
        if ($submittedRevision === null) {
            throw new \LogicException('A pending faculty submission must have a frozen submitted revision.');
        }

        $review = new TemplateReview($submission, $user, ReviewDecision::Approved);
        $submission->recordReview($review, $submittedRevision);
        $entityManager->persist($review);
        $entityManager->flush();

        $this->addFlash('success', sprintf(
            '%s %s was approved unchanged and published as the shared template.',
            $submission->getCommonCourse()->getCourseSubject(),
            $submission->getCommonCourse()->getCourseNumber(),
        ));

        return $this->redirectToRoute('app_admin_syllabus_template_reviews');
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/syllabus-templates/new', name: 'app_admin_syllabus_templates_new', methods: ['GET', 'POST'])]
    public function create(Request $request, #[CurrentUser] User $user, EntityManagerInterface $entityManager): Response
    {
        $data = new CoordinatorTemplateData();
        $form = $this->createForm(CoordinatorTemplateType::class, $data, ['include_course_identity' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($data->program === null) {
                throw new \LogicException('A program is required for a shared syllabus template.');
            }

            if ($this->courseIdentityExists($entityManager, $data)) {
                $form->addError(new FormError('A shared template already exists for this program, course, and delivery type.'));
            } else {
                $course = new CommonCourse(
                    $data->program,
                    $data->courseSubject,
                    $data->courseNumber,
                    $data->courseName,
                    $data->deliveryType,
                );
                $submission = new TemplateSubmission($course, $user, ProposalOrigin::CoordinatorCreated);
                $submission->addRevision($user, RevisionAuthorType::Coordinator, $data->toContent());

                $entityManager->persist($course);
                $entityManager->persist($submission);
                $entityManager->flush();

                $this->addFlash('success', 'Shared syllabus template draft created.');

                return $this->redirectToRoute('app_admin_syllabus_templates_edit', ['id' => $submission->getId()]);
            }
        }

        return $this->render('syllabus_template/admin/form.html.twig', [
            'form' => $form,
            'submission' => null,
            'pageTitle' => 'Create Shared Syllabus Template',
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/syllabus-templates/{id}/edit', name: 'app_admin_syllabus_templates_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(
        TemplateSubmission $submission,
        Request $request,
        #[CurrentUser] User $user,
        EntityManagerInterface $entityManager,
    ): Response
    {
        $this->assertAdminTemplateEditable($submission);
        $originalData = CoordinatorTemplateData::fromSubmission($submission);
        $data = CoordinatorTemplateData::fromSubmission($submission);
        $form = $this->createForm(CoordinatorTemplateType::class, $data, ['include_course_identity' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($data->program === null) {
                throw new \LogicException('A program is required for a shared syllabus template.');
            }

            if ($data->isEquivalentTo($originalData)) {
                $this->addFlash('info', 'No changes were detected, so no new revision was created.');

                return $this->redirectToRoute('app_admin_syllabus_templates_edit', ['id' => $submission->getId()]);
            }

            if ($this->courseIdentityExists($entityManager, $data, $submission->getCommonCourse())) {
                $form->addError(new FormError('A shared template already exists for this program, course, and delivery type.'));
            } else {
                $editableSubmission = $submission;
                if ($submission->getOrigin() === ProposalOrigin::FacultySubmission) {
                    $editableSubmission = $submission->createCoordinatorRevisionDraft($user, $data->toContent());
                    $entityManager->persist($editableSubmission);
                } elseif ($submission->getStatus() === SubmissionStatus::Approved) {
                    $submission->beginCoordinatorRevision($user, $data->toContent());
                } else {
                    $submission->addRevision($user, RevisionAuthorType::Coordinator, $data->toContent());
                }
                $editableSubmission->getCommonCourse()->updateDraftDetails(
                    $editableSubmission,
                    $data->program,
                    $data->courseSubject,
                    $data->courseNumber,
                    $data->courseName,
                    $data->deliveryType,
                );
                $entityManager->flush();

                $this->addFlash('success', 'Course details and a new immutable draft revision were saved.');

                return $this->redirectToRoute('app_admin_syllabus_templates_edit', ['id' => $editableSubmission->getId()]);
            }
        }

        return $this->render('syllabus_template/admin/form.html.twig', [
            'form' => $form,
            'submission' => $submission,
            'pageTitle' => sprintf('Edit %s %s', $submission->getCommonCourse()->getCourseSubject(), $submission->getCommonCourse()->getCourseNumber()),
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/syllabus-templates/{id}/publish', name: 'app_admin_syllabus_templates_publish', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function publish(TemplateSubmission $submission, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->assertEditableCoordinatorDraft($submission);

        if (!$this->isCsrfTokenValid('publish-syllabus-template-'.$submission->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid publish token.');
        }

        $revision = $submission->getWorkingRevision();
        if ($revision === null || !$revision->isComplete()) {
            $this->addFlash('error', 'Complete all required fields before publishing this template.');

            return $this->redirectToRoute('app_admin_syllabus_templates_edit', ['id' => $submission->getId()]);
        }

        $submission->publishCoordinatorTemplate($revision);
        $entityManager->flush();
        $this->addFlash('success', 'Shared syllabus template published.');

        return $this->redirectToRoute('app_admin_syllabus_templates');
    }

    private function assertAdminTemplateEditable(TemplateSubmission $submission): void
    {
        $isCoordinatorTemplate = $submission->getOrigin() === ProposalOrigin::CoordinatorCreated
            && in_array($submission->getStatus(), [SubmissionStatus::Draft, SubmissionStatus::Approved], true);
        $isCurrentApprovedFacultyTemplate = $submission->getOrigin() === ProposalOrigin::FacultySubmission
            && in_array($submission->getStatus(), [SubmissionStatus::Approved, SubmissionStatus::ApprovedWithEdits], true)
            && $submission->getApprovedRevision() !== null
            && $submission->getCommonCourse()->getCurrentApprovedRevision() === $submission->getApprovedRevision();

        if (!$isCoordinatorTemplate && !$isCurrentApprovedFacultyTemplate) {
            throw $this->createNotFoundException('An editable shared template was not found.');
        }
    }

    private function assertEditableCoordinatorDraft(TemplateSubmission $submission): void
    {
        $this->assertAdminTemplateEditable($submission);
        if ($submission->getStatus() !== SubmissionStatus::Draft) {
            throw $this->createNotFoundException('An editable coordinator template draft was not found.');
        }
    }

    private function courseIdentityExists(
        EntityManagerInterface $entityManager,
        CoordinatorTemplateData $data,
        ?CommonCourse $currentCourse = null,
    ): bool
    {
        $existing = $entityManager->getRepository(CommonCourse::class)->findOneBy([
            'program' => $data->program,
            'courseSubject' => strtoupper(trim($data->courseSubject)),
            'courseNumber' => trim($data->courseNumber),
            'deliveryType' => $data->deliveryType,
        ]);

        return $existing !== null && $existing !== $currentCourse;
    }
}
