<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Symfony's entity user provider has no built-in "is this account active"
 * check — without this, native form_login happily logs in an account with
 * is_active = 0 (verified by testing directly against a fresh inactive
 * row). This is what actually enforces the email-verification gate at
 * login time; LegacySessionAuthenticator checks isActive() separately for
 * the legacy-session bridge path.
 */
class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof User && !$user->isActive()) {
            throw new CustomUserMessageAccountStatusException(
                'Please verify your email before signing in.'
            );
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
