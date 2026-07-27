<?php

namespace Tests\Unit;

use App\Entity\SyllabusTemplate\CommonCourse;
use App\Entity\SyllabusTemplate\CourseOffering;
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

    public function testApprovalUnchangedCannotReplaceANewerSharedTemplateRevision(): void
    {
        [$course, , $faculty, $coordinator] = $this->fixture();
        $sharedTemplate = new TemplateSubmission($course, $coordinator, ProposalOrigin::CoordinatorCreated);
        $firstSharedRevision = $sharedTemplate->addRevision($coordinator, RevisionAuthorType::Coordinator, $this->completeContent());
        $sharedTemplate->publishCoordinatorTemplate($firstSharedRevision);

        $facultyProposal = new TemplateSubmission($course, $faculty, ProposalOrigin::FacultySubmission, $firstSharedRevision);
        $facultyRevision = $this->completeFacultyRevision($facultyProposal, $faculty, $firstSharedRevision->getContent());
        $facultyProposal->submit($facultyRevision);

        $newSharedRevision = $sharedTemplate->beginCoordinatorRevision($coordinator, $this->completeContent([
            'catalog_description' => 'Newer shared content',
        ]));
        $sharedTemplate->publishCoordinatorTemplate($newSharedRevision);

        self::assertTrue($facultyProposal->hasSharedTemplateChanged());
        $review = new TemplateReview($facultyProposal, $coordinator, ReviewDecision::Approved);

        try {
            $facultyProposal->recordReview($review, $facultyRevision);
            self::fail('Approval unchanged should reject a proposal based on an outdated shared revision.');
        } catch (\DomainException $exception) {
            self::assertSame('Approval without edits cannot replace a newer shared template revision.', $exception->getMessage());
        }

        self::assertSame(SubmissionStatus::Submitted, $facultyProposal->getStatus());
        self::assertNull($facultyProposal->getReview());
        self::assertSame($newSharedRevision, $course->getCurrentApprovedRevision());
    }

    public function testApprovalWithEditsPreservesFacultyRevisionAndPublishesCoordinatorRevision(): void
    {
        [$course, $submission, $faculty, $coordinator] = $this->fixture();
        $facultyRevision = $this->completeFacultyRevision($submission, $faculty, ['catalog_description' => 'Original']);
        $submission->submit($facultyRevision);
        $coordinatorRevision = $submission->addRevision($coordinator, RevisionAuthorType::Coordinator, $this->completeContent([
            'catalog_description' => 'Edited',
        ]));

        $review = new TemplateReview($submission, $coordinator, ReviewDecision::ApprovedWithEdits, 'Standardized the catalog description.');
        $submission->recordReview($review, $coordinatorRevision);

        self::assertSame(SubmissionStatus::ApprovedWithEdits, $submission->getStatus());
        self::assertSame($facultyRevision, $submission->getSubmittedRevision());
        self::assertSame($coordinatorRevision, $submission->getApprovedRevision());
        self::assertSame($coordinatorRevision, $course->getCurrentApprovedRevision());
        self::assertSame(2, $submission->getRevisions()->count());
    }

    public function testApprovalWithEditsCanCorrectAllCourseDetailsWithoutChangingSubmittedRevision(): void
    {
        [$course, $submission, $faculty, $coordinator] = $this->fixture();
        $facultyRevision = $this->completeFacultyRevision($submission, $faculty);
        $submission->submit($facultyRevision);
        $coordinatorRevision = $submission->addRevision($coordinator, RevisionAuthorType::Coordinator, $facultyRevision->getContent());
        $newProgram = new Program('Software Engineering', 'BS', '2027');

        $course->updateDuringFacultyReview(
            $submission,
            $newProgram,
            'ser',
            '401',
            'Capstone Design',
            DeliveryType::Hybrid,
        );
        $review = new TemplateReview($submission, $coordinator, ReviewDecision::ApprovedWithEdits);
        $submission->recordReview($review, $coordinatorRevision, courseDetailsChanged: true);

        self::assertSame(SubmissionStatus::ApprovedWithEdits, $submission->getStatus());
        self::assertSame($facultyRevision, $submission->getSubmittedRevision());
        self::assertSame($coordinatorRevision, $submission->getApprovedRevision());
        self::assertSame($newProgram, $course->getProgram());
        self::assertSame('SER', $course->getCourseSubject());
        self::assertSame('401', $course->getCourseNumber());
        self::assertSame('Capstone Design', $course->getCourseName());
        self::assertSame(DeliveryType::Hybrid, $course->getDeliveryType());
    }

    public function testCourseDetailsCannotBeChangedAfterFacultyReviewIsComplete(): void
    {
        [$course, $submission, $faculty, $coordinator] = $this->fixture();
        $facultyRevision = $this->completeFacultyRevision($submission, $faculty);
        $submission->submit($facultyRevision);
        $coordinatorRevision = $submission->addRevision($coordinator, RevisionAuthorType::Coordinator, $this->completeContent(['catalog_description' => 'Edited']));
        $submission->recordReview(
            new TemplateReview($submission, $coordinator, ReviewDecision::ApprovedWithEdits),
            $coordinatorRevision,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Course details can only be corrected while a faculty submission is pending review.');

        $course->updateDuringFacultyReview(
            $submission,
            $course->getProgram(),
            'CSE',
            '999',
            'Too Late',
            DeliveryType::Online,
        );
    }

    public function testApprovalWithEditsCannotReplaceANewerSharedTemplateWithoutReconciliation(): void
    {
        [$course, , $faculty, $coordinator] = $this->fixture();
        $sharedTemplate = new TemplateSubmission($course, $coordinator, ProposalOrigin::CoordinatorCreated);
        $firstSharedRevision = $sharedTemplate->addRevision($coordinator, RevisionAuthorType::Coordinator, $this->completeContent());
        $sharedTemplate->publishCoordinatorTemplate($firstSharedRevision);
        $facultyProposal = new TemplateSubmission($course, $faculty, ProposalOrigin::FacultySubmission, $firstSharedRevision);
        $facultyRevision = $this->completeFacultyRevision($facultyProposal, $faculty, $firstSharedRevision->getContent());
        $facultyProposal->submit($facultyRevision);

        $newSharedRevision = $sharedTemplate->beginCoordinatorRevision($coordinator, $this->completeContent(['catalog_description' => 'New shared baseline']));
        $sharedTemplate->publishCoordinatorTemplate($newSharedRevision);
        $coordinatorRevision = $facultyProposal->addRevision($coordinator, RevisionAuthorType::Coordinator, $this->completeContent(['catalog_description' => 'Review edit']));
        $review = new TemplateReview($facultyProposal, $coordinator, ReviewDecision::ApprovedWithEdits);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Approval with edits cannot replace a newer shared template revision without reconciliation.');

        try {
            $facultyProposal->recordReview($review, $coordinatorRevision);
        } finally {
            self::assertSame($facultyRevision, $facultyProposal->getSubmittedRevision());
            self::assertSame($newSharedRevision, $course->getCurrentApprovedRevision());
        }
    }

    public function testApprovalWithEditsRejectsAnUnchangedCoordinatorRevision(): void
    {
        [, $submission, $faculty, $coordinator] = $this->fixture();
        $facultyRevision = $this->completeFacultyRevision($submission, $faculty);
        $submission->submit($facultyRevision);
        $coordinatorRevision = $submission->addRevision($coordinator, RevisionAuthorType::Coordinator, $facultyRevision->getContent());
        $review = new TemplateReview($submission, $coordinator, ReviewDecision::ApprovedWithEdits);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Approval with edits requires a meaningful content change.');

        $submission->recordReview($review, $coordinatorRevision);
    }

    public function testBlankFacultyProposalCannotReplaceASharedTemplatePublishedWhileItWaited(): void
    {
        [$course, $facultyProposal, $faculty, $coordinator] = $this->fixture();
        $facultyRevision = $this->completeFacultyRevision($facultyProposal, $faculty);
        $facultyProposal->submit($facultyRevision);

        $sharedTemplate = new TemplateSubmission($course, $coordinator, ProposalOrigin::CoordinatorCreated);
        $sharedRevision = $sharedTemplate->addRevision($coordinator, RevisionAuthorType::Coordinator, $this->completeContent());
        $sharedTemplate->publishCoordinatorTemplate($sharedRevision);

        self::assertTrue($facultyProposal->hasSharedTemplateChanged());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Approval without edits cannot replace a newer shared template revision.');

        $facultyProposal->recordReview(
            new TemplateReview($facultyProposal, $coordinator, ReviewDecision::Approved),
            $facultyRevision,
        );
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

    public function testFacultyCannotOptIntoCoordinatorSelfReviewException(): void
    {
        [, $submission, $faculty] = $this->fixture();
        $revision = $this->completeFacultyRevision($submission, $faculty);
        $submission->submit($revision);

        $this->expectException(\InvalidArgumentException::class);
        new TemplateReview(
            $submission,
            $faculty,
            ReviewDecision::Approved,
            allowCoordinatorSelfReviewForDemonstration: true,
        );
    }

    public function testCoordinatorCanSelfReviewFacultySubmissionForDemonstration(): void
    {
        [$course, , $coordinator] = $this->fixture();
        $coordinator->setRole('admin');
        $offering = new CourseOffering(
            $course,
            '2026-2027',
            'Fall',
            DeliveryType::InPerson,
            $coordinator,
            '001',
        );
        $submission = TemplateSubmission::forFacultyOffering($offering, $coordinator);
        $revision = $this->completeFacultyRevision($submission, $coordinator);
        $submission->submit($revision);
        $review = new TemplateReview(
            $submission,
            $coordinator,
            ReviewDecision::Approved,
            allowCoordinatorSelfReviewForDemonstration: true,
        );

        $submission->recordReview($review, $revision);

        self::assertSame(SubmissionStatus::Approved, $submission->getStatus());
        self::assertSame($coordinator, $review->getReviewer());
        self::assertSame($revision, $offering->getCurrentApprovedRevision());
        self::assertNull($course->getCurrentApprovedRevision());
    }

    public function testCoordinatorCanKeepAnIncompleteTemplateDraftAndPublishACompleteRevision(): void
    {
        [$course, , , $coordinator] = $this->fixture();
        $proposal = new TemplateSubmission($course, $coordinator, ProposalOrigin::CoordinatorCreated);
        $incomplete = $proposal->addRevision(
            $coordinator,
            RevisionAuthorType::Coordinator,
            ['catalog_description' => 'Draft'],
        );

        self::assertSame(SubmissionStatus::Draft, $proposal->getStatus());
        self::assertSame(['credits', 'course_coordinators', 'credit_category'], $incomplete->getMissingFields());
        self::assertSame($incomplete, $proposal->getWorkingRevision());

        $complete = $proposal->addRevision(
            $coordinator,
            RevisionAuthorType::Coordinator,
            $this->completeContent(['catalog_description' => 'Complete template', 'course_outcomes' => ['Outcome']]),
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
            'catalog_description' => 'Original description',
        ]));
        $proposal->publishCoordinatorTemplate($firstRevision);

        $newDraft = $proposal->beginCoordinatorRevision($coordinator, $firstRevision->getContent());

        self::assertSame(SubmissionStatus::Draft, $proposal->getStatus());
        self::assertSame(2, $newDraft->getRevisionNumber());
        self::assertSame($firstRevision->getContent(), $newDraft->getContent());
        self::assertSame($firstRevision, $proposal->getApprovedRevision());
        self::assertSame($firstRevision, $course->getCurrentApprovedRevision());

        $updated = $proposal->addRevision($coordinator, RevisionAuthorType::Coordinator, $this->completeContent([
            'catalog_description' => 'Updated description',
        ]));
        $proposal->publishCoordinatorTemplate($updated);

        self::assertSame(SubmissionStatus::Approved, $proposal->getStatus());
        self::assertSame($updated, $proposal->getApprovedRevision());
        self::assertSame($updated, $course->getCurrentApprovedRevision());
        self::assertSame($firstRevision, $proposal->getRevisions()->get(0));
    }

    public function testCurrentApprovedFacultyTemplateCanSeedAnIndependentCoordinatorDraft(): void
    {
        [$course, $facultyProposal, $faculty, $coordinator] = $this->fixture();
        $facultyRevision = $this->completeFacultyRevision($facultyProposal, $faculty, [
            'catalog_description' => 'Approved faculty content',
        ]);
        $facultyProposal->submit($facultyRevision);
        $facultyProposal->recordReview(
            new TemplateReview($facultyProposal, $coordinator, ReviewDecision::Approved),
            $facultyRevision,
        );

        $coordinatorDraft = $facultyProposal->createCoordinatorRevisionDraft($coordinator, array_replace(
            $facultyRevision->getContent(),
            ['catalog_description' => 'Coordinator revision draft'],
        ));

        self::assertSame(ProposalOrigin::CoordinatorCreated, $coordinatorDraft->getOrigin());
        self::assertSame(SubmissionStatus::Draft, $coordinatorDraft->getStatus());
        self::assertSame($facultyRevision, $coordinatorDraft->getBasedOnRevision());
        self::assertSame('Coordinator revision draft', $coordinatorDraft->getWorkingRevision()?->getContent()['catalog_description']);
        self::assertSame(SubmissionStatus::Approved, $facultyProposal->getStatus());
        self::assertSame('Approved faculty content', $facultyRevision->getContent()['catalog_description']);
        self::assertSame($facultyRevision, $course->getCurrentApprovedRevision());
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

    public function testFacultyWorkingCopyRemainsIndependentFromApprovedSharedRevision(): void
    {
        [$course, , $faculty, $coordinator] = $this->fixture();
        $sharedTemplate = new TemplateSubmission($course, $coordinator, ProposalOrigin::CoordinatorCreated);
        $approvedRevision = $sharedTemplate->addRevision(
            $coordinator,
            RevisionAuthorType::Coordinator,
            $this->completeContent(['catalog_description' => 'Approved shared description']),
        );
        $sharedTemplate->publishCoordinatorTemplate($approvedRevision);

        $facultyProposal = new TemplateSubmission($course, $faculty, ProposalOrigin::FacultySubmission, $approvedRevision);
        $initialCopy = $facultyProposal->addRevision($faculty, RevisionAuthorType::Faculty, $approvedRevision->getContent());
        $editedCopy = $facultyProposal->addRevision(
            $faculty,
            RevisionAuthorType::Faculty,
            array_replace($initialCopy->getContent(), ['catalog_description' => 'Faculty-specific description']),
        );

        self::assertSame($approvedRevision, $facultyProposal->getBasedOnRevision());
        self::assertSame('Approved shared description', $approvedRevision->getContent()['catalog_description']);
        self::assertSame('Approved shared description', $initialCopy->getContent()['catalog_description']);
        self::assertSame('Faculty-specific description', $editedCopy->getContent()['catalog_description']);
        self::assertSame($editedCopy, $facultyProposal->getWorkingRevision());
    }

    public function testFacultyOwnerCanPrepareTheirUnsubmittedDraftForDeletion(): void
    {
        [$course, , $faculty, $coordinator] = $this->fixture();
        $sharedTemplate = new TemplateSubmission($course, $coordinator, ProposalOrigin::CoordinatorCreated);
        $approvedRevision = $sharedTemplate->addRevision(
            $coordinator,
            RevisionAuthorType::Coordinator,
            $this->completeContent(),
        );
        $sharedTemplate->publishCoordinatorTemplate($approvedRevision);
        $facultyDraft = new TemplateSubmission($course, $faculty, ProposalOrigin::FacultySubmission, $approvedRevision);
        $facultyDraft->addRevision($faculty, RevisionAuthorType::Faculty, $approvedRevision->getContent());

        $facultyDraft->prepareFacultyDraftDeletion($faculty);

        self::assertNull($facultyDraft->getWorkingRevision());
        self::assertNull($facultyDraft->getBasedOnRevision());
    }

    public function testAnotherUserCannotPrepareFacultyDraftForDeletion(): void
    {
        [, $submission, , $coordinator] = $this->fixture();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only the proposal owner can delete their faculty draft.');

        $submission->prepareFacultyDraftDeletion($coordinator);
    }

    public function testBlankFacultyDraftCanUpdateAllCourseIdentityFieldsBeforeSubmission(): void
    {
        [$course, , $faculty] = $this->fixture();
        $submission = new TemplateSubmission($course, $faculty, ProposalOrigin::FacultySubmission);
        $submission->addRevision($faculty, RevisionAuthorType::Faculty, $this->completeContent());
        $newProgram = new Program('Software Engineering', 'BS', '2027');

        $course->updateBlankFacultyDraftDetails(
            $submission,
            $newProgram,
            'ser',
            '401',
            'Capstone Design',
            DeliveryType::Hybrid,
        );

        self::assertSame($newProgram, $course->getProgram());
        self::assertSame('SER', $course->getCourseSubject());
        self::assertSame('401', $course->getCourseNumber());
        self::assertSame('Capstone Design', $course->getCourseName());
        self::assertSame(DeliveryType::Hybrid, $course->getDeliveryType());
    }

    public function testTemplateBasedFacultyDraftCannotRenameSharedCourse(): void
    {
        [$course, , $faculty, $coordinator] = $this->fixture();
        $sharedTemplate = new TemplateSubmission($course, $coordinator, ProposalOrigin::CoordinatorCreated);
        $approvedRevision = $sharedTemplate->addRevision($coordinator, RevisionAuthorType::Coordinator, $this->completeContent());
        $sharedTemplate->publishCoordinatorTemplate($approvedRevision);
        $facultyDraft = new TemplateSubmission($course, $faculty, ProposalOrigin::FacultySubmission, $approvedRevision);
        $facultyDraft->addRevision($faculty, RevisionAuthorType::Faculty, $approvedRevision->getContent());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Course details can only be changed through their blank faculty draft.');

        $course->updateBlankFacultyDraftDetails(
            $facultyDraft,
            $course->getProgram(),
            'SER',
            '401',
            'Changed Shared Course',
            DeliveryType::Online,
        );
    }

    public function testIncompleteFacultyProposalCannotBeSubmitted(): void
    {
        [, $submission, $faculty] = $this->fixture();
        $revision = $submission->addRevision(
            $faculty,
            RevisionAuthorType::Faculty,
            ['credits' => 3],
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
            'credits' => 3,
            'course_coordinators' => ['Coordinator Name'],
            'credit_category' => 'engineering',
        ];
    }
}
