<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entity\SyllabusTemplate\SubmissionStatus;
use App\ReadModel\SyllabusReadiness;
use App\ReadModel\SyllabusReadinessState;
use App\Repository\SyllabusReadinessRepository;
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

    public function testDeriveSharedTemplateNeedingPublicationFields(): void
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

        $this->assertSame(SyllabusReadinessState::SharedTemplateNeedsPublicationFields, $readiness->getState());
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

    public function testDeriveSharedTemplateReadyToPublish(): void
    {
        $templateInfo = $this->publishedTemplateInfo();
        $templateInfo['is_published'] = false;

        $readiness = SyllabusReadiness::fromDomainState($this->courseInfo, $templateInfo, null);

        $this->assertSame(SyllabusReadinessState::SharedTemplateReadyToPublish, $readiness->getState());
        $this->assertTrue($readiness->isCoordinatorPublishable());
        $this->assertSame('Blocked', $readiness->getState()->getCategory());
    }

    public function testDerivePublishedSharedTemplateWithoutOffering(): void
    {
        $templateInfo = $this->publishedTemplateInfo();

        $readiness = SyllabusReadiness::fromDomainState($this->courseInfo, $templateInfo, null);

        $this->assertSame(SyllabusReadinessState::SharedTemplatePublishedNoOffering, $readiness->getState());
        $this->assertEmpty($readiness->getMissingRequiredFields());
        $this->assertSame('Missing', $readiness->getState()->getCategory());
    }

    public function testDeriveFacultyDraftNeedingSubmissionFields(): void
    {
        $templateInfo = $this->publishedTemplateInfo();

        $draftInfo = [
            'id' => 301,
            'status' => SubmissionStatus::Draft,
            'denial_feedback' => null,
            'updated_at' => '2026-07-18T12:00:00Z',
            'faculty_submittable' => false,
            'faculty_submission_blocking_fields' => ['credits'],
            'appendix_a_ready' => false,
            'appendix_a_blocking_fields' => ['credits', 'contact_hours'],
        ];

        $readiness = SyllabusReadiness::fromDomainState($this->courseInfo, $templateInfo, $draftInfo);

        $this->assertSame(SyllabusReadinessState::FacultyDraftNeedsSubmissionFields, $readiness->getState());
        $this->assertSame(SubmissionStatus::Draft, $readiness->getWorkflowStatus());
        $this->assertSame(301, $readiness->getSyllabusId());
        $this->assertNotNull($readiness->getUpdatedAt());
        $this->assertSame('2026-07-18T12:00:00+00:00', $readiness->getUpdatedAt()->format(\DateTimeInterface::ATOM));
        $this->assertFalse($readiness->isFacultySubmittable());
        $this->assertSame(['credits'], $readiness->getFacultySubmissionBlockingFields());
        $this->assertSame('Blocked', $readiness->getState()->getCategory());
    }

    public function testDeriveFacultyDraftReadyToSubmit(): void
    {
        $draftInfo = [
            'id' => 301,
            'status' => SubmissionStatus::Draft,
            'denial_feedback' => null,
            'updated_at' => '2026-07-18T12:00:00Z',
            'faculty_submittable' => true,
            'faculty_submission_blocking_fields' => [],
            'appendix_a_ready' => false,
            'appendix_a_blocking_fields' => ['contact_hours'],
        ];

        $readiness = SyllabusReadiness::fromDomainState(
            $this->courseInfo,
            $this->publishedTemplateInfo(),
            $draftInfo,
        );

        $this->assertSame(SyllabusReadinessState::FacultyDraftReadyToSubmit, $readiness->getState());
        $this->assertTrue($readiness->isFacultySubmittable());
        $this->assertSame('Blocked', $readiness->getState()->getCategory());
    }

    public function testDeriveAwaitingCoordinatorReview(): void
    {
        $templateInfo = $this->publishedTemplateInfo();

        $draftInfo = [
            'id' => 301,
            'status' => SubmissionStatus::Submitted,
            'denial_feedback' => null,
            'updated_at' => '2026-07-18T13:00:00Z',
            'faculty_submittable' => true,
            'faculty_submission_blocking_fields' => [],
            'appendix_a_ready' => false,
            'appendix_a_blocking_fields' => ['contact_hours'],
        ];

        $readiness = SyllabusReadiness::fromDomainState($this->courseInfo, $templateInfo, $draftInfo);

        $this->assertSame(SyllabusReadinessState::AwaitingCoordinatorReview, $readiness->getState());
        $this->assertSame(SubmissionStatus::Submitted, $readiness->getWorkflowStatus());
        $this->assertSame('Awaiting review', $readiness->getState()->getCategory());
    }

    public function testBlankOfferingSubmissionTakesPrecedenceOverMissingSharedTemplate(): void
    {
        $draftInfo = [
            'id' => 302,
            'status' => SubmissionStatus::Submitted,
            'denial_feedback' => null,
            'updated_at' => '2026-07-27T21:09:29Z',
            'faculty_submittable' => true,
            'faculty_submission_blocking_fields' => [],
            'appendix_a_ready' => false,
            'appendix_a_blocking_fields' => ['contact_hours'],
            'course_offering' => [
                'id' => 401,
                'academic_year' => '2026-2027',
                'term' => 'Fall',
                'section' => '001',
                'delivery_type' => 'in_person',
            ],
        ];

        $readiness = SyllabusReadiness::fromDomainState($this->courseInfo, null, $draftInfo);

        self::assertSame(SyllabusReadinessState::AwaitingCoordinatorReview, $readiness->getState());
        self::assertSame('Awaiting review', $readiness->getState()->getCategory());
        self::assertSame(SubmissionStatus::Submitted, $readiness->getWorkflowStatus());
        self::assertSame(401, $readiness->getCourseOfferingId());
    }

    public function testDeriveDeniedSubmissionNeedingRevision(): void
    {
        $templateInfo = $this->publishedTemplateInfo();

        $draftInfo = [
            'id' => 301,
            'status' => SubmissionStatus::Denied,
            'denial_feedback' => 'Please update specific goals mapping.',
            'updated_at' => '2026-07-18T14:00:00Z',
            'faculty_submittable' => false,
            'faculty_submission_blocking_fields' => ['specific_goals'],
            'appendix_a_ready' => false,
            'appendix_a_blocking_fields' => ['specific_goals'],
        ];

        $readiness = SyllabusReadiness::fromDomainState($this->courseInfo, $templateInfo, $draftInfo);

        $this->assertSame(SyllabusReadinessState::DeniedNeedsRevision, $readiness->getState());
        $this->assertSame(SubmissionStatus::Denied, $readiness->getWorkflowStatus());
        $this->assertSame(['specific_goals'], $readiness->getMissingRequiredFields());
        $this->assertSame('Blocked', $readiness->getState()->getCategory());
    }

    public function testDeriveApprovedAndReady(): void
    {
        $templateInfo = $this->publishedTemplateInfo();

        $draftInfo = [
            'id' => 301,
            'status' => SubmissionStatus::Approved,
            'denial_feedback' => null,
            'updated_at' => '2026-07-18T15:00:00Z',
            'faculty_submittable' => true,
            'faculty_submission_blocking_fields' => [],
            'appendix_a_ready' => true,
            'appendix_a_blocking_fields' => [],
        ];

        $readiness = SyllabusReadiness::fromDomainState($this->courseInfo, $templateInfo, $draftInfo);

        $this->assertSame(SyllabusReadinessState::ApprovedAppendixAReady, $readiness->getState());
        $this->assertSame(SubmissionStatus::Approved, $readiness->getWorkflowStatus());
        $this->assertTrue($readiness->isAppendixAReady());
        $this->assertSame('Ready', $readiness->getState()->getCategory());
    }

    public function testApprovedRevisionCanRemainAppendixAIncomplete(): void
    {
        $draftInfo = [
            'id' => 301,
            'status' => SubmissionStatus::ApprovedWithEdits,
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
        $this->assertSame(SubmissionStatus::ApprovedWithEdits, $readiness->getWorkflowStatus());
        $this->assertFalse($readiness->isAppendixAReady());
        $this->assertSame(['contact_hours', 'instructors'], $readiness->getAppendixABlockingFields());
        $this->assertSame(['contact_hours', 'instructors'], $readiness->getMissingRequiredFields());
        $this->assertSame('Blocked', $readiness->getState()->getCategory());
    }

    public function testCanonicalReadinessFiltersCanBeCombined(): void
    {
        $sharedTemplate = new SyllabusReadiness(
            '1',
            '101',
            'CSE 310',
            'Data Structures',
            SyllabusReadinessState::SharedTemplateReadyToPublish,
            coordinatorPublishable: true,
        );
        $offering = new SyllabusReadiness(
            '1',
            '101',
            'CSE 310',
            'Data Structures',
            SyllabusReadinessState::ApprovedAppendixAReady,
            courseOffering: [
                'id' => 501,
                'academic_year' => '2026-2027',
                'term' => 'Fall',
                'section' => '001',
                'delivery_type' => 'in_person',
            ],
            facultySubmittable: true,
            coordinatorPublishable: true,
            appendixAReady: true,
            workflowStatus: SubmissionStatus::Approved,
        );

        self::assertSame(
            [$offering],
            SyllabusReadinessRepository::filterRows(
                [$sharedTemplate, $offering],
                target: 'course_offering',
                workflow: SubmissionStatus::Approved,
                facultySubmittable: true,
                appendixAReady: true,
            ),
        );
        self::assertSame(
            [$sharedTemplate],
            SyllabusReadinessRepository::filterRows(
                [$sharedTemplate, $offering],
                target: 'shared_template',
                coordinatorPublishable: true,
            ),
        );
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
