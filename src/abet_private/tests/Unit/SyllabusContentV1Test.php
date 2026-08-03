<?php

namespace Tests\Unit;

use App\Entity\Program;
use App\Entity\SyllabusTemplate\CommonCourse;
use App\Entity\SyllabusTemplate\DeliveryType;
use App\Entity\SyllabusTemplate\ProposalOrigin;
use App\Entity\SyllabusTemplate\RevisionAuthorType;
use App\Entity\SyllabusTemplate\SyllabusContentV1;
use App\Entity\SyllabusTemplate\TemplateSubmission;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class SyllabusContentV1Test extends TestCase
{
    public function testLegacyContentIsNormalizedWithoutDiscardingExtensions(): void
    {
        $content = SyllabusContentV1::normalize([
            'creditHours' => '3',
            'courseCoordinators' => [' First Coordinator ', 'Second Coordinator', 'First Coordinator'],
            'creditCategorization' => ' engineering ',
            'catalogDescription' => ' Description ',
            'courseOutcomes' => [' Outcome one '],
            'topics' => [' Testing '],
            'custom_schema_field' => ['preserve-me'],
        ]);

        self::assertSame('1.0', $content['schema_version']);
        self::assertSame(3.0, $content['credits']);
        self::assertSame(['First Coordinator', 'Second Coordinator'], $content['course_coordinators']);
        self::assertSame('engineering', $content['credit_category']);
        self::assertSame('Description', $content['catalog_description']);
        self::assertSame(['Outcome one'], $content['course_outcomes']);
        self::assertSame(['Testing'], $content['topics_covered']);
        self::assertSame(['preserve-me'], $content['custom_schema_field']);
        self::assertArrayNotHasKey('creditHours', $content);
        self::assertArrayNotHasKey('topics', $content);
    }

    public function testCanonicalValueWinsWhenLegacyAliasIsAlsoPresent(): void
    {
        $content = SyllabusContentV1::normalize([
            'credits' => 4,
            'creditHours' => 3,
        ]);

        self::assertSame(4.0, $content['credits']);
        self::assertArrayNotHasKey('creditHours', $content);
    }

    public function testLegacyMissingFieldNamesAreNormalized(): void
    {
        self::assertSame(
            ['credits', 'course_coordinators', 'credit_category'],
            SyllabusContentV1::normalizeFieldNames([
                'creditHours',
                'courseCoordinators',
                'creditCategorization',
            ]),
        );
    }

    public function testNewRevisionPersistsCanonicalContentAndSchemaVersion(): void
    {
        $user = (new User())->setEmail('coordinator@example.edu');
        $course = new CommonCourse(
            new Program('Computer Science', 'BS', '2026'),
            'CSE',
            '360',
            'Software Engineering',
            DeliveryType::InPerson,
        );
        $submission = new TemplateSubmission($course, $user, ProposalOrigin::CoordinatorCreated);

        $revision = $submission->addRevision($user, RevisionAuthorType::Coordinator, [
            'creditHours' => 3,
            'courseCoordinators' => ['Coordinator'],
            'creditCategorization' => 'engineering',
        ]);

        self::assertSame('1.0', $revision->getSchemaVersion());
        self::assertSame(3.0, $revision->getContent()['credits']);
        self::assertArrayNotHasKey('creditHours', $revision->getContent());
    }

    public function testSharedJsonSchemaDeclaresTheSameVersion(): void
    {
        $path = dirname(__DIR__, 3).'/shared/contracts/syllabus_content_v1.schema.json';
        $schema = json_decode((string)file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(SyllabusContentV1::VERSION, $schema['properties']['schema_version']['const']);
        self::assertArrayHasKey('course_outcomes', $schema['properties']);
        self::assertArrayHasKey('topics_covered', $schema['properties']);
    }
}
