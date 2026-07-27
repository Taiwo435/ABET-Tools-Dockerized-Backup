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
            'faculty_submittable' => false,
            'faculty_submission_blocking_fields' => ['credits', 'course_coordinators'],
            'coordinator_publishable' => false,
            'coordinator_publication_blocking_fields' => ['credits', 'course_coordinators'],
            'appendix_a_ready' => false,
            'appendix_a_blocking_fields' => ['credits', 'contact_hours', 'instructors'],
        ];

        $readiness = SyllabusReadiness::fromDomainState($this->courseInfo, $templateInfo, null);

        $this->assertSame(SyllabusReadinessState::SharedTemplateIncomplete, $readiness->getState());
        $this->assertSame(201, $readiness->getSyllabusId());
        $this->assertEquals(
            ['credits', 'course_coordinators'],
            $readiness->getMissingRequiredFields()
        );
        $this->assertFalse($readiness->isCoordinatorPublishable());
        $this->assertSame(
            ['credits', 'course_coordinators'],
            $readiness->getCoordinatorPublicationBlockingFields(),
        );
        $this->assertSame('Blocked', $readiness->getState()->getCategory());
    }

    public function testDeriveSharedTemplatePublished(): void
    {
        $templateInfo = $this->publishedTemplateInfo();

        $readiness = SyllabusReadiness::fromDomainState($this->courseInfo, $templateInfo, null);

        $this->assertSame(SyllabusReadinessState::SharedTemplatePublished, $readiness->getState());
        $this->assertEmpty($readiness->getMissingRequiredFields());
        $this->assertSame('Missing', $readiness->getState()->getCategory());
    }

    public function testDeriveFacultyDraftInProgress(): void
    {
        $templateInfo = $this->publishedTemplateInfo();

        $draftInfo = [
            'id' => 301,
            'is_submitted' => false,
            'is_approved' => false,
            'denial_feedback' => null,
            'updated_at' => '2026-07-18T12:00:00Z',
            'faculty_submittable' => false,
            'faculty_submission_blocking_fields' => ['credits'],
            'appendix_a_ready' => false,
            'appendix_a_blocking_fields' => ['credits', 'contact_hours'],
        ];

        $readiness = SyllabusReadiness::fromDomainState($this->courseInfo, $templateInfo, $draftInfo);

        $this->assertSame(SyllabusReadinessState::FacultyDraftInProgress, $readiness->getState());
        $this->assertSame(301, $readiness->getSyllabusId());
        $this->assertNotNull($readiness->getUpdatedAt());
        $this->assertSame('2026-07-18T12:00:00+00:00', $readiness->getUpdatedAt()->format(\DateTimeInterface::ATOM));
        $this->assertFalse($readiness->isFacultySubmittable());
        $this->assertSame(['credits'], $readiness->getFacultySubmissionBlockingFields());
        $this->assertSame('Blocked', $readiness->getState()->getCategory());
    }

    public function testDeriveSubmittedForReview(): void
    {
        $templateInfo = $this->publishedTemplateInfo();

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
        $templateInfo = $this->publishedTemplateInfo();

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
        $templateInfo = $this->publishedTemplateInfo();

        $draftInfo = [
            'id' => 301,
            'is_submitted' => true,
            'is_approved' => true,
            'denial_feedback' => null,
            'updated_at' => '2026-07-18T15:00:00Z',
            'faculty_submittable' => true,
            'faculty_submission_blocking_fields' => [],
            'appendix_a_ready' => true,
            'appendix_a_blocking_fields' => [],
        ];

        $readiness = SyllabusReadiness::fromDomainState($this->courseInfo, $templateInfo, $draftInfo);

        $this->assertSame(SyllabusReadinessState::ApprovedAndReadyForAppendixA, $readiness->getState());
        $this->assertTrue($readiness->isAppendixAReady());
        $this->assertSame('Ready', $readiness->getState()->getCategory());
    }

    public function testApprovedRevisionCanRemainAppendixAIncomplete(): void
    {
        $draftInfo = [
            'id' => 301,
            'is_submitted' => true,
            'is_approved' => true,
            'denial_feedback' => null,
            'updated_at' => '2026-07-18T15:00:00Z',
            'faculty_submittable' => true,
            'faculty_submission_blocking_fields' => [],
            'appendix_a_ready' => false,
            'appendix_a_blocking_fields' => ['contact_hours', 'instructors'],
        ];

        $readiness = SyllabusReadiness::fromDomainState(
            $this->courseInfo,
            $this->publishedTemplateInfo(),
            $draftInfo,
        );

        $this->assertSame(SyllabusReadinessState::ApprovedAppendixAIncomplete, $readiness->getState());
        $this->assertFalse($readiness->isAppendixAReady());
        $this->assertSame(['contact_hours', 'instructors'], $readiness->getAppendixABlockingFields());
        $this->assertSame(['contact_hours', 'instructors'], $readiness->getMissingRequiredFields());
        $this->assertSame('Blocked', $readiness->getState()->getCategory());
    }

    /**
     * @return array{
     *     id: int,
     *     is_published: bool,
     *     faculty_submittable: bool,
     *     faculty_submission_blocking_fields: string[],
     *     coordinator_publishable: bool,
     *     coordinator_publication_blocking_fields: string[],
     *     appendix_a_ready: bool,
     *     appendix_a_blocking_fields: string[]
     * }
     */
    private function publishedTemplateInfo(): array
    {
        return [
            'id' => 201,
            'is_published' => true,
            'faculty_submittable' => true,
            'faculty_submission_blocking_fields' => [],
            'coordinator_publishable' => true,
            'coordinator_publication_blocking_fields' => [],
            'appendix_a_ready' => true,
            'appendix_a_blocking_fields' => [],
        ];
    }
}
