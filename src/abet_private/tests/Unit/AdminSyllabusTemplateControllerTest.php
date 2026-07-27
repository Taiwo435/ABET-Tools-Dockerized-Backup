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
        self::assertStringContainsString("syllabus_template/_lifecycle_badges.html.twig", $template);
        self::assertStringContainsString('revision.coordinatorPublicationBlockingFields', $template);
        self::assertStringContainsString('template.status.value', $template);
        self::assertStringNotContainsString('template.commonCourse.program.initials', $template);
        self::assertStringContainsString('id="pending-review"', $template);
        self::assertStringContainsString('{{ pendingReviewCount }}', $template);
        self::assertStringContainsString('Shared templates are reusable baselines', $template);
        self::assertStringContainsString('Publication readiness', $template);
        self::assertStringContainsString('Program syllabus status', $template);
        self::assertStringContainsString('class="metrics-grid"', $template);
        self::assertStringContainsString("readinessCounts['Awaiting review']", $template);
        self::assertStringContainsString('name="program"', $template);
        self::assertStringNotContainsString("path('app_program_readiness'", $template);
        self::assertStringContainsString("view: 'appendix_a'", $template);
        self::assertStringContainsString('metric-card--static metric-blocked', $template);
        self::assertStringContainsString('metric-card--static metric-missing', $template);
        self::assertStringContainsString('onchange="this.form.submit()"', $template);
        self::assertStringNotContainsString('Show summary', $template);
        self::assertStringContainsString('aria-label="Syllabus workspace views"', $template);
        self::assertStringContainsString('Course offerings &amp; review', $template);
        self::assertStringContainsString('Appendix A readiness', $template);
        self::assertStringContainsString("activeView == 'shared'", $template);
        self::assertStringContainsString("activeView == 'offerings'", $template);
        self::assertStringContainsString("activeView == 'appendix_a'", $template);
        self::assertStringContainsString('pendingSubmissions', $template);
        self::assertStringContainsString('reviewedSubmissions', $template);
        self::assertStringContainsString('id="review-history"', $template);
        self::assertStringContainsString('offeringRows', $template);
        self::assertStringContainsString('appendixRows', $template);
    }

    public function testAdminPanelLinksToSharedSyllabusTemplates(): void
    {
        $template = file_get_contents(dirname(__DIR__, 2).'/templates/tools/admin_panel/home.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString("path('app_admin_syllabus_templates')", $template);
        self::assertStringContainsString('Shared Syllabus Templates', $template);
    }

    public function testAppendixAExportRouteIsAdminOnlyAndVersionedByFilename(): void
    {
        $method = (new ReflectionClass(AdminSyllabusTemplateController::class))->getMethod('exportAppendixA');
        $route = $method->getAttributes(Route::class)[0]->newInstance();
        $grant = $method->getAttributes(IsGranted::class)[0]->newInstance();
        $source = file_get_contents(dirname(__DIR__, 2).'/src/Controller/SyllabusTemplate/AdminSyllabusTemplateController.php');

        self::assertSame('/admin/syllabus-templates/{id}/appendix-a.json', $route->path);
        self::assertSame('app_admin_syllabus_template_appendix_a_export', $route->name);
        self::assertSame(['GET'], $route->methods);
        self::assertSame('ROLE_ADMIN', $grant->attribute);
        self::assertIsString($source);
        self::assertStringContainsString('AppendixAReportExportBoundary $exporter', $source);
        self::assertStringContainsString("'blocking_fields'", $source);
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
        self::assertStringContainsString("path('app_admin_syllabus_templates_new', {program: readinessProgram.id})", $index);
        self::assertStringContainsString("path('app_admin_syllabus_templates_edit'", $index);
        self::assertStringContainsString("path('app_admin_syllabus_templates_publish'", $form);
        self::assertStringContainsString("path('app_admin_syllabus_templates_publish'", $index);
        self::assertStringContainsString("path('app_admin_syllabus_templates_edit'", $index);
        self::assertStringContainsString('submission.workingRevision.coordinatorPublicationBlockingFields', $form);
        self::assertStringContainsString('Publish Current Revision', $form);
        self::assertStringContainsString('form.program is defined', $form);
        self::assertStringContainsString('Publish now', $index);
        self::assertStringContainsString('Create new revision', $index);
        self::assertStringContainsString("template.origin.value == 'faculty_submission'", $index);
        self::assertStringContainsString('approved faculty revision; the faculty submission remains unchanged', $form);
        self::assertStringContainsString('form.contactHours', $form);
        self::assertStringContainsString('form.instructors', $form);
        self::assertStringContainsString('form.textbooks', $form);
        self::assertStringContainsString('form.courseType', $form);
        self::assertStringContainsString('form.specificGoals', $form);
        self::assertStringContainsString('form.studentOutcomes', $form);
        self::assertStringContainsString('form.topicsCovered', $form);
        self::assertStringContainsString('submission.workingRevision.appendixAReady', $form);
    }

    public function testAdminManagerIncludesCurrentApprovedFacultyTemplates(): void
    {
        $repository = file_get_contents(dirname(__DIR__, 2).'/src/Repository/SyllabusTemplate/TemplateSubmissionRepository.php');
        $controller = file_get_contents(dirname(__DIR__, 2).'/src/Controller/SyllabusTemplate/AdminSyllabusTemplateController.php');

        self::assertIsString($repository);
        self::assertIsString($controller);
        self::assertStringContainsString('findManagedTemplates', $repository);
        self::assertStringContainsString('submission.approvedRevision = currentApprovedRevision', $repository);
        self::assertStringContainsString('ProposalOrigin::CoordinatorCreated', $repository);
        self::assertStringContainsString('SubmissionStatus::Draft', $repository);
        self::assertStringContainsString('SubmissionKind::SharedTemplate', $repository);
        self::assertStringContainsString('$submissions->findManagedTemplates($filter, $selectedProgram)', $controller);
        self::assertStringContainsString('$submissions->findPendingFacultyReviews($selectedProgram)', $controller);
        self::assertStringContainsString("'activeView' => \$activeView", $controller);
        self::assertStringContainsString("'offeringRows' => \$offeringRows", $controller);
        self::assertStringContainsString("'appendixRows' => \$appendixRows", $controller);
        self::assertStringContainsString("'reviewedSubmissions' => \$reviewedSubmissions", $controller);
        self::assertStringContainsString('$readiness->getReadinessRowsForProgram($selectedProgram->getId())', $controller);
        self::assertStringContainsString('SyllabusReadinessRepository::countRowsByCategory($readinessRows)', $controller);
        self::assertStringContainsString("'readinessProgram' => \$selectedProgram", $controller);
        self::assertStringContainsString("'readinessPrograms' => \$programs", $controller);
        self::assertStringContainsString('$this->revisions->saveCoordinatorRevision($submission, $user, $data)', $controller);
    }
}
