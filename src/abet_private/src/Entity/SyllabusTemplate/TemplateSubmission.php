<?php

namespace App\Entity\SyllabusTemplate;

use App\Entity\User;
use App\Repository\SyllabusTemplate\TemplateSubmissionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TemplateSubmissionRepository::class)]
#[ORM\Table(name: 'syllabus_template_submissions')]
#[ORM\Index(name: 'idx_syllabus_submission_queue', columns: ['status', 'submitted_at'])]
#[ORM\Index(name: 'idx_syllabus_proposal_origin_status', columns: ['origin', 'status'])]
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
    #[ORM\JoinColumn(name: 'submitted_by_user_id', nullable: false, onDelete: 'RESTRICT')]
    private User $submittedBy;

    #[ORM\Column(length: 32, enumType: ProposalOrigin::class)]
    private ProposalOrigin $origin;

    #[ORM\Column(length: 32, enumType: SubmissionStatus::class)]
    private SubmissionStatus $status = SubmissionStatus::Draft;

    #[ORM\OneToMany(mappedBy: 'submission', targetEntity: TemplateRevision::class, cascade: ['persist'])]
    #[ORM\OrderBy(['revisionNumber' => 'ASC'])]
    private Collection $revisions;

    #[ORM\ManyToOne(targetEntity: TemplateRevision::class)]
    #[ORM\JoinColumn(name: 'working_revision_id', nullable: true, onDelete: 'RESTRICT')]
    private ?TemplateRevision $workingRevision = null;

    #[ORM\ManyToOne(targetEntity: TemplateRevision::class)]
    #[ORM\JoinColumn(name: 'based_on_revision_id', nullable: true, onDelete: 'SET NULL')]
    private ?TemplateRevision $basedOnRevision = null;

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

    public function __construct(
        CommonCourse $commonCourse,
        User $submittedBy,
        ProposalOrigin $origin,
        ?TemplateRevision $basedOnRevision = null,
    )
    {
        if ($basedOnRevision !== null && $basedOnRevision->getSubmission()->getCommonCourse() !== $commonCourse) {
            throw new \InvalidArgumentException('The source template revision belongs to a different common course.');
        }

        $this->commonCourse = $commonCourse;
        $this->submittedBy = $submittedBy;
        $this->origin = $origin;
        $this->basedOnRevision = $basedOnRevision;
        $this->revisions = new ArrayCollection();
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getCommonCourse(): CommonCourse { return $this->commonCourse; }
    public function getSubmittedBy(): User { return $this->submittedBy; }
    public function getOrigin(): ProposalOrigin { return $this->origin; }
    public function getStatus(): SubmissionStatus { return $this->status; }
    public function getWorkingRevision(): ?TemplateRevision { return $this->workingRevision; }
    public function getBasedOnRevision(): ?TemplateRevision { return $this->basedOnRevision; }
    public function getSubmittedRevision(): ?TemplateRevision { return $this->submittedRevision; }
    public function getApprovedRevision(): ?TemplateRevision { return $this->approvedRevision; }
    public function getReview(): ?TemplateReview { return $this->review; }
    public function getSubmittedAt(): ?\DateTimeImmutable { return $this->submittedAt; }
    public function getDecidedAt(): ?\DateTimeImmutable { return $this->decidedAt; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    /** @return Collection<int, TemplateRevision> */
    public function getRevisions(): Collection { return $this->revisions; }

    public function addRevision(
        User $author,
        RevisionAuthorType $authorType,
        array $content,
    ): TemplateRevision
    {
        if ($this->origin === ProposalOrigin::FacultySubmission
            && $authorType === RevisionAuthorType::Faculty
            && $this->status !== SubmissionStatus::Draft) {
            throw new \DomainException('Faculty revisions can only be added while the submission is a draft.');
        }
        if ($this->origin === ProposalOrigin::FacultySubmission
            && $authorType === RevisionAuthorType::Coordinator
            && $this->status !== SubmissionStatus::Submitted) {
            throw new \DomainException('Coordinator revisions can only be added during review.');
        }
        if ($this->origin === ProposalOrigin::CoordinatorCreated
            && ($authorType !== RevisionAuthorType::Coordinator || $this->status !== SubmissionStatus::Draft)) {
            throw new \DomainException('Coordinator-created templates can only be revised by a coordinator while in draft.');
        }
        if ($author !== $this->submittedBy && $authorType === RevisionAuthorType::Faculty) {
            throw new \DomainException('Only the proposal owner can create a faculty revision.');
        }

        $completeness = TemplateContentCompleteness::assess($content);
        $revision = new TemplateRevision(
            $this,
            $author,
            $authorType,
            $this->revisions->count() + 1,
            $content,
            $completeness['status'],
            $completeness['missingFields'],
        );
        $this->revisions->add($revision);
        $this->workingRevision = $revision;
        $this->updatedAt = new \DateTimeImmutable();

        return $revision;
    }

    public function submit(TemplateRevision $revision, ?\DateTimeImmutable $at = null): void
    {
        if ($this->origin !== ProposalOrigin::FacultySubmission) {
            throw new \DomainException('Coordinator-created templates are published directly instead of submitted for review.');
        }
        if ($this->status !== SubmissionStatus::Draft) {
            throw new \DomainException('Only a draft can be submitted.');
        }
        if (!$this->revisions->contains($revision) || $revision->getAuthorType() !== RevisionAuthorType::Faculty) {
            throw new \DomainException('A submission must use one of its faculty-authored revisions.');
        }
        if (!$revision->isComplete()) {
            throw new \DomainException('An incomplete faculty template cannot be submitted for review.');
        }

        $this->submittedRevision = $revision;
        $this->status = SubmissionStatus::Submitted;
        $this->submittedAt = $this->updatedAt = $at ?? new \DateTimeImmutable();
    }

    public function publishCoordinatorTemplate(TemplateRevision $revision, ?\DateTimeImmutable $at = null): void
    {
        if ($this->origin !== ProposalOrigin::CoordinatorCreated || $this->status !== SubmissionStatus::Draft) {
            throw new \DomainException('Only a coordinator-created draft can be published directly.');
        }
        if (!$this->revisions->contains($revision)
            || $revision->getAuthorType() !== RevisionAuthorType::Coordinator
            || !$revision->isComplete()) {
            throw new \DomainException('Direct publication requires a complete coordinator-authored revision.');
        }

        $this->approvedRevision = $revision;
        $this->status = SubmissionStatus::Approved;
        $this->decidedAt = $this->updatedAt = $at ?? new \DateTimeImmutable();
        $this->commonCourse->publish($revision);
    }

    public function beginCoordinatorRevision(User $author, array $content): TemplateRevision
    {
        if ($this->origin !== ProposalOrigin::CoordinatorCreated
            || $this->status !== SubmissionStatus::Approved
            || $this->approvedRevision === null) {
            throw new \DomainException('Only a published coordinator template can begin a new revision.');
        }

        $this->status = SubmissionStatus::Draft;

        return $this->addRevision($author, RevisionAuthorType::Coordinator, $content);
    }

    public function prepareFacultyDraftDeletion(User $owner): void
    {
        if ($this->origin !== ProposalOrigin::FacultySubmission
            || $this->status !== SubmissionStatus::Draft
            || $this->submittedBy !== $owner) {
            throw new \DomainException('Only the proposal owner can delete their faculty draft.');
        }

        // Break revision references before the submission is removed so the
        // database can cascade-delete its immutable, unsubmitted revisions.
        $this->workingRevision = null;
        $this->basedOnRevision = null;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function recordReview(TemplateReview $review, TemplateRevision $approvedRevision, ?\DateTimeImmutable $at = null): void
    {
        if ($this->origin !== ProposalOrigin::FacultySubmission) {
            throw new \DomainException('Coordinator-created templates do not require a review decision.');
        }
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
        if (!$approvedRevision->isComplete()) {
            throw new \DomainException('An incomplete revision cannot be approved.');
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
        if ($this->origin !== ProposalOrigin::FacultySubmission) {
            throw new \DomainException('Coordinator-created templates are not denied through faculty review.');
        }
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
