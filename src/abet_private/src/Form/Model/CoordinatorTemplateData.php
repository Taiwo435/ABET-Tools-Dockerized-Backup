<?php

namespace App\Form\Model;

use App\Entity\Program;
use App\Entity\SyllabusTemplate\DeliveryType;
use App\Entity\SyllabusTemplate\SyllabusContentV1;
use App\Entity\SyllabusTemplate\TemplateRevision;
use App\Entity\SyllabusTemplate\TemplateSubmission;

final class CoordinatorTemplateData
{
    public ?Program $program = null;

    public string $courseSubject = '';

    public string $courseNumber = '';

    public string $courseName = '';

    public DeliveryType $deliveryType = DeliveryType::InPerson;

    public string $academicYear = '';
    public string $term = '';
    public string $section = '';

    public ?float $creditHours = null;

    public string $contactHours = '';
    public string $courseCoordinators = '';
    public string $instructors = '';
    public string $textbooks = '';
    public string $creditCategorization = '';
    public string $catalogDescription = '';
    public string $prerequisites = '';
    public ?string $courseType = null;
    public string $specificGoals = '';
    public string $courseOutcomes = '';
    public string $studentOutcomes = '';
    public string $topicsCovered = '';

    /** @var array<string, mixed> */
    private array $preservedContent = [];

    public static function fromRevision(TemplateRevision $revision): self
    {
        return self::fromContent($revision->getContent());
    }

    /** @param array<string, mixed> $content */
    public static function fromContent(array $content): self
    {
        $data = new self();
        $content = SyllabusContentV1::normalize($content);
        $data->preservedContent = $content;
        $data->creditHours = isset($content['credits']) && is_numeric($content['credits'])
            ? (float)$content['credits']
            : null;
        $data->contactHours = (string)($content['contact_hours'] ?? '');
        $data->courseCoordinators = self::listToLines($content['course_coordinators'] ?? []);
        $data->instructors = self::listToLines($content['instructors'] ?? []);
        $data->textbooks = self::listToLines($content['textbooks'] ?? []);
        $data->creditCategorization = (string)($content['credit_category'] ?? '');
        $data->catalogDescription = (string)($content['catalog_description'] ?? '');
        $data->prerequisites = (string)($content['prerequisites'] ?? '');
        $data->courseType = (string)($content['course_type'] ?? '');
        $data->specificGoals = self::listToLines($content['specific_goals'] ?? []);
        $data->courseOutcomes = self::listToLines($content['course_outcomes'] ?? []);
        $data->studentOutcomes = self::listToLines($content['student_outcomes'] ?? []);
        $data->topicsCovered = self::listToLines($content['topics_covered'] ?? []);

        return $data;
    }

    public static function fromSubmission(TemplateSubmission $submission): self
    {
        $revision = $submission->getWorkingRevision();
        $data = $revision === null ? new self() : self::fromRevision($revision);
        $course = $submission->getCommonCourse();
        $data->program = $course->getProgram();
        $data->courseSubject = $course->getCourseSubject();
        $data->courseNumber = $course->getCourseNumber();
        $data->courseName = $course->getCourseName();
        $data->deliveryType = $course->getDeliveryType();

        return $data;
    }

    /** @return array<string, mixed> */
    public function toContent(): array
    {
        return SyllabusContentV1::normalize(array_replace($this->preservedContent, [
            'credits' => $this->creditHours,
            'contact_hours' => trim($this->contactHours),
            'course_coordinators' => self::linesToList($this->courseCoordinators),
            'instructors' => self::linesToList($this->instructors),
            'textbooks' => self::linesToList($this->textbooks),
            'credit_category' => trim($this->creditCategorization),
            'catalog_description' => trim($this->catalogDescription),
            'prerequisites' => trim($this->prerequisites),
            'course_type' => trim((string)$this->courseType),
            'specific_goals' => self::linesToList($this->specificGoals),
            'course_outcomes' => self::linesToList($this->courseOutcomes),
            'student_outcomes' => self::linesToList($this->studentOutcomes),
            'topics_covered' => self::linesToList($this->topicsCovered),
        ]));
    }

    public function isEquivalentTo(self $other): bool
    {
        return $this->hasSameCourseIdentityAs($other)
            && $this->toContent() === $other->toContent();
    }

    public function hasSameCourseIdentityAs(self $other): bool
    {
        return $this->program === $other->program
            && strtoupper(trim($this->courseSubject)) === strtoupper(trim($other->courseSubject))
            && trim($this->courseNumber) === trim($other->courseNumber)
            && trim($this->courseName) === trim($other->courseName)
            && $this->deliveryType === $other->deliveryType;
    }

    /** @return list<string> */
    private static function linesToList(string $value): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (string $line): string => trim($line),
            preg_split('/\R/', $value) ?: [],
        ))));
    }

    private static function listToLines(mixed $value): string
    {
        if (!is_array($value)) {
            return is_scalar($value) ? (string)$value : '';
        }

        return implode("\n", array_map('strval', $value));
    }
}
