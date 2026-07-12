<?php

namespace App\Entity\SyllabusTemplate;

use App\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'syllabus_template_submissions')]
#[ORM\Index(name: 'idx_syllabus_submission_queue', columns: ['status', 'submitted_at'])]
class TemplateSubmission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CommonCourse::class)]
    #[ORM\JoinColumn(name: 'common_course_id', nullable: false, onDelete: 'RESTRICT')]
    private CommonCourse $commonCourse;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'faculty_user_id', nullable: false, onDelete: 'RESTRICT')]
    private User $faculty;

    #[ORM\Column(length: 32, enumType: SubmissionStatus::class)]
    private SubmissionStatus $status = SubmissionStatus::Draft;

    #[ORM\OneToMany(mappedBy: 'submission', targetEntity: TemplateRevision::class, cascade: ['persist'])]
    #[ORM\OrderBy(['revisionNumber' => 'ASC'])]
    private Collection $revisions;

    #[ORM\ManyToOne(targetEntity: TemplateRevision::class)]
    #[ORM\JoinColumn(name: 'submitted_revision_id', nullable: true, onDelete: 'RESTRICT')]
    private ?TemplateRevision $submittedRevision = null;

    #[ORM\ManyToOne(targetEntity: TemplateRevision::class)]
    #[ORM\JoinColumn(name: 'approved_revision_id', nullable: true, onDelete: 'RESTRICT')]
    private ?TemplateRevision $approvedRevision = null;

    #[ORM\OneToOne(mappedBy: 'submission', targetEntity: TemplateReview::class, cascade: ['persist'])]
    private ?TemplateReview $review = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(name: 'submitted_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $submittedAt = null;

    #[ORM\Column(name: 'decided_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $decidedAt = null;

    public function __construct(CommonCourse $commonCourse, User $faculty)
    {
        $this->commonCourse = $commonCourse;
        $this->faculty = $faculty;
        $this->revisions = new ArrayCollection();
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getCommonCourse(): CommonCourse { return $this->commonCourse; }
    public function getFaculty(): User { return $this->faculty; }
    public function getStatus(): SubmissionStatus { return $this->status; }
    public function getSubmittedRevision(): ?TemplateRevision { return $this->submittedRevision; }
    public function getApprovedRevision(): ?TemplateRevision { return $this->approvedRevision; }
    public function getReview(): ?TemplateReview { return $this->review; }
    public function getSubmittedAt(): ?\DateTimeImmutable { return $this->submittedAt; }
    public function getDecidedAt(): ?\DateTimeImmutable { return $this->decidedAt; }

    /** @return Collection<int, TemplateRevision> */
    public function getRevisions(): Collection { return $this->revisions; }

    public function addRevision(User $author, RevisionAuthorType $authorType, array $content): TemplateRevision
    {
        if ($authorType === RevisionAuthorType::Faculty && $this->status !== SubmissionStatus::Draft) {
            throw new \DomainException('Faculty revisions can only be added while the submission is a draft.');
        }
        if ($authorType === RevisionAuthorType::Coordinator && $this->status !== SubmissionStatus::Submitted) {
            throw new \DomainException('Coordinator revisions can only be added during review.');
        }

        if ($authorType === RevisionAuthorType::Faculty && $author !== $this->faculty) {
            throw new \DomainException('Only the submission owner can create a faculty revision.');
        }

        $revision = new TemplateRevision($this, $author, $authorType, $this->revisions->count() + 1, $content);
        $this->revisions->add($revision);
        $this->updatedAt = new \DateTimeImmutable();

        return $revision;
    }

    public function submit(TemplateRevision $revision, ?\DateTimeImmutable $at = null): void
    {
        if ($this->status !== SubmissionStatus::Draft) {
            throw new \DomainException('Only a draft can be submitted.');
        }
        if (!$this->revisions->contains($revision) || $revision->getAuthorType() !== RevisionAuthorType::Faculty) {
            throw new \DomainException('A submission must use one of its faculty-authored revisions.');
        }

        $this->submittedRevision = $revision;
        $this->status = SubmissionStatus::Submitted;
        $this->submittedAt = $this->updatedAt = $at ?? new \DateTimeImmutable();
    }

    public function recordReview(TemplateReview $review, TemplateRevision $approvedRevision, ?\DateTimeImmutable $at = null): void
    {
        if ($this->status !== SubmissionStatus::Submitted || $this->review !== null) {
            throw new \DomainException('Only an unreviewed submitted template can receive a decision.');
        }
        if ($review->getSubmission() !== $this || $approvedRevision->getSubmission() !== $this) {
            throw new \DomainException('The review and approved revision must belong to this submission.');
        }
        if ($review->getDecision() === ReviewDecision::Approved && $approvedRevision !== $this->submittedRevision) {
            throw new \DomainException('Approval without edits must publish the submitted faculty revision.');
        }
        if ($review->getDecision() === ReviewDecision::ApprovedWithEdits
            && ($approvedRevision === $this->submittedRevision || $approvedRevision->getAuthorType() !== RevisionAuthorType::Coordinator)) {
            throw new \DomainException('Approval with edits requires a coordinator-authored revision.');
        }

        $this->review = $review;
        $this->approvedRevision = $approvedRevision;
        $this->status = match ($review->getDecision()) {
            ReviewDecision::Approved => SubmissionStatus::Approved,
            ReviewDecision::ApprovedWithEdits => SubmissionStatus::ApprovedWithEdits,
            ReviewDecision::Denied => throw new \DomainException('A denied submission cannot have an approved revision.'),
        };
        $this->decidedAt = $this->updatedAt = $at ?? new \DateTimeImmutable();
        $this->commonCourse->publish($approvedRevision);
    }

    public function recordDenial(TemplateReview $review, ?\DateTimeImmutable $at = null): void
    {
        if ($this->status !== SubmissionStatus::Submitted || $this->review !== null) {
            throw new \DomainException('Only an unreviewed submitted template can be denied.');
        }
        if ($review->getSubmission() !== $this || $review->getDecision() !== ReviewDecision::Denied) {
            throw new \DomainException('A denial review for this submission is required.');
        }

        $this->review = $review;
        $this->status = SubmissionStatus::Denied;
        $this->decidedAt = $this->updatedAt = $at ?? new \DateTimeImmutable();
    }
}
