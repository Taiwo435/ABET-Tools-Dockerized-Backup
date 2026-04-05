<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260405044821 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table created by Muzzamil, adds faculty_qualifications_by_program.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS faculty_qualifications_by_program (
    qual_program_id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    program_label VARCHAR(255) NOT NULL,
    professors TEXT,
    associate_professors TEXT,
    assistant_professors TEXT,
    lecturers_pop TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE CASCADE
);
SQL
        );

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE faculty_qualifications_by_program');
    }
}
