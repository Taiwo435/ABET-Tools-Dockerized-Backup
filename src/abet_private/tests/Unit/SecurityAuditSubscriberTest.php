<?php

namespace Tests\Unit;

use App\EventSubscriber\SecurityAuditSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class SecurityAuditSubscriberTest extends TestCase
{
    public function testItImplementsTheSubscriberInterface(): void
    {
        self::assertTrue(is_subclass_of(SecurityAuditSubscriber::class, EventSubscriberInterface::class));
    }

    public function testItSubscribesToLoginAndLogoutEvents(): void
    {
        $events = SecurityAuditSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(LoginSuccessEvent::class, $events);
        self::assertArrayHasKey(LoginFailureEvent::class, $events);
        self::assertArrayHasKey(LogoutEvent::class, $events);
    }

    /**
     * A LoginSuccessEvent fired by LegacySessionAuthenticator means the user
     * already logged in through the legacy /login page (which logs its own
     * audit_log/login_events rows) and is just being recognized on the first
     * Symfony-routed request. Logging it again here would double-count every
     * such login in the audit trail, so this guard must stay in place.
     */
    public function testSkipsLegacySessionAuthenticatorToAvoidDoubleLogging(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2).'/src/EventSubscriber/SecurityAuditSubscriber.php'
        );

        self::assertIsString($source);
        self::assertStringContainsString('LegacySessionAuthenticator', $source);
        self::assertStringContainsString('instanceof LegacySessionAuthenticator', $source);
    }
}
