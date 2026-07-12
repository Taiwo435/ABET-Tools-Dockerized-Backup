<?php

namespace Tests\Unit;

use App\Controller\SyllabusTemplate\AdminSyllabusTemplateController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AdminSyllabusTemplateControllerTest extends TestCase
{
    public function testIndexRouteIsAdminOnly(): void
    {
        $method = (new ReflectionClass(AdminSyllabusTemplateController::class))->getMethod('index');
        $route = $method->getAttributes(Route::class)[0]->newInstance();
        $grant = $method->getAttributes(IsGranted::class)[0]->newInstance();

        self::assertSame('/admin/syllabus-templates', $route->path);
        self::assertSame('app_admin_syllabus_templates', $route->name);
        self::assertSame(['GET'], $route->methods);
        self::assertSame('ROLE_ADMIN', $grant->attribute);
    }

    public function testIndexExposesCompletenessFilterAndMissingFields(): void
    {
        $template = file_get_contents(dirname(__DIR__, 2).'/templates/syllabus_template/admin/index.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString('Shared Syllabus Templates', $template);
        self::assertStringContainsString('name="completeness"', $template);
        self::assertStringContainsString('revision.completenessStatus.value', $template);
        self::assertStringContainsString('revision.missingFields', $template);
        self::assertStringContainsString('template.status.value', $template);
    }

    public function testAdminPanelLinksToSharedSyllabusTemplates(): void
    {
        $template = file_get_contents(dirname(__DIR__, 2).'/templates/tools/admin_panel/home.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString("path('app_admin_syllabus_templates')", $template);
        self::assertStringContainsString('Shared Syllabus Templates', $template);
    }

    #[DataProvider('coordinatorWriteRoutes')]
    public function testCoordinatorWriteRoutesAreAdminOnly(string $methodName, string $path, string $routeName, array $methods): void
    {
        $method = (new ReflectionClass(AdminSyllabusTemplateController::class))->getMethod($methodName);
        $route = $method->getAttributes(Route::class)[0]->newInstance();
        $grant = $method->getAttributes(IsGranted::class)[0]->newInstance();

        self::assertSame($path, $route->path);
        self::assertSame($routeName, $route->name);
        self::assertSame($methods, $route->methods);
        self::assertSame('ROLE_ADMIN', $grant->attribute);
    }

    public static function coordinatorWriteRoutes(): array
    {
        return [
            'create' => ['create', '/admin/syllabus-templates/new', 'app_admin_syllabus_templates_new', ['GET', 'POST']],
            'edit' => ['edit', '/admin/syllabus-templates/{id}/edit', 'app_admin_syllabus_templates_edit', ['GET', 'POST']],
            'publish' => ['publish', '/admin/syllabus-templates/{id}/publish', 'app_admin_syllabus_templates_publish', ['POST']],
        ];
    }

    public function testCoordinatorFormsExposeCreateEditAndPublishActions(): void
    {
        $index = file_get_contents(dirname(__DIR__, 2).'/templates/syllabus_template/admin/index.html.twig');
        $form = file_get_contents(dirname(__DIR__, 2).'/templates/syllabus_template/admin/form.html.twig');

        self::assertIsString($index);
        self::assertIsString($form);
        self::assertStringContainsString("path('app_admin_syllabus_templates_new')", $index);
        self::assertStringContainsString("path('app_admin_syllabus_templates_edit'", $index);
        self::assertStringContainsString("path('app_admin_syllabus_templates_publish'", $form);
        self::assertStringContainsString('submission.workingRevision.missingFields', $form);
        self::assertStringContainsString('Publish Current Revision', $form);
    }
}
