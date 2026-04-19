<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260419012513 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Modification by Max to make program_id nullable';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE continuous_improvement MODIFY program_id INT NULL;');

    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE continuous_improvement MODIFY program_id INT NOT NULL;');

    }
}
