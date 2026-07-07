<?php

namespace Tests\Controller;

use App\Controller\AccountController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AccountControllerTest extends TestCase
{
    public function testAccountOverviewRouteRequiresAuthenticatedUserWithoutShadowingLegacyProfile(): void
    {
        self::assertTrue(
            class_exists(AccountController::class),
            'The account profile controller does not exist.'
        );

        $method = (new ReflectionClass(AccountController::class))->getMethod('me');

        $routeAttributes = $method->getAttributes(Route::class);
        self::assertCount(1, $routeAttributes);

        $route = $routeAttributes[0]->newInstance();
        self::assertSame('/account/overview/', $route->path);
        self::assertSame('app_account_overview', $route->name);
        self::assertSame(['GET'], $route->methods);

        $grantAttributes = $method->getAttributes(IsGranted::class);
        self::assertCount(1, $grantAttributes);
        self::assertSame('IS_AUTHENTICATED_FULLY', $grantAttributes[0]->newInstance()->attribute);
    }

    public function testAccountProfileTemplateShowsSafeAccountFields(): void
    {
        $template = file_get_contents(
            dirname(__DIR__, 2).'/templates/account/me.html.twig'
        );

        self::assertIsString($template);
        self::assertStringContainsString('My Account', $template);
        self::assertStringContainsString('profile.email', $template);
        self::assertStringContainsString('profile.asurite', $template);
        self::assertStringContainsString('profile.status', $template);
        self::assertStringContainsString('profile.permissions', $template);
        self::assertStringContainsString('profile.lastLogin', $template);
        self::assertStringContainsString('profile.createdAt', $template);
        self::assertStringNotContainsString('password', strtolower($template));
        self::assertStringNotContainsString('token', strtolower($template));
    }

    public function testBaseHeaderLinksToSymfonyAccountOverviewRoute(): void
    {
        $template = file_get_contents(
            dirname(__DIR__, 2).'/templates/base.html.twig'
        );

        self::assertIsString($template);
        self::assertStringContainsString("path('app_account_overview')", $template);
        self::assertStringNotContainsString('href="/account/me/"', $template);
    }
}
