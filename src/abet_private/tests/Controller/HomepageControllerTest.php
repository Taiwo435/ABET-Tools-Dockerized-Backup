<?php

declare(strict_types=1);

namespace Tests\Controller;

use PHPUnit\Framework\TestCase;

final class HomepageControllerTest extends TestCase
{
    public function testReadinessCardUsesProgramSelectionRouteWithoutHardcodedProgram(): void
    {
        $template = file_get_contents(dirname(__DIR__, 2).'/templates/homepage/home.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString("path('app_program_readiness_select')", $template);
        self::assertStringContainsString('Open Syllabus Readiness', $template);
        self::assertStringNotContainsString("programId: 1", $template);
    }
}
