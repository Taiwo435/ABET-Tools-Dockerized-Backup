<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260727000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add versioned source provenance to immutable syllabus revisions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE syllabus_template_revisions ADD source_provenance JSON DEFAULT NULL');
        $this->addSql(<<<'SQL'
UPDATE syllabus_template_revisions
SET source_provenance = JSON_OBJECT(
    'schema_version', '1.0',
    'source_type', 'manual_entry',
    'source_document', NULL,
    'source_revision', NULL,
    'extraction', NULL,
    'fields', JSON_OBJECT()
)
WHERE source_provenance IS NULL
SQL);
        $this->addSql('ALTER TABLE syllabus_template_revisions MODIFY source_provenance JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE syllabus_template_revisions DROP source_provenance');
    }
}
