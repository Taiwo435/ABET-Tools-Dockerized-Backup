<?php

declare(strict_types=1);

namespace App\ReadModel;

use App\Entity\SyllabusTemplate\SubmissionStatus;
use DateTimeImmutable;

/**
 * Represents the syllabus readiness status for a common course or one of its offerings.
 */
class SyllabusReadiness
{
    private string $programId;
    private string $courseId;
    private string $courseCode;
    private string $courseTitle;
    private SyllabusReadinessState $state;
    /** @var string[] */
    private array $missingRequiredFields;
    private ?int $syllabusId;
    private ?DateTimeImmutable $updatedAt;
    /** @var array{id: int, academic_year: string, term: string, section: string, delivery_type: string}|null */
    private ?array $courseOffering;
    private bool $facultySubmittable;
    /** @var string[] */
    private array $facultySubmissionBlockingFields;
    private bool $coordinatorPublishable;
    /** @var string[] */
    private array $coordinatorPublicationBlockingFields;
    private bool $appendixAReady;
    /** @var string[] */
    private array $appendixABlockingFields;
    private ?SubmissionStatus $workflowStatus;

    /**
     * @param string[] $missingRequiredFields
     * @param array{id: int, academic_year: string, term: string, section: string, delivery_type: string}|null $courseOffering
     * @param string[] $facultySubmissionBlockingFields
     * @param string[] $coordinatorPublicationBlockingFields
     * @param string[] $appendixABlockingFields
     */
    public function __construct(
        string $programId,
        string $courseId,
        string $courseCode,
        string $courseTitle,
        SyllabusReadinessState $state,
        array $missingRequiredFields = [],
        ?int $syllabusId = null,
        ?DateTimeImmutable $updatedAt = null,
        ?array $courseOffering = null,
        bool $facultySubmittable = false,
        array $facultySubmissionBlockingFields = [],
        bool $coordinatorPublishable = false,
        array $coordinatorPublicationBlockingFields = [],
        bool $appendixAReady = false,
        array $appendixABlockingFields = [],
        ?SubmissionStatus $workflowStatus = null,
    ) {
        $this->programId = $programId;
        $this->courseId = $courseId;
        $this->courseCode = $courseCode;
        $this->courseTitle = $courseTitle;
        $this->state = $state;
        $this->missingRequiredFields = $missingRequiredFields;
        $this->syllabusId = $syllabusId;
        $this->updatedAt = $updatedAt;
        $this->courseOffering = $courseOffering;
        $this->facultySubmittable = $facultySubmittable;
        $this->facultySubmissionBlockingFields = $facultySubmissionBlockingFields;
        $this->coordinatorPublishable = $coordinatorPublishable;
        $this->coordinatorPublicationBlockingFields = $coordinatorPublicationBlockingFields;
        $this->appendixAReady = $appendixAReady;
        $this->appendixABlockingFields = $appendixABlockingFields;
        $this->workflowStatus = $workflowStatus;
    }

    public function getProgramId(): string
    {
        return $this->programId;
    }

    public function getCourseId(): string
    {
        return $this->courseId;
    }

    public function getCourseCode(): string
    {
        return $this->courseCode;
    }

    public function getCourseTitle(): string
    {
        return $this->courseTitle;
    }

    public function getState(): SyllabusReadinessState
    {
        return $this->state;
    }

    /**
     * @return string[]
     */
    public function getMissingRequiredFields(): array
    {
        return $this->missingRequiredFields;
    }

