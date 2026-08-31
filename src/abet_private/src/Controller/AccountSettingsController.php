<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * #132: Migrated from src/public/account/settings/{index,email,password,sessions}.php.
 *
 * Note: legacy password.php had a real bug — it queried `users` on a
 * `user_id` column that doesn't exist (the PK is `id`). Fixed below.
 */
final class AccountSettingsController extends AbstractController
{
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/account/settings/', name: 'app_account_settings', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('account/settings/index.html.twig');
    }

    // Not implemented in the legacy app either — preserving the same 501.
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/account/settings/email/', name: 'app_account_settings_email', methods: ['POST'])]
    public function updateEmail(): Response
    {
        return new Response('Not implemented yet', Response::HTTP_NOT_IMPLEMENTED);
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/account/settings/password/', name: 'app_account_settings_password', methods: ['GET'])]
    public function passwordForm(): Response
    {
        return $this->render('account/settings/password.html.twig');
    }

    // Validation rules ported 1:1 from legacy password.php.
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/account/settings/password/', name: 'app_account_settings_password_update', methods: ['POST'])]
    public function updatePassword(Request $request, #[CurrentUser] User $user, Connection $connection): Response
    {
        if (!$this->isCsrfTokenValid('settings_password', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid session. Please refresh and try again.');
            return $this->redirectToRoute('app_account_settings_password');
        }

        $currentPassword = (string) $request->request->get('current_password', '');
        $newPassword     = (string) $request->request->get('new_password', '');
        $confirmPassword = (string) $request->request->get('confirm_password', '');

        if ($newPassword !== $confirmPassword) {
            $this->addFlash('error', 'New passwords do not match.');
            return $this->redirectToRoute('app_account_settings_password');
        }

        if (strlen($newPassword) < 8) {
            $this->addFlash('error', 'New password must be at least 8 characters.');
            return $this->redirectToRoute('app_account_settings_password');
        }

        if ($newPassword === $currentPassword) {
            $this->addFlash('error', 'New password must be different from the current password.');
            return $this->redirectToRoute('app_account_settings_password');
        }

        // Fixed: `id`, not `user_id` (see class docblock).
        $row = $connection->fetchAssociative(
            'SELECT password_hash FROM users WHERE id = :uid LIMIT 1',
            ['uid' => $user->getId()]
        );

        if (!$row) {
            $this->addFlash('error', 'Account not found.');
            return $this->redirectToRoute('app_account_settings_password');
        }

        if (!password_verify($currentPassword, (string) $row['password_hash'])) {
            $this->addFlash('error', 'Current password is incorrect.');
            return $this->redirectToRoute('app_account_settings_password');
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

        $connection->executeStatement(
            'UPDATE users SET password_hash = :hash WHERE id = :uid',
            ['hash' => $newHash, 'uid' => $user->getId()]
        );

        // Force re-auth with the new password, matching legacy behavior.
        $this->addFlash('success', 'Password updated. Please sign in again.');
        return $this->redirectToRoute('app_logout');
    }

    // Not implemented in the legacy app either — preserving the same 501.
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/account/settings/sessions/', name: 'app_account_settings_sessions', methods: ['POST'])]
    public function updateSessions(): Response
    {
        return new Response('Not implemented yet', Response::HTTP_NOT_IMPLEMENTED);
    }
}
