<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AppendixAReportWiringTest extends TestCase
{
    public function testLongReportForwardsLifecycleAppendixContract(): void
    {
        $srcRoot = dirname(__DIR__, 3);
        $page = file_get_contents($srcRoot.'/public/report-generator/index.php');
        $handler = file_get_contents($srcRoot.'/public/report-generator/generateLR.php');
        $api = file_get_contents($srcRoot.'/report_generation_api/report_generation_api.py');

        self::assertIsString($page);
        self::assertIsString($handler);
        self::assertIsString($api);
        self::assertStringContainsString('id="appendixContractFile"', $page);
        self::assertStringContainsString(
            "fd.append('appendix_a_contract[]', file)",
            $page,
        );
        self::assertStringContainsString("\$_FILES['appendix_a_contract']", $handler);
        self::assertStringContainsString("'appendix_a_contract' => \$appendixAContract", $handler);
        self::assertStringContainsString('appendix_a_contract: Dict[str, Any]', $api);
        self::assertStringContainsString(
            'validate_contract(request.appendix_a_contract)',
            $api,
        );
        self::assertStringContainsString(
            'appendix_a_contract=appendix_a_contract',
            $api,
        );
    }

    public function testAppendixRendererCannotFallBackToLegacySyllabusStorage(): void
    {
        $srcRoot = dirname(__DIR__, 3);
        $renderer = file_get_contents(
            $srcRoot.'/report_generation_api/report/appendices/appendix_a.py',
        );
        $dataAdapter = $srcRoot.'/report_generation_api/report/data/appendix_a_data.py';

        self::assertIsString($renderer);
        self::assertStringContainsString(
            'questionnaire.appendix_a_contract',
            $renderer,
        );
        self::assertStringNotContainsString('appendix_a_data', $renderer);
        self::assertStringNotContainsString('course_syllabi', $renderer);
        self::assertFileDoesNotExist($dataAdapter);
    }
}
