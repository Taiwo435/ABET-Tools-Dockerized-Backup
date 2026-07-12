<?php

namespace App\Entity\SyllabusTemplate;

use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'syllabus_template_revisions')]
#[ORM\UniqueConstraint(name: 'uniq_syllabus_submission_revision', columns: ['submission_id', 'revision_number'])]
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

    #[ORM\Column(name: 'schema_version', options: ['default' => 1])]
    private int $schemaVersion = 1;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(TemplateSubmission $submission, User $author, RevisionAuthorType $authorType, int $revisionNumber, array $content)
    {
        if ($revisionNumber <= 0 || $content === []) {
            throw new \InvalidArgumentException('A positive revision number and non-empty content are required.');
        }

        $this->submission = $submission;
        $this->author = $author;
        $this->authorType = $authorType;
        $this->revisionNumber = $revisionNumber;
        $this->content = $content;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getSubmission(): TemplateSubmission { return $this->submission; }
    public function getAuthor(): User { return $this->author; }
    public function getAuthorType(): RevisionAuthorType { return $this->authorType; }
    public function getRevisionNumber(): int { return $this->revisionNumber; }
    public function getContent(): array { return $this->content; }
    public function getSchemaVersion(): int { return $this->schemaVersion; }
}
