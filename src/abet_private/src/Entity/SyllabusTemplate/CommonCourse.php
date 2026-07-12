<?php

namespace App\Entity\SyllabusTemplate;

use App\Entity\Program;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'syllabus_common_courses')]
#[ORM\UniqueConstraint(name: 'uniq_syllabus_common_course', columns: ['program_id', 'course_subject', 'course_number', 'delivery_type'])]
class CommonCourse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Program::class)]
    #[ORM\JoinColumn(name: 'program_id', referencedColumnName: 'program_id', nullable: false, onDelete: 'RESTRICT')]
    private Program $program;

    #[ORM\Column(name: 'course_subject', length: 50)]
    private string $courseSubject;

    #[ORM\Column(name: 'course_number', length: 50)]
    private string $courseNumber;

    #[ORM\Column(name: 'course_name', length: 255)]
    private string $courseName;

    #[ORM\Column(name: 'delivery_type', length: 32, enumType: DeliveryType::class)]
    private DeliveryType $deliveryType;

    #[ORM\ManyToOne(targetEntity: TemplateRevision::class)]
    #[ORM\JoinColumn(name: 'current_approved_revision_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?TemplateRevision $currentApprovedRevision = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Program $program, string $courseSubject, string $courseNumber, string $courseName, DeliveryType $deliveryType)
    {
        $this->program = $program;
        $this->courseSubject = strtoupper(trim($courseSubject));
        $this->courseNumber = trim($courseNumber);
        $this->courseName = trim($courseName);
        $this->deliveryType = $deliveryType;
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();

        if ($this->courseSubject === '' || $this->courseNumber === '' || $this->courseName === '') {
            throw new \InvalidArgumentException('Course subject, number, and name are required.');
        }
    }

    public function getId(): ?int { return $this->id; }
    public function getProgram(): Program { return $this->program; }
    public function getCourseSubject(): string { return $this->courseSubject; }
    public function getCourseNumber(): string { return $this->courseNumber; }
    public function getCourseName(): string { return $this->courseName; }
    public function getDeliveryType(): DeliveryType { return $this->deliveryType; }
    public function getCurrentApprovedRevision(): ?TemplateRevision { return $this->currentApprovedRevision; }

    public function publish(TemplateRevision $revision): void
    {
        if ($revision->getSubmission()->getCommonCourse() !== $this) {
            throw new \DomainException('The approved revision belongs to a different common course.');
        }

        $this->currentApprovedRevision = $revision;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
