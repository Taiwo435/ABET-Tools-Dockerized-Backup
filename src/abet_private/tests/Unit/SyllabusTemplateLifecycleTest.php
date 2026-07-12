<?php

namespace Tests\Unit;

use App\Entity\SyllabusTemplate\CommonCourse;
use App\Entity\SyllabusTemplate\DeliveryType;
use App\Entity\SyllabusTemplate\ReviewDecision;
use App\Entity\SyllabusTemplate\RevisionAuthorType;
use App\Entity\SyllabusTemplate\SubmissionStatus;
use App\Entity\SyllabusTemplate\TemplateReview;
use App\Entity\SyllabusTemplate\TemplateSubmission;
use App\Entity\Program;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class SyllabusTemplateLifecycleTest extends TestCase
{
    public function testFacultySubmissionCanBeApprovedUnchanged(): void
    {
        [$course, $submission, $faculty, $coordinator] = $this->fixture();
        $revision = $submission->addRevision($faculty, RevisionAuthorType::Faculty, ['courseName' => 'Software Engineering']);

        $submission->submit($revision);
        $review = new TemplateReview($submission, $coordinator, ReviewDecision::Approved, 'Meets the common-course standard.');
        $submission->recordReview($review, $revision);

        self::assertSame(SubmissionStatus::Approved, $submission->getStatus());
        self::assertSame($revision, $submission->getSubmittedRevision());
        self::assertSame($revision, $submission->getApprovedRevision());
        self::assertSame($revision, $course->getCurrentApprovedRevision());
    }

    public function testApprovalWithEditsPreservesFacultyRevisionAndPublishesCoordinatorRevision(): void
    {
        [$course, $submission, $faculty, $coordinator] = $this->fixture();
        $facultyRevision = $submission->addRevision($faculty, RevisionAuthorType::Faculty, ['catalogDescription' => 'Original']);
        $submission->submit($facultyRevision);
        $coordinatorRevision = $submission->addRevision($coordinator, RevisionAuthorType::Coordinator, ['catalogDescription' => 'Edited']);

        $review = new TemplateReview($submission, $coordinator, ReviewDecision::ApprovedWithEdits, 'Standardized the catalog description.');
        $submission->recordReview($review, $coordinatorRevision);

        self::assertSame(SubmissionStatus::ApprovedWithEdits, $submission->getStatus());
        self::assertSame($facultyRevision, $submission->getSubmittedRevision());
        self::assertSame($coordinatorRevision, $submission->getApprovedRevision());
        self::assertSame($coordinatorRevision, $course->getCurrentApprovedRevision());
        self::assertSame(2, $submission->getRevisions()->count());
    }

    public function testDenialRequiresCommentAndDoesNotPublishRevision(): void
    {
        [$course, $submission, $faculty, $coordinator] = $this->fixture();
        $revision = $submission->addRevision($faculty, RevisionAuthorType::Faculty, ['topics' => ['Testing']]);
        $submission->submit($revision);

        $review = new TemplateReview($submission, $coordinator, ReviewDecision::Denied, 'Required outcomes are missing.');
        $submission->recordDenial($review);

        self::assertSame(SubmissionStatus::Denied, $submission->getStatus());
        self::assertNull($submission->getApprovedRevision());
        self::assertNull($course->getCurrentApprovedRevision());
    }

    public function testSubmittedFacultyRevisionCannotBeMutatedByAddingAnotherFacultyRevision(): void
    {
        [, $submission, $faculty] = $this->fixture();
        $revision = $submission->addRevision($faculty, RevisionAuthorType::Faculty, ['topics' => ['Testing']]);
        $submission->submit($revision);

        $this->expectException(\DomainException::class);
        $submission->addRevision($faculty, RevisionAuthorType::Faculty, ['topics' => ['Changed']]);
    }

    public function testDenialWithoutFeedbackIsRejected(): void
    {
        [, $submission, $faculty, $coordinator] = $this->fixture();
        $revision = $submission->addRevision($faculty, RevisionAuthorType::Faculty, ['topics' => ['Testing']]);
        $submission->submit($revision);

        $this->expectException(\InvalidArgumentException::class);
        new TemplateReview($submission, $coordinator, ReviewDecision::Denied);
    }

    public function testFacultyCannotReviewOwnSubmission(): void
    {
        [, $submission, $faculty] = $this->fixture();
        $revision = $submission->addRevision($faculty, RevisionAuthorType::Faculty, ['topics' => ['Testing']]);
        $submission->submit($revision);

        $this->expectException(\InvalidArgumentException::class);
        new TemplateReview($submission, $faculty, ReviewDecision::Approved);
    }

    /** @return array{CommonCourse, TemplateSubmission, User, User} */
    private function fixture(): array
    {
        $faculty = (new User())->setEmail('faculty@example.edu');
        $coordinator = (new User())->setEmail('coordinator@example.edu');
        $program = new Program('Computer Science', 'BS', '2026');
        $course = new CommonCourse($program, 'cse', '360', 'Software Engineering', DeliveryType::InPerson);

        return [$course, new TemplateSubmission($course, $faculty), $faculty, $coordinator];
    }
}
