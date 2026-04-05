<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Services;


final class Version20260405052855 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'database schema refactor to add index to courses.';
    }

        public function up(Schema $schema): void
    {
        $table = $schema->getTable('courses');

        // Check if the index already exists
        $this->skipIf($table->hasIndex('uniq_course_professor'), 'Skipped due to existing index');

        $this->addSql('ALTER TABLE courses ADD UNIQUE INDEX uniq_course_professor (course_id, professor_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE courses DROP INDEX uniq_course_professor');
    }
}
