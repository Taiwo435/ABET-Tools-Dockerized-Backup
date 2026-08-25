<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


// Behavior parity notes vs. the legacy version:
//  - CSRF now uses Symfony's native token system instead of the custom
//    session-based tokens (csrf_token_forgot_password / csrf_token_reset_password).
//  - The legacy version stored the "notice" and "dev_reset_link" as raw
//    session keys, manually unset() after reading. Symfony's native flash
//    bag does this same self-clear-after-one-read behavior natively, so
//    that's used here instead.
//  - password_resets is not Doctrine-entity-mapped, so this uses raw DBAL,
//    same pattern as AccountProfileController / RegisterController.
//  - Fixed here (not present before): rp_send_password_reset_email() called
//    send_password_reset_email(), a function that did not exist anywhere in
//    the codebase -- meaning this feature always silently fell through to
//    the dev-log fallback even with a real mailer configured. Added the
//    missing function to lib/mailer.php as part of this migration.
//  - Reset links are now built via Symfony's generateUrl() instead of
//    manually concatenating an app-URL env var.
final class PasswordResetController extends AbstractController
{
    private const TOKEN_TTL_MINUTES = 15;
    private const RATE_LIMIT_SECONDS = 60;

    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(Request $request, Connection $connection): Response
    {
        $errors = [];
        $email = '';

        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('email', ''));

