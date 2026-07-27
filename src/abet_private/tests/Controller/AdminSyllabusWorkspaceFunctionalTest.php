<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Entity\Program;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFunction;

final class AdminSyllabusWorkspaceFunctionalTest extends TestCase
{
    private Environment $twig;
    private Program $program;

    protected function setUp(): void
    {
        $template = file_get_contents(
            dirname(__DIR__, 2).'/templates/syllabus_template/admin/index.html.twig',
        );
        self::assertIsString($template);

        $this->twig = new Environment(new ArrayLoader([
            'base.html.twig' => '{% block title %}{% endblock %}{% block styles %}{% endblock %}{% block body %}{% endblock %}',
            'syllabus_template/admin/index.html.twig' => $template,
        ]));
        $this->twig->addFunction(new TwigFunction(
            'path',
            static fn (string $route, array $parameters = []): string =>
                '/'.$route.($parameters === [] ? '' : '?'.http_build_query($parameters)),
        ));
        $this->twig->addFunction(new TwigFunction(
            'csrf_token',
            static fn (string $tokenId): string => 'token-'.$tokenId,
        ));

        $this->program = new Program('Computer Science', 'BS', '2026');
        (new \ReflectionProperty($this->program, 'id'))->setValue($this->program, 1);
    }

    public function testProgramSelectorAutomaticallyUpdatesTheActiveView(): void
    {
        $html = $this->renderView('offerings');

        self::assertStringContainsString('id="readiness-program"', $html);
        self::assertStringContainsString('onchange="this.form.submit()"', $html);
        self::assertStringContainsString('name="view" value="offerings"', $html);
        self::assertStringContainsString('_fragment=syllabus-workspace', $html);
        self::assertStringNotContainsString('Show summary', $html);
    }

    public function testSharedTemplatesViewIsFocusedAndProgramScoped(): void
    {
        $html = $this->renderView('shared');

        self::assertStringContainsString('id="syllabus-workspace" class="syllabus-workspace-tabs"', $html);
        self::assertStringContainsString('Needs attention', $html);
        self::assertStringNotContainsString('Action required', $html);
        self::assertStringContainsString('id="shared-view-heading"', $html);
        self::assertStringContainsString('id="completeness" name="completeness" onchange="this.form.submit()"', $html);
        self::assertStringNotContainsString('Apply filter', $html);
        self::assertStringContainsString('No shared templates in this program', $html);
        self::assertStringNotContainsString('id="offerings-view-heading"', $html);
        self::assertStringNotContainsString('id="appendix-view-heading"', $html);
    }

    public function testCourseOfferingsViewSeparatesPendingReviewAndOfferingInventory(): void
    {
        $html = $this->renderView('offerings');

        self::assertStringContainsString('id="offerings-view-heading"', $html);
        self::assertStringContainsString('id="pending-review"', $html);
        self::assertStringContainsString('id="review-history"', $html);
        self::assertStringContainsString('No faculty submissions are waiting', $html);
        self::assertStringContainsString('No term-specific faculty offerings exist', $html);
        self::assertStringContainsString('No completed faculty reviews exist', $html);
        self::assertStringNotContainsString('id="shared-view-heading"', $html);
    }

    public function testAppendixAViewShowsSharedBaselineAndOfferingReportingContexts(): void
    {
        $html = $this->renderView('appendix_a');

        self::assertStringContainsString('id="appendix-view-heading"', $html);
        self::assertStringContainsString('Published shared baselines and approved course-offering syllabi', $html);
        self::assertStringContainsString('<h3>Shared baselines</h3>', $html);
        self::assertStringContainsString('No published shared baselines', $html);
        self::assertStringContainsString('<h3>Course offerings</h3>', $html);
        self::assertStringContainsString('No approved course-offering syllabi', $html);
        self::assertStringNotContainsString('id="offerings-view-heading"', $html);
    }

    private function renderView(string $activeView): string
    {
        return $this->twig->render('syllabus_template/admin/index.html.twig', [
            'templates' => [],
            'completenessFilter' => '',
            'pendingSubmissions' => [],
            'reviewedSubmissions' => [],
            'pendingReviewCount' => 0,
            'readinessCounts' => [
                'Ready' => 0,
                'Blocked' => 0,
                'Awaiting review' => 0,
                'Missing' => 0,
            ],
            'allTargetCount' => 0,
            'reportReadyCount' => 0,
            'readinessProgram' => $this->program,
            'readinessPrograms' => [[
                'program_id' => 1,
                'program_name' => 'Computer Science',
                'program_code' => 'BS',
                'program_year' => '2026',
            ]],
            'activeView' => $activeView,
            'offeringRows' => [],
            'appendixRows' => [],
            'sharedAppendixTemplates' => [],
        ]);
    }
}
