<?php

namespace App\Controller\SyllabusTemplate;

use App\Entity\Program;
use App\Entity\SyllabusTemplate\CompletenessStatus;
use App\Entity\SyllabusTemplate\CommonCourse;
use App\Entity\SyllabusTemplate\ProposalOrigin;
use App\Entity\SyllabusTemplate\ReviewDecision;
use App\Entity\SyllabusTemplate\SubmissionKind;
use App\Entity\SyllabusTemplate\SubmissionStatus;
use App\Entity\SyllabusTemplate\SyllabusCompletenessPurpose;
use App\Entity\SyllabusTemplate\TemplateContentCompleteness;
use App\Entity\SyllabusTemplate\TemplateSubmission;
use App\Entity\SyllabusTemplate\TemplateReview;
use App\Entity\User;
use App\Form\Model\CoordinatorTemplateData;
use App\Form\SyllabusTemplate\CoordinatorTemplateType;
use App\ReadModel\SyllabusReadiness;
use App\Repository\SyllabusReadinessRepository;
use App\Repository\SyllabusTemplate\TemplateSubmissionRepository;
use App\Service\Report\AppendixAReportExportBoundary;
use App\Service\SyllabusTemplate\SyllabusPrefillService;
use App\Service\SyllabusTemplate\SyllabusRevisionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Form\FormError;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AdminSyllabusTemplateController extends AbstractController
{
    public function __construct(
        private readonly SyllabusPrefillService $prefill,
        private readonly SyllabusRevisionService $revisions,
    ) {
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/syllabus-templates/{id}/appendix-a.json', name: 'app_admin_syllabus_template_appendix_a_export', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function exportAppendixA(
        TemplateSubmission $submission,
        AppendixAReportExportBoundary $exporter,
    ): JsonResponse {
        $revision = $submission->getApprovedRevision();
        if ($revision === null) {
            throw $this->createNotFoundException('An approved syllabus revision was not found.');
        }
        if (!$revision->isAppendixAReady()) {
            return $this->json([
                'error' => 'The approved revision is not Appendix A ready.',
                'blocking_fields' => $revision->getAppendixABlockingFields(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $response = $this->json($exporter->export([$revision])->toArray());
        $response->headers->set(
            'Content-Disposition',
            sprintf(
                'attachment; filename="%s-%s-appendix-a.json"',
                strtolower($submission->getCommonCourse()->getCourseSubject()),
                strtolower($submission->getCommonCourse()->getCourseNumber()),
            ),
        );

        return $response;
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/syllabus-templates', name: 'app_admin_syllabus_templates', methods: ['GET'])]
    public function index(
        Request $request,
        TemplateSubmissionRepository $submissions,
        SyllabusReadinessRepository $readiness,
    ): Response {
        $filter = CompletenessStatus::tryFrom($request->query->getString('completeness'));
        $activeView = match ($request->query->getString('view')) {
            'offerings', 'appendix_a' => $request->query->getString('view'),
            default => 'shared',
        };
        $programs = $readiness->getAllPrograms();
        $requestedProgramId = $request->query->getInt('program');
        $selectedProgramId = $requestedProgramId > 0
            ? $requestedProgramId
            : ($programs[0]['program_id'] ?? null);
        $selectedProgram = $selectedProgramId !== null
            ? $readiness->findProgram($selectedProgramId)
            : null;

        if ($requestedProgramId > 0 && $selectedProgram === null) {
            throw $this->createNotFoundException('The requested program was not found.');
        }

        $readinessRows = $selectedProgram !== null
            ? $readiness->getReadinessRowsForProgram($selectedProgram->getId())
            : [];
        $offeringRows = SyllabusReadinessRepository::filterRows(
            $readinessRows,
            target: 'course_offering',
        );
        $appendixRows = array_values(array_filter(
            $offeringRows,
            static fn (SyllabusReadiness $row): bool => in_array(
                $row->getWorkflowStatus(),
                [SubmissionStatus::Approved, SubmissionStatus::ApprovedWithEdits],
                true,
            ),
        ));
        $pendingSubmissions = $selectedProgram !== null
            ? $submissions->findPendingFacultyReviews($selectedProgram)
            : [];
        $reviewedSubmissions = $selectedProgram !== null
            ? $submissions->findReviewedFacultySubmissions($selectedProgram)
            : [];

        return $this->render('syllabus_template/admin/index.html.twig', [
            'templates' => $selectedProgram !== null
                ? $submissions->findManagedTemplates($filter, $selectedProgram)
                : [],
            'completenessFilter' => $filter?->value ?? '',
            'pendingSubmissions' => $pendingSubmissions,
            'pendingReviewCount' => count($pendingSubmissions),
            'reviewedSubmissions' => $reviewedSubmissions,
            'readinessCounts' => SyllabusReadinessRepository::countRowsByCategory($readinessRows),
            'readinessProgram' => $selectedProgram,
            'readinessPrograms' => $programs,
            'activeView' => $activeView,
            'offeringRows' => $offeringRows,
            'appendixRows' => $appendixRows,
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/syllabus-template-reviews', name: 'app_admin_syllabus_template_reviews', methods: ['GET'])]
    public function reviewQueue(Request $request): Response
    {
        $programId = $request->query->getInt('program');

        return $this->redirectToRoute('app_admin_syllabus_templates', array_filter([
            'program' => $programId > 0 ? $programId : null,
            'view' => 'offerings',
            '_fragment' => 'pending-review',
        ]));
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/syllabus-template-reviews/history', name: 'app_admin_syllabus_template_review_history', methods: ['GET'], priority: 10)]
    public function reviewHistory(Request $request): Response
    {
        $programId = $request->query->getInt('program');

        return $this->redirectToRoute('app_admin_syllabus_templates', array_filter([
            'program' => $programId > 0 ? $programId : null,
            'view' => 'offerings',
            '_fragment' => 'review-history',
        ]));
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/syllabus-template-reviews/{id}', name: 'app_admin_syllabus_template_review', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function reviewDetail(int $id, TemplateSubmissionRepository $submissions): Response
    {
        $submission = $submissions->findFacultyReview($id);
        if ($submission === null) {
            throw $this->createNotFoundException('A faculty syllabus submission was not found.');
        }

        return $this->render('syllabus_template/admin/review_detail.html.twig', [
            'submission' => $submission,
            'sharedTemplateChanged' => $submission->hasSharedTemplateChanged(),
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/syllabus-template-reviews/{id}/deny', name: 'app_admin_syllabus_template_review_deny', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function deny(
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

        if (!$this->isCsrfTokenValid('deny-syllabus-submission-'.$submission->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid syllabus denial token.');
        }

        $feedback = trim($request->request->getString('feedback'));
        if ($feedback === '') {
            return $this->render('syllabus_template/admin/review_detail.html.twig', [
                'submission' => $submission,
                'sharedTemplateChanged' => $submission->hasSharedTemplateChanged(),
                'denialFeedback' => $request->request->getString('feedback'),
                'denialError' => 'Coordinator feedback is required to deny this submission.',
            ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        $review = new TemplateReview($submission, $user, ReviewDecision::Denied, $feedback);
        $submission->recordDenial($review);
        $entityManager->persist($review);
        $entityManager->flush();

        $this->addFlash('success', sprintf(
            '%s %s was denied and the faculty member can now see the review feedback.',
            $submission->getCommonCourse()->getCourseSubject(),
            $submission->getCommonCourse()->getCourseNumber(),
        ));

        return $this->redirectToRoute(
            'app_admin_syllabus_templates',
            $this->workspaceParameters($submission, 'offerings', 'review-history'),
        );
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/syllabus-template-reviews/{id}/edit', name: 'app_admin_syllabus_template_review_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function approveWithEdits(
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

        $submittedRevision = $submission->getSubmittedRevision();
        if ($submittedRevision === null) {
            throw new \LogicException('A pending faculty submission must have a frozen submitted revision.');
        }

        $originalData = $this->prefill->fromSubmission($submission);
        $data = $this->prefill->fromSubmission($submission);
        $isSharedTemplateSubmission = $submission->getKind() === SubmissionKind::SharedTemplate;
        $form = $this->createForm(CoordinatorTemplateType::class, $data, [
            'include_course_identity' => $isSharedTemplateSubmission,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->isCsrfTokenValid('approve-with-edits-syllabus-submission-'.$submission->getId(), $request->request->getString('_decision_token'))) {
                throw $this->createAccessDeniedException('Invalid syllabus approval token.');
            }

            if ($submission->hasSharedTemplateChanged()) {
                $form->addError(new FormError('This proposal is based on an older shared template and must be reconciled before approval.'));
            } elseif ($data->isEquivalentTo($originalData)) {
                $form->addError(new FormError('Make at least one meaningful change before approving with edits.'));
            } elseif ($isSharedTemplateSubmission && $data->program === null) {
                $form->addError(new FormError('A program is required before approving with edits.'));
            } elseif ($isSharedTemplateSubmission && $this->courseIdentityExists($entityManager, $data, $submission->getCommonCourse())) {
                $form->addError(new FormError('Another shared template already uses this program, course, and delivery type.'));
            } else {
                $content = $data->toContent();
                $approvalPurpose = $isSharedTemplateSubmission
                    ? SyllabusCompletenessPurpose::CoordinatorPublishable
                    : SyllabusCompletenessPurpose::FacultySubmittable;
                $completeness = TemplateContentCompleteness::assess($content, $approvalPurpose);
                if ($completeness['status'] !== CompletenessStatus::Complete) {
                    $form->addError(new FormError('Complete all required fields before approving with edits.'));
                } else {
                    $courseDetailsChanged = $isSharedTemplateSubmission
                        && !$data->hasSameCourseIdentityAs($originalData);
                    $coordinatorRevision = $this->revisions->addCoordinatorRevision($submission, $user, $data);
                    if ($courseDetailsChanged) {
                        $submission->getCommonCourse()->updateDuringFacultyReview(
                            $submission,
                            $data->program,
                            $data->courseSubject,
                            $data->courseNumber,
                            $data->courseName,
                            $data->deliveryType,
                        );
                    }
                    $review = new TemplateReview($submission, $user, ReviewDecision::ApprovedWithEdits);
                    $submission->recordReview($review, $coordinatorRevision, courseDetailsChanged: $courseDetailsChanged);
                    $entityManager->persist($review);
                    $entityManager->flush();

                    $this->addFlash('success', sprintf(
                        '%s %s was approved with coordinator edits and published to %s.',
                        $submission->getCommonCourse()->getCourseSubject(),
                        $submission->getCommonCourse()->getCourseNumber(),
                        $this->approvalTargetLabel($submission),
                    ));

                    return $this->redirectToRoute(
                        'app_admin_syllabus_templates',
                        $this->workspaceParameters($submission, 'offerings', 'review-history'),
                    );
                }
            }
        }

        return $this->render('syllabus_template/admin/review_edit.html.twig', [
            'form' => $form,
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
            '%s %s submission was approved and published to %s.',
            $submission->getCommonCourse()->getCourseSubject(),
            $submission->getCommonCourse()->getCourseNumber(),
            $this->approvalTargetLabel($submission),
        ));

        return $this->redirectToRoute(
            'app_admin_syllabus_templates',
            $this->workspaceParameters($submission, 'offerings', 'review-history'),
        );
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/syllabus-templates/new', name: 'app_admin_syllabus_templates_new', methods: ['GET', 'POST'])]
    public function create(Request $request, #[CurrentUser] User $user, EntityManagerInterface $entityManager): Response
    {
        $data = new CoordinatorTemplateData();
        $requestedProgramId = $request->query->getInt('program');
        if ($requestedProgramId > 0) {
            $program = $entityManager->getRepository(Program::class)->find($requestedProgramId);
            if ($program instanceof Program) {
                $data->program = $program;
            }
        }
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
                $this->revisions->addCoordinatorRevision($submission, $user, $data);

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
            'workspaceProgramId' => $data->program?->getId() ?? $requestedProgramId,
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
        $originalData = $this->prefill->fromSubmission($submission);
        $data = $this->prefill->fromSubmission($submission);
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
                $editableSubmission = $this->revisions->saveCoordinatorRevision($submission, $user, $data);
                if ($editableSubmission !== $submission) {
                    $entityManager->persist($editableSubmission);
                }
                $entityManager->flush();

                $this->addFlash('success', 'Course details and a new immutable draft revision were saved.');

                return $this->redirectToRoute('app_admin_syllabus_templates_edit', ['id' => $editableSubmission->getId()]);
            }
        }

        return $this->render('syllabus_template/admin/form.html.twig', [
            'form' => $form,
            'submission' => $submission,
            'pageTitle' => sprintf('Edit %s %s', $submission->getCommonCourse()->getCourseSubject(), $submission->getCommonCourse()->getCourseNumber()),
            'workspaceProgramId' => $submission->getCommonCourse()->getProgram()->getId(),
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
        if ($revision === null || !$revision->isCoordinatorPublishable()) {
            $this->addFlash('error', 'Complete all required fields before publishing this template.');

            return $this->redirectToRoute('app_admin_syllabus_templates_edit', ['id' => $submission->getId()]);
        }

        $submission->publishCoordinatorTemplate($revision);
        $entityManager->flush();
        $this->addFlash('success', 'Shared syllabus template published.');

        return $this->redirectToRoute(
            'app_admin_syllabus_templates',
            $this->workspaceParameters($submission, 'shared'),
        );
    }

    private function assertAdminTemplateEditable(TemplateSubmission $submission): void
    {
        $isCoordinatorTemplate = $submission->getOrigin() === ProposalOrigin::CoordinatorCreated
            && $submission->getKind() === SubmissionKind::SharedTemplate
            && in_array($submission->getStatus(), [SubmissionStatus::Draft, SubmissionStatus::Approved], true);
        $isCurrentApprovedFacultyTemplate = $submission->getOrigin() === ProposalOrigin::FacultySubmission
            && $submission->getKind() === SubmissionKind::SharedTemplate
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

    private function approvalTargetLabel(TemplateSubmission $submission): string
    {
        return $submission->getKind() === SubmissionKind::FacultyOffering
            ? 'the course offering'
            : 'the shared template';
    }

    /**
     * @return array{program: int|null, view: string, _fragment?: string}
     */
    private function workspaceParameters(
        TemplateSubmission $submission,
        string $view,
        ?string $fragment = null,
    ): array {
        $parameters = [
            'program' => $submission->getCommonCourse()->getProgram()->getId(),
            'view' => $view,
        ];
        if ($fragment !== null) {
            $parameters['_fragment'] = $fragment;
        }

        return $parameters;
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