            if (!$this->isCsrfTokenValid('forgot_password', (string) $request->request->get('_csrf_token'))) {
                $errors[] = 'Invalid request. Please try again.';
            }
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please enter a valid email address.';
            }

            if (empty($errors)) {
                $result = $this->requestPasswordReset(
                    $connection,
                    $email,
                    $request->getClientIp(),
                    $request->headers->get('User-Agent')
                );

                $this->addFlash('reset_notice', $result['public_message']);
                if (!empty($result['dev_reset_link'])) {
                    $this->addFlash('reset_dev_link', $result['dev_reset_link']);
                }

                return $this->redirectToRoute('app_forgot_password_sent');
            }
        }

        return $this->render('security/forgot_password.html.twig', [
            'errors' => $errors,
            'email' => $email,
        ]);
    }

    #[Route('/forgot-password/sent', name: 'app_forgot_password_sent', methods: ['GET'])]
    public function forgotPasswordSent(RequestStack $requestStack): Response
    {
        $notices = $requestStack->getSession()->getFlashBag()->get('reset_notice');
        $devLinks = $requestStack->getSession()->getFlashBag()->get('reset_dev_link');

        return $this->render('security/forgot_password_sent.html.twig', [
            'notice' => $notices[0] ?? 'If an account exists for that email, a reset link has been sent.',
            'devResetLink' => $devLinks[0] ?? null,
        ]);
    }

    #[Route('/reset-password', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(Request $request, Connection $connection): Response
    {
        $errors = [];
        $token = trim((string) ($request->query->get('token') ?? $request->request->get('token', '')));
        $tokenCheck = $token !== '' ? $this->validateResetToken($connection, $token) : null;

        if ($request->isMethod('POST')) {
            $password = (string) $request->request->get('password', '');
            $confirmPassword = (string) $request->request->get('confirm_password', '');

            if (!$this->isCsrfTokenValid('reset_password', (string) $request->request->get('_csrf_token'))) {
                $errors[] = 'Invalid request. Please refresh and try again.';
            }
            if ($token === '') {
                $errors[] = 'Missing reset token.';
            }
            if ($password === '' || $confirmPassword === '') {
                $errors[] = 'Please fill out both password fields.';
            }
            if ($password !== $confirmPassword) {
                $errors[] = 'Passwords do not match.';
            }
            foreach ($this->passwordPolicyCheck($password) as $issue) {
                $errors[] = $issue;
            }

            if (empty($errors)) {
                $result = $this->completePasswordReset($connection, $token, $password);

                if (!empty($result['ok'])) {
                    return $this->redirectToRoute('app_reset_password_success');
                }

                $reason = $result['reason'] ?? 'unknown';
                if (in_array($reason, ['invalid_token', 'expired_token', 'used_token'], true)) {
                    $errors[] = 'This reset link is invalid or has expired. Please request a new one.';
                } else {
                    $errors[] = 'Unable to reset password right now. Please try again.';
                }
            }

            $tokenCheck = $token !== '' ? $this->validateResetToken($connection, $token) : null;
        }

        return $this->render('security/reset_password.html.twig', [
            'errors' => $errors,
            'token' => $token,
            'isTokenValid' => !empty($tokenCheck['ok']),
        ]);
    }

    #[Route('/reset-password/success', name: 'app_reset_password_success', methods: ['GET'])]
    public function resetPasswordSuccess(): Response
    {
        return $this->render('security/reset_password_success.html.twig');
    }

    /**
     * Ported from reset_password_lib.php's rp_password_policy_check().
     *
     * @return list<string>
     */
    private function passwordPolicyCheck(string $password): array
    {
        $issues = [];
        if (strlen($password) < 10) {
            $issues[] = 'Password must be at least 10 characters.';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $issues[] = 'Password must contain at least 1 lowercase letter.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $issues[] = 'Password must contain at least 1 uppercase letter.';
        }
        if (!preg_match('/\d/', $password)) {
            $issues[] = 'Password must contain at least 1 number.';
        }
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $issues[] = 'Password must contain at least 1 special character.';
        }
        return $issues;
    }

    /**
     * Ported from rp_request_password_reset(). Always returns a generic
     * public message (anti-enumeration), regardless of whether the email
     * actually matches an account.
     */
    private function requestPasswordReset(Connection $connection, string $email, ?string $ip, ?string $userAgent): array
    {
        $publicMessage = 'If an account exists for that email, a reset link has been sent.';
        $email = trim(strtolower($email));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => true, 'public_message' => $publicMessage];
        }

        $user = $connection->fetchAssociative(
            'SELECT id, email FROM users WHERE email = :email LIMIT 1',
            ['email' => $email]
        );

        if (!$user) {
            return ['ok' => true, 'public_message' => $publicMessage];
        }

        $userId = (int) $user['id'];

        $latest = $connection->fetchAssociative(
            'SELECT created_at FROM password_resets WHERE user_id = :user_id ORDER BY id DESC LIMIT 1',
            ['user_id' => $userId]
        );

        if ($latest && !empty($latest['created_at'])) {
            $createdTs = strtotime((string) $latest['created_at']);
            if ($createdTs !== false && (time() - $createdTs) < self::RATE_LIMIT_SECONDS) {
                return ['ok' => true, 'public_message' => $publicMessage];
            }
        }

        $connection->executeStatement(
            'UPDATE password_resets SET used_at = NOW() WHERE user_id = :user_id AND used_at IS NULL',
            ['user_id' => $userId]
        );

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = (new \DateTime('+' . self::TOKEN_TTL_MINUTES . ' minutes'))->format('Y-m-d H:i:s');

        $connection->executeStatement(
            'INSERT INTO password_resets (user_id, email, token_hash, expires_at, used_at, created_at, requested_ip, user_agent)
             VALUES (:user_id, :email, :token_hash, :expires_at, NULL, NOW(), :requested_ip, :user_agent)',
            [
                'user_id' => $userId,
                'email' => $email,
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt,
                'requested_ip' => $ip,
                'user_agent' => $userAgent,
            ]
        );

        $resetLink = $this->generateUrl('app_reset_password', ['token' => $rawToken], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);

        require_once getenv('ABET_PRIVATE_DIR') . '/lib/mailer.php';
        $subject = 'Reset Your ABET Tools Password';
        $safeLink = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');
        $htmlBody = "<p>Hello,</p><p>We received a request to reset your password for your ABET Tools account.</p><p><a href=\"{$safeLink}\">Click here to reset your password</a></p><p>This link expires in " . self::TOKEN_TTL_MINUTES . " minutes and can only be used once.</p><p>If you did not request a password reset, you can ignore this email.</p>";
        $textBody = "Hello,\n\nWe received a request to reset your password for your ABET Tools account.\n\nUse this link to reset your password:\n{$resetLink}\n\nThis link expires in " . self::TOKEN_TTL_MINUTES . " minutes and can only be used once.\n\nIf you did not request a password reset, you can ignore this email.\n";

        $sent = send_password_reset_email($email, $subject, $htmlBody, $textBody);

        $result = ['ok' => true, 'public_message' => $publicMessage];
        if (!$sent) {
            $result['dev_reset_link'] = $resetLink;
        }
        return $result;
    }

    /**
     * Ported from rp_validate_reset_token().
     */
    private function validateResetToken(Connection $connection, string $rawToken): array
    {
        $rawToken = trim($rawToken);
        if (!preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
            return ['ok' => false, 'reason' => 'invalid_token'];
        }

        $tokenHash = hash('sha256', $rawToken);
        $row = $connection->fetchAssociative(
            'SELECT id, user_id, email, expires_at, used_at FROM password_resets WHERE token_hash = :token_hash LIMIT 1',
            ['token_hash' => $tokenHash]
        );

        if (!$row) {
            return ['ok' => false, 'reason' => 'invalid_token'];
        }
        if (!empty($row['used_at'])) {
            return ['ok' => false, 'reason' => 'used_token'];
        }

        $expTs = strtotime((string) $row['expires_at']);
        if ($expTs === false || $expTs < time()) {
            return ['ok' => false, 'reason' => 'expired_token'];
        }

        return ['ok' => true, 'reset' => $row];
    }

    /**
     * Ported from rp_complete_password_reset().
     */
    private function completePasswordReset(Connection $connection, string $rawToken, string $newPassword): array
    {
        $check = $this->validateResetToken($connection, $rawToken);
        if (empty($check['ok'])) {
            return ['ok' => false, 'reason' => $check['reason'] ?? 'invalid_token'];
        }

        if (!empty($this->passwordPolicyCheck($newPassword))) {
            return ['ok' => false, 'reason' => 'password_policy_failed'];
        }

        $resetRow = $check['reset'];
        $resetId = (int) $resetRow['id'];
        $userId = (int) $resetRow['user_id'];
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        try {
            $connection->beginTransaction();

            $connection->executeStatement(
                'UPDATE users SET password_hash = :password_hash WHERE id = :user_id',
                ['password_hash' => $passwordHash, 'user_id' => $userId]
            );
            $connection->executeStatement(
                'UPDATE password_resets SET used_at = NOW() WHERE id = :id AND used_at IS NULL',
                ['id' => $resetId]
            );
            $connection->executeStatement(
                'UPDATE password_resets SET used_at = NOW() WHERE user_id = :user_id AND used_at IS NULL',
                ['user_id' => $userId]
            );

            $connection->commit();
        } catch (\Throwable $e) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            return ['ok' => false, 'reason' => 'server_error'];
        }

        return ['ok' => true];
    }
}
