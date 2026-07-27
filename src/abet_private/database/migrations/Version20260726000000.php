<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260726000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add course offerings, explicit submission kinds, and string syllabus content schema versions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE syllabus_course_offerings (
    id INT AUTO_INCREMENT NOT NULL,
    common_course_id INT NOT NULL,
    instructor_user_id INT DEFAULT NULL,
    current_approved_revision_id INT DEFAULT NULL,
    academic_year VARCHAR(20) NOT NULL,
    term VARCHAR(32) NOT NULL,
    section VARCHAR(50) DEFAULT '' NOT NULL,
    delivery_type VARCHAR(32) NOT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    UNIQUE INDEX uniq_syllabus_course_offering (common_course_id, academic_year, term, section),
    INDEX IDX_SYLLABUS_OFFERING_COURSE (common_course_id),
    INDEX IDX_SYLLABUS_OFFERING_INSTRUCTOR (instructor_user_id),
    INDEX IDX_SYLLABUS_OFFERING_APPROVED_REVISION (current_approved_revision_id),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql("ALTER TABLE syllabus_template_submissions ADD submission_kind VARCHAR(32) DEFAULT 'shared_template' NOT NULL");
        $this->addSql('ALTER TABLE syllabus_template_submissions ADD course_offering_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_SYLLABUS_SUBMISSION_OFFERING ON syllabus_template_submissions (course_offering_id)');
        $this->addSql("ALTER TABLE syllabus_template_revisions MODIFY schema_version VARCHAR(16) DEFAULT '1.0' NOT NULL");
        $this->addSql("UPDATE syllabus_template_revisions SET schema_version = '1.0'");

        $this->addSql('ALTER TABLE syllabus_course_offerings ADD CONSTRAINT FK_SYLLABUS_OFFERING_COURSE FOREIGN KEY (common_course_id) REFERENCES syllabus_common_courses (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE syllabus_course_offerings ADD CONSTRAINT FK_SYLLABUS_OFFERING_INSTRUCTOR FOREIGN KEY (instructor_user_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE syllabus_course_offerings ADD CONSTRAINT FK_SYLLABUS_OFFERING_APPROVED_REVISION FOREIGN KEY (current_approved_revision_id) REFERENCES syllabus_template_revisions (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE syllabus_template_submissions ADD CONSTRAINT FK_SYLLABUS_SUBMISSION_OFFERING FOREIGN KEY (course_offering_id) REFERENCES syllabus_course_offerings (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE syllabus_template_submissions DROP FOREIGN KEY FK_SYLLABUS_SUBMISSION_OFFERING');
        $this->addSql('ALTER TABLE syllabus_course_offerings DROP FOREIGN KEY FK_SYLLABUS_OFFERING_COURSE');
        $this->addSql('ALTER TABLE syllabus_course_offerings DROP FOREIGN KEY FK_SYLLABUS_OFFERING_INSTRUCTOR');
        $this->addSql('ALTER TABLE syllabus_course_offerings DROP FOREIGN KEY FK_SYLLABUS_OFFERING_APPROVED_REVISION');
        $this->addSql('ALTER TABLE syllabus_template_submissions DROP INDEX IDX_SYLLABUS_SUBMISSION_OFFERING');
        $this->addSql('ALTER TABLE syllabus_template_submissions DROP course_offering_id');
        $this->addSql('ALTER TABLE syllabus_template_submissions DROP submission_kind');
        $this->addSql('ALTER TABLE syllabus_template_revisions MODIFY schema_version INT DEFAULT 1 NOT NULL');
        $this->addSql('DROP TABLE syllabus_course_offerings');
    }
}
