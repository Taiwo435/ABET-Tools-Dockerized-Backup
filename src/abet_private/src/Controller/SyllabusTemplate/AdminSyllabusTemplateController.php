<?php

namespace App\Controller\SyllabusTemplate;

use App\Entity\SyllabusTemplate\CompletenessStatus;
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

final class AdminSyllabusTemplateController extends AbstractController
{
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/syllabus-templates', name: 'app_admin_syllabus_templates', methods: ['GET'])]
    public function index(Request $request, TemplateSubmissionRepository $submissions): Response
    {
        $filter = CompletenessStatus::tryFrom($request->query->getString('completeness'));

        return $this->render('syllabus_template/admin/index.html.twig', [
            'templates' => $submissions->findCoordinatorTemplates($filter),
            'completenessFilter' => $filter?->value ?? '',
        ]);
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
        $this->assertEditableCoordinatorDraft($submission);
        $workingRevision = $submission->getWorkingRevision();
        $data = $workingRevision === null
            ? new CoordinatorTemplateData()
            : CoordinatorTemplateData::fromRevision($workingRevision);
        $form = $this->createForm(CoordinatorTemplateType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $submission->addRevision($user, RevisionAuthorType::Coordinator, $data->toContent());
            $entityManager->flush();

            $this->addFlash('success', 'A new immutable draft revision was saved.');

            return $this->redirectToRoute('app_admin_syllabus_templates_edit', ['id' => $submission->getId()]);
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

    private function assertEditableCoordinatorDraft(TemplateSubmission $submission): void
    {
        if ($submission->getOrigin() !== ProposalOrigin::CoordinatorCreated
            || $submission->getStatus() !== SubmissionStatus::Draft) {
            throw $this->createNotFoundException('An editable coordinator template draft was not found.');
        }
    }
}
