<?php
declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260719010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add requested_permissions column for the admin permission request queue (#87/#88)';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->getTable('users')->hasColumn('requested_permissions')) {
            $this->addSql('ALTER TABLE users ADD requested_permissions INT NULL DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->getTable('users')->hasColumn('requested_permissions')) {
            $this->addSql('ALTER TABLE users DROP COLUMN requested_permissions');
        }
    }
}
