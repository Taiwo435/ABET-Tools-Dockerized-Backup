<?php

namespace Tests\Controller;

use App\Controller\AdminTemplatesController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AdminTemplatesControllerTest extends TestCase
{
    public function testTemplatesRouteRequiresAdminRole(): void
    {
        self::assertTrue(
            class_exists(AdminTemplatesController::class),
            'The admin templates controller does not exist.'
        );

        $method = (new ReflectionClass(AdminTemplatesController::class))->getMethod('index');

        $routeAttributes = $method->getAttributes(Route::class);
        self::assertCount(1, $routeAttributes);

        $route = $routeAttributes[0]->newInstance();
        self::assertSame('/admin/templates', $route->path);
        self::assertSame('app_admin_templates', $route->name);
        self::assertSame(['GET'], $route->methods);

        $grantAttributes = $method->getAttributes(IsGranted::class);
        self::assertCount(1, $grantAttributes);
        self::assertSame('ROLE_ADMIN', $grantAttributes[0]->newInstance()->attribute);
    }

    public function testTemplatesPageListsExistingTemplatesAndStoredCourseDrafts(): void
    {
        $template = file_get_contents(
            dirname(__DIR__, 2).'/templates/tools/admin_panel/templates.html.twig'
        );

        self::assertIsString($template);
        self::assertStringContainsString('Canvas Shell Templates', $template);
        self::assertStringContainsString('Existing Templates', $template);
        self::assertStringContainsString('Stored Course Drafts', $template);
        self::assertStringContainsString('template.courseSubject', $template);
        self::assertStringContainsString('template.courseNumber', $template);
        self::assertStringContainsString('template.courseName', $template);
        self::assertStringContainsString('template.updatedAt', $template);
        self::assertStringContainsString('draft.courseSubject', $template);
        self::assertStringContainsString('draft.courseNumber', $template);
        self::assertStringContainsString('draft.courseName', $template);
        self::assertStringContainsString('draft.instructors', $template);
    }

    public function testAdminPanelLinksToTemplatesPage(): void
    {
        $template = file_get_contents(
            dirname(__DIR__, 2).'/templates/tools/admin_panel/home.html.twig'
        );

        self::assertIsString($template);
        self::assertStringContainsString("path('app_admin_templates')", $template);
        self::assertStringContainsString('Canvas Shell Templates', $template);
    }
}
