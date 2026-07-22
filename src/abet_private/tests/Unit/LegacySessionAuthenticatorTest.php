<?php

namespace Tests\Security;

use App\Security\LegacySessionAuthenticator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;

final class LegacySessionAuthenticatorTest extends TestCase
{
    public function testItImplementsTheAuthenticatorInterface(): void
    {
        self::assertTrue(class_exists(LegacySessionAuthenticator::class));
        self::assertTrue(is_subclass_of(LegacySessionAuthenticator::class, AuthenticatorInterface::class));
    }

    public function testItIsRegisteredOnTheMainFirewall(): void
    {
        $config = file_get_contents(
            dirname(__DIR__, 2).'/config/packages/security.yaml'
        );

        self::assertIsString($config);
        self::assertStringContainsString('custom_authenticators:', $config);
        self::assertStringContainsString('App\Security\LegacySessionAuthenticator', $config);
    }

    /**
     * The legacy login flow (lib/auth.php) writes directly to the top-level
     * $_SESSION superglobal, not through Symfony's Session bag API. This
     * test locks in that the authenticator reads $_SESSION directly rather
     * than $session->get() — using the bag API here would silently break
     * recognition of legacy logins, since Symfony's Session::get()/has()
     * only see data under its own "_sf2_attributes" namespace.
     */
    public function testReadsFromSessionSuperglobalNotSessionBagApi(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2).'/src/Security/LegacySessionAuthenticator.php'
        );

        self::assertIsString($source);
        self::assertStringContainsString('$_SESSION[$key]', $source);
        self::assertStringNotContainsString('getSession()->get(', $source);
    }
}
