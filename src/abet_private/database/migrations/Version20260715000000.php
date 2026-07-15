<?php
declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260715000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email_verification_token column for account email verification (#85/#86)';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->getTable('users')->hasColumn('email_verification_token')) {
            $this->addSql('ALTER TABLE users ADD email_verification_token VARCHAR(64) NULL DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->getTable('users')->hasColumn('email_verification_token')) {
            $this->addSql('ALTER TABLE users DROP COLUMN email_verification_token');
        }
    }
}
