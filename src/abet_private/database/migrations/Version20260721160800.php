<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260721160800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_template, is_published, is_submitted, is_approved, and denial_feedback columns to course_syllabi table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE course_syllabi ADD COLUMN is_template BOOLEAN NOT NULL DEFAULT FALSE;');
        $this->addSql('ALTER TABLE course_syllabi ADD COLUMN is_published BOOLEAN NOT NULL DEFAULT FALSE;');
        $this->addSql('ALTER TABLE course_syllabi ADD COLUMN is_submitted BOOLEAN NOT NULL DEFAULT FALSE;');
        $this->addSql('ALTER TABLE course_syllabi ADD COLUMN is_approved BOOLEAN NOT NULL DEFAULT FALSE;');
        $this->addSql('ALTER TABLE course_syllabi ADD COLUMN denial_feedback TEXT NULL DEFAULT NULL;');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE course_syllabi DROP COLUMN is_template;');
        $this->addSql('ALTER TABLE course_syllabi DROP COLUMN is_published;');
        $this->addSql('ALTER TABLE course_syllabi DROP COLUMN is_submitted;');
        $this->addSql('ALTER TABLE course_syllabi DROP COLUMN is_approved;');
        $this->addSql('ALTER TABLE course_syllabi DROP COLUMN denial_feedback;');
    }
}
