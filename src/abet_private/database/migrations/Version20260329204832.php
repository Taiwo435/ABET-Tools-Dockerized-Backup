<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260329204832 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds job_history table for celery background workers (reportgen)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS job_history (
    id VARCHAR(36) PRIMARY KEY,
    job_type VARCHAR(100) NOT NULL,
    service VARCHAR(100) NOT NULL,
    submitted_by INT DEFAULT NULL,
    status ENUM('pending', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
    message TEXT DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    params JSON DEFAULT NULL,
    result_meta JSON DEFAULT NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL DEFAULT NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_service (service),
    INDEX idx_created (created_at),
    INDEX idx_submitted_by (submitted_by),
    -- Foreign key to users table for submitted_by, nullable in case we want to allow system-submitted jobs in the future
    FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE
    SET NULL
);
SQL
        );

    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE job_history');
    }
}
