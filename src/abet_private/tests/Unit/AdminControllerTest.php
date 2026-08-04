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
        self::assertStringContainsString("path('app_admin_users')", $template);
        self::assertStringNotContainsString('aria-disabled="true"', $template);
        self::assertStringNotContainsString('Coming Soon', $template);
    }

    public function testSymfonyHomepageLinksAdminsToCentralAdminPanel(): void
    {
        $template = file_get_contents(
            dirname(__DIR__, 2).'/templates/homepage/home.html.twig'
        );

        self::assertIsString($template);
        self::assertStringContainsString("is_granted('ROLE_ADMIN')", $template);
        self::assertStringContainsString("path('app_admin_panel')", $template);
        self::assertStringNotContainsString('/AssignmentsGrades/admin.php', $template);
    }

    public function testAdminUsersRouteRequiresAdminRole(): void
    {
        self::assertTrue(
            class_exists(AdminController::class),
            'The admin controller does not exist.'
        );

        $method = (new ReflectionClass(AdminController::class))->getMethod('users');

        $routeAttributes = $method->getAttributes(Route::class);
        self::assertCount(1, $routeAttributes);

        $route = $routeAttributes[0]->newInstance();
        self::assertSame('/admin/users', $route->path);
        self::assertSame('app_admin_users', $route->name);
        self::assertSame(['GET'], $route->methods);

        $grantAttributes = $method->getAttributes(IsGranted::class);
        self::assertCount(1, $grantAttributes);
        self::assertSame('ROLE_ADMIN', $grantAttributes[0]->newInstance()->attribute);
    }

    public function testAdminUsersTemplateShowsUserManagementFields(): void
    {
        $template = file_get_contents(
            dirname(__DIR__, 2).'/templates/tools/admin_panel/users.html.twig'
        );

        self::assertIsString($template);
        self::assertStringContainsString('User Management', $template);
        self::assertStringContainsString('users', $template);
        self::assertStringContainsString('user.email', $template);
        self::assertStringContainsString('user.active', $template);
        self::assertStringContainsString('user.permissions', $template);
        self::assertStringContainsString('Unknown', $template);
    }

    public function testAdminUsersToggleAdminRouteRequiresAdminRoleAndPost(): void
    {
        $method = (new ReflectionClass(AdminController::class))->getMethod('toggleAdmin');

        $routeAttributes = $method->getAttributes(Route::class);
        self::assertCount(1, $routeAttributes);

        $route = $routeAttributes[0]->newInstance();
        self::assertSame('/admin/users/{id}/toggle-admin', $route->path);
        self::assertSame('app_admin_users_toggle_admin', $route->name);
        self::assertSame(['POST'], $route->methods);

        $grantAttributes = $method->getAttributes(IsGranted::class);
        self::assertCount(1, $grantAttributes);
        self::assertSame('ROLE_ADMIN', $grantAttributes[0]->newInstance()->attribute);
    }

    /**
     * An admin must not be able to revoke (or grant, though that's a no-op)
     * their own admin access through this endpoint — otherwise a lone admin
     * could lock themselves out with a stray click.
     */
    public function testAdminUsersToggleAdminRejectsActingOnSelf(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2).'/src/Controller/AdminController.php'
        );

        self::assertIsString($source);
        self::assertStringContainsString('$user->getId() === $currentUser->getId()', $source);
    }

    public function testAdminUsersTemplateOffersAdminToggleWithCsrfProtection(): void
    {
        $template = file_get_contents(
            dirname(__DIR__, 2).'/templates/tools/admin_panel/users.html.twig'
        );

        self::assertIsString($template);
        self::assertStringContainsString('app_admin_users_toggle_admin', $template);
        self::assertStringContainsString("csrf_token('admin_toggle_admin')", $template);
    }

    public function testAdminPermissionQueueRouteRequiresAdminRole(): void
    {
        $method = (new ReflectionClass(AdminController::class))->getMethod('queue');

        $routeAttributes = $method->getAttributes(Route::class);
        self::assertCount(1, $routeAttributes);

        $route = $routeAttributes[0]->newInstance();
        self::assertSame('/admin/queue', $route->path);
        self::assertSame('app_admin_permission_queue', $route->name);
        self::assertSame(['GET'], $route->methods);

        $grantAttributes = $method->getAttributes(IsGranted::class);
        self::assertCount(1, $grantAttributes);
        self::assertSame('ROLE_ADMIN', $grantAttributes[0]->newInstance()->attribute);
    }

    public function testAdminPermissionQueueApproveRouteRequiresAdminRoleAndPost(): void
    {
        $method = (new ReflectionClass(AdminController::class))->getMethod('approve');

        $routeAttributes = $method->getAttributes(Route::class);
        self::assertCount(1, $routeAttributes);

        $route = $routeAttributes[0]->newInstance();
        self::assertSame('/admin/queue/{id}/approve', $route->path);
        self::assertSame('app_admin_permission_queue_approve', $route->name);
        self::assertSame(['POST'], $route->methods);

        $grantAttributes = $method->getAttributes(IsGranted::class);
        self::assertCount(1, $grantAttributes);
        self::assertSame('ROLE_ADMIN', $grantAttributes[0]->newInstance()->attribute);
    }

    public function testAdminPermissionQueueDenyRouteRequiresAdminRoleAndPost(): void
    {
        $method = (new ReflectionClass(AdminController::class))->getMethod('deny');

        $routeAttributes = $method->getAttributes(Route::class);
        self::assertCount(1, $routeAttributes);

        $route = $routeAttributes[0]->newInstance();
        self::assertSame('/admin/queue/{id}/deny', $route->path);
        self::assertSame('app_admin_permission_queue_deny', $route->name);
        self::assertSame(['POST'], $route->methods);

        $grantAttributes = $method->getAttributes(IsGranted::class);
        self::assertCount(1, $grantAttributes);
        self::assertSame('ROLE_ADMIN', $grantAttributes[0]->newInstance()->attribute);
    }

    public function testPermissionQueueTemplateShowsRequestFieldsAndCsrfProtectedActions(): void
    {
        $template = file_get_contents(
            dirname(__DIR__, 2).'/templates/tools/admin_panel/queue.html.twig'
        );

        self::assertIsString($template);
        self::assertStringContainsString('req.email', $template);
        self::assertStringContainsString('req.currentPermissions', $template);
        self::assertStringContainsString('req.requestedPermissions', $template);
        self::assertStringContainsString("csrf_token('admin_queue_action')", $template);
        self::assertStringContainsString('app_admin_permission_queue_approve', $template);
        self::assertStringContainsString('app_admin_permission_queue_deny', $template);
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
