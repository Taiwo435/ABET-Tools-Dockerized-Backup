<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Program;
use App\Entity\SyllabusTemplate\CommonCourse;
use App\Entity\SyllabusTemplate\ReviewDecision;
use App\Entity\SyllabusTemplate\SubmissionKind;
use App\Entity\SyllabusTemplate\SubmissionStatus;
use App\Entity\SyllabusTemplate\TemplateRevision;
use App\Entity\SyllabusTemplate\TemplateSubmission;
use App\ReadModel\SyllabusReadiness;
use App\Repository\SyllabusTemplate\TemplateSubmissionRepository;
use Doctrine\Persistence\ManagerRegistry;

final class SyllabusReadinessRepository
{
    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly TemplateSubmissionRepository $submissions,
    ) {}

    /**
     * Projects syllabus submissions and templates for a program into readiness rows.
     *
     * @param int|string $programId
     * @param string|null $filter
     * @return SyllabusReadiness[]
     */
    public function getReadinessRowsForProgram(int|string $programId, ?string $filter = null): array
    {
        $program = $this->registry->getRepository(Program::class)->find($programId);
        if (!$program instanceof Program) {
            return [];
        }

        /** @var list<CommonCourse> $commonCourses */
        $commonCourses = $this->registry->getRepository(CommonCourse::class)->findBy(
            ['program' => $program],
            ['courseSubject' => 'ASC', 'courseNumber' => 'ASC', 'deliveryType' => 'ASC'],
        );

        /** @var array<int, array{shared: list<TemplateSubmission>, faculty_by_offering: array<int, TemplateSubmission>}> $submissionsByCourse */
        $submissionsByCourse = [];
        foreach ($this->submissions->findForProgramReadiness($program) as $submission) {
            $courseId = $submission->getCommonCourse()->getId();
            if ($courseId === null) {
                continue;
            }

            $submissionsByCourse[$courseId] ??= ['shared' => [], 'faculty_by_offering' => []];
            if ($submission->getKind() === SubmissionKind::SharedTemplate) {
                $submissionsByCourse[$courseId]['shared'][] = $submission;
                continue;
            }

            $offeringId = $submission->getCourseOffering()?->getId();
            if ($offeringId !== null
                && !isset($submissionsByCourse[$courseId]['faculty_by_offering'][$offeringId])) {
                $submissionsByCourse[$courseId]['faculty_by_offering'][$offeringId] = $submission;
            }
        }

        $readinessRows = [];
        foreach ($commonCourses as $commonCourse) {
            $courseId = $commonCourse->getId();
            $courseSubmissions = $courseId !== null
                ? ($submissionsByCourse[$courseId] ?? ['shared' => [], 'faculty_by_offering' => []])
                : ['shared' => [], 'faculty_by_offering' => []];
            $sharedSubmission = $courseSubmissions['shared'][0] ?? null;
            $publishedRevision = $commonCourse->getCurrentApprovedRevision();
            $templateRevision = $publishedRevision ?? $this->selectRevision($sharedSubmission);

            $courseInfo = [
                'program_id' => (string)$program->getId(),
                'course_id' => (string)$courseId,
                'course_code' => trim($commonCourse->getCourseSubject().' '.$commonCourse->getCourseNumber()),
                'course_title' => $commonCourse->getCourseName(),
            ];

            $facultySubmissions = array_values($courseSubmissions['faculty_by_offering']);
            if ($facultySubmissions === []) {
                $facultySubmissions = [null];
            }

            foreach ($facultySubmissions as $facultySubmission) {
                $readinessRows[] = SyllabusReadiness::fromDomainState(
                    $courseInfo,
                    $this->buildTemplateInfo($templateRevision, $publishedRevision !== null),
                    $this->buildSubmissionInfo($facultySubmission),
                );
            }
        }

        if ($filter !== null && $filter !== '') {
            $readinessRows = array_values(array_filter($readinessRows, function (SyllabusReadiness $row) use ($filter) {
                if (strcasecmp($row->getState()->getCategory(), $filter) === 0) {
                    return true;
                }
                if (strcasecmp($row->getState()->value, $filter) === 0) {
                    return true;
                }
                return false;
            }));
        }

        return $readinessRows;
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
     * }|null
     */
    private function buildTemplateInfo(?TemplateRevision $revision, bool $isPublished): ?array
    {
        if ($revision === null || $revision->getId() === null) {
            return null;
        }

        return [
            'id' => $revision->getId(),
            'is_published' => $isPublished,
            'faculty_submittable' => $revision->isFacultySubmittable(),
            'faculty_submission_blocking_fields' => $revision->getFacultySubmissionBlockingFields(),
            'coordinator_publishable' => $revision->isCoordinatorPublishable(),
            'coordinator_publication_blocking_fields' => $revision->getCoordinatorPublicationBlockingFields(),
            'appendix_a_ready' => $revision->isAppendixAReady(),
            'appendix_a_blocking_fields' => $revision->getAppendixABlockingFields(),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     status: SubmissionStatus,
     *     denial_feedback: ?string,
     *     updated_at: string,
     *     faculty_submittable: bool,
     *     faculty_submission_blocking_fields: string[],
     *     appendix_a_ready: bool,
     *     appendix_a_blocking_fields: string[],
     *     course_offering: array{
     *         id: int,
     *         academic_year: string,
     *         term: string,
     *         section: string,
     *         delivery_type: string
     *     }
     * }|null
     */
    private function buildSubmissionInfo(?TemplateSubmission $submission): ?array
    {
        if ($submission === null || $submission->getId() === null) {
            return null;
        }

        $status = $submission->getStatus();
        $review = $submission->getReview();
        $offering = $submission->getCourseOffering();
        $revision = $this->selectRevision($submission);
        if ($offering === null || $offering->getId() === null) {
            throw new \LogicException('A faculty-offering submission must reference a persisted course offering.');
        }
        if ($revision === null) {
            throw new \LogicException('A persisted faculty-offering submission must have a lifecycle revision.');
        }

        return [
            'id' => $submission->getId(),
            'status' => $status,
            'denial_feedback' => $status === SubmissionStatus::Denied
                && $review?->getDecision() === ReviewDecision::Denied
                    ? $review->getComment()
                    : null,
            'updated_at' => $submission->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            'faculty_submittable' => $revision->isFacultySubmittable(),
            'faculty_submission_blocking_fields' => $revision->getFacultySubmissionBlockingFields(),
            'appendix_a_ready' => $revision->isAppendixAReady(),
            'appendix_a_blocking_fields' => $revision->getAppendixABlockingFields(),
            'course_offering' => [
                'id' => $offering->getId(),
                'academic_year' => $offering->getAcademicYear(),
                'term' => $offering->getTerm(),
                'section' => $offering->getSection(),
                'delivery_type' => $offering->getDeliveryType()->value,
            ],
        ];
    }

    private function selectRevision(?TemplateSubmission $submission): ?TemplateRevision
    {
        return $submission?->getApprovedRevision()
            ?? $submission?->getSubmittedRevision()
            ?? $submission?->getWorkingRevision();
    }

    /**
     * Parses a curriculum course string into subject, number, and title.
     * Fallbacks are handled gracefully.
     *
     * @param string $courseStr
     * @return array{subject: string, number: string, title: string}
     */
    public function parseCourse(string $courseStr): array
    {
        // Matches e.g. "CSE 310 Data Structures" or "MAT 243 Discrete Math" or "CSE 485"
        // Also supports numbers with suffixes like "101L"
        if (preg_match('/^([A-Za-z]{2,5})\s*(\d{3}[A-Za-z]?)\s*(.*)$/', $courseStr, $matches)) {
            return [
                'subject' => $matches[1],
                'number' => $matches[2],
                'title' => trim($matches[3]),
            ];
        }

        // Fallback: If it doesn't match the standard university code format, split by space
        $parts = explode(' ', $courseStr, 2);
        if (count($parts) === 2) {
            return [
                'subject' => $parts[0],
                'number' => '',
                'title' => trim($parts[1]),
            ];
        }

        return [
            'subject' => '',
            'number' => '',
            'title' => $courseStr,
        ];
    }

    /**
     * @return array<array{program_id: int|string, program_name: string, program_code: string, program_year: string}>
     */
    public function getAllPrograms(): array
    {
        /** @var list<Program> $programs */
        $programs = $this->registry->getRepository(Program::class)->findBy(
            [],
            ['name' => 'ASC', 'year' => 'DESC'],
        );

        return array_map(
            static fn (Program $program): array => [
                'program_id' => $program->getId(),
                'program_name' => $program->getName(),
                'program_code' => $program->getCode(),
                'program_year' => $program->getYear(),
            ],
            $programs,
        );
    }
}
