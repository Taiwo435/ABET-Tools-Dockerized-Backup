<?php

declare(strict_types=1);

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
use App\ReadModel\SyllabusReadinessState;
use App\Repository\SyllabusReadinessRepository;
use PHPUnit\Framework\TestCase;

final class SyllabusReadinessRepositoryTest extends TestCase
{
    public function testProjectsBlankSubmittedOfferingAsAwaitingReview(): void
    {
        $program = new Program('Computer Science', 'BS', '2026');
        $this->setId($program, 1);
        $faculty = $this->user('faculty@example.edu');
        $course = $this->course($program, 101, 'CS', '310', 'Data Structures');
        $offering = new CourseOffering(
            $course,
            '2026-2027',
            'Fall',
            DeliveryType::InPerson,
            $faculty,
            '001',
        );
        $this->setId($offering, 401);
        $submission = TemplateSubmission::forFacultyOffering($offering, $faculty);
        $this->setId($submission, 501);
        $revision = $submission->addRevision(
            $faculty,
            RevisionAuthorType::Faculty,
            $this->transitionContent(),
        );
        $this->setId($revision, 601);
        $submission->submit($revision);

        $rows = SyllabusReadinessRepository::projectRows($program, [$course], [$submission]);

        self::assertCount(1, $rows);
        self::assertSame(SyllabusReadinessState::AwaitingCoordinatorReview, $rows[0]->getState());
        self::assertSame(401, $rows[0]->getCourseOfferingId());
        self::assertSame([
            'Ready' => 0,
            'Blocked' => 0,
            'Awaiting review' => 1,
            'Missing' => 0,
        ], SyllabusReadinessRepository::countRowsByCategory($rows));
    }

    public function testProjectsCanonicalSharedTemplatesAndEveryOffering(): void
    {
        $program = new Program('Computer Science', 'BS', '2026');
        $this->setId($program, 1);
        $coordinator = $this->user('coordinator@example.edu');
        $faculty = $this->user('faculty@example.edu');

        $missingCourse = $this->course($program, 101, 'CSE', '101', 'Introduction');

        $draftCourse = $this->course($program, 102, 'CSE', '102', 'Programming');
        $sharedDraft = new TemplateSubmission(
            $draftCourse,
            $coordinator,
            ProposalOrigin::CoordinatorCreated,
        );
        $this->setId($sharedDraft, 202);
        $sharedDraftRevision = $sharedDraft->addRevision(
            $coordinator,
            RevisionAuthorType::Coordinator,
            [],
        );
        $this->setId($sharedDraftRevision, 302);

        $offeringCourse = $this->course($program, 103, 'CSE', '310', 'Data Structures');
        $publishedTemplate = new TemplateSubmission(
            $offeringCourse,
            $coordinator,
            ProposalOrigin::CoordinatorCreated,
        );
        $this->setId($publishedTemplate, 203);
        $publishedRevision = $publishedTemplate->addRevision(
            $coordinator,
            RevisionAuthorType::Coordinator,
            $this->transitionContent(),
        );
        $this->setId($publishedRevision, 303);
        $publishedTemplate->publishCoordinatorTemplate($publishedRevision);

        $fallOffering = new CourseOffering(
            $offeringCourse,
            '2026-2027',
            'Fall',
            DeliveryType::InPerson,
            $faculty,
            '001',
        );
        $this->setId($fallOffering, 401);
        $fallSubmission = TemplateSubmission::forFacultyOffering(
            $fallOffering,
            $faculty,
            $publishedRevision,
        );
        $this->setId($fallSubmission, 501);
        $fallRevision = $fallSubmission->addRevision(
            $faculty,
            RevisionAuthorType::Faculty,
            [],
        );
        $this->setId($fallRevision, 601);

        $springOffering = new CourseOffering(
            $offeringCourse,
            '2026-2027',
            'Spring',
            DeliveryType::Online,
            $faculty,
            '002',
        );
        $this->setId($springOffering, 402);
        $springSubmission = TemplateSubmission::forFacultyOffering(
            $springOffering,
            $faculty,
            $publishedRevision,
        );
        $this->setId($springSubmission, 502);
        $springRevision = $springSubmission->addRevision(
            $faculty,
            RevisionAuthorType::Faculty,
            $this->transitionContent(),
        );
        $this->setId($springRevision, 602);
        $springSubmission->submit($springRevision);
        $approval = new TemplateReview(
            $springSubmission,
            $coordinator,
            ReviewDecision::Approved,
        );
        $springSubmission->recordReview($approval, $springRevision);

        $rows = SyllabusReadinessRepository::projectRows(
            $program,
            [$missingCourse, $draftCourse, $offeringCourse],
            [$sharedDraft, $publishedTemplate, $fallSubmission, $springSubmission],
        );

        self::assertCount(4, $rows);
        self::assertSame(SyllabusReadinessState::NoSharedTemplate, $rows[0]->getState());
        self::assertSame(
            SyllabusReadinessState::SharedTemplateNeedsPublicationFields,
            $rows[1]->getState(),
        );
        self::assertSame(
            SyllabusReadinessState::FacultyDraftNeedsSubmissionFields,
            $rows[2]->getState(),
        );
        self::assertSame(401, $rows[2]->getCourseOfferingId());
        self::assertSame('Fall', $rows[2]->getTerm());
        self::assertSame(
            SyllabusReadinessState::ApprovedAppendixAIncomplete,
            $rows[3]->getState(),
        );
        self::assertSame(402, $rows[3]->getCourseOfferingId());
        self::assertSame('Spring', $rows[3]->getTerm());
        self::assertContains('contact_hours', $rows[3]->getAppendixABlockingFields());
        self::assertSame([
            'Ready' => 0,
            'Blocked' => 3,
            'Awaiting review' => 0,
            'Missing' => 1,
        ], SyllabusReadinessRepository::countRowsByCategory($rows));
    }

    /** @return array<string, mixed> */
    private function transitionContent(): array
    {
        return [
            'credits' => 3,
            'course_coordinators' => ['Coordinator'],
            'credit_category' => 'engineering',
        ];
    }

    private function course(
        Program $program,
        int $id,
        string $subject,
        string $number,
        string $name,
    ): CommonCourse {
        $course = new CommonCourse(
            $program,
            $subject,
            $number,
            $name,
            DeliveryType::InPerson,
        );
        $this->setId($course, $id);

        return $course;
    }

    private function user(string $email): User
    {
        return (new User())
            ->setEmail($email)
            ->setPasswordHash('test');
    }

    private function setId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setValue($entity, $id);
    }
}