    public function getSyllabusId(): ?int
    {
        return $this->syllabusId;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getCourseOfferingId(): ?int
    {
        return $this->courseOffering['id'] ?? null;
    }

    public function getAcademicYear(): ?string
    {
        return $this->courseOffering['academic_year'] ?? null;
    }

    public function getTerm(): ?string
    {
        return $this->courseOffering['term'] ?? null;
    }

    public function getSection(): ?string
    {
        return $this->courseOffering['section'] ?? null;
    }

    public function getDeliveryType(): ?string
    {
        return $this->courseOffering['delivery_type'] ?? null;
    }

    public function isFacultySubmittable(): bool
    {
        return $this->facultySubmittable;
    }

    /** @return string[] */
    public function getFacultySubmissionBlockingFields(): array
    {
        return $this->facultySubmissionBlockingFields;
    }

    public function isCoordinatorPublishable(): bool
    {
        return $this->coordinatorPublishable;
    }

    /** @return string[] */
    public function getCoordinatorPublicationBlockingFields(): array
    {
        return $this->coordinatorPublicationBlockingFields;
    }

    public function isAppendixAReady(): bool
    {
        return $this->appendixAReady;
    }

    /** @return string[] */
    public function getAppendixABlockingFields(): array
    {
        return $this->appendixABlockingFields;
    }

    public function getWorkflowStatus(): ?SubmissionStatus
    {
        return $this->workflowStatus;
    }

    /**
     * Builds the presentation row from canonical lifecycle and completeness projections.
     *
     * @param array{
     *     program_id: string|int,
     *     course_id: string|int,
     *     course_code: string,
     *     course_title: string
     * } $courseInfo
     * @param array{
     *     id: int,
     *     is_published: bool,
     *     faculty_submittable: bool,
     *     faculty_submission_blocking_fields: string[],
     *     coordinator_publishable: bool,
     *     coordinator_publication_blocking_fields: string[],
     *     appendix_a_ready: bool,
     *     appendix_a_blocking_fields: string[]
     * }|null $templateInfo
     * @param array{
     *     id: int,
     *     status: SubmissionStatus,
     *     denial_feedback: ?string,
     *     updated_at: string,
     *     faculty_submittable: bool,
     *     faculty_submission_blocking_fields: string[],
     *     appendix_a_ready: bool,
     *     appendix_a_blocking_fields: string[],
     *     course_offering?: array{
     *         id: int,
     *         academic_year: string,
     *         term: string,
     *         section: string,
     *         delivery_type: string
     *     }
     * }|null $draftInfo
     */
    public static function fromDomainState(
        array $courseInfo,
        ?array $templateInfo = null,
        ?array $draftInfo = null
    ): self {
        $programId = (string) $courseInfo['program_id'];
        $courseId = (string) $courseInfo['course_id'];
        $courseCode = (string) $courseInfo['course_code'];
        $courseTitle = (string) $courseInfo['course_title'];
        $courseOffering = $draftInfo['course_offering'] ?? null;
        $facultySubmittable = $draftInfo['faculty_submittable']
            ?? $templateInfo['faculty_submittable']
            ?? false;
        $facultyBlockingFields = $draftInfo['faculty_submission_blocking_fields']
            ?? $templateInfo['faculty_submission_blocking_fields']
            ?? [];
        $coordinatorPublishable = $templateInfo['coordinator_publishable'] ?? false;
        $coordinatorBlockingFields = $templateInfo['coordinator_publication_blocking_fields'] ?? [];
        $appendixAReady = $draftInfo['appendix_a_ready']
            ?? $templateInfo['appendix_a_ready']
            ?? false;
        $appendixABlockingFields = $draftInfo['appendix_a_blocking_fields']
            ?? $templateInfo['appendix_a_blocking_fields']
            ?? [];
        $workflowStatus = $draftInfo['status'] ?? null;
        $syllabusId = $draftInfo['id'] ?? $templateInfo['id'] ?? null;
        $updatedAt = isset($draftInfo['updated_at'])
            ? new DateTimeImmutable($draftInfo['updated_at'])
            : null;

        if ($templateInfo === null) {
            $state = SyllabusReadinessState::NoSharedTemplate;
            $blockingFields = [];
        } elseif (!$templateInfo['is_published']) {
            $state = $coordinatorPublishable
                ? SyllabusReadinessState::SharedTemplateReadyToPublish
                : SyllabusReadinessState::SharedTemplateNeedsPublicationFields;
            $blockingFields = $coordinatorBlockingFields;
        } elseif ($draftInfo === null) {
            $state = SyllabusReadinessState::SharedTemplatePublishedNoOffering;
            $blockingFields = [];
        } elseif (!$workflowStatus instanceof SubmissionStatus) {
            throw new \InvalidArgumentException('A faculty readiness projection requires a canonical workflow status.');
        } else {
            [$state, $blockingFields] = match ($workflowStatus) {
                SubmissionStatus::Draft => $facultySubmittable
                    ? [SyllabusReadinessState::FacultyDraftReadyToSubmit, []]
                    : [SyllabusReadinessState::FacultyDraftNeedsSubmissionFields, $facultyBlockingFields],
                SubmissionStatus::Submitted => [SyllabusReadinessState::AwaitingCoordinatorReview, []],
                SubmissionStatus::Approved, SubmissionStatus::ApprovedWithEdits => $appendixAReady
                    ? [SyllabusReadinessState::ApprovedAppendixAReady, []]
                    : [SyllabusReadinessState::ApprovedAppendixAIncomplete, $appendixABlockingFields],
                SubmissionStatus::Denied => [SyllabusReadinessState::DeniedNeedsRevision, $facultyBlockingFields],
            };
        }

        return new self(
            $programId,
            $courseId,
            $courseCode,
            $courseTitle,
            $state,
            $blockingFields,
            $syllabusId,
            $updatedAt,
            $courseOffering,
            $facultySubmittable,
            $facultyBlockingFields,
            $coordinatorPublishable,
            $coordinatorBlockingFields,
            $appendixAReady,
            $appendixABlockingFields,
            $workflowStatus,
        );
    }
}
