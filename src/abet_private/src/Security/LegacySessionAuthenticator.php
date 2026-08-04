<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Bridges the legacy PHP-session login (src/public/auth/login.php, lib/auth.php)
 * into Symfony's security system.
 *
 * Both the legacy login flow and Symfony's native session storage share the
 * same PHPSESSID cookie (neither side sets a custom session name), so a user
 * who authenticated via the legacy /login page already has `user_email`
 * sitting in the session by the time a Symfony-routed request comes in —
 * this authenticator just recognizes it, so the first Symfony-routed
 * request after a legacy login doesn't get bounced back to /login.
 */
final class LegacySessionAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly UserProviderInterface $userProvider,
    ) {
    }

    /**
     * The legacy login flow (lib/auth.php) writes directly to the top-level
     * $_SESSION superglobal (e.g. $_SESSION['user_id']), not through
     * Symfony's Session object. Symfony's Session::get()/has() only see data
     * stored in its own namespaced "_sf2_attributes" bag, so reading via
     * $request->getSession() here would never find the legacy keys even
     * though they're in the very same PHPSESSID-backed session file. We have
     * to start the session (so the native $_SESSION superglobal is bound)
     * and read the superglobal directly, the same way legacy code does.
     */
    private function readLegacySessionValue(Request $request, string $key): ?string
    {
        if (!$request->hasPreviousSession()) {
            return null;
        }

        // Ensures session_start() has run and $_SESSION is bound.
        $request->getSession()->start();

        return isset($_SESSION[$key]) ? (string) $_SESSION[$key] : null;
    }

    public function supports(Request $request): ?bool
    {
        return $this->readLegacySessionValue($request, 'user_id') !== null
            && $this->readLegacySessionValue($request, 'user_email') !== null;
    }

    public function authenticate(Request $request): Passport
    {
        $email = (string) $this->readLegacySessionValue($request, 'user_email');

        $userBadge = new UserBadge($email, function (string $identifier) {
            $user = $this->userProvider->loadUserByIdentifier($identifier);

            if (!$user->isActive()) {
                throw new UserNotFoundException('Legacy session user is no longer active.');
            }

            return $user;
        });

        return new SelfValidatingPassport($userBadge);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return null;
    }
}
