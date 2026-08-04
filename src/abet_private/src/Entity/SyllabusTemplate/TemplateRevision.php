<?php

namespace App\Entity\SyllabusTemplate;

use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'syllabus_template_revisions')]
#[ORM\UniqueConstraint(name: 'uniq_syllabus_submission_revision', columns: ['submission_id', 'revision_number'])]
#[ORM\Index(name: 'idx_syllabus_revision_completeness', columns: ['completeness_status'])]
class TemplateRevision
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: TemplateSubmission::class, inversedBy: 'revisions')]
    #[ORM\JoinColumn(name: 'submission_id', nullable: false, onDelete: 'CASCADE')]
    private TemplateSubmission $submission;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'author_user_id', nullable: false, onDelete: 'RESTRICT')]
    private User $author;

    #[ORM\Column(name: 'author_type', length: 32, enumType: RevisionAuthorType::class)]
    private RevisionAuthorType $authorType;

    #[ORM\Column(name: 'revision_number')]
    private int $revisionNumber;

    #[ORM\Column(type: 'json')]
    private array $content;

    #[ORM\Column(name: 'completeness_status', length: 16, enumType: CompletenessStatus::class)]
    private CompletenessStatus $completenessStatus;

    #[ORM\Column(name: 'missing_fields', type: 'json')]
    private array $missingFields;

    #[ORM\Column(name: 'schema_version', length: 16, options: ['default' => SyllabusContentV1::VERSION])]
    private string $schemaVersion = SyllabusContentV1::VERSION;

    #[ORM\Column(name: 'source_provenance', type: 'json')]
    private array $sourceProvenance;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        TemplateSubmission $submission,
        User $author,
        RevisionAuthorType $authorType,
        int $revisionNumber,
        array $content,
        CompletenessStatus $completenessStatus,
        array $missingFields = [],
        ?SyllabusProvenanceV1 $sourceProvenance = null,
    )
    {
        if ($revisionNumber <= 0) {
            throw new \InvalidArgumentException('A positive revision number is required.');
        }
        $missingFields = array_values(array_unique(array_filter(array_map('strval', $missingFields))));
        if ($completenessStatus === CompletenessStatus::Complete && $missingFields !== []) {
            throw new \InvalidArgumentException('A complete revision cannot have missing fields.');
        }
        if ($completenessStatus === CompletenessStatus::Incomplete && $missingFields === []) {
            throw new \InvalidArgumentException('An incomplete revision must identify its missing fields.');
        }

        $this->submission = $submission;
        $this->author = $author;
        $this->authorType = $authorType;
        $this->revisionNumber = $revisionNumber;
        $this->content = SyllabusContentV1::normalize($content);
        $this->schemaVersion = (string)$this->content['schema_version'];
        $this->completenessStatus = $completenessStatus;
        $this->missingFields = $missingFields;
        $this->sourceProvenance = ($sourceProvenance ?? SyllabusProvenanceV1::manualEntry())->toArray();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getSubmission(): TemplateSubmission { return $this->submission; }
    public function getAuthor(): User { return $this->author; }
    public function getAuthorType(): RevisionAuthorType { return $this->authorType; }
    public function getRevisionNumber(): int { return $this->revisionNumber; }
    public function getContent(): array { return SyllabusContentV1::normalize($this->content); }
    public function getCompletenessStatus(): CompletenessStatus { return $this->completenessStatus; }
    public function getMissingFields(): array { return SyllabusContentV1::normalizeFieldNames($this->missingFields); }
    public function isComplete(): bool { return $this->completenessStatus === CompletenessStatus::Complete; }
    public function getSchemaVersion(): string { return $this->schemaVersion; }
    public function getSourceProvenance(): SyllabusProvenanceV1 { return SyllabusProvenanceV1::fromArray($this->sourceProvenance); }
    public function getSourceType(): SyllabusRevisionSource { return $this->getSourceProvenance()->getSourceType(); }

    /**
     * @return array{
     *     purpose: SyllabusCompletenessPurpose,
     *     status: CompletenessStatus,
     *     missingFields: list<string>,
     *     invalidFields: list<string>,
     *     blockingFields: list<string>,
     *     warnings: list<string>
     * }
     */
    public function getCompletenessFor(SyllabusCompletenessPurpose $purpose): array
    {
        return TemplateContentCompleteness::assess($this->getContent(), $purpose);
    }

    public function isCompleteFor(SyllabusCompletenessPurpose $purpose): bool
    {
        return $this->getCompletenessFor($purpose)['status'] === CompletenessStatus::Complete;
    }

    /** @return list<string> */
    public function getBlockingFieldsFor(SyllabusCompletenessPurpose $purpose): array
    {
        return $this->getCompletenessFor($purpose)['blockingFields'];
    }

    public function isDraftSaveable(): bool
    {
        return $this->isCompleteFor(SyllabusCompletenessPurpose::DraftSaveable);
    }

    public function isFacultySubmittable(): bool
    {
        return $this->isCompleteFor(SyllabusCompletenessPurpose::FacultySubmittable);
    }

    public function isCoordinatorPublishable(): bool
    {
        return $this->isCompleteFor(SyllabusCompletenessPurpose::CoordinatorPublishable);
    }

    public function isAppendixAReady(): bool
    {
        return $this->isCompleteFor(SyllabusCompletenessPurpose::AppendixAReady);
    }

    /** @return list<string> */
    public function getFacultySubmissionBlockingFields(): array
    {
        return $this->getBlockingFieldsFor(SyllabusCompletenessPurpose::FacultySubmittable);
    }

    /** @return list<string> */
    public function getCoordinatorPublicationBlockingFields(): array
    {
        return $this->getBlockingFieldsFor(SyllabusCompletenessPurpose::CoordinatorPublishable);
    }

    /** @return list<string> */
    public function getAppendixABlockingFields(): array
    {
        return $this->getBlockingFieldsFor(SyllabusCompletenessPurpose::AppendixAReady);
    }
}
