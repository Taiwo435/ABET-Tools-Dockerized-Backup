<?php

namespace App\Controller\SyllabusTemplate;

use App\Entity\SyllabusTemplate\CommonCourse;
use App\Entity\SyllabusTemplate\CourseOffering;
use App\Entity\SyllabusTemplate\ProposalOrigin;
use App\Entity\SyllabusTemplate\SubmissionKind;
use App\Entity\SyllabusTemplate\SubmissionStatus;
use App\Entity\SyllabusTemplate\TemplateSubmission;
use App\Entity\User;
use App\Form\Model\CoordinatorTemplateData;
use App\Form\SyllabusTemplate\CoordinatorTemplateType;
use App\Repository\SyllabusTemplate\TemplateSubmissionRepository;
use App\Service\SyllabusTemplate\SyllabusPrefillService;
use App\Service\SyllabusTemplate\SyllabusRevisionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormError;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class FacultySyllabusTemplateController extends AbstractController
{
    public function __construct(
        private readonly SyllabusPrefillService $prefill,
        private readonly SyllabusRevisionService $revisions,
    ) {
    }

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
            'proposals' => $submissions->findBy([
                'submittedBy' => $user,
                'origin' => ProposalOrigin::FacultySubmission,
                'status' => [
                    SubmissionStatus::Submitted,
                    SubmissionStatus::Approved,
                    SubmissionStatus::ApprovedWithEdits,
                    SubmissionStatus::Denied,
                ],
            ], ['updatedAt' => 'DESC']),
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/syllabus-templates/new', name: 'app_faculty_syllabus_templates_new', methods: ['GET', 'POST'], priority: 10)]
    public function createBlank(
        Request $request,
        #[CurrentUser] User $user,
        EntityManagerInterface $entityManager,
    ): Response
    {
        $data = new CoordinatorTemplateData();
        $form = $this->createForm(CoordinatorTemplateType::class, $data, [
            'include_course_identity' => true,
            'include_offering_identity' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($data->program === null) {
                throw new \LogicException('A program is required for a faculty syllabus draft.');
            }

            if ($this->courseIdentityExists($entityManager, $data)) {
                $form->addError(new FormError('This common course already exists. Use its available shared template instead.'));
            } else {
                $course = new CommonCourse(
                    $data->program,
                    $data->courseSubject,
                    $data->courseNumber,
                    $data->courseName,
                    $data->deliveryType,
                );
                $offering = new CourseOffering(
                    $course,
                    $data->academicYear,
                    $data->term,
                    $data->deliveryType,
                    $user,
                    $data->section,
                );
                $submission = TemplateSubmission::forFacultyOffering($offering, $user);
                $this->revisions->addFacultyRevision($submission, $user, $data);

                $entityManager->persist($course);
                $entityManager->persist($offering);
                $entityManager->persist($submission);
                $entityManager->flush();

                $this->addFlash('success', 'Your blank faculty syllabus draft was created.');

                return $this->redirectToRoute('app_faculty_syllabus_templates_edit', ['id' => $submission->getId()]);
            }
        }

        return $this->render('syllabus_template/faculty/form.html.twig', [
            'form' => $form,
            'submission' => null,
            'course' => null,
            'basedOnRevision' => null,
            'pageTitle' => 'Create Blank Faculty Draft',
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
        $approvedRevision = $course->getCurrentApprovedRevision();
        if ($approvedRevision === null) {
            throw $this->createNotFoundException('An approved shared template was not found for this course.');
        }

        $data = $this->prefill->fromRevision($approvedRevision, $course);
        $form = $this->createForm(CoordinatorTemplateType::class, $data, [
            'include_offering_identity' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->offeringIdentityExists($entityManager, $course, $data)) {
                $form->addError(new FormError('This course offering already exists. Use a different academic year, term, or section.'));
            } else {
                $offering = new CourseOffering(
                    $course,
                    $data->academicYear,
                    $data->term,
                    $data->deliveryType,
                    $user,
                    $data->section,
                );
                $submission = TemplateSubmission::forFacultyOffering($offering, $user, $approvedRevision);
                $this->revisions->addFacultyRevision($submission, $user, $data);
                $entityManager->persist($offering);
                $entityManager->persist($submission);
                $entityManager->flush();

                $this->addFlash('success', 'Your course-offering syllabus draft was created.');

                return $this->redirectToRoute('app_faculty_syllabus_templates_edit', ['id' => $submission->getId()]);
            }
        }

        return $this->render('syllabus_template/faculty/form.html.twig', [
            'form' => $form,
            'submission' => null,
            'course' => $course,
            'basedOnRevision' => $approvedRevision,
            'pageTitle' => sprintf('Use %s %s Template', $course->getCourseSubject(), $course->getCourseNumber()),
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

        $originalData = $this->prefill->fromSubmission($submission);
        $data = $this->prefill->fromSubmission($submission);
        $isBlankDraft = $submission->getBasedOnRevision() === null;
        $isOfferingDraft = $submission->getKind() === SubmissionKind::FacultyOffering;
        $form = $this->createForm(CoordinatorTemplateType::class, $data, [
            'include_course_identity' => $isBlankDraft,
            'include_offering_identity' => $isOfferingDraft,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($data->isEquivalentTo($originalData)) {
                $this->addFlash('info', 'No changes were detected.');
            } else {
                if ($isBlankDraft && $data->program === null) {
                    throw new \LogicException('A program is required for a blank faculty syllabus draft.');
                }

                if ($isBlankDraft && $this->courseIdentityExists($entityManager, $data, $submission->getCommonCourse())) {
                    $form->addError(new FormError('This common course already exists. Choose a different identity or use its shared template.'));
                } elseif ($isOfferingDraft && $this->offeringIdentityExists(
                    $entityManager,
                    $submission->getCommonCourse(),
                    $data,
                    $submission->getCourseOffering(),
                )) {
                    $form->addError(new FormError('Another offering already uses this academic year, term, and section.'));
                } else {
                    $this->revisions->addFacultyRevision(
                        $submission,
                        $user,
                        $data,
                        updateBlankCourseIdentity: $isBlankDraft,
                        updateOfferingIdentity: $isOfferingDraft,
                    );
                    $entityManager->flush();
                    $this->addFlash('success', 'Your faculty working copy was saved.');

                    return $this->redirectToRoute('app_faculty_syllabus_templates_edit', ['id' => $submission->getId()]);
                }
            }

            if (!$form->getErrors(true)->count()) {
                return $this->redirectToRoute('app_faculty_syllabus_templates_edit', ['id' => $submission->getId()]);
            }
        }

        return $this->render('syllabus_template/faculty/form.html.twig', [
            'form' => $form,
            'submission' => $submission,
            'course' => $submission->getCommonCourse(),
            'basedOnRevision' => $submission->getBasedOnRevision(),
            'pageTitle' => sprintf('Edit %s %s Faculty Draft', $submission->getCommonCourse()->getCourseSubject(), $submission->getCommonCourse()->getCourseNumber()),
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/syllabus-templates/drafts/{id}/submit', name: 'app_faculty_syllabus_templates_submit', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function submit(
        TemplateSubmission $submission,
        Request $request,
        #[CurrentUser] User $user,
        EntityManagerInterface $entityManager,
    ): Response
    {
        $this->assertFacultyDraftOwner($submission, $user);

        if (!$this->isCsrfTokenValid('submit-faculty-syllabus-'.$submission->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid submission token.');
        }

        $revision = $submission->getWorkingRevision();
        if ($revision === null || !$revision->isFacultySubmittable()) {
            $this->addFlash('error', 'Complete all required fields before submitting this draft for approval.');

            return $this->redirectToRoute('app_faculty_syllabus_templates_edit', ['id' => $submission->getId()]);
        }

        $submission->submit($revision);
        $entityManager->flush();
        $this->addFlash('success', 'Your syllabus proposal was submitted for approval.');

        return $this->redirectToRoute('app_faculty_syllabus_templates');
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
        $course = $submission->getCommonCourse();
        $offering = $submission->getCourseOffering();
        $deleteBlankCourse = $submission->getBasedOnRevision() === null
            && $course->getCurrentApprovedRevision() === null;

        if (!$this->isCsrfTokenValid('delete-faculty-syllabus-'.$submission->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid draft deletion token.');
        }

        $submission->prepareFacultyDraftDeletion($user);
        $entityManager->flush();
        $entityManager->remove($submission);
        $entityManager->flush();
        if ($offering !== null) {
            $entityManager->remove($offering);
            $entityManager->flush();
        }
        if ($deleteBlankCourse) {
            $entityManager->remove($course);
            $entityManager->flush();
        }

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

    private function offeringIdentityExists(
        EntityManagerInterface $entityManager,
        CommonCourse $course,
        CoordinatorTemplateData $data,
        ?CourseOffering $currentOffering = null,
    ): bool {
        $existing = $entityManager->getRepository(CourseOffering::class)->findOneBy([
            'commonCourse' => $course,
            'academicYear' => trim($data->academicYear),
            'term' => trim($data->term),
            'section' => trim($data->section),
        ]);

        return $existing !== null && $existing !== $currentOffering;
    }
}
