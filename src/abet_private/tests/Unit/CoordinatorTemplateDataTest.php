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
use PHPUnit\Framework\TestCase;

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

        self::assertSame(['First Coordinator', 'Second Coordinator'], $content['courseCoordinators']);
        self::assertSame(['Outcome one', 'Outcome two'], $content['courseOutcomes']);
        self::assertSame(['preserve-me'], $content['customSchemaField']);
    }
}
