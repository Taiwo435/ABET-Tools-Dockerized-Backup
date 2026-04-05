<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;


final class Version20260405052855 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'database schema refactor by Sonakshi, changes course primary key to composite key.';
    }

    public function up(Schema $schema): void
    {
        // Drop existing primary key
        $this->addSql('ALTER TABLE courses DROP PRIMARY KEY');

        // Add composite primary key
        $this->addSql('ALTER TABLE courses ADD PRIMARY KEY (course_id, professor_id)');
    }

    public function down(Schema $schema): void
    {
        // Drop composite primary key
        $this->addSql('ALTER TABLE courses DROP PRIMARY KEY');

        // Restore original primary key
        $this->addSql('ALTER TABLE courses ADD PRIMARY KEY (course_id)');
    }
}
