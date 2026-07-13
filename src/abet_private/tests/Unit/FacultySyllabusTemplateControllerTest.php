<?php

namespace Tests\Unit;

use App\Controller\SyllabusTemplate\FacultySyllabusTemplateController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class FacultySyllabusTemplateControllerTest extends TestCase
{
    #[DataProvider('facultyRoutes')]
    public function testFacultyRoutesRequireAnAuthenticatedUser(
        string $methodName,
        string $path,
        string $routeName,
        array $methods,
    ): void {
        $method = (new ReflectionClass(FacultySyllabusTemplateController::class))->getMethod($methodName);
        $route = $method->getAttributes(Route::class)[0]->newInstance();
        $grant = $method->getAttributes(IsGranted::class)[0]->newInstance();

        self::assertSame($path, $route->path);
        self::assertSame($routeName, $route->name);
        self::assertSame($methods, $route->methods);
        self::assertSame('ROLE_USER', $grant->attribute);
    }

    public static function facultyRoutes(): array
    {
        return [
            'index' => ['index', '/syllabus-templates', 'app_faculty_syllabus_templates', ['GET']],
            'use template' => ['useTemplate', '/syllabus-templates/{id}/use', 'app_faculty_syllabus_templates_use', ['GET', 'POST']],
            'edit' => ['edit', '/syllabus-templates/drafts/{id}/edit', 'app_faculty_syllabus_templates_edit', ['GET', 'POST']],
            'delete' => ['delete', '/syllabus-templates/drafts/{id}/delete', 'app_faculty_syllabus_templates_delete', ['POST']],
        ];
    }

    public function testFacultyTemplatesExposeSelectionDraftAndEditingUi(): void
    {
        $index = file_get_contents(dirname(__DIR__, 2).'/templates/syllabus_template/faculty/index.html.twig');
        $form = file_get_contents(dirname(__DIR__, 2).'/templates/syllabus_template/faculty/form.html.twig');
        $holdDelete = file_get_contents(dirname(__DIR__, 2).'/templates/syllabus_template/faculty/_hold_delete_behavior.html.twig');
        $homepage = file_get_contents(dirname(__DIR__, 2).'/templates/homepage/home.html.twig');

        self::assertIsString($index);
        self::assertIsString($form);
        self::assertIsString($holdDelete);
        self::assertIsString($homepage);
        self::assertStringContainsString("path('app_faculty_syllabus_templates_use'", $index);
        self::assertStringContainsString("path('app_faculty_syllabus_templates_edit'", $index);
        self::assertStringContainsString('Use template', $index);
        self::assertStringContainsString('Save working copy', $form);
        self::assertStringContainsString('Create my draft', $form);
        self::assertStringContainsString('Nothing is saved until', $form);
        self::assertStringContainsString("path('app_faculty_syllabus_templates_delete'", $form);
        self::assertStringContainsString("path('app_faculty_syllabus_templates_delete'", $index);
        self::assertStringContainsString('Hold to delete', $index);
        self::assertStringContainsString('Hold to delete', $form);
        self::assertStringContainsString('Deletions are permanent.', $index);
        self::assertStringContainsString('Deletions are permanent.', $form);
        self::assertStringNotContainsString('confirm(', $form);
        self::assertStringContainsString('const holdDuration = 2000', $holdDelete);
        self::assertStringContainsString("button.addEventListener('pointerdown', begin)", $holdDelete);
        self::assertStringContainsString("button.addEventListener('keydown', begin)", $holdDelete);
        self::assertStringContainsString('requestSubmit()', $holdDelete);
        self::assertStringContainsString("path('app_faculty_syllabus_templates')", $homepage);
    }
}
