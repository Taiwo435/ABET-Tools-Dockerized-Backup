<?php

namespace Tests\Unit;

use App\Entity\Program;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProgramInitialsTest extends TestCase
{
    #[DataProvider('programNames')]
    public function testProgramInitialsAreDerivedFromTheProgramName(string $name, string $expected): void
    {
        $program = new Program($name, 'BS', '2026');

        self::assertSame($expected, $program->getInitials());
    }

    public static function programNames(): array
    {
        return [
            'computer science' => ['Computer Science', 'CS'],
            'computer systems engineering' => ['Computer Systems Engineering', 'CSE'],
            'software engineering' => ['Software Engineering', 'SE'],
        ];
    }
}
