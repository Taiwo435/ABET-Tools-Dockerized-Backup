<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Entity\SyllabusTemplate\SubmissionStatus;
use App\ReadModel\SyllabusReadiness;
use App\ReadModel\SyllabusReadinessState;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFunction;

final class ProgramReadinessFunctionalTest extends TestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        $template = file_get_contents(
            dirname(__DIR__, 2).'/templates/tools/program_readiness/index.html.twig',
        );
        self::assertIsString($template);

        $this->twig = new Environment(new ArrayLoader([
            'base.html.twig' => '{% block title %}{% endblock %}{% block styles %}{% endblock %}{% block body %}{% endblock %}',
            'tools/program_readiness/index.html.twig' => $template,
        ]));
        $this->twig->addFunction(new TwigFunction(
            'path',
            static fn (string $route, array $parameters = []): string =>
                '/'.$route.($parameters === [] ? '' : '?'.http_build_query($parameters)),
        ));
    }

    public function testOverviewSeparatesTargetWorkflowAndPurposeReadiness(): void
    {
        $html = $this->render([
            new SyllabusReadiness(
                '1',
                '101',
                'CSE 310',
                'Data Structures',
                SyllabusReadinessState::SharedTemplateReadyToPublish,
                syllabusId: 201,
                facultySubmittable: true,
                coordinatorPublishable: true,
                appendixAReady: false,
                appendixABlockingFields: ['contact_hours'],
            ),
            new SyllabusReadiness(
                '1',
                '101',
                'CSE 310',
                'Data Structures',
                SyllabusReadinessState::ApprovedAppendixAIncomplete,
                ['contact_hours', 'instructors'],
                301,
                new \DateTimeImmutable('2026-07-18T15:00:00Z'),
                [
                    'id' => 501,
                    'academic_year' => '2026-2027',
                    'term' => 'Fall',
                    'section' => '001',
                    'delivery_type' => 'in_person',
                ],
                true,
                [],
                true,
                [],
                false,
                ['contact_hours', 'instructors'],
                SubmissionStatus::Approved,
            ),
        ]);

        self::assertStringContainsString('Shared-template publication, offering workflow, and report readiness', $html);
        self::assertStringContainsString('Reusable common-course baseline', $html);
        self::assertStringContainsString('No offering submission', $html);
        self::assertStringContainsString('Fall 2026-2027', $html);
        self::assertStringContainsString('Section 001', $html);
        self::assertStringContainsString('In Person', $html);
        self::assertStringContainsString('Approved, Appendix A evidence incomplete', $html);
        self::assertStringContainsString('Ready to submit', $html);
        self::assertStringContainsString('Ready to publish', $html);
        self::assertStringContainsString('Evidence incomplete', $html);
        self::assertStringContainsString('Contact Hours', $html);
        self::assertStringContainsString('Instructors', $html);
    }

    public function testOverviewExposesCanonicalFiltersAndNumericSelectionRoute(): void
    {
        $html = $this->render([]);

        foreach (['category', 'target', 'workflow', 'faculty', 'coordinator', 'appendix_a'] as $parameter) {
            self::assertStringContainsString(sprintf('name="%s"', $parameter), $html);
        }

        self::assertStringContainsString('/app_program_readiness_select', $html);
        self::assertStringContainsString('?program=', $html);
        self::assertStringNotContainsString('PLACEHOLDER', $html);
        self::assertStringContainsString('/assets/css/readiness.css?v=20260727', $html);
        self::assertStringNotContainsString('class="metrics-grid"', $html);
        self::assertStringContainsString('colspan="7"', $html);
        self::assertStringContainsString('No matching syllabus targets', $html);
    }

    /** @param list<SyllabusReadiness> $rows */
    private function render(array $rows): string
    {
        return $this->twig->render('tools/program_readiness/index.html.twig', [
            'program_id' => 1,
            'program' => [
                'program_id' => 1,
                'program_name' => 'Computer Science',
                'program_code' => 'BS',
                'program_year' => '2026',
            ],
            'programs' => [[
                'program_id' => 1,
                'program_name' => 'Computer Science',
                'program_code' => 'BS',
                'program_year' => '2026',
            ]],
            'rows' => $rows,
            'active_filter' => null,
            'active_filters' => [
                'category' => null,
                'target' => null,
                'workflow' => null,
                'faculty' => '',
                'coordinator' => '',
                'appendix_a' => '',
            ],
            'workflow_statuses' => SubmissionStatus::cases(),
        ]);
    }
}
