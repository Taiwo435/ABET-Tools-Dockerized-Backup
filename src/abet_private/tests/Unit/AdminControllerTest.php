<?php

namespace Tests\Controller;

use App\Controller\AdminController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AdminControllerTest extends TestCase
{
    public function testAdminPanelRouteRequiresAdminRole(): void
    {
        self::assertTrue(
            class_exists(AdminController::class),
            'The central admin panel controller does not exist.'
        );

        $method = (new ReflectionClass(AdminController::class))->getMethod('index');

        $routeAttributes = $method->getAttributes(Route::class);
        self::assertCount(1, $routeAttributes);

        $route = $routeAttributes[0]->newInstance();
        self::assertSame('/admin', $route->path);
        self::assertSame('app_admin_panel', $route->name);
        self::assertSame(['GET'], $route->methods);

        $grantAttributes = $method->getAttributes(IsGranted::class);
        self::assertCount(1, $grantAttributes);
        self::assertSame('ROLE_ADMIN', $grantAttributes[0]->newInstance()->attribute);
    }

    public function testAdminPanelOnlyLinksImplementedTools(): void
    {
        $template = file_get_contents(
            dirname(__DIR__, 2).'/templates/tools/admin_panel/home.html.twig'
        );

        self::assertIsString($template);
        self::assertStringContainsString("path('app_assignments_grades')", $template);
        self::assertStringContainsString('aria-disabled="true"', $template);
        self::assertStringContainsString('Coming Soon', $template);
        self::assertStringNotContainsString("path('app_admin_users')", $template);
    }

    public function testDoctrineMapsTheExistingEntityDirectory(): void
    {
        $configuration = file_get_contents(
            dirname(__DIR__, 2).'/config/packages/doctrine.yaml'
        );

        self::assertIsString($configuration);
        self::assertStringContainsString(
            "dir: '%kernel.project_dir%/src/Entity'",
            $configuration
        );
    }
}
