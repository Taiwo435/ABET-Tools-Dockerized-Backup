<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Services;

require_once __DIR__.'/../../bootstrap.php';

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260322173456 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Gives users the PERMISSIONS column';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->skipIf(Services::doesColumnExist("users", "permissions"), 'Skipping this migration.');
        $this->addSql('ALTER TABLE users ADD permissions INT NOT NULL;');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE users DROP COLUMN permissions;');
    }
}
