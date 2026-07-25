<?php
declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260725030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Switch email verification from a link token to a 6-digit code: add expiry and attempt-count columns';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->getTable('users')->hasColumn('email_verification_expires_at')) {
            $this->addSql('ALTER TABLE users ADD email_verification_expires_at DATETIME NULL DEFAULT NULL');
        }
        if (!$schema->getTable('users')->hasColumn('email_verification_attempts')) {
            $this->addSql('ALTER TABLE users ADD email_verification_attempts INT NOT NULL DEFAULT 0');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->getTable('users')->hasColumn('email_verification_attempts')) {
            $this->addSql('ALTER TABLE users DROP COLUMN email_verification_attempts');
        }
        if ($schema->getTable('users')->hasColumn('email_verification_expires_at')) {
            $this->addSql('ALTER TABLE users DROP COLUMN email_verification_expires_at');
        }
    }
}
