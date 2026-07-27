<?php

namespace Tests\Unit;

use App\Entity\Program;
use App\Entity\SyllabusTemplate\CommonCourse;
use App\Entity\SyllabusTemplate\DeliveryType;
use App\Entity\SyllabusTemplate\ProposalOrigin;
use App\Entity\SyllabusTemplate\RevisionAuthorType;
use App\Entity\SyllabusTemplate\TemplateSubmission;
use App\Entity\User;
use App\Form\Model\CoordinatorTemplateData;
use App\Service\SyllabusTemplate\SyllabusPrefillService;
use App\Service\SyllabusTemplate\SyllabusRevisionService;
use PHPUnit\Framework\TestCase;

final class SyllabusApplicationServicesTest extends TestCase
{
    public function testExtractedCanonicalContentPrefillsEveryAppendixField(): void
    {
        $course = $this->course();
        $data = (new SyllabusPrefillService())->fromExtractedContent([
            'credits' => 3,
            'contact_hours' => '3 hours/week',
            'credit_category' => 'engineering',
            'course_coordinators' => ['Coordinator'],
            'instructors' => ['Faculty One'],
            'textbooks' => ['Reference Book'],
            'catalog_description' => 'Course description.',
            'prerequisites' => 'CSE 205',
            'course_type' => 'R',
            'specific_goals' => ['Apply engineering design.'],
            'course_outcomes' => ['Build a system.'],
            'student_outcomes' => ['SO 2'],
            'topics_covered' => ['Requirements'],
        ], $course);

        self::assertSame($course->getProgram(), $data->program);
        self::assertSame('CSE', $data->courseSubject);
        self::assertSame(3.0, $data->creditHours);
        self::assertSame('3 hours/week', $data->contactHours);
        self::assertSame('Faculty One', $data->instructors);
        self::assertSame('Reference Book', $data->textbooks);
        self::assertSame('CSE 205', $data->prerequisites);
        self::assertSame('R', $data->courseType);
        self::assertSame('Apply engineering design.', $data->specificGoals);
        self::assertSame('SO 2', $data->studentOutcomes);
        self::assertSame('Requirements', $data->topicsCovered);
    }

    public function testRevisionServiceUsesTheSameCanonicalMappingForFacultyChanges(): void
    {
        $faculty = (new User())->setEmail('faculty@example.edu');
        $submission = new TemplateSubmission($this->course(), $faculty, ProposalOrigin::FacultySubmission);
        $data = $this->completeData();

        $revision = (new SyllabusRevisionService())->addFacultyRevision(
            $submission,
            $faculty,
            $data,
        );

        self::assertSame(['Faculty One'], $revision->getContent()['instructors']);
        self::assertSame(['Requirements', 'Design'], $revision->getContent()['topics_covered']);
        self::assertTrue($revision->isAppendixAReady());
    }

    public function testCoordinatorRevisionServiceCreatesIndependentDraftFromApprovedFacultyTemplate(): void
    {
        $faculty = (new User())->setEmail('faculty@example.edu');
        $coordinator = (new User())->setEmail('coordinator@example.edu');
        $course = $this->course();
        $submission = new TemplateSubmission($course, $faculty, ProposalOrigin::FacultySubmission);
        $facultyRevision = $submission->addRevision(
            $faculty,
            RevisionAuthorType::Faculty,
            $this->completeData()->toContent(),
        );
        $submission->submit($facultyRevision);
        $submission->recordReview(
            new \App\Entity\SyllabusTemplate\TemplateReview(
                $submission,
                $coordinator,
                \App\Entity\SyllabusTemplate\ReviewDecision::Approved,
            ),
            $facultyRevision,
        );
        $data = (new SyllabusPrefillService())->fromSubmission($submission);
        $data->catalogDescription = 'Coordinator-owned revision.';

        $draft = (new SyllabusRevisionService())->saveCoordinatorRevision(
            $submission,
            $coordinator,
            $data,
        );

        self::assertNotSame($submission, $draft);
        self::assertSame(ProposalOrigin::CoordinatorCreated, $draft->getOrigin());
        self::assertSame('Coordinator-owned revision.', $draft->getWorkingRevision()?->getContent()['catalog_description']);
        self::assertSame($facultyRevision, $submission->getApprovedRevision());
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

    private function completeData(): CoordinatorTemplateData
    {
        $data = new CoordinatorTemplateData();
        $data->program = new Program('Computer Science', 'BS', '2026');
        $data->courseSubject = 'CSE';
        $data->courseNumber = '360';
        $data->courseName = 'Software Engineering';
        $data->creditHours = 3;
        $data->contactHours = '3 hours/week';
        $data->creditCategorization = 'engineering';
        $data->courseCoordinators = 'Coordinator';
        $data->instructors = 'Faculty One';
        $data->catalogDescription = 'Software engineering principles.';
        $data->courseType = 'R';
        $data->specificGoals = 'Apply engineering design.';
        $data->studentOutcomes = 'SO 2';
        $data->topicsCovered = "Requirements\nDesign";

        return $data;
    }
}
