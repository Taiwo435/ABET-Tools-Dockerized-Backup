<?php

namespace App\Controller;

use App\Entity\Permissions;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\MailerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

// Permissions is declared inside User.php, so it isn't found by PSR-4
// autoloading on its own — require it explicitly, same as AccountController.

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
                'id' => $user->getId(),
                'email' => $user->getEmail(),
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

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/users/{id}/toggle-admin', name: 'app_admin_users_toggle_admin', methods: ['POST'])]
    public function toggleAdmin(
        User $user, 
        Request $request, 
        EntityManagerInterface $em, 
        #[CurrentUser] User $currentUser,
        MailerService $mailer): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('admin_toggle_admin', (string) $request->request->get('csrf_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($user->getId() === $currentUser->getId()) {
            // Prevents an admin from locking themselves out by revoking their
            // own access — must be done by a different admin instead.
            throw $this->createAccessDeniedException('You cannot change your own admin access.');
        }

        $isCurrentlyAdmin = ($user->getPermissions() & Permissions::ROLE_ADMIN->value) !== 0;
        $user->setPermission(Permissions::ROLE_ADMIN, !$isCurrentlyAdmin);
        $em->flush();

        if (!$isCurrentlyAdmin) {
            $mailer->send_permission_approved_email($user->getEmail(), ['ROLE_ADMIN']);
        }

        return $this->redirectToRoute('app_admin_users');
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/queue', name: 'app_admin_permission_queue', methods: ['GET'])]
    public function queue(UserRepository $userRepository): Response
    {
        $pendingUsers = $userRepository->createQueryBuilder('u')
            ->andWhere('u.requestedPermissions IS NOT NULL')
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();

        $requests = array_map(
            static fn (User $user): array => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'currentPermissions' => $user->getRoles(),
                'requestedPermissions' => $user->getRequestedPermissionNames(),
            ],
            $pendingUsers
        );

        return $this->render('tools/admin_panel/queue.html.twig', [
            'requests' => $requests,
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/queue/{id}/approve', name: 'app_admin_permission_queue_approve', methods: ['POST'])]
    public function approve(
        User $user, 
        Request $request, 
        EntityManagerInterface $em,
        MailerService $mailer): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('admin_queue_action', (string) $request->request->get('csrf_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $requested = $user->getRequestedPermissions();
        if ($requested !== null) {
            $user->setPermissions($user->getPermissions() | $requested);
            $grantedNames = $user->getRequestedPermissionNames();
            $user->setRequestedPermissions(null);
            $em->flush();

            $mailer->send_permission_approved_email($user->getEmail(), $grantedNames);
        }

        return $this->redirectToRoute('app_admin_permission_queue');
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/queue/{id}/deny', name: 'app_admin_permission_queue_deny', methods: ['POST'])]
    public function deny(User $user, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('admin_queue_action', (string) $request->request->get('csrf_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $user->setRequestedPermissions(null);
        $em->flush();

        return $this->redirectToRoute('app_admin_permission_queue');
    }
}
