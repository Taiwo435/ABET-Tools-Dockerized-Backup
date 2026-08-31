<?php

namespace App\Controller;

use App\Entity\Permissions;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// #132: migrated from src/public/auth/register.php.
//
// Behavior parity notes vs. the legacy version:
//  - CSRF now uses Symfony's native token system instead of the custom
//    session-based csrf_token_register implementation.
//  - Password policy check (5 rules) ported 1:1.
//  - New accounts always default to ROLE_FACULTY_FORM and cannot self-select
//    permissions -- this was already fixed on the legacy version before
//    migration (see #89); preserved here, not re-fixed.
//  - email_verification_token/email_verification_expires_at are not yet
//    mapped on the User entity, so this uses raw DBAL for those two columns,
//    same pattern as AccountProfileController.
//  - Email sending still goes through the existing lib/mailer.php
//    send_verification_email() function rather than being rewritten to use
//    MailerInterface directly, to avoid introducing a second, untested
//    mailer code path alongside the one already in use elsewhere.
final class RegisterController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request, Connection $connection): Response
    {
        $errors = [];
        $success = false;
        $email = '';

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('register', (string) $request->request->get('_csrf_token'))) {
                $errors[] = 'Invalid or missing form token. Please refresh the page and try again.';
            }

            $email = strtolower(trim((string) $request->request->get('email', '')));
            $password = (string) $request->request->get('password', '');
            $confirm = (string) $request->request->get('confirm_password', '');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please enter a valid email address.';
            }

            $policyIssues = $this->passwordPolicyCheck($password);
            if (!empty($policyIssues)) {
                $errors[] = 'Password is too weak. It must include: ' . implode(', ', $policyIssues) . '.';
            }

            if ($password !== $confirm) {
                $errors[] = 'Passwords do not match.';
            }

            if (empty($errors)) {
                $existing = $connection->fetchOne(
                    'SELECT id FROM users WHERE email = :email LIMIT 1',
                    ['email' => $email]
                );

                if ($existing !== false) {
                    $errors[] = 'An account with that email already exists.';
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    // New accounts always default to the lowest-privilege role
                    // (Faculty). Users can no longer self-select permissions
                    // at signup (#89).
                    $permissions = Permissions::ROLE_FACULTY_FORM->value;
                    $verificationCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $expiresAt = (new \DateTime('+15 minutes'))->format('Y-m-d H:i:s');

                    $connection->executeStatement(
                        'INSERT INTO users (email, password_hash, is_active, permissions, email_verification_token, email_verification_expires_at)
                         VALUES (:email, :password_hash, 0, :permissions, :token, :expires_at)',
                        [
                            'email' => $email,
                            'password_hash' => $hash,
                            'permissions' => $permissions,
                            'token' => $verificationCode,
                            'expires_at' => $expiresAt,
                        ]
                    );

                    require_once getenv('ABET_PRIVATE_DIR') . '/lib/mailer.php';
                    send_verification_email($email, $verificationCode);

                    $success = true;
                }
            }
        }

        return $this->render('security/register.html.twig', [
            'errors' => $errors,
            'success' => $success,
            'email' => $email,
        ]);
    }

    /**
     * Ported 1:1 from the legacy password_policy_check().
     *
     * @return list<string> Human-readable descriptions of unmet requirements.
     */
    private function passwordPolicyCheck(string $password): array
    {
        $issues = [];

        if (strlen($password) < 10) {
            $issues[] = 'at least 10 characters';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $issues[] = 'at least 1 number';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $issues[] = 'at least 1 lowercase letter';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $issues[] = 'at least 1 uppercase letter';
        }
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $issues[] = 'at least 1 special character';
        }

        return $issues;
    }
}
