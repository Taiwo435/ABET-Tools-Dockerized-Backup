<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SyllabusLifecycleUiTest extends TestCase
{
    public function testSharedBadgePartialExplainsTargetReadinessAppendixAndSource(): void
    {
        $partial = file_get_contents(dirname(__DIR__, 2).'/templates/syllabus_template/_lifecycle_badges.html.twig');
        $styles = file_get_contents(dirname(__DIR__, 3).'/public/assets/css/syllabus-lifecycle.css');

        self::assertIsString($partial);
        self::assertIsString($styles);
        self::assertStringContainsString('Course offering', $partial);
        self::assertStringContainsString('Shared template', $partial);
        self::assertStringContainsString('Ready to submit', $partial);
        self::assertStringContainsString('Ready to publish', $partial);
        self::assertStringContainsString('Appendix A ready', $partial);
        self::assertStringContainsString('PDF extraction', $partial);
        self::assertStringContainsString('.syllabus-badge--offering', $styles);
        self::assertStringContainsString('.syllabus-lifecycle', $styles);
    }
}
