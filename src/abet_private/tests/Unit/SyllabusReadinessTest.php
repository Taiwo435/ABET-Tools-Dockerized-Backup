<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\ReadModel\SyllabusReadiness;
use App\ReadModel\SyllabusReadinessState;
use PHPUnit\Framework\TestCase;

final class SyllabusReadinessTest extends TestCase
{
    private array $courseInfo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->courseInfo = [
            'program_id' => 1,
            'course_id' => 101,
            'course_code' => 'CSE 310',
            'course_title' => 'Data Structures and Algorithms',
        ];
    }

    public function testDeriveNoSharedTemplate(): void
    {
        $readiness = SyllabusReadiness::fromDomainState($this->courseInfo, null, null);

        $this->assertSame('1', $readiness->getProgramId());
        $this->assertSame('101', $readiness->getCourseId());
        $this->assertSame('CSE 310', $readiness->getCourseCode());
        $this->assertSame('Data Structures and Algorithms', $readiness->getCourseTitle());
        $this->assertSame(SyllabusReadinessState::NoSharedTemplate, $readiness->getState());
        $this->assertEmpty($readiness->getMissingRequiredFields());
        $this->assertNull($readiness->getSyllabusId());
        $this->assertNull($readiness->getUpdatedAt());
        $this->assertSame('Missing', $readiness->getState()->getCategory());
    }

    public function testDeriveSharedTemplateIncomplete(): void
    {
        $templateInfo = [
            'id' => 201,
            'is_published' => false,
            'catalog_description' => '', // missing
            'course_type' => 'R',
            'credits' => null, // missing
            'contact_hours' => '3 hours',
            'specific_goals' => [], // missing
            'student_outcomes' => ['1', '2'],
            'topics_covered' => null, // missing
        ];

        $readiness = SyllabusReadiness::fromDomainState($this->courseInfo, $templateInfo, null);

        $this->assertSame(SyllabusReadinessState::SharedTemplateIncomplete, $readiness->getState());
        $this->assertSame(201, $readiness->getSyllabusId());
        $this->assertEquals(
            ['Catalog Description', 'Credits', 'Specific Goals', 'Topics Covered'],
            $readiness->getMissingRequiredFields()
        );
        $this->assertSame('Blocked', $readiness->getState()->getCategory());
    }

    public function testDeriveSharedTemplatePublished(): void
    {
        $templateInfo = [
            'id' => 201,
            'is_published' => true,
            'catalog_description' => 'A course on algs.',
            'course_type' => 'R',
            'credits' => 3,
            'contact_hours' => '3 hours',
            'specific_goals' => ['Goal 1'],
            'student_outcomes' => ['1'],
            'topics_covered' => ['Topic 1'],
        ];

        $readiness = SyllabusReadiness::fromDomainState($this->courseInfo, $templateInfo, null);

        $this->assertSame(SyllabusReadinessState::SharedTemplatePublished, $readiness->getState());
        $this->assertEmpty($readiness->getMissingRequiredFields());
        $this->assertSame('Missing', $readiness->getState()->getCategory());
    }

    public function testDeriveFacultyDraftInProgress(): void
    {
        $templateInfo = [
            'id' => 201,
            'is_published' => true,
            'catalog_description' => 'A course on algs.',
            'course_type' => 'R',
            'credits' => 3,
            'contact_hours' => '3 hours',
            'specific_goals' => ['Goal 1'],
            'student_outcomes' => ['1'],
            'topics_covered' => ['Topic 1'],
        ];

        $draftInfo = [
            'id' => 301,
            'is_submitted' => false,
            'is_approved' => false,
            'denial_feedback' => null,
            'updated_at' => '2026-07-18T12:00:00Z',
        ];

        $readiness = SyllabusReadiness::fromDomainState($this->courseInfo, $templateInfo, $draftInfo);

        $this->assertSame(SyllabusReadinessState::FacultyDraftInProgress, $readiness->getState());
        $this->assertSame(301, $readiness->getSyllabusId());
        $this->assertNotNull($readiness->getUpdatedAt());
        $this->assertSame('2026-07-18T12:00:00+00:00', $readiness->getUpdatedAt()->format(\DateTimeInterface::ATOM));
        $this->assertSame('Blocked', $readiness->getState()->getCategory());
    }

    public function testDeriveSubmittedForReview(): void
    {
        $templateInfo = [
            'id' => 201,
            'is_published' => true,
            'catalog_description' => 'A course on algs.',
            'course_type' => 'R',
            'credits' => 3,
            'contact_hours' => '3 hours',
            'specific_goals' => ['Goal 1'],
            'student_outcomes' => ['1'],
            'topics_covered' => ['Topic 1'],
        ];

        $draftInfo = [
            'id' => 301,
            'is_submitted' => true,
            'is_approved' => false,
            'denial_feedback' => null,
            'updated_at' => '2026-07-18T13:00:00Z',
        ];

        $readiness = SyllabusReadiness::fromDomainState($this->courseInfo, $templateInfo, $draftInfo);

        $this->assertSame(SyllabusReadinessState::SubmittedForReview, $readiness->getState());
        $this->assertSame('Awaiting review', $readiness->getState()->getCategory());
    }

    public function testDeriveDeniedWithFeedback(): void
    {
        $templateInfo = [
            'id' => 201,
            'is_published' => true,
            'catalog_description' => 'A course on algs.',
            'course_type' => 'R',
            'credits' => 3,
            'contact_hours' => '3 hours',
            'specific_goals' => ['Goal 1'],
            'student_outcomes' => ['1'],
            'topics_covered' => ['Topic 1'],
        ];

        $draftInfo = [
            'id' => 301,
            'is_submitted' => true,
            'is_approved' => false,
            'denial_feedback' => 'Please update specific goals mapping.',
            'updated_at' => '2026-07-18T14:00:00Z',
        ];

        $readiness = SyllabusReadiness::fromDomainState($this->courseInfo, $templateInfo, $draftInfo);

        $this->assertSame(SyllabusReadinessState::DeniedWithFeedback, $readiness->getState());
        $this->assertSame('Blocked', $readiness->getState()->getCategory());
    }

    public function testDeriveApprovedAndReady(): void
    {
        $templateInfo = [
            'id' => 201,
            'is_published' => true,
            'catalog_description' => 'A course on algs.',
            'course_type' => 'R',
            'credits' => 3,
            'contact_hours' => '3 hours',
            'specific_goals' => ['Goal 1'],
            'student_outcomes' => ['1'],
            'topics_covered' => ['Topic 1'],
        ];

        $draftInfo = [
            'id' => 301,
            'is_submitted' => true,
            'is_approved' => true,
            'denial_feedback' => null,
            'updated_at' => '2026-07-18T15:00:00Z',
        ];

        $readiness = SyllabusReadiness::fromDomainState($this->courseInfo, $templateInfo, $draftInfo);

        $this->assertSame(SyllabusReadinessState::ApprovedAndReadyForAppendixA, $readiness->getState());
        $this->assertSame('Ready', $readiness->getState()->getCategory());
    }
}
