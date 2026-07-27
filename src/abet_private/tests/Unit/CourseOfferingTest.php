<?php

namespace Tests\Unit;

use App\Entity\Program;
use App\Entity\SyllabusTemplate\CommonCourse;
use App\Entity\SyllabusTemplate\CourseOffering;
use App\Entity\SyllabusTemplate\DeliveryType;
use App\Entity\SyllabusTemplate\ProposalOrigin;
use App\Entity\SyllabusTemplate\ReviewDecision;
use App\Entity\SyllabusTemplate\RevisionAuthorType;
use App\Entity\SyllabusTemplate\SubmissionKind;
use App\Entity\SyllabusTemplate\SubmissionStatus;
use App\Entity\SyllabusTemplate\TemplateReview;
use App\Entity\SyllabusTemplate\TemplateSubmission;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class CourseOfferingTest extends TestCase
{
    public function testOfferingCapturesTermDeliverySectionAndInstructor(): void
    {
        $faculty = (new User())->setEmail('faculty@example.edu');
        $offering = new CourseOffering(
            $this->course(),
            ' 2026-2027 ',
            ' Fall ',
            DeliveryType::Hybrid,
            $faculty,
            ' 001 ',
        );

        self::assertSame('2026-2027', $offering->getAcademicYear());
        self::assertSame('Fall', $offering->getTerm());
        self::assertSame('001', $offering->getSection());
        self::assertSame(DeliveryType::Hybrid, $offering->getDeliveryType());
        self::assertSame($faculty, $offering->getInstructor());
        self::assertNull($offering->getCurrentApprovedRevision());
    }

    public function testFacultyOfferingFactoryRecordsAnExplicitTargetKind(): void
    {
        $faculty = (new User())->setEmail('faculty@example.edu');
        $offering = new CourseOffering(
            $this->course(),
            '2026-2027',
            'Fall',
            DeliveryType::InPerson,
            $faculty,
        );

        $submission = TemplateSubmission::forFacultyOffering($offering, $faculty);

        self::assertSame(SubmissionKind::FacultyOffering, $submission->getKind());
        self::assertSame(ProposalOrigin::FacultySubmission, $submission->getOrigin());
        self::assertSame($offering, $submission->getCourseOffering());
        self::assertSame($offering->getCommonCourse(), $submission->getCommonCourse());
    }

    public function testExistingSubmissionConstructorRemainsSharedTemplateWork(): void
    {
        $coordinator = (new User())->setEmail('coordinator@example.edu');
        $submission = new TemplateSubmission(
            $this->course(),
            $coordinator,
            ProposalOrigin::CoordinatorCreated,
        );

        self::assertSame(SubmissionKind::SharedTemplate, $submission->getKind());
        self::assertNull($submission->getCourseOffering());
    }

    public function testFacultyOfferingKindCannotBeCreatedWithoutAnOffering(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('requires a course offering');

        new TemplateSubmission(
            $this->course(),
            (new User())->setEmail('faculty@example.edu'),
            ProposalOrigin::FacultySubmission,
            null,
            SubmissionKind::FacultyOffering,
        );
    }

    public function testApprovedFacultyOfferingPublishesOnlyToItsOffering(): void
    {
        $faculty = (new User())->setEmail('faculty@example.edu');
        $coordinator = (new User())->setEmail('coordinator@example.edu');
        $course = $this->course();
        $sharedTemplate = new TemplateSubmission($course, $coordinator, ProposalOrigin::CoordinatorCreated);
        $sharedRevision = $sharedTemplate->addRevision(
            $coordinator,
            RevisionAuthorType::Coordinator,
            $this->submittableContent(['catalog_description' => 'Shared baseline']),
        );
        $sharedTemplate->publishCoordinatorTemplate($sharedRevision);

        $offering = new CourseOffering($course, '2026-2027', 'Fall', DeliveryType::InPerson, $faculty);
        $submission = TemplateSubmission::forFacultyOffering($offering, $faculty, $sharedRevision);
        $facultyRevision = $submission->addRevision(
            $faculty,
            RevisionAuthorType::Faculty,
            $this->submittableContent(['catalog_description' => 'Fall offering']),
        );
        $submission->submit($facultyRevision);

        // A newer shared baseline does not make an offering snapshot unsafe to
        // approve because the offering cannot replace the common-course source.
        $newSharedRevision = $sharedTemplate->beginCoordinatorRevision(
            $coordinator,
            $this->submittableContent(['catalog_description' => 'New shared baseline']),
        );
        $sharedTemplate->publishCoordinatorTemplate($newSharedRevision);

        self::assertFalse($submission->hasSharedTemplateChanged());
        $submission->recordReview(
            new TemplateReview($submission, $coordinator, ReviewDecision::Approved),
            $facultyRevision,
        );

        self::assertSame(SubmissionStatus::Approved, $submission->getStatus());
        self::assertSame($facultyRevision, $offering->getCurrentApprovedRevision());
        self::assertSame($newSharedRevision, $course->getCurrentApprovedRevision());
    }

    public function testOfferingApprovalWithEditsPublishesCoordinatorRevisionToOffering(): void
    {
        $faculty = (new User())->setEmail('faculty@example.edu');
        $coordinator = (new User())->setEmail('coordinator@example.edu');
        $course = $this->course();
        $offering = new CourseOffering($course, '2026-2027', 'Spring', DeliveryType::Hybrid, $faculty);
        $submission = TemplateSubmission::forFacultyOffering($offering, $faculty);
        $facultyRevision = $submission->addRevision(
            $faculty,
            RevisionAuthorType::Faculty,
            $this->submittableContent(['catalog_description' => 'Faculty version']),
        );
        $submission->submit($facultyRevision);
        $coordinatorRevision = $submission->addRevision(
            $coordinator,
            RevisionAuthorType::Coordinator,
            $this->submittableContent(['catalog_description' => 'Coordinator edit']),
        );

        $submission->recordReview(
            new TemplateReview($submission, $coordinator, ReviewDecision::ApprovedWithEdits),
            $coordinatorRevision,
        );

        self::assertSame(SubmissionStatus::ApprovedWithEdits, $submission->getStatus());
        self::assertSame($facultyRevision, $submission->getSubmittedRevision());
        self::assertSame($coordinatorRevision, $offering->getCurrentApprovedRevision());
        self::assertNull($course->getCurrentApprovedRevision());
    }

    public function testPublicationTargetsRejectTheOtherSubmissionKind(): void
    {
        $faculty = (new User())->setEmail('faculty@example.edu');
        $course = $this->course();
        $offering = new CourseOffering($course, '2026-2027', 'Summer', DeliveryType::Online, $faculty);
        $submission = TemplateSubmission::forFacultyOffering($offering, $faculty);
        $revision = $submission->addRevision(
            $faculty,
            RevisionAuthorType::Faculty,
            $this->submittableContent(),
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only a shared-template revision for this common course can be published.');

        $course->publish($revision);
    }

    private function course(): CommonCourse
    {
        return new CommonCourse(
            new Program('Computer Science', 'BS', '2026'),
            'CSE',
            '360',
            'Software Engineering',
            DeliveryType::InPerson,
        );
    }

    /** @param array<string, mixed> $overrides */
    private function submittableContent(array $overrides = []): array
    {
        return $overrides + [
            'credits' => 3,
            'course_coordinators' => ['Coordinator Name'],
            'credit_category' => 'engineering',
        ];
    }
}
