<?php

namespace Tests\Unit;

use App\Entity\Program;
use App\Entity\SyllabusTemplate\CommonCourse;
use App\Entity\SyllabusTemplate\CompletenessStatus;
use App\Entity\SyllabusTemplate\DeliveryType;
use App\Entity\SyllabusTemplate\ProposalOrigin;
use App\Entity\SyllabusTemplate\RevisionAuthorType;
use App\Entity\SyllabusTemplate\SyllabusCompletenessPurpose;
use App\Entity\SyllabusTemplate\TemplateContentCompleteness;
use App\Entity\SyllabusTemplate\TemplateSubmission;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class SyllabusCompletenessPurposeTest extends TestCase
{
    public function testIncompleteContentCanAlwaysBeSavedAsADraft(): void
    {
        $assessment = TemplateContentCompleteness::assess(
            ['credits' => -1, 'course_type' => 'unknown'],
            SyllabusCompletenessPurpose::DraftSaveable,
        );

        self::assertSame(CompletenessStatus::Complete, $assessment['status']);
        self::assertSame([], $assessment['blockingFields']);
    }

    public function testFacultyAndCoordinatorTransitionsUseTheirNamedProfile(): void
    {
        $content = $this->transitionContent();

        self::assertSame(
            CompletenessStatus::Complete,
            TemplateContentCompleteness::assess(
                $content,
                SyllabusCompletenessPurpose::FacultySubmittable,
            )['status'],
        );
        self::assertSame(
            CompletenessStatus::Complete,
            TemplateContentCompleteness::assess(
                $content,
                SyllabusCompletenessPurpose::CoordinatorPublishable,
            )['status'],
        );
        self::assertSame(
            CompletenessStatus::Incomplete,
            TemplateContentCompleteness::assess(
                $content,
                SyllabusCompletenessPurpose::AppendixAReady,
            )['status'],
        );
    }

    public function testAppendixAProfileRequiresTheAccreditationEvidenceFields(): void
    {
        $content = $this->transitionContent() + [
            'contact_hours' => '3 hours/week',
            'instructors' => ['Faculty Name'],
            'catalog_description' => 'Software engineering principles.',
            'course_type' => 'R',
            'specific_goals' => ['Apply a lifecycle process.'],
            'student_outcomes' => ['SO 1'],
            'topics_covered' => ['Requirements', 'Design'],
        ];

        $assessment = TemplateContentCompleteness::assess(
            $content,
            SyllabusCompletenessPurpose::AppendixAReady,
        );

        self::assertSame(CompletenessStatus::Complete, $assessment['status']);
        self::assertSame([], $assessment['blockingFields']);
    }

    public function testRequiredValuesCanBePresentButInvalid(): void
    {
        $assessment = TemplateContentCompleteness::assess(
            $this->transitionContent(['credits' => 0]),
            SyllabusCompletenessPurpose::FacultySubmittable,
        );

        self::assertSame(CompletenessStatus::Incomplete, $assessment['status']);
        self::assertSame([], $assessment['missingFields']);
        self::assertSame(['credits'], $assessment['invalidFields']);
        self::assertSame(['credits'], $assessment['blockingFields']);
    }

    public function testRevisionExposesPurposeSpecificConvenienceChecks(): void
    {
        $faculty = (new User())->setEmail('faculty@example.edu');
        $course = new CommonCourse(
            new Program('Computer Science', 'BS', '2026'),
            'CSE',
            '360',
            'Software Engineering',
            DeliveryType::InPerson,
        );
        $submission = new TemplateSubmission($course, $faculty, ProposalOrigin::FacultySubmission);
        $revision = $submission->addRevision(
            $faculty,
            RevisionAuthorType::Faculty,
            $this->transitionContent(),
        );

        self::assertTrue($revision->isDraftSaveable());
        self::assertTrue($revision->isFacultySubmittable());
        self::assertTrue($revision->isCoordinatorPublishable());
        self::assertFalse($revision->isAppendixAReady());
        self::assertSame([], $revision->getFacultySubmissionBlockingFields());
        self::assertContains('contact_hours', $revision->getAppendixABlockingFields());
    }

    /** @param array<string, mixed> $overrides */
    private function transitionContent(array $overrides = []): array
    {
        return $overrides + [
            'credits' => 3,
            'course_coordinators' => ['Coordinator Name'],
            'credit_category' => 'engineering',
        ];
    }
}
