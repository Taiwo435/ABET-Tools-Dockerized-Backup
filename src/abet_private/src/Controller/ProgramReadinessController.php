<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Permissions;
use App\Entity\SyllabusTemplate\SubmissionStatus;
use App\Entity\User;
use App\Repository\SyllabusReadinessRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ProgramReadinessController extends AbstractController
{
    public function __construct(private readonly SyllabusReadinessRepository $readinessRepository) {}

    #[Route('/program/readiness', name: 'app_program_readiness_select', methods: ['GET'])]
    public function selectProgram(Request $request, #[CurrentUser] User $user): Response
    {
        $this->assertCoordinatorAccess($user);

        $requestedProgramId = $request->query->getInt('program');
        if ($requestedProgramId > 0) {
            if ($this->readinessRepository->findProgram($requestedProgramId) === null) {
                throw $this->createNotFoundException('The requested program was not found.');
            }

            return $this->redirectToRoute('app_program_readiness', ['programId' => $requestedProgramId]);
        }

        $programs = $this->readinessRepository->getAllPrograms();
        $firstProgramId = $programs[0]['program_id'] ?? null;
        if (!is_int($firstProgramId)) {
            throw $this->createNotFoundException('No programs are available for syllabus readiness.');
        }

        return $this->redirectToRoute('app_program_readiness', ['programId' => $firstProgramId]);
    }

    #[Route(
        '/program/{programId}/readiness',
        name: 'app_program_readiness',
        requirements: ['programId' => '\d+'],
        methods: ['GET'],
    )]
    public function index(
        int $programId,
        Request $request,
        #[CurrentUser] User $user,
    ): Response {
        $this->assertCoordinatorAccess($user);

        $program = $this->readinessRepository->findProgram($programId);
        if ($program === null) {
            throw $this->createNotFoundException('The requested program was not found.');
        }

        $allRows = $this->readinessRepository->getReadinessRowsForProgram($programId);
        $category = $this->normalizeCategory(
            $request->query->getString('category', $request->query->getString('filter')),
            ['Ready', 'Blocked', 'Awaiting review', 'Missing'],
        );
        $target = match ($request->query->getString('target')) {
            'shared_template', 'course_offering' => $request->query->getString('target'),
            default => null,
        };
        $workflow = SubmissionStatus::tryFrom($request->query->getString('workflow'));
        $facultyReadiness = $this->parseReadiness($request, 'faculty');
        $coordinatorReadiness = $this->parseReadiness($request, 'coordinator');
        $appendixAReadiness = $this->parseReadiness($request, 'appendix_a');
        $filteredRows = SyllabusReadinessRepository::filterRows(
            $allRows,
            $category,
            $target,
            $workflow,
            $facultyReadiness,
            $coordinatorReadiness,
            $appendixAReadiness,
        );
        $programs = $this->readinessRepository->getAllPrograms();

        return $this->render('tools/program_readiness/index.html.twig', [
            'program_id' => $programId,
            'program' => [
                'program_id' => $program->getId(),
                'program_name' => $program->getName(),
                'program_code' => $program->getCode(),
                'program_year' => $program->getYear(),
            ],
            'rows' => $filteredRows,
            'active_filter' => $category,
            'active_filters' => [
                'category' => $category,
                'target' => $target,
                'workflow' => $workflow?->value,
                'faculty' => $this->readinessValue($facultyReadiness),
                'coordinator' => $this->readinessValue($coordinatorReadiness),
                'appendix_a' => $this->readinessValue($appendixAReadiness),
            ],
            'workflow_statuses' => SubmissionStatus::cases(),
            'programs' => $programs,
        ]);
    }

    /** @param list<string> $categories */
    private function normalizeCategory(string $requested, array $categories): ?string
    {
        if ($requested === '') {
            return null;
        }

        foreach ($categories as $category) {
            if (strcasecmp($requested, $category) === 0) {
                return $category;
            }
        }

        return $requested;
    }

    private function parseReadiness(Request $request, string $parameter): ?bool
    {
        return match (strtolower($request->query->getString($parameter))) {
            'ready' => true,
            'blocked', 'not_ready' => false,
            default => null,
        };
    }

    private function readinessValue(?bool $readiness): string
    {
        return match ($readiness) {
            true => 'ready',
            false => 'blocked',
            null => '',
        };
    }

    private function assertCoordinatorAccess(User $user): void
    {
        if (!$user->hasPermission(Permissions::ROLE_COORDINATOR_FORM)) {
            throw $this->createAccessDeniedException('Access Denied.');
        }
    }
}
