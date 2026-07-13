<?php

namespace Tests\Unit;

use App\Entity\SyllabusTemplate\CommonCourse;
use App\Entity\SyllabusTemplate\DeliveryType;
use App\Entity\SyllabusTemplate\ProposalOrigin;
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
        $revision = $this->completeFacultyRevision($submission, $faculty);

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
        $facultyRevision = $this->completeFacultyRevision($submission, $faculty, ['catalogDescription' => 'Original']);
        $submission->submit($facultyRevision);
        $coordinatorRevision = $submission->addRevision($coordinator, RevisionAuthorType::Coordinator, $this->completeContent([
            'catalogDescription' => 'Edited',
        ]));

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
        $revision = $this->completeFacultyRevision($submission, $faculty);
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
        $revision = $this->completeFacultyRevision($submission, $faculty);
        $submission->submit($revision);

        $this->expectException(\DomainException::class);
        $submission->addRevision($faculty, RevisionAuthorType::Faculty, $this->completeContent(['topics' => ['Changed']]));
    }

    public function testDenialWithoutFeedbackIsRejected(): void
    {
        [, $submission, $faculty, $coordinator] = $this->fixture();
        $revision = $this->completeFacultyRevision($submission, $faculty);
        $submission->submit($revision);

        $this->expectException(\InvalidArgumentException::class);
        new TemplateReview($submission, $coordinator, ReviewDecision::Denied);
    }

    public function testFacultyCannotReviewOwnSubmission(): void
    {
        [, $submission, $faculty] = $this->fixture();
        $revision = $this->completeFacultyRevision($submission, $faculty);
        $submission->submit($revision);

        $this->expectException(\InvalidArgumentException::class);
        new TemplateReview($submission, $faculty, ReviewDecision::Approved);
    }

    public function testCoordinatorCanKeepAnIncompleteTemplateDraftAndPublishACompleteRevision(): void
    {
        [$course, , , $coordinator] = $this->fixture();
        $proposal = new TemplateSubmission($course, $coordinator, ProposalOrigin::CoordinatorCreated);
        $incomplete = $proposal->addRevision(
            $coordinator,
            RevisionAuthorType::Coordinator,
            ['catalogDescription' => 'Draft'],
        );

        self::assertSame(SubmissionStatus::Draft, $proposal->getStatus());
        self::assertSame(['creditHours', 'courseCoordinators', 'creditCategorization'], $incomplete->getMissingFields());
        self::assertSame($incomplete, $proposal->getWorkingRevision());

        $complete = $proposal->addRevision(
            $coordinator,
            RevisionAuthorType::Coordinator,
            $this->completeContent(['catalogDescription' => 'Complete template', 'courseOutcomes' => ['Outcome']]),
        );
        $proposal->publishCoordinatorTemplate($complete);

        self::assertSame(SubmissionStatus::Approved, $proposal->getStatus());
        self::assertSame($complete, $proposal->getApprovedRevision());
        self::assertSame($complete, $course->getCurrentApprovedRevision());
        self::assertNull($proposal->getReview());
    }

    public function testIncompleteCoordinatorTemplateCannotBePublished(): void
    {
        [$course, , , $coordinator] = $this->fixture();
        $proposal = new TemplateSubmission($course, $coordinator, ProposalOrigin::CoordinatorCreated);
        $revision = $proposal->addRevision(
            $coordinator,
            RevisionAuthorType::Coordinator,
            [],
        );

        $this->expectException(\DomainException::class);
        $proposal->publishCoordinatorTemplate($revision);
    }

    public function testCoordinatorCanCorrectCourseDetailsBeforePublication(): void
    {
        [$course, , , $coordinator] = $this->fixture();
        $proposal = new TemplateSubmission($course, $coordinator, ProposalOrigin::CoordinatorCreated);
        $proposal->addRevision($coordinator, RevisionAuthorType::Coordinator, $this->completeContent());
        $newProgram = new Program('Computer Systems Engineering', 'BSE', '2027');

        $course->updateDraftDetails($proposal, $newProgram, 'cse', '361', 'Software Engineering II', DeliveryType::Online);

        self::assertSame($newProgram, $course->getProgram());
        self::assertSame('CSE', $course->getCourseSubject());
        self::assertSame('361', $course->getCourseNumber());
        self::assertSame('Software Engineering II', $course->getCourseName());
        self::assertSame(DeliveryType::Online, $course->getDeliveryType());
    }

    public function testPublishedCourseDetailsCannotBeChangedThroughDraftEditing(): void
    {
        [$course, , , $coordinator] = $this->fixture();
        $proposal = new TemplateSubmission($course, $coordinator, ProposalOrigin::CoordinatorCreated);
        $revision = $proposal->addRevision($coordinator, RevisionAuthorType::Coordinator, $this->completeContent());
        $proposal->publishCoordinatorTemplate($revision);

        $this->expectException(\DomainException::class);
        $course->updateDraftDetails($proposal, $course->getProgram(), 'CSE', '999', 'Changed', DeliveryType::Online);
    }

    public function testPublishedCoordinatorTemplateCanBeginAndPublishANewRevision(): void
    {
        [$course, , , $coordinator] = $this->fixture();
        $proposal = new TemplateSubmission($course, $coordinator, ProposalOrigin::CoordinatorCreated);
        $firstRevision = $proposal->addRevision($coordinator, RevisionAuthorType::Coordinator, $this->completeContent([
            'catalogDescription' => 'Original description',
        ]));
        $proposal->publishCoordinatorTemplate($firstRevision);

        $newDraft = $proposal->beginCoordinatorRevision($coordinator, $firstRevision->getContent());

        self::assertSame(SubmissionStatus::Draft, $proposal->getStatus());
        self::assertSame(2, $newDraft->getRevisionNumber());
        self::assertSame($firstRevision->getContent(), $newDraft->getContent());
        self::assertSame($firstRevision, $proposal->getApprovedRevision());
        self::assertSame($firstRevision, $course->getCurrentApprovedRevision());

        $updated = $proposal->addRevision($coordinator, RevisionAuthorType::Coordinator, $this->completeContent([
            'catalogDescription' => 'Updated description',
        ]));
        $proposal->publishCoordinatorTemplate($updated);

        self::assertSame(SubmissionStatus::Approved, $proposal->getStatus());
        self::assertSame($updated, $proposal->getApprovedRevision());
        self::assertSame($updated, $course->getCurrentApprovedRevision());
        self::assertSame($firstRevision, $proposal->getRevisions()->get(0));
    }

    public function testFacultyProposalRecordsTheApprovedTemplateUsedForPrefill(): void
    {
        [$course, , $faculty, $coordinator] = $this->fixture();
        $coordinatorProposal = new TemplateSubmission($course, $coordinator, ProposalOrigin::CoordinatorCreated);
        $template = $coordinatorProposal->addRevision(
            $coordinator,
            RevisionAuthorType::Coordinator,
            $this->completeContent(),
        );
        $coordinatorProposal->publishCoordinatorTemplate($template);

        $facultyProposal = new TemplateSubmission($course, $faculty, ProposalOrigin::FacultySubmission, $template);

        self::assertSame($template, $facultyProposal->getBasedOnRevision());
        self::assertSame(ProposalOrigin::FacultySubmission, $facultyProposal->getOrigin());
        self::assertSame($faculty, $facultyProposal->getSubmittedBy());
    }

    public function testIncompleteFacultyProposalCannotBeSubmitted(): void
    {
        [, $submission, $faculty] = $this->fixture();
        $revision = $submission->addRevision(
            $faculty,
            RevisionAuthorType::Faculty,
            ['creditHours' => 3],
        );

        $this->expectException(\DomainException::class);
        $submission->submit($revision);
    }

    /** @return array{CommonCourse, TemplateSubmission, User, User} */
    private function fixture(): array
    {
        $faculty = (new User())->setEmail('faculty@example.edu');
        $coordinator = (new User())->setEmail('coordinator@example.edu');
        $program = new Program('Computer Science', 'BS', '2026');
        $course = new CommonCourse($program, 'cse', '360', 'Software Engineering', DeliveryType::InPerson);

        return [
            $course,
            new TemplateSubmission($course, $faculty, ProposalOrigin::FacultySubmission),
            $faculty,
            $coordinator,
        ];
    }

    private function completeFacultyRevision(TemplateSubmission $submission, User $faculty, array $content = []): \App\Entity\SyllabusTemplate\TemplateRevision
    {
        return $submission->addRevision(
            $faculty,
            RevisionAuthorType::Faculty,
            $this->completeContent($content),
        );
    }

    private function completeContent(array $overrides = []): array
    {
        return $overrides + [
            'creditHours' => 3,
            'courseCoordinators' => ['Coordinator Name'],
            'creditCategorization' => 'engineering',
        ];
    }
}
