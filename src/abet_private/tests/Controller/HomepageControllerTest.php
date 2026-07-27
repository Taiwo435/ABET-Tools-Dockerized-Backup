<?php

declare(strict_types=1);

namespace Tests\Controller;

use PHPUnit\Framework\TestCase;

final class HomepageControllerTest extends TestCase
{
    public function testStandaloneSyllabusReadinessDestinationIsRetired(): void
    {
        $template = file_get_contents(dirname(__DIR__, 2).'/templates/homepage/home.html.twig');

        self::assertIsString($template);
        self::assertStringNotContainsString("path('app_program_readiness_select')", $template);
        self::assertStringNotContainsString('Syllabus Status Dashboard', $template);
        self::assertStringNotContainsString('Open Syllabus Readiness', $template);
    }
}
