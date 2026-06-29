<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AccountController extends AbstractController
{
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/account/overview/', name: 'app_account_overview', methods: ['GET'])]
    public function me(#[CurrentUser] User $user): Response
    {
        return $this->render('account/me.html.twig', [
            'profile' => [
                'email' => $user->getEmail(),
                'asurite' => $user->getAsurite(),
                'role' => $user->getRole(),
                'status' => $user->isActive() ? 'Active' : 'Inactive',
                'permissions' => $user->getRoles(),
                'lastLogin' => $user->getLastLogin(),
                'createdAt' => $user->getCreatedAt(),
            ],
        ]);
    }
}
