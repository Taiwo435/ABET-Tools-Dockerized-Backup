<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260712000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Symfony-owned common-course syllabus template submissions, immutable revisions, and coordinator reviews';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE syllabus_common_courses (
    id INT AUTO_INCREMENT NOT NULL,
    program_id INT NOT NULL,
    current_approved_revision_id INT DEFAULT NULL,
    course_subject VARCHAR(50) NOT NULL,
    course_number VARCHAR(50) NOT NULL,
    course_name VARCHAR(255) NOT NULL,
    delivery_type VARCHAR(32) NOT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    UNIQUE INDEX uniq_syllabus_common_course (program_id, course_subject, course_number, delivery_type),
    INDEX IDX_SYLLABUS_COMMON_PROGRAM (program_id),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE syllabus_template_submissions (
    id INT AUTO_INCREMENT NOT NULL,
    common_course_id INT NOT NULL,
    faculty_user_id INT NOT NULL,
    submitted_revision_id INT DEFAULT NULL,
    approved_revision_id INT DEFAULT NULL,
    status VARCHAR(32) NOT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    submitted_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    decided_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX IDX_SYLLABUS_SUBMISSION_COURSE (common_course_id),
    INDEX IDX_SYLLABUS_SUBMISSION_FACULTY (faculty_user_id),
    INDEX idx_syllabus_submission_queue (status, submitted_at),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE syllabus_template_revisions (
    id INT AUTO_INCREMENT NOT NULL,
    submission_id INT NOT NULL,
    author_user_id INT NOT NULL,
    author_type VARCHAR(32) NOT NULL,
    revision_number INT NOT NULL,
    content JSON NOT NULL,
    schema_version INT DEFAULT 1 NOT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    UNIQUE INDEX uniq_syllabus_submission_revision (submission_id, revision_number),
    INDEX IDX_SYLLABUS_REVISION_AUTHOR (author_user_id),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE syllabus_template_reviews (
    id INT AUTO_INCREMENT NOT NULL,
    submission_id INT NOT NULL,
    reviewer_user_id INT NOT NULL,
    decision VARCHAR(32) NOT NULL,
    comment LONGTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    UNIQUE INDEX UNIQ_SYLLABUS_REVIEW_SUBMISSION (submission_id),
    INDEX IDX_SYLLABUS_REVIEWER (reviewer_user_id),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql('ALTER TABLE syllabus_common_courses ADD CONSTRAINT FK_SYLLABUS_COMMON_PROGRAM FOREIGN KEY (program_id) REFERENCES programs (program_id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE syllabus_template_submissions ADD CONSTRAINT FK_SYLLABUS_SUBMISSION_COURSE FOREIGN KEY (common_course_id) REFERENCES syllabus_common_courses (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE syllabus_template_submissions ADD CONSTRAINT FK_SYLLABUS_SUBMISSION_FACULTY FOREIGN KEY (faculty_user_id) REFERENCES users (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE syllabus_template_revisions ADD CONSTRAINT FK_SYLLABUS_REVISION_SUBMISSION FOREIGN KEY (submission_id) REFERENCES syllabus_template_submissions (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE syllabus_template_revisions ADD CONSTRAINT FK_SYLLABUS_REVISION_AUTHOR FOREIGN KEY (author_user_id) REFERENCES users (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE syllabus_template_reviews ADD CONSTRAINT FK_SYLLABUS_REVIEW_SUBMISSION FOREIGN KEY (submission_id) REFERENCES syllabus_template_submissions (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE syllabus_template_reviews ADD CONSTRAINT FK_SYLLABUS_REVIEWER FOREIGN KEY (reviewer_user_id) REFERENCES users (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE syllabus_template_submissions ADD CONSTRAINT FK_SYLLABUS_SUBMITTED_REVISION FOREIGN KEY (submitted_revision_id) REFERENCES syllabus_template_revisions (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE syllabus_template_submissions ADD CONSTRAINT FK_SYLLABUS_APPROVED_REVISION FOREIGN KEY (approved_revision_id) REFERENCES syllabus_template_revisions (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE syllabus_common_courses ADD CONSTRAINT FK_SYLLABUS_CURRENT_REVISION FOREIGN KEY (current_approved_revision_id) REFERENCES syllabus_template_revisions (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_SYLLABUS_CURRENT_REVISION ON syllabus_common_courses (current_approved_revision_id)');
        $this->addSql('CREATE INDEX IDX_SYLLABUS_SUBMITTED_REVISION ON syllabus_template_submissions (submitted_revision_id)');
        $this->addSql('CREATE INDEX IDX_SYLLABUS_APPROVED_REVISION ON syllabus_template_submissions (approved_revision_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE syllabus_common_courses DROP FOREIGN KEY FK_SYLLABUS_CURRENT_REVISION');
        $this->addSql('ALTER TABLE syllabus_template_submissions DROP FOREIGN KEY FK_SYLLABUS_SUBMITTED_REVISION');
        $this->addSql('ALTER TABLE syllabus_template_submissions DROP FOREIGN KEY FK_SYLLABUS_APPROVED_REVISION');
        $this->addSql('DROP TABLE syllabus_template_reviews');
        $this->addSql('DROP TABLE syllabus_template_revisions');
        $this->addSql('DROP TABLE syllabus_template_submissions');
        $this->addSql('DROP TABLE syllabus_common_courses');
    }
}
