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
 * #51/132: Migrated from src/public/account/profile/index.php + update.php.
 *
 * Behavior parity notes vs. the legacy version:
 *  - CSRF now uses Symfony's native token system instead of the custom
 *    lib/csrf.php implementation.
 *  - Success/error state is now passed via flash messages instead of
 *    ?saved=1 / ?error=... query parameters.
 *  - Known pre-existing issue (not introduced or fixed here): user_profiles.user_id
 *    has no UNIQUE constraint in the schema, so the insert-vs-update branch
 *    below relies solely on the preceding existence check, not a true
 *    upsert. This mirrors the legacy behavior exactly. See #51 follow-up.
 */
final class AccountProfileController extends AbstractController
{
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/account/profile/', name: 'app_account_profile', methods: ['GET'])]
    public function index(#[CurrentUser] User $user, Connection $connection): Response
    {
        $profile = $connection->fetchAssociative(
            'SELECT display_name, department, phone, office_location, bio
             FROM user_profiles
             WHERE user_id = :uid
             LIMIT 1',
            ['uid' => $user->getId()]
        );

        $profile = array_merge([
            'display_name'    => '',
            'department'      => '',
            'phone'           => '',
            'office_location' => '',
            'bio'             => '',
        ], $profile ?: []);

        return $this->render('account/profile.html.twig', [
            'profile' => $profile,
        ]);
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/account/profile/update/', name: 'app_account_profile_update', methods: ['POST'])]
    public function update(Request $request, #[CurrentUser] User $user, Connection $connection): Response
    {
        if (!$this->isCsrfTokenValid('profile_update', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid session. Please refresh and try again.');
            return $this->redirectToRoute('app_account_profile');
        }

        $userId = $user->getId();

        [$data, $errors] = $this->validateProfileInput($request->request->all());

        if (!empty($errors)) {
            $this->addFlash('error', 'Please correct the highlighted fields.');
            return $this->redirectToRoute('app_account_profile');
        }

        $exists = (bool) $connection->fetchOne(
            'SELECT COUNT(*) FROM user_profiles WHERE user_id = :uid',
            ['uid' => $userId]
        );

        if (!$exists) {
            $ok = (bool) $connection->executeStatement(
                'INSERT INTO user_profiles
                    (user_id, display_name, department, phone, office_location, bio)
                 VALUES
                    (:uid, :display_name, :department, :phone, :office_location, :bio)',
                array_merge(['uid' => $userId], $data)
            );
        } else {
            $ok = (bool) $connection->executeStatement(
                'UPDATE user_profiles SET
                    display_name = :display_name,
                    department = :department,
                    phone = :phone,
                    office_location = :office_location,
                    bio = :bio,
                    updated_at = CURRENT_TIMESTAMP
                 WHERE user_id = :uid',
                array_merge(['uid' => $userId], $data)
            );
        }

        try {
            $connection->executeStatement(
                'INSERT INTO audit_log (actor_user_id, action, target_type, target_id, metadata, ip_address)
                 VALUES (:actor, :action, :target_type, :target_id, :metadata, :ip)',
                [
                    'actor' => $userId,
                    'action' => $ok ? 'profile_update' : 'profile_update_failed',
                    'target_type' => 'user_profile',
                    'target_id' => (string) $userId,
                    'metadata' => json_encode(['fields' => array_keys($data)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'ip' => $request->getClientIp(),
                ]
            );
        } catch (\Throwable $e) {
            // Don't block user flow on logging failure — matches legacy behavior.
        }

        if (!$ok) {
            $this->addFlash('error', 'Failed to save your profile. Please try again.');
            return $this->redirectToRoute('app_account_profile');
        }

        $this->addFlash('success', 'Changes saved.');
        return $this->redirectToRoute('app_account_profile');
    }

    /**
     * Ported 1:1 from lib/validators.php's validate_profile_input().
     *
     * @return array{0: array<string,string>, 1: array<string,string>}
     */
    private function validateProfileInput(array $post): array
    {
        $data = [
            'display_name'    => $this->vTrim($post['display_name'] ?? '', 120),
            'department'      => $this->vTrim($post['department'] ?? '', 120),
            'phone'           => $this->vTrim($post['phone'] ?? '', 30),
            'office_location' => $this->vTrim($post['office_location'] ?? '', 120),
            'bio'             => $this->vTrim($post['bio'] ?? '', 500),
        ];

        $errors = [];

        if ($data['display_name'] !== '' && mb_strlen($data['display_name']) < 2) {
            $errors['display_name'] = 'Display name is too short.';
        }

        if ($data['phone'] !== '' && !preg_match('/^[0-9+\-\s().]{7,30}$/', $data['phone'])) {
            $errors['phone'] = 'Phone format is invalid.';
        }

        return [$data, $errors];
    }

    private function vTrim(?string $value, int $maxLen): string
    {
        $v = trim((string) $value);
        if (mb_strlen($v) > $maxLen) {
            $v = mb_substr($v, 0, $maxLen);
        }
        return $v;
    }
}
