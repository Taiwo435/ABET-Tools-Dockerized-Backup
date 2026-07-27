<?php

namespace App\Entity\SyllabusTemplate;

use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'syllabus_course_offerings')]
#[ORM\UniqueConstraint(
    name: 'uniq_syllabus_course_offering',
    columns: ['common_course_id', 'academic_year', 'term', 'section'],
)]
class CourseOffering
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CommonCourse::class)]
    #[ORM\JoinColumn(name: 'common_course_id', nullable: false, onDelete: 'RESTRICT')]
    private CommonCourse $commonCourse;

    #[ORM\Column(name: 'academic_year', length: 20)]
    private string $academicYear;

    #[ORM\Column(length: 32)]
    private string $term;

    #[ORM\Column(length: 50, options: ['default' => ''])]
    private string $section;

    #[ORM\Column(name: 'delivery_type', length: 32, enumType: DeliveryType::class)]
    private DeliveryType $deliveryType;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'instructor_user_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $instructor;

    #[ORM\ManyToOne(targetEntity: TemplateRevision::class)]
    #[ORM\JoinColumn(name: 'current_approved_revision_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?TemplateRevision $currentApprovedRevision = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        CommonCourse $commonCourse,
        string $academicYear,
        string $term,
        DeliveryType $deliveryType,
        ?User $instructor = null,
        ?string $section = null,
    ) {
        $this->commonCourse = $commonCourse;
        $this->academicYear = trim($academicYear);
        $this->term = trim($term);
        $this->section = trim((string)$section);
        $this->deliveryType = $deliveryType;
        $this->instructor = $instructor;
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();

        if ($this->academicYear === '' || $this->term === '') {
            throw new \InvalidArgumentException('Academic year and term are required for a course offering.');
        }
    }

    public function getId(): ?int { return $this->id; }
    public function getCommonCourse(): CommonCourse { return $this->commonCourse; }
    public function getAcademicYear(): string { return $this->academicYear; }
    public function getTerm(): string { return $this->term; }
    public function getSection(): string { return $this->section; }
    public function getDeliveryType(): DeliveryType { return $this->deliveryType; }
    public function getInstructor(): ?User { return $this->instructor; }
    public function getCurrentApprovedRevision(): ?TemplateRevision { return $this->currentApprovedRevision; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function assignInstructor(?User $instructor): void
    {
        $this->instructor = $instructor;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
