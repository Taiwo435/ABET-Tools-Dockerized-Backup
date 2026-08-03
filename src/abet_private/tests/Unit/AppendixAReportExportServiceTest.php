<?php

namespace Tests\Unit;

use App\Entity\Program;
use App\Entity\SyllabusTemplate\CommonCourse;
use App\Entity\SyllabusTemplate\CourseOffering;
use App\Entity\SyllabusTemplate\DeliveryType;
use App\Entity\SyllabusTemplate\ProposalOrigin;
use App\Entity\SyllabusTemplate\ReviewDecision;
use App\Entity\SyllabusTemplate\RevisionAuthorType;
use App\Entity\SyllabusTemplate\TemplateReview;
use App\Entity\SyllabusTemplate\TemplateSubmission;
use App\Entity\User;
use App\Service\Report\AppendixAReportExportService;
use PHPUnit\Framework\TestCase;

final class AppendixAReportExportServiceTest extends TestCase
{
    public function testPublishedSharedBaselineCrossesTheReportBoundary(): void
    {
        $coordinator = (new User())->setEmail('coordinator@example.edu');
        $submission = new TemplateSubmission(
            $this->course(),
            $coordinator,
            ProposalOrigin::CoordinatorCreated,
        );
        $revision = $submission->addRevision(
            $coordinator,
            RevisionAuthorType::Coordinator,
            $this->appendixContent(),
        );
        $submission->publishCoordinatorTemplate($revision);

        $payload = (new AppendixAReportExportService())->export([$revision])->toArray();

        self::assertCount(1, $payload['courses']);
        self::assertSame('CSE 360', $payload['courses'][0]['course_code']);
        self::assertSame('in_person', $payload['courses'][0]['delivery_type']);
    }

    public function testApprovedOfferingRevisionExportsTheVersionedAppendixContract(): void
    {
        [$revision] = $this->approvedOfferingRevision();

        $payload = (new AppendixAReportExportService())->export([$revision])->toArray();

        self::assertSame('1.0', $payload['schema_version']);
        self::assertCount(1, $payload['courses']);
        self::assertSame([
            'course_code',
            'course_name',
            'credits',
            'contact_hours',
            'credit_category',
            'delivery_type',
            'instructors',
            'textbooks',
            'catalog_description',
            'prerequisites',
            'course_type',
            'specific_goals',
            'student_outcomes',
            'topics_covered',
        ], array_keys($payload['courses'][0]));
        self::assertSame('CSE 360', $payload['courses'][0]['course_code']);
        self::assertSame('hybrid', $payload['courses'][0]['delivery_type']);
        self::assertSame(['Faculty One'], $payload['courses'][0]['instructors']);
    }

    public function testExportRejectsRevisionThatIsNotAppendixReady(): void
    {
        $faculty = (new User())->setEmail('faculty@example.edu');
        $course = $this->course();
        $offering = new CourseOffering($course, '2026-2027', 'Fall', DeliveryType::Hybrid, $faculty);
        $submission = TemplateSubmission::forFacultyOffering($offering, $faculty);
        $revision = $submission->addRevision(
            $faculty,
            RevisionAuthorType::Faculty,
            [
                'credits' => 3,
                'course_coordinators' => ['Coordinator'],
                'credit_category' => 'engineering',
            ],
        );
        $submission->submit($revision);
        $submission->recordReview(
            new TemplateReview(
                $submission,
                (new User())->setEmail('coordinator@example.edu'),
                ReviewDecision::Approved,
            ),
            $revision,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not Appendix A ready');

        (new AppendixAReportExportService())->export([$revision]);
    }

    public function testExportRejectsTwoSelectedOfferingsForTheSameCourse(): void
    {
        [$firstRevision, $course, $faculty, $coordinator] = $this->approvedOfferingRevision();
        $secondOffering = new CourseOffering($course, '2026-2027', 'Spring', DeliveryType::Online, $faculty);
        $secondSubmission = TemplateSubmission::forFacultyOffering($secondOffering, $faculty);
        $secondRevision = $secondSubmission->addRevision(
            $faculty,
            RevisionAuthorType::Faculty,
            $this->appendixContent(),
        );
        $secondSubmission->submit($secondRevision);
        $secondSubmission->recordReview(
            new TemplateReview($secondSubmission, $coordinator, ReviewDecision::Approved),
            $secondRevision,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('more than one syllabus for CSE 360');

        (new AppendixAReportExportService())->export([$firstRevision, $secondRevision]);
    }

    /** @return array{0: \App\Entity\SyllabusTemplate\TemplateRevision, 1: CommonCourse, 2: User, 3: User} */
    private function approvedOfferingRevision(): array
    {
        $faculty = (new User())->setEmail('faculty@example.edu');
        $coordinator = (new User())->setEmail('coordinator@example.edu');
        $course = $this->course();
        $offering = new CourseOffering($course, '2026-2027', 'Fall', DeliveryType::Hybrid, $faculty);
        $submission = TemplateSubmission::forFacultyOffering($offering, $faculty);
        $revision = $submission->addRevision(
            $faculty,
            RevisionAuthorType::Faculty,
            $this->appendixContent(),
        );
        $submission->submit($revision);
        $submission->recordReview(
            new TemplateReview($submission, $coordinator, ReviewDecision::Approved),
            $revision,
        );

        return [$revision, $course, $faculty, $coordinator];
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

    /** @return array<string, mixed> */
    private function appendixContent(): array
    {
        return [
            'credits' => 3,
            'contact_hours' => '3 hours/week',
            'credit_category' => 'engineering',
            'course_coordinators' => ['Coordinator'],
            'instructors' => ['Faculty One'],
            'textbooks' => ['Reference Book'],
            'catalog_description' => 'Software engineering principles.',
            'prerequisites' => '',
            'course_type' => 'R',
            'specific_goals' => ['Apply engineering design.'],
            'student_outcomes' => ['SO 2'],
            'topics_covered' => ['Requirements', 'Design'],
        ];
    }
}
