<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

// #132: migrated from src/public/tools/{AdminPanel,tool1,tool2}/index.php
// These were empty scaffolding in the legacy app (no real content, no
// links pointing at them anywhere). Preserved as placeholder routes
// rather than deleted, in case they're built out later. Note: the real,
// working admin panel already exists at /admin (AdminController) -- this
// AdminPanel placeholder is a distinct, separate stub, not a duplicate.
final class ToolsPlaceholderController extends AbstractController
{
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/tools/adminpanel/', name: 'app_tools_admin_panel_placeholder', methods: ['GET'])]
    public function adminPanel(): Response
    {
        return $this->render('tools/placeholder.html.twig', [
            'title' => 'Admin Panel',
        ]);
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/tools/tool1/', name: 'app_tools_tool1_placeholder', methods: ['GET'])]
    public function tool1(): Response
    {
        return $this->render('tools/placeholder.html.twig', [
            'title' => 'Tool 1',
        ]);
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/tools/tool2/', name: 'app_tools_tool2_placeholder', methods: ['GET'])]
    public function tool2(): Response
    {
        return $this->render('tools/placeholder.html.twig', [
            'title' => 'Tool 2',
        ]);
    }
}
