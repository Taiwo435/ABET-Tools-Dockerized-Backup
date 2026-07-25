<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\ReadModel\SyllabusReadiness;
use App\ReadModel\SyllabusReadinessState;
use App\Repository\SyllabusReadinessRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Doctrine\DBAL\Connection;

final class SyllabusReadinessRepositoryTest extends KernelTestCase
{
    private Connection $connection;
    private SyllabusReadinessRepository $repository;
    private int $testProgramId = 9999;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->connection = $container->get(Connection::class);
        $this->repository = $container->get(SyllabusReadinessRepository::class);

        $this->cleanDatabase();

        // Insert a test program
        $this->connection->executeStatement(
            'INSERT INTO programs (program_id, program_name, program_code, program_year) VALUES (:id, "Test Program", "TEST", "2026")',
            ['id' => $this->testProgramId]
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
            'DELETE FROM course_syllabi WHERE program_id = :id',
            ['id' => $this->testProgramId]
        );
        $this->connection->executeStatement(
            'DELETE FROM curriculum WHERE program_id = :id',
            ['id' => $this->testProgramId]
        );
        $this->connection->executeStatement(
            'DELETE FROM programs WHERE program_id = :id',
            ['id' => $this->testProgramId]
        );
    }

    public function testParseCourse(): void
    {
        $parsed = $this->repository->parseCourse('CSE 310 Data Structures and Algorithms');
        $this->assertSame('CSE', $parsed['subject']);
        $this->assertSame('310', $parsed['number']);
        $this->assertSame('Data Structures and Algorithms', $parsed['title']);

        $parsed = $this->repository->parseCourse('MAT 243 Discrete Mathematical Structures');
        $this->assertSame('MAT', $parsed['subject']);
        $this->assertSame('243', $parsed['number']);
        $this->assertSame('Discrete Mathematical Structures', $parsed['title']);

        $parsed = $this->repository->parseCourse('CSE 485');
        $this->assertSame('CSE', $parsed['subject']);
        $this->assertSame('485', $parsed['number']);
        $this->assertSame('', $parsed['title']);

        // Suffixes
        $parsed = $this->repository->parseCourse('FSE 100A Introduction to Engineering');
        $this->assertSame('FSE', $parsed['subject']);
        $this->assertSame('100A', $parsed['number']);
        $this->assertSame('Introduction to Engineering', $parsed['title']);

        // Fallbacks
        $parsed = $this->repository->parseCourse('NonStandard Course Name');
        $this->assertSame('NonStandard', $parsed['subject']);
        $this->assertSame('', $parsed['number']);
        $this->assertSame('Course Name', $parsed['title']);
    }

    public function testGetReadinessRowsNoSharedTemplate(): void
    {
        // Insert a curriculum course
        $this->connection->executeStatement(
            'INSERT INTO curriculum (program_id, course, course_type, credit_hours_other) VALUES (:id, "CSE 310 Data Structures", "R", 3)',
            ['id' => $this->testProgramId]
        );

        $rows = $this->repository->getReadinessRowsForProgram($this->testProgramId);

        $this->assertCount(1, $rows);
        $this->assertSame('CSE 310', $rows[0]->getCourseCode());
        $this->assertSame('Data Structures', $rows[0]->getCourseTitle());
        $this->assertSame(SyllabusReadinessState::NoSharedTemplate, $rows[0]->getState());
        $this->assertSame('Missing', $rows[0]->getState()->getCategory());
        $this->assertNull($rows[0]->getSyllabusId());
    }

    public function testGetReadinessRowsSharedTemplateIncomplete(): void
    {
        // Insert a curriculum course
        $this->connection->executeStatement(
            'INSERT INTO curriculum (program_id, course, course_type, credit_hours_other) VALUES (:id, "CSE 310 Data Structures", "R", 3)',
            ['id' => $this->testProgramId]
        );

        // Insert an incomplete template (is_template = true, is_published = false, missing catalog_description)
        $this->connection->executeStatement(
            'INSERT INTO course_syllabi (program_id, course_subject, course_number, course_name, is_template, is_published, catalog_description, credits) 
             VALUES (:id, "CSE", "310", "Data Structures", 1, 0, NULL, 3)',
            ['id' => $this->testProgramId]
        );

        $rows = $this->repository->getReadinessRowsForProgram($this->testProgramId);

        $this->assertCount(1, $rows);
        $this->assertSame(SyllabusReadinessState::SharedTemplateIncomplete, $rows[0]->getState());
        $this->assertSame('Blocked', $rows[0]->getState()->getCategory());
        $this->assertContains('Catalog Description', $rows[0]->getMissingRequiredFields());
    }

    public function testGetReadinessRowsSharedTemplatePublishedNoDraft(): void
    {
        // Insert a curriculum course
        $this->connection->executeStatement(
            'INSERT INTO curriculum (program_id, course, course_type, credit_hours_other) VALUES (:id, "CSE 310 Data Structures", "R", 3)',
            ['id' => $this->testProgramId]
        );

        // Insert a complete published template
        $this->connection->executeStatement(
            'INSERT INTO course_syllabi (program_id, course_subject, course_number, course_name, is_template, is_published, catalog_description, course_type, credits, contact_hours, specific_goals, student_outcomes, topics_covered) 
             VALUES (:id, "CSE", "310", "Data Structures", 1, 1, "Desc", "R", 3, "3 hours", "[\"Goal 1\"]", "[\"1\"]", "[\"Topic 1\"]")',
            ['id' => $this->testProgramId]
        );

        $rows = $this->repository->getReadinessRowsForProgram($this->testProgramId);

        $this->assertCount(1, $rows);
        $this->assertSame(SyllabusReadinessState::SharedTemplatePublished, $rows[0]->getState());
        $this->assertSame('Missing', $rows[0]->getState()->getCategory());
    }

    public function testGetReadinessRowsFacultyDraftInProgress(): void
    {
        // Insert a curriculum course
        $this->connection->executeStatement(
            'INSERT INTO curriculum (program_id, course, course_type, credit_hours_other) VALUES (:id, "CSE 310 Data Structures", "R", 3)',
            ['id' => $this->testProgramId]
        );

        // Insert template
        $this->connection->executeStatement(
            'INSERT INTO course_syllabi (program_id, course_subject, course_number, course_name, is_template, is_published, catalog_description, course_type, credits, contact_hours, specific_goals, student_outcomes, topics_covered) 
             VALUES (:id, "CSE", "310", "Data Structures", 1, 1, "Desc", "R", 3, "3 hours", "[\"Goal 1\"]", "[\"1\"]", "[\"Topic 1\"]")',
            ['id' => $this->testProgramId]
        );

        // Insert draft in progress (is_template = false, is_submitted = false, is_approved = false)
        $this->connection->executeStatement(
            'INSERT INTO course_syllabi (program_id, course_subject, course_number, course_name, is_template, is_submitted, is_approved, updated_at) 
             VALUES (:id, "CSE", "310", "Data Structures", 0, 0, 0, "2026-07-21 12:00:00")',
            ['id' => $this->testProgramId]
        );

        $rows = $this->repository->getReadinessRowsForProgram($this->testProgramId);

        $this->assertCount(1, $rows);
        $this->assertSame(SyllabusReadinessState::FacultyDraftInProgress, $rows[0]->getState());
        $this->assertSame('Blocked', $rows[0]->getState()->getCategory());
        $this->assertNotNull($rows[0]->getUpdatedAt());
    }

    public function testGetReadinessRowsSubmittedForReview(): void
    {
        // Insert a curriculum course
        $this->connection->executeStatement(
            'INSERT INTO curriculum (program_id, course, course_type, credit_hours_other) VALUES (:id, "CSE 310 Data Structures", "R", 3)',
            ['id' => $this->testProgramId]
        );

        // Insert template
        $this->connection->executeStatement(
            'INSERT INTO course_syllabi (program_id, course_subject, course_number, course_name, is_template, is_published, catalog_description, course_type, credits, contact_hours, specific_goals, student_outcomes, topics_covered) 
             VALUES (:id, "CSE", "310", "Data Structures", 1, 1, "Desc", "R", 3, "3 hours", "[\"Goal 1\"]", "[\"1\"]", "[\"Topic 1\"]")',
            ['id' => $this->testProgramId]
        );

        // Insert draft submitted (is_template = false, is_submitted = true, is_approved = false)
        $this->connection->executeStatement(
            'INSERT INTO course_syllabi (program_id, course_subject, course_number, course_name, is_template, is_submitted, is_approved, updated_at) 
             VALUES (:id, "CSE", "310", "Data Structures", 0, 1, 0, "2026-07-21 12:00:00")',
            ['id' => $this->testProgramId]
        );

        $rows = $this->repository->getReadinessRowsForProgram($this->testProgramId);

        $this->assertCount(1, $rows);
        $this->assertSame(SyllabusReadinessState::SubmittedForReview, $rows[0]->getState());
        $this->assertSame('Awaiting review', $rows[0]->getState()->getCategory());
    }

    public function testGetReadinessRowsDeniedWithFeedback(): void
    {
        // Insert a curriculum course
        $this->connection->executeStatement(
            'INSERT INTO curriculum (program_id, course, course_type, credit_hours_other) VALUES (:id, "CSE 310 Data Structures", "R", 3)',
            ['id' => $this->testProgramId]
        );

        // Insert template
        $this->connection->executeStatement(
            'INSERT INTO course_syllabi (program_id, course_subject, course_number, course_name, is_template, is_published, catalog_description, course_type, credits, contact_hours, specific_goals, student_outcomes, topics_covered) 
             VALUES (:id, "CSE", "310", "Data Structures", 1, 1, "Desc", "R", 3, "3 hours", "[\"Goal 1\"]", "[\"1\"]", "[\"Topic 1\"]")',
            ['id' => $this->testProgramId]
        );

        // Insert draft denied with feedback (is_template = false, is_submitted = false, is_approved = false, denial_feedback = "Please fix outcomes")
        $this->connection->executeStatement(
            'INSERT INTO course_syllabi (program_id, course_subject, course_number, course_name, is_template, is_submitted, is_approved, denial_feedback, updated_at) 
             VALUES (:id, "CSE", "310", "Data Structures", 0, 0, 0, "Please fix outcomes", "2026-07-21 12:00:00")',
            ['id' => $this->testProgramId]
        );

        $rows = $this->repository->getReadinessRowsForProgram($this->testProgramId);

        $this->assertCount(1, $rows);
        $this->assertSame(SyllabusReadinessState::DeniedWithFeedback, $rows[0]->getState());
        $this->assertSame('Blocked', $rows[0]->getState()->getCategory());
    }

    public function testGetReadinessRowsApprovedAndReady(): void
    {
        // Insert a curriculum course
        $this->connection->executeStatement(
            'INSERT INTO curriculum (program_id, course, course_type, credit_hours_other) VALUES (:id, "CSE 310 Data Structures", "R", 3)',
            ['id' => $this->testProgramId]
        );

        // Insert template
        $this->connection->executeStatement(
            'INSERT INTO course_syllabi (program_id, course_subject, course_number, course_name, is_template, is_published, catalog_description, course_type, credits, contact_hours, specific_goals, student_outcomes, topics_covered) 
             VALUES (:id, "CSE", "310", "Data Structures", 1, 1, "Desc", "R", 3, "3 hours", "[\"Goal 1\"]", "[\"1\"]", "[\"Topic 1\"]")',
            ['id' => $this->testProgramId]
        );

        // Insert draft approved (is_template = false, is_submitted = true, is_approved = true)
        $this->connection->executeStatement(
            'INSERT INTO course_syllabi (program_id, course_subject, course_number, course_name, is_template, is_submitted, is_approved, updated_at) 
             VALUES (:id, "CSE", "310", "Data Structures", 0, 1, 1, "2026-07-21 12:00:00")',
            ['id' => $this->testProgramId]
        );

        $rows = $this->repository->getReadinessRowsForProgram($this->testProgramId);

        $this->assertCount(1, $rows);
        $this->assertSame(SyllabusReadinessState::ApprovedAndReadyForAppendixA, $rows[0]->getState());
        $this->assertSame('Ready', $rows[0]->getState()->getCategory());
    }

    public function testGetReadinessRowsWithFiltering(): void
    {
        // 1. Insert course 1 (Missing template)
        $this->connection->executeStatement(
            'INSERT INTO curriculum (program_id, course, course_type, credit_hours_other) VALUES (:id, "CSE 310 Data Structures", "R", 3)',
            ['id' => $this->testProgramId]
        );

        // 2. Insert course 2 (Approved template - Ready)
        $this->connection->executeStatement(
            'INSERT INTO curriculum (program_id, course, course_type, credit_hours_other) VALUES (:id, "CSE 400 Capstone", "R", 3)',
            ['id' => $this->testProgramId]
        );
        $this->connection->executeStatement(
            'INSERT INTO course_syllabi (program_id, course_subject, course_number, course_name, is_template, is_published, catalog_description, course_type, credits, contact_hours, specific_goals, student_outcomes, topics_covered) 
             VALUES (:id, "CSE", "400", "Capstone", 1, 1, "Desc", "R", 3, "3 hours", "[\"Goal 1\"]", "[\"1\"]", "[\"Topic 1\"]")',
            ['id' => $this->testProgramId]
        );
        $this->connection->executeStatement(
            'INSERT INTO course_syllabi (program_id, course_subject, course_number, course_name, is_template, is_submitted, is_approved) 
             VALUES (:id, "CSE", "400", "Capstone", 0, 1, 1)',
            ['id' => $this->testProgramId]
        );

        // Fetch all - should be 2
        $all = $this->repository->getReadinessRowsForProgram($this->testProgramId);
        $this->assertCount(2, $all);

        // Filter by category 'Ready' - should be 1 (CSE 400)
        $ready = $this->repository->getReadinessRowsForProgram($this->testProgramId, 'Ready');
        $this->assertCount(1, $ready);
        $this->assertSame('CSE 400', $ready[0]->getCourseCode());

        // Filter by category 'Missing' - should be 1 (CSE 310)
        $missing = $this->repository->getReadinessRowsForProgram($this->testProgramId, 'Missing');
        $this->assertCount(1, $missing);
        $this->assertSame('CSE 310', $missing[0]->getCourseCode());

        // Filter by enum state string (case-insensitive) - e.g. 'No shared template'
        $stateFilter = $this->repository->getReadinessRowsForProgram($this->testProgramId, 'No shared template');
        $this->assertCount(1, $stateFilter);
        $this->assertSame('CSE 310', $stateFilter[0]->getCourseCode());
    }
}
