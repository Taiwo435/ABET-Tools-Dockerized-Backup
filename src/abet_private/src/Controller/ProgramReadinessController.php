<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\Permissions;
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
    private SyllabusReadinessRepository $readinessRepository;

    public function __construct(SyllabusReadinessRepository $readinessRepository)
    {
        $this->readinessRepository = $readinessRepository;
    }

    #[Route('/program/{programId}/readiness', name: 'app_program_readiness', methods: ['GET'])]
    public function index(
        string|int $programId,
        Request $request,
        #[CurrentUser] User $user
    ): Response {
        // Enforce authorization using existing project permission mechanism
        if (!$user->hasPermission(Permissions::ROLE_COORDINATOR_FORM)) {
            throw $this->createAccessDeniedException('Access Denied.');
        }

        // 1. Fetch all readiness rows for the program to compute overall counts
        $allRows = $this->readinessRepository->getReadinessRowsForProgram($programId);

        // 2. Compute summary counts using existing read-model categories
        $counts = [
            'Ready' => 0,
            'Blocked' => 0,
            'Awaiting review' => 0,
            'Missing' => 0,
        ];

        foreach ($allRows as $row) {
            $category = $row->getState()->getCategory();
            if (isset($counts[$category])) {
                $counts[$category]++;
            }
        }

        // 3. Delegate filtering to the repository/query service if requested
        $filter = $request->query->get('filter');
        if ($filter !== null) {
            foreach (array_keys($counts) as $key) {
                if (strcasecmp((string)$filter, $key) === 0) {
                    $filter = $key;
                    break;
                }
            }
        }
        $filteredRows = $this->readinessRepository->getReadinessRowsForProgram($programId, $filter);

        $programs = $this->readinessRepository->getAllPrograms();

        $currentProgram = null;
        foreach ($programs as $prog) {
            if ((string)$prog['program_id'] === (string)$programId) {
                $currentProgram = $prog;
                break;
            }
        }

        return $this->render('tools/program_readiness/index.html.twig', [
            'program_id' => $programId,
            'program' => $currentProgram,
            'rows' => $filteredRows,
            'counts' => $counts,
            'active_filter' => $filter,
            'programs' => $programs,
        ]);
    }
}
