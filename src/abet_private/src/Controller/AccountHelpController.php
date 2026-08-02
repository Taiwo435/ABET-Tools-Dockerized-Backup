<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

// #132: migrated from src/public/account/help/.php
// contact is a stub, matching legacy 501 behavior

final class AccountHelpController extends AbstractController
{
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/account/help/', name: 'app_account_help', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('account/help/index.html.twig');
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/account/help/faq/', name: 'app_account_help_faq', methods: ['GET'])]
    public function faq(): Response
    {
        return $this->render('account/help/faq.html.twig');
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/account/help/contact/', name: 'app_account_help_contact', methods: ['POST'])]
    public function contact(): Response
    {
        return new Response('Not implemented yet', Response::HTTP_NOT_IMPLEMENTED);
    }
}
