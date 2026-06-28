<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AdminController extends AbstractController
{
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin', name: 'app_admin_panel', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('tools/admin_panel/home.html.twig');
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/users', name: 'app_admin_users', methods: ['GET'])]
    public function users(UserRepository $userRepository): Response
    {
        $users = array_map(
            static fn ($user): array => [
                'email' => $user->getEmail(),
                'role' => $user->getRole(),
                'active' => $user->isActive(),
                'permissions' => $user->getRoles(),
                'lastLogin' => $user->getLastLogin(),
                'createdAt' => $user->getCreatedAt(),
            ],
            $userRepository->findBy([], ['email' => 'ASC'])
        );

        return $this->render('tools/admin_panel/users.html.twig', [
            'users' => $users,
        ]);
    }
}
