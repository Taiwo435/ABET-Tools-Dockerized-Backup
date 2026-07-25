<?php

declare(strict_types=1);

namespace Tests\Controller;

use App\Entity\User;
use App\Entity\Permissions;
use App\ReadModel\SyllabusReadinessState;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProgramReadinessFunctionalTest extends WebTestCase
{
    private Connection $connection;
    private EntityManagerInterface $em;
    private KernelBrowser $client;
    private int $programId1 = 9901;
    private int $programId2 = 9902;
    private string $userEmail = 'coordinator@example.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = self::createClient();

        $container = self::getContainer();
        $this->connection = $container->get(Connection::class);
        $this->em = $container->get(EntityManagerInterface::class);

        $this->cleanDatabase();

        // 1. Create Coordinator User
        $user = new User();
        $user->setEmail($this->userEmail);
        $user->setPasswordHash('some_password_hash');
        $user->setIsActive(true);
        $user->setPermission(Permissions::ROLE_COORDINATOR_FORM, true);
        $this->em->persist($user);
        $this->em->flush();

        // 2. Insert two test programs
        $this->connection->executeStatement(
            'INSERT INTO programs (program_id, program_name, program_code, program_year) VALUES (:id, "Computer Science Test", "CS", "2026")',
            ['id' => $this->programId1]
        );
        $this->connection->executeStatement(
            'INSERT INTO programs (program_id, program_name, program_code, program_year) VALUES (:id, "Systems Engineering Test", "CSE", "2026")',
            ['id' => $this->programId2]
        );

        // 3. Insert curriculum courses for Program 1
        $courses = [
            'CSE 101 Course One',
            'CSE 102 Course Two',
            'CSE 103 Course Three',
            'CSE 104 Course Four',
            'CSE 105 Course Five',
            'CSE 106 Course Six',
            'CSE 107 Course Seven',
        ];

        foreach ($courses as $c) {
            $this->connection->executeStatement(
                'INSERT INTO curriculum (program_id, course, course_type, credit_hours_other) VALUES (:pid, :course, "R", 3)',
                ['pid' => $this->programId1, 'course' => $c]
            );
        }

        // 4. Set up readiness states on Program 1
        // CSE 101: No Shared Template (no template, no draft)

        // CSE 102: Shared Template Incomplete (template exists but incomplete and unpublished, no draft)
        $this->connection->executeStatement(
            'INSERT INTO course_syllabi (program_id, course_subject, course_number, is_template, is_published, catalog_description, credits) 
             VALUES (:pid, "CSE", "102", 1, 0, NULL, NULL)',
            ['pid' => $this->programId1]
        );

        // CSE 103: Shared Template Published (template exists, complete and published, no draft)
        $this->connection->executeStatement(
            'INSERT INTO course_syllabi (program_id, course_subject, course_number, is_template, is_published, catalog_description, course_type, credits, contact_hours, specific_goals, student_outcomes, topics_covered) 
             VALUES (:pid, "CSE", "103", 1, 1, "Descr", "R", 3, "3 hrs", \'["Goal"]\', \'["Outcome"]\', \'["Topic"]\')',
            ['pid' => $this->programId1]
        );

        // CSE 104: Faculty Draft In Progress (template exists and complete/published, draft started but not submitted)
        $this->connection->executeStatement(
            'INSERT INTO course_syllabi (program_id, course_subject, course_number, is_template, is_published, catalog_description, course_type, credits, contact_hours, specific_goals, student_outcomes, topics_covered) 
             VALUES (:pid, "CSE", "104", 1, 1, "Descr", "R", 3, "3 hrs", \'["Goal"]\', \'["Outcome"]\', \'["Topic"]\')',
            ['pid' => $this->programId1]
        );
        $this->connection->executeStatement(
            'INSERT INTO course_syllabi (program_id, course_subject, course_number, is_template, is_published, is_submitted, is_approved) 
             VALUES (:pid, "CSE", "104", 0, 0, 0, 0)',
            ['pid' => $this->programId1]
        );

        // CSE 105: Submitted for Review (template exists/complete/published, draft submitted, not approved)
        $this->connection->executeStatement(
            'INSERT INTO course_syllabi (program_id, course_subject, course_number, is_template, is_published, catalog_description, course_type, credits, contact_hours, specific_goals, student_outcomes, topics_covered) 
             VALUES (:pid, "CSE", "105", 1, 1, "Descr", "R", 3, "3 hrs", \'["Goal"]\', \'["Outcome"]\', \'["Topic"]\')',
            ['pid' => $this->programId1]
        );
        $this->connection->executeStatement(
            'INSERT INTO course_syllabi (program_id, course_subject, course_number, is_template, is_published, is_submitted, is_approved) 
             VALUES (:pid, "CSE", "105", 0, 0, 1, 0)',
            ['pid' => $this->programId1]
        );

        // CSE 106: Approved and Ready (template exists/complete/published, draft submitted, approved)
        $this->connection->executeStatement(
            'INSERT INTO course_syllabi (program_id, course_subject, course_number, is_template, is_published, catalog_description, course_type, credits, contact_hours, specific_goals, student_outcomes, topics_covered) 
             VALUES (:pid, "CSE", "106", 1, 1, "Descr", "R", 3, "3 hrs", \'["Goal"]\', \'["Outcome"]\', \'["Topic"]\')',
            ['pid' => $this->programId1]
        );
        $this->connection->executeStatement(
            'INSERT INTO course_syllabi (program_id, course_subject, course_number, is_template, is_published, is_submitted, is_approved) 
             VALUES (:pid, "CSE", "106", 0, 0, 1, 1)',
            ['pid' => $this->programId1]
        );

        // CSE 107: Denied with Feedback (template exists/complete/published, draft submitted, denied/not approved, with feedback)
        $this->connection->executeStatement(
            'INSERT INTO course_syllabi (program_id, course_subject, course_number, is_template, is_published, catalog_description, course_type, credits, contact_hours, specific_goals, student_outcomes, topics_covered) 
             VALUES (:pid, "CSE", "107", 1, 1, "Descr", "R", 3, "3 hrs", \'["Goal"]\', \'["Outcome"]\', \'["Topic"]\')',
            ['pid' => $this->programId1]
        );
        $this->connection->executeStatement(
            'INSERT INTO course_syllabi (program_id, course_subject, course_number, is_template, is_published, is_submitted, is_approved, denial_feedback) 
             VALUES (:pid, "CSE", "107", 0, 0, 1, 0, "Requires more goals info")',
            ['pid' => $this->programId1]
        );
    }

    protected function tearDown(): void
    {
        $this->cleanDatabase();
        parent::tearDown();
    }

    private function cleanDatabase(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM course_syllabi WHERE program_id IN (:p1, :p2)',
            ['p1' => $this->programId1, 'p2' => $this->programId2]
        );
        $this->connection->executeStatement(
            'DELETE FROM curriculum WHERE program_id IN (:p1, :p2)',
            ['p1' => $this->programId1, 'p2' => $this->programId2]
        );
        $this->connection->executeStatement(
            'DELETE FROM programs WHERE program_id IN (:p1, :p2)',
            ['p1' => $this->programId1, 'p2' => $this->programId2]
        );

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $this->userEmail]);
        if ($user !== null) {
            $this->em->remove($user);
            $this->em->flush();
        }
    }

    public function testDashboardRendersSummaryCountsCorrectly(): void
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $this->userEmail]);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', sprintf('/program/%d/readiness', $this->programId1));
        self::assertResponseIsSuccessful();

        $allCardText = $crawler->filter('.metric-card.metric-all')->text();
        self::assertStringContainsString('7', $allCardText);
        self::assertStringContainsString('All Courses', $allCardText);

        $readyCardText = $crawler->filter('.metric-card.metric-ready')->text();
        self::assertStringContainsString('1', $readyCardText);
        self::assertStringContainsString('Ready', $readyCardText);

        $awaitingCardText = $crawler->filter('.metric-card.metric-awaiting')->text();
        self::assertStringContainsString('1', $awaitingCardText);
        self::assertStringContainsString('Awaiting Review', $awaitingCardText);

        $blockedCardText = $crawler->filter('.metric-card.metric-blocked')->text();
        self::assertStringContainsString('3', $blockedCardText);
        self::assertStringContainsString('Blocked', $blockedCardText);

        $missingCardText = $crawler->filter('.metric-card.metric-missing')->text();
        self::assertStringContainsString('2', $missingCardText);
        self::assertStringContainsString('Missing', $missingCardText);
    }

    public function testDashboardRendersEveryReadinessStateAndMissingFields(): void
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $this->userEmail]);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', sprintf('/program/%d/readiness', $this->programId1));
        self::assertResponseIsSuccessful();

        $tableText = $crawler->filter('.readiness-table')->text();

        self::assertStringContainsString(SyllabusReadinessState::NoSharedTemplate->value, $tableText);
        self::assertStringContainsString(SyllabusReadinessState::SharedTemplateIncomplete->value, $tableText);
        self::assertStringContainsString(SyllabusReadinessState::SharedTemplatePublished->value, $tableText);
        self::assertStringContainsString(SyllabusReadinessState::FacultyDraftInProgress->value, $tableText);
        self::assertStringContainsString(SyllabusReadinessState::SubmittedForReview->value, $tableText);
        self::assertStringContainsString(SyllabusReadinessState::ApprovedAndReadyForAppendixA->value, $tableText);
        self::assertStringContainsString(SyllabusReadinessState::DeniedWithFeedback->value, $tableText);

        $row102 = $crawler->filter('tr:contains("CSE 102")');
        self::assertCount(1, $row102);
        self::assertStringContainsString('Catalog Description', $row102->text());
        self::assertStringContainsString('Credits', $row102->text());

        self::assertStringContainsString('CSE 101', $tableText);
        self::assertStringContainsString('Course One', $tableText);
    }

    public function testFilteringReturnsExpectedRows(): void
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $this->userEmail]);
        $this->client->loginUser($user);

        // Filter: Ready (Only CSE 106 should be visible)
        $crawler = $this->client->request('GET', sprintf('/program/%d/readiness?filter=Ready', $this->programId1));
        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('.readiness-table tbody tr');
        self::assertCount(1, $rows);
        self::assertStringContainsString('CSE 106', $crawler->filter('.readiness-table tbody')->text());

        // Filter: Blocked (CSE 102, CSE 104, CSE 107 should be visible)
        $crawler = $this->client->request('GET', sprintf('/program/%d/readiness?filter=Blocked', $this->programId1));
        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('.readiness-table tbody tr');
        self::assertCount(3, $rows);
        $tbodyText = $crawler->filter('.readiness-table tbody')->text();
        self::assertStringContainsString('CSE 102', $tbodyText);
        self::assertStringContainsString('CSE 104', $tbodyText);
        self::assertStringContainsString('CSE 107', $tbodyText);

        // Filter: case-insensitive category checks e.g. "missing"
        $crawler = $this->client->request('GET', sprintf('/program/%d/readiness?filter=missing', $this->programId1));
        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('.readiness-table tbody tr');
        self::assertCount(2, $rows);
        $tbodyText = $crawler->filter('.readiness-table tbody')->text();
        self::assertStringContainsString('CSE 101', $tbodyText);
        self::assertStringContainsString('CSE 103', $tbodyText);
    }

    public function testProgramSelectionAndEmptyProgramDisplaysGracefully(): void
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $this->userEmail]);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', sprintf('/program/%d/readiness', $this->programId2));
        self::assertResponseIsSuccessful();

        $emptyRowText = $crawler->filter('.empty-state')->text();
        self::assertStringContainsString('No courses match the selected readiness filter', $emptyRowText);

        $totalCardText = $crawler->filter('.metric-card.metric-all')->text();
        self::assertStringContainsString('0', $totalCardText);

        $options = $crawler->filter('#program-selector option');
        self::assertGreaterThanOrEqual(2, count($options));

        self::assertCount(1, $options->filter(sprintf('[value="%d"]', $this->programId1)));
        self::assertCount(1, $options->filter(sprintf('[value="%d"]', $this->programId2)));
    }
}
