<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260331234222 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'SQL tables to store all ABET doc information by Taiwo435';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
	$this->addSQL(<<<'SQL'
-- Stores all free-text sections across the report
-- section_key identifies which part e.g. 'background_contact', 'background_program_history', 'criterion1_advising'
CREATE TABLE IF NOT EXISTS report_sections (
    section_id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    section_key VARCHAR(100) NOT NULL,
    content TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE CASCADE
);

-- Appendix A: Course Syllabi
-- One row per course syllabus
CREATE TABLE IF NOT EXISTS course_syllabi (
    syllabus_id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    course_number VARCHAR(50),
    course_name VARCHAR(255),
    credits INT,
    contact_hours TEXT,
    credit_categorization VARCHAR(100),
    instructor_name VARCHAR(255),
    textbook TEXT,
    catalog_description TEXT,
    prerequisites TEXT,
    course_type ENUM('R', 'E', 'SE'),
    specific_goals TEXT,
    student_outcomes TEXT,
    topics_covered TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE CASCADE
);

-- Submission Attesting to Compliance
CREATE TABLE IF NOT EXISTS submission_compliance (
    submission_id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    signatory_name VARCHAR(255),
    signatory_title VARCHAR(255),
    submission_date DATE,
    compliance_statement TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE CASCADE
);
SQL);

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
	$this.addSql('DROP TABLE report_sections;');
	$this.addSql('DROP TABLE course_syllabi;');
	$this.addSql('DROP TABLE submission_compliance;');

    }
}
