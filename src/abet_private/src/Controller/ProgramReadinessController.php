<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Compatibility redirects for bookmarks created before syllabus lifecycle
 * reporting was consolidated into the coordinator workspace.
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ProgramReadinessController extends AbstractController
{
    #[Route('/program/readiness', name: 'app_program_readiness_select', methods: ['GET'])]
    public function selectProgram(Request $request): RedirectResponse
    {
        $parameters = [];
        $programId = $request->query->getInt('program');
        if ($programId > 0) {
            $parameters['program'] = $programId;
        }

        return $this->redirectToRoute('app_admin_syllabus_templates', $parameters);
    }

    #[Route(
        '/program/{programId}/readiness',
        name: 'app_program_readiness',
        requirements: ['programId' => '\d+'],
        methods: ['GET'],
    )]
    public function program(int $programId, Request $request): RedirectResponse
    {
        $view = match (strtolower($request->query->getString('category'))) {
            'ready' => 'appendix_a',
            'awaiting review' => 'offerings',
            default => $request->query->getString('target') === 'course_offering'
                ? 'offerings'
                : 'shared',
        };

        return $this->redirectToRoute('app_admin_syllabus_templates', [
            'program' => $programId,
            'view' => $view,
        ]);
    }
}
