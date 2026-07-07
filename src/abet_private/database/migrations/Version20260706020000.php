<?php
declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260706020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop deprecated role column from users table (#160)';
    }

    public function up(Schema $schema): void
    {
        if ($schema->getTable('users')->hasColumn('role')) {
            $this->addSql('ALTER TABLE users DROP COLUMN role');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->getTable('users')->hasColumn('role')) {
            $this->addSql("ALTER TABLE users ADD role ENUM('admin', 'faculty') NOT NULL DEFAULT 'faculty'");
        }
    }
}
