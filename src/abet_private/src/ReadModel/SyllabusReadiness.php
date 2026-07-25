<?php

declare(strict_types=1);

namespace App\ReadModel;

use DateTimeImmutable;

/**
 * Represents the syllabus readiness status for a single course in a program.
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

    /**
     * @param string[] $missingRequiredFields
     */
    public function __construct(
        string $programId,
        string $courseId,
        string $courseCode,
        string $courseTitle,
        SyllabusReadinessState $state,
        array $missingRequiredFields = [],
        ?int $syllabusId = null,
        ?DateTimeImmutable $updatedAt = null
    ) {
        $this->programId = $programId;
        $this->courseId = $courseId;
        $this->courseCode = $courseCode;
        $this->courseTitle = $courseTitle;
        $this->state = $state;
        $this->missingRequiredFields = $missingRequiredFields;
        $this->syllabusId = $syllabusId;
        $this->updatedAt = $updatedAt;
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

    /**
     * Factory method to derive the readiness state of a course from the given domain information.
     * Since the actual domain model classes do not exist in the database yet, we define this method
     * to take raw array inputs representing the hypothetical database rows or future entity states.
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
     *     catalog_description: ?string,
     *     course_type: ?string,
     *     credits: ?int,
     *     contact_hours: ?string,
     *     specific_goals: ?array,
     *     student_outcomes: ?array,
     *     topics_covered: ?array
     * }|null $templateInfo
     * @param array{
     *     id: int,
     *     is_submitted: bool,
     *     is_approved: bool,
     *     denial_feedback: ?string,
     *     updated_at: string
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

        // 1. If there is no shared template
        if ($templateInfo === null) {
            return new self(
                $programId,
                $courseId,
                $courseCode,
                $courseTitle,
                SyllabusReadinessState::NoSharedTemplate
            );
        }

        // Identify missing required fields in the template
        $missingRequiredFields = self::computeMissingFields($templateInfo);

        // 2. If the shared template is incomplete
        if (!$templateInfo['is_published'] && !empty($missingRequiredFields)) {
            return new self(
                $programId,
                $courseId,
                $courseCode,
                $courseTitle,
                SyllabusReadinessState::SharedTemplateIncomplete,
                $missingRequiredFields,
                $templateInfo['id']
            );
        }

        // 3. If there is a template, but no faculty draft has been started
        if ($draftInfo === null) {
            return new self(
                $programId,
                $courseId,
                $courseCode,
                $courseTitle,
                SyllabusReadinessState::SharedTemplatePublished,
                [],
                $templateInfo['id']
            );
        }

        $syllabusId = $draftInfo['id'];
        $updatedAt = isset($draftInfo['updated_at']) ? new DateTimeImmutable($draftInfo['updated_at']) : null;

        // 4. If faculty draft is approved
        if ($draftInfo['is_approved']) {
            return new self(
                $programId,
                $courseId,
                $courseCode,
                $courseTitle,
                SyllabusReadinessState::ApprovedAndReadyForAppendixA,
                [],
                $syllabusId,
                $updatedAt
            );
        }

        // 5. If faculty draft is denied with feedback
        if (!$draftInfo['is_approved'] && !empty($draftInfo['denial_feedback'])) {
            return new self(
                $programId,
                $courseId,
                $courseCode,
                $courseTitle,
                SyllabusReadinessState::DeniedWithFeedback,
                [],
                $syllabusId,
                $updatedAt
            );
        }

        // 6. If faculty draft is submitted for review
        if ($draftInfo['is_submitted']) {
            return new self(
                $programId,
                $courseId,
                $courseCode,
                $courseTitle,
                SyllabusReadinessState::SubmittedForReview,
                [],
                $syllabusId,
                $updatedAt
            );
        }

        // 7. Otherwise, faculty draft is in progress
        return new self(
            $programId,
            $courseId,
            $courseCode,
            $courseTitle,
            SyllabusReadinessState::FacultyDraftInProgress,
            [],
            $syllabusId,
            $updatedAt
        );
    }

    /**
     * Computes missing required fields from the template/syllabus info.
     *
     * @param array $info
     * @return string[]
     */
    private static function computeMissingFields(array $info): array
    {
        $missing = [];
        $requiredFields = [
            'catalog_description' => 'Catalog Description',
            'course_type' => 'Course Type',
            'credits' => 'Credits',
            'contact_hours' => 'Contact Hours',
            'specific_goals' => 'Specific Goals',
            'student_outcomes' => 'Student Outcomes',
            'topics_covered' => 'Topics Covered',
        ];

        foreach ($requiredFields as $key => $label) {
            $value = $info[$key] ?? null;
            if (self::isEmpty($value)) {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    private static function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_string($value) && trim($value) === '') {
            return true;
        }
        if (is_array($value) && empty($value)) {
            return true;
        }
        return false;
    }
}
