<?php

namespace App\Entity\SyllabusTemplate;

use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'syllabus_template_reviews')]
class TemplateReview
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'review', targetEntity: TemplateSubmission::class)]
    #[ORM\JoinColumn(name: 'submission_id', nullable: false, unique: true, onDelete: 'CASCADE')]
    private TemplateSubmission $submission;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'reviewer_user_id', nullable: false, onDelete: 'RESTRICT')]
    private User $reviewer;

    #[ORM\Column(length: 32, enumType: ReviewDecision::class)]
    private ReviewDecision $decision;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(TemplateSubmission $submission, User $reviewer, ReviewDecision $decision, ?string $comment = null)
    {
        if ($reviewer === $submission->getFaculty()) {
            throw new \InvalidArgumentException('Faculty cannot review their own submission.');
        }

        $comment = trim((string)$comment);
        if ($decision === ReviewDecision::Denied && $comment === '') {
            throw new \InvalidArgumentException('A denial requires a reviewer comment.');
        }

        $this->submission = $submission;
        $this->reviewer = $reviewer;
        $this->decision = $decision;
        $this->comment = $comment !== '' ? $comment : null;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getSubmission(): TemplateSubmission { return $this->submission; }
    public function getReviewer(): User { return $this->reviewer; }
    public function getDecision(): ReviewDecision { return $this->decision; }
    public function getComment(): ?string { return $this->comment; }
}
