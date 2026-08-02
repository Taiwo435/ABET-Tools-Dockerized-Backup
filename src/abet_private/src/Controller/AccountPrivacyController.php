<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

// #132: migrated from src/public/account/privacy/.php
// consent/export-data/delete-request are stubs, matching legacy 501 behavior

final class AccountPrivacyController extends AbstractController
{
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/account/privacy/', name: 'app_account_privacy', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('account/privacy/index.html.twig');
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/account/privacy/consent/', name: 'app_account_privacy_consent', methods: ['POST'])]
    public function consent(): Response
    {
        return new Response('Not implemented yet', Response::HTTP_NOT_IMPLEMENTED);
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/account/privacy/export-data/', name: 'app_account_privacy_export_data', methods: ['POST'])]
    public function exportData(): Response
    {
        return new Response('Not implemented yet', Response::HTTP_NOT_IMPLEMENTED);
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/account/privacy/delete-request/', name: 'app_account_privacy_delete_request', methods: ['POST'])]
    public function deleteRequest(): Response
    {
        return new Response('Not implemented yet', Response::HTTP_NOT_IMPLEMENTED);
    }
}
