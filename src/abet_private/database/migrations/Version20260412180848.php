<?php

declare(strict_types=1);

namespace Migrations;

use Services;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260412180848 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds colums by CUrbati https://github.com/hoang-danny05/ABET-Tools-Dockerized/pull/150/changes#diff-a2e01b544849eebd096cbbac3bc5673fbb90fa7b2a44554bd0b44bb70a911a98';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // programs table
        $this->skipIf(Services::doesColumnExist("programs", "program_year"), 'Skipping this migration.');
        $this->addSql('ALTER TABLE programs ADD program_year VARCHAR(20) NOT NULL;');
        $this->addSql('ALTER TABLE programs ADD UNIQUE KEY unique_program (program_name, program_code, program_year);');
        
        //course_
        $this->skipIf(Services::doesColumnExist("course_syllabi", "course_subject"), 'Skipping this migration.');
        $this->addSql('ALTER TABLE course_syllabi ADD course_subject VARCHAR(50);');

        // new datatype for these guys
        $this->addSql('ALTER TABLE course_syllabi DROP instructor_name = NULL;');
        $this->addSql('ALTER TABLE course_syllabi ADD instructor_name JSON;');

        $this->addSql('ALTER TABLE course_syllabi DROP textbook = NULL;');
        $this->addSql('ALTER TABLE course_syllabi ADD textbook JSON;');

        $this->addSql('ALTER TABLE course_syllabi DROP specific_goals = NULL;');
        $this->addSql('ALTER TABLE course_syllabi ADD specific_goals JSON;');

        $this->addSql('ALTER TABLE course_syllabi DROP student_outcomes = NULL;');
        $this->addSql('ALTER TABLE course_syllabi ADD student_outcomes JSON;');

        $this->addSql('ALTER TABLE course_syllabi DROP topics_covered = NULL;');
        $this->addSql('ALTER TABLE course_syllabi ADD topics_covered JSON;');

    }

    public function down(Schema $schema): void
    {
        // programs table rollback
        $this->throwIrreversibleMigrationException("Down not supported");
        $this->skipIf(!Services::doesColumnExist("programs", "program_year"), 'Skipping this migration.');
        $this->addSql('ALTER TABLE programs DROP INDEX unique_program;');
        $this->addSql('ALTER TABLE programs DROP COLUMN program_year;');

        // course_syllabi rollback
        $this->skipIf(!Services::doesColumnExist("course_syllabi", "course_subject"), 'Skipping this migration.');
        $this->addSql('ALTER TABLE course_syllabi DROP COLUMN course_subject;');

        // same thing but in reverse
        $this->addSql('ALTER TABLE course_syllabi SET instructor_name = NULL;');
        $this->addSql('ALTER TABLE course_syllabi MODIFY instructor_name VARCHAR(255);');

        $this->addSql('ALTER TABLE course_syllabi SET textbook = NULL;');
        $this->addSql('ALTER TABLE course_syllabi MODIFY textbook TEXT;');

        $this->addSql('ALTER TABLE course_syllabi SET specific_goals = NULL;');
        $this->addSql('ALTER TABLE course_syllabi MODIFY specific_goals TEXT;');

        $this->addSql('ALTER TABLE course_syllabi SET student_outcomes = NULL;');
        $this->addSql('ALTER TABLE course_syllabi MODIFY student_outcomes TEXT;');

        $this->addSql('ALTER TABLE course_syllabi SET topics_covered = NULL;');
        $this->addSql('ALTER TABLE course_syllabi MODIFY topics_covered TEXT;');
    }
}
