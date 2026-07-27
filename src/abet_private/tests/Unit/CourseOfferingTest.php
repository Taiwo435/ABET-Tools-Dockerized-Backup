<?php

namespace Tests\Unit;

use App\Entity\Program;
use App\Entity\SyllabusTemplate\CommonCourse;
use App\Entity\SyllabusTemplate\CourseOffering;
use App\Entity\SyllabusTemplate\DeliveryType;
use App\Entity\SyllabusTemplate\ProposalOrigin;
use App\Entity\SyllabusTemplate\SubmissionKind;
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
}
