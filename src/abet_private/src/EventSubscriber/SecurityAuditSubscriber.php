<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Security\LegacySessionAuthenticator;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Ports the audit_log/login_events logging that used to live inline in the
 * legacy src/public/auth/login.php and logout.php pages, now that /login
 * and /logout are handled natively by Symfony (see SecurityController).
 *
 * Also re-populates the raw $_SESSION keys (user_id, user_email,
 * user_permissions, ...) that login.php used to write directly. Plenty of
 * legacy pages still reachable through LegacyBridge (faculty-form,
 * coordinator-form, AssignmentsGrades/admin.php, account/*) gate themselves
 * with require_login()/require_role(), which read those keys straight off
 * $_SESSION, not through Symfony's security context. Without this, logging
 * in via native /login leaves those pages unable to see you're logged in at
 * all — LegacySessionAuthenticator only bridges the other direction (legacy
 * session -> Symfony), so nothing else fills this gap.
 *
 * Skips LoginSuccessEvent when the authenticator is LegacySessionAuthenticator:
 * that path re-authenticates an *already* logged-in legacy session (e.g. the
 * first Symfony-routed request after a legacy /login), which was already
 * logged once by login.php itself — logging it again here would double-count
 * every such login in the audit trail, and the legacy $_SESSION keys are
 * already present in that case anyway.
 */
final class SecurityAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly RequestStack $requestStack,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LoginFailureEvent::class => 'onLoginFailure',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        if ($event->getAuthenticator() instanceof LegacySessionAuthenticator) {
            return;
        }

        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $event->getRequest()->getSession()->start();
        $_SESSION['user_id'] = $user->getId();
        $_SESSION['user_email'] = $user->getEmail();
        $_SESSION['user_permissions'] = $user->getPermissions();
        $_SESSION['created_at'] = time();
        $_SESSION['last_activity'] = time();

        $this->logLoginEvent($user->getId(), $user->getEmail(), 'success', null);
        $this->logAudit($user->getId(), 'login_success', 'user', (string) $user->getId(), [
            'permissions' => $user->getPermissions(),
        ]);

        $this->connection->executeStatement(
            'UPDATE users SET last_login = NOW() WHERE id = :id',
            ['id' => $user->getId()],
        );
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $emailAttempted = $event->getRequest()->request->get('_username');

        $this->logLoginEvent(null, is_string($emailAttempted) ? $emailAttempted : null, 'failed_password', 'bad_credentials_or_inactive');
    }

    public function onLogout(LogoutEvent $event): void
    {
        $user = $event->getToken()?->getUser();
        if (!$user instanceof User) {
            return;
        }

        $this->logAudit($user->getId(), 'logout', 'user', (string) $user->getId(), [
            'user_agent' => $this->requestStack->getCurrentRequest()?->headers->get('User-Agent'),
        ]);
    }

    private function logLoginEvent(?int $userId, ?string $emailAttempted, string $result, ?string $reason): void
    {
        try {
            $this->connection->executeStatement(
                'INSERT INTO login_events (user_id, email_attempted, result, reason, ip_address, user_agent)
                 VALUES (:user_id, :email_attempted, :result, :reason, :ip, :ua)',
                [
                    'user_id' => $userId,
                    'email_attempted' => $emailAttempted,
                    'result' => $result,
                    'reason' => $reason,
                    'ip' => $this->requestStack->getCurrentRequest()?->getClientIp(),
                    'ua' => $this->requestStack->getCurrentRequest()?->headers->get('User-Agent'),
                ],
            );
        } catch (\Throwable) {
            // Fail-open: never break login/logout on a logging failure.
        }
    }

    private function logAudit(int $actorUserId, string $action, string $targetType, string $targetId, array $metadata): void
    {
        try {
            $this->connection->executeStatement(
                'INSERT INTO audit_log (actor_user_id, action, target_type, target_id, metadata, ip_address)
                 VALUES (:actor, :action, :target_type, :target_id, :metadata, :ip)',
                [
                    'actor' => $actorUserId,
                    'action' => $action,
                    'target_type' => $targetType,
                    'target_id' => $targetId,
                    'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'ip' => $this->requestStack->getCurrentRequest()?->getClientIp(),
                ],
            );
        } catch (\Throwable) {
            // Fail-open
        }
    }
}
