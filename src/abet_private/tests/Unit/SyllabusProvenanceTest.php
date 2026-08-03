<?php

namespace Tests\Unit;

use App\Entity\Program;
use App\Entity\SyllabusTemplate\CommonCourse;
use App\Entity\SyllabusTemplate\DeliveryType;
use App\Entity\SyllabusTemplate\ProposalOrigin;
use App\Entity\SyllabusTemplate\RevisionAuthorType;
use App\Entity\SyllabusTemplate\SyllabusProvenanceV1;
use App\Entity\SyllabusTemplate\SyllabusRevisionSource;
use App\Entity\SyllabusTemplate\TemplateSubmission;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class SyllabusProvenanceTest extends TestCase
{
    public function testRevisionDefaultsToManualEntryProvenance(): void
    {
        $user = (new User())->setEmail('faculty@example.edu');
        $submission = new TemplateSubmission($this->course(), $user, ProposalOrigin::FacultySubmission);
        $revision = $submission->addRevision(
            $user,
            RevisionAuthorType::Faculty,
            ['credits' => 3],
        );

        self::assertSame(SyllabusRevisionSource::ManualEntry, $revision->getSourceType());
        self::assertSame(SyllabusProvenanceV1::VERSION, $revision->getSourceProvenance()->toArray()['schema_version']);
    }

    public function testPdfProvenanceRecordsDocumentExtractionAndCanonicalFieldSources(): void
    {
        $provenance = SyllabusProvenanceV1::pdfExtraction(
            'CSE-360-Syllabus.pdf',
            str_repeat('a', 64),
            128_000,
            [
                'contactHours' => ['page' => 2, 'confidence' => 0.93, 'method' => 'layout_model'],
            ],
            'syllabus-parser',
            '2026.7',
            new \DateTimeImmutable('2026-07-27T10:00:00+00:00'),
        );
        $data = $provenance->toArray();

        self::assertSame(SyllabusRevisionSource::PdfExtraction, $provenance->getSourceType());
        self::assertSame('application/pdf', $data['source_document']['media_type']);
        self::assertSame(str_repeat('a', 64), $data['source_document']['sha256']);
        self::assertSame('2026-07-27T10:00:00+00:00', $data['extraction']['extracted_at']);
        self::assertSame(2, $data['fields']['contact_hours']['page']);
        self::assertSame(0.93, $data['fields']['contact_hours']['confidence']);
        self::assertArrayNotHasKey('contactHours', $data['fields']);
    }

    public function testPdfProvenanceRejectsInvalidConfidence(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('confidence');

        SyllabusProvenanceV1::pdfExtraction(
            'syllabus.pdf',
            str_repeat('b', 64),
            100,
            ['credits' => ['confidence' => 1.5]],
        );
    }

    private function course(): CommonCourse
    {
        return new CommonCourse(
            new Program('Computer Science', 'BS', '2026'),
            'CSE',
            '360',
            'Software Engineering',
            DeliveryType::InPerson,
        );
    }
}
