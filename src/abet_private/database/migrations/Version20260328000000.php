<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Services;

require_once __DIR__.'/../../bootstrap.php';

/**
 * Creates the course_sections table for multi-section course support.
 */
final class Version20260328000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add course_sections table so a course can have multiple sections with different Canvas links';
    }

    public function up(Schema $schema): void
    {
        $conn = Services::getConnection();
        $sm = $conn->createSchemaManager();

        $this->skipIf($sm->tablesExist(['course_sections']), 'course_sections table already exists, skipping.');

        $this->addSql('
            CREATE TABLE course_sections (
                section_id INT AUTO_INCREMENT PRIMARY KEY,
                course_id INT NOT NULL,
                section_label VARCHAR(100),
                canvas_source_id VARCHAR(50) NOT NULL,
                canvas_dest_id VARCHAR(50) NOT NULL,
                course_data JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE
            )
        ');

        // Migrate any existing courses rows into a default section each
        $this->addSql("
            INSERT INTO course_sections (course_id, section_label, canvas_source_id, canvas_dest_id, course_data)
            SELECT course_id, 'Section 001', '', '', course_data
            FROM courses
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS course_sections');
    }
}
