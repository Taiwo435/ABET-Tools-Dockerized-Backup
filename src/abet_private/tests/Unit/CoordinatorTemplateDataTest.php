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
use App\Form\SyllabusTemplate\CoordinatorTemplateType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

final class CoordinatorTemplateDataTest extends TestCase
{
    public function testEditingPreservesUnknownStructuredFieldsAndNormalizesLists(): void
    {
        $user = (new User())->setEmail('coordinator@example.edu');
        $course = new CommonCourse(new Program('Computer Science', 'BS', '2026'), 'CSE', '110', 'Principles of Programming', DeliveryType::InPerson);
        $submission = new TemplateSubmission($course, $user, ProposalOrigin::CoordinatorCreated);
        $revision = $submission->addRevision($user, RevisionAuthorType::Coordinator, [
            'creditHours' => 3,
            'courseCoordinators' => ['Original Coordinator'],
            'creditCategorization' => 'engineering',
            'customSchemaField' => ['preserve-me'],
        ]);

        $data = CoordinatorTemplateData::fromRevision($revision);
        $data->courseCoordinators = "First Coordinator\nSecond Coordinator\nFirst Coordinator";
        $data->courseOutcomes = "Outcome one\n\nOutcome two";
        $content = $data->toContent();

        self::assertSame(['First Coordinator', 'Second Coordinator'], $content['course_coordinators']);
        self::assertSame(['Outcome one', 'Outcome two'], $content['course_outcomes']);
        self::assertSame('1.0', $content['schema_version']);
        self::assertArrayNotHasKey('courseCoordinators', $content);
        self::assertSame(['preserve-me'], $content['customSchemaField']);
    }

    public function testEditingLoadsCourseIdentityAlongsideRevisionContent(): void
    {
        $user = (new User())->setEmail('coordinator@example.edu');
        $program = new Program('Computer Science', 'BS', '2026');
        $course = new CommonCourse($program, 'CSE', '340', 'Principles of Programming Languages', DeliveryType::Hybrid);
        $submission = new TemplateSubmission($course, $user, ProposalOrigin::CoordinatorCreated);
        $submission->addRevision($user, RevisionAuthorType::Coordinator, [
            'creditHours' => 3,
            'courseCoordinators' => ['Bazzi'],
            'creditCategorization' => 'engineering',
        ]);

        $data = CoordinatorTemplateData::fromSubmission($submission);

        self::assertSame($program, $data->program);
        self::assertSame('CSE', $data->courseSubject);
        self::assertSame('340', $data->courseNumber);
        self::assertSame('Principles of Programming Languages', $data->courseName);
        self::assertSame(DeliveryType::Hybrid, $data->deliveryType);
        self::assertSame(3.0, $data->creditHours);
    }

    public function testOptionalDescriptionAndOutcomesCanBeSubmittedBlank(): void
    {
        $data = new CoordinatorTemplateData();
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addType(new CoordinatorTemplateType())
            ->getFormFactory();
        $form = $factory->create(CoordinatorTemplateType::class, $data);

        $form->submit([
            'creditHours' => '3',
            'courseCoordinators' => 'Bazzi',
            'creditCategorization' => 'engineering',
            'catalogDescription' => '',
            'courseOutcomes' => '',
        ]);

        self::assertTrue($form->isSubmitted());
        self::assertTrue($form->isValid());
        self::assertSame('', $data->catalogDescription);
        self::assertSame('', $data->courseOutcomes);
        self::assertSame([], $data->toContent()['course_outcomes']);
    }

    public function testBlankOptionalFieldsDoNotBlockPublicationCompleteness(): void
    {
        $user = (new User())->setEmail('coordinator@example.edu');
        $course = new CommonCourse(new Program('Computer Science', 'BS', '2026'), 'CSE', '110', 'Principles of Programming', DeliveryType::InPerson);
        $submission = new TemplateSubmission($course, $user, ProposalOrigin::CoordinatorCreated);
        $data = new CoordinatorTemplateData();
        $data->creditHours = 3;
        $data->courseCoordinators = 'Bazzi';
        $data->creditCategorization = 'engineering';

        $revision = $submission->addRevision($user, RevisionAuthorType::Coordinator, $data->toContent());

        self::assertTrue($revision->isComplete());
        self::assertSame('', $revision->getContent()['catalog_description']);
        self::assertSame([], $revision->getContent()['course_outcomes']);
    }

    public function testEquivalentDraftDataIgnoresHarmlessWhitespaceAndCase(): void
    {
        $program = new Program('Computer Science', 'BS', '2026');
        $original = new CoordinatorTemplateData();
        $original->program = $program;
        $original->courseSubject = 'CSE';
        $original->courseNumber = '340';
        $original->courseName = 'Programming Languages';
        $original->creditHours = 3;
        $original->courseCoordinators = 'Bazzi';
        $original->creditCategorization = 'engineering';

        $submitted = clone $original;
        $submitted->courseSubject = ' cse ';
        $submitted->courseNumber = ' 340 ';
        $submitted->courseName = ' Programming Languages ';

        self::assertTrue($submitted->isEquivalentTo($original));

        $submitted->courseName = 'Programming Language Concepts';
        self::assertFalse($submitted->isEquivalentTo($original));
        self::assertFalse($submitted->hasSameCourseIdentityAs($original));

        $submitted->courseName = 'Programming Languages';
        $submitted->catalogDescription = 'Coordinator clarification';
        self::assertTrue($submitted->hasSameCourseIdentityAs($original));
        self::assertFalse($submitted->isEquivalentTo($original));
    }
}
