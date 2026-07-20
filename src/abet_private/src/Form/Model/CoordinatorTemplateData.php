<?php

namespace App\Form\Model;

use App\Entity\Program;
use App\Entity\SyllabusTemplate\DeliveryType;
use App\Entity\SyllabusTemplate\TemplateRevision;
use App\Entity\SyllabusTemplate\TemplateSubmission;

final class CoordinatorTemplateData
{
    public ?Program $program = null;

    public string $courseSubject = '';

    public string $courseNumber = '';

    public string $courseName = '';

    public DeliveryType $deliveryType = DeliveryType::InPerson;

    public ?float $creditHours = null;

    public string $courseCoordinators = '';
    public string $creditCategorization = '';
    public string $catalogDescription = '';
    public string $courseOutcomes = '';

    /** @var array<string, mixed> */
    private array $preservedContent = [];

    public static function fromRevision(TemplateRevision $revision): self
    {
        $data = new self();
        $content = $revision->getContent();
        $data->preservedContent = $content;
        $data->creditHours = isset($content['creditHours']) && is_numeric($content['creditHours'])
            ? (float)$content['creditHours']
            : null;
        $data->courseCoordinators = self::listToLines($content['courseCoordinators'] ?? []);
        $data->creditCategorization = (string)($content['creditCategorization'] ?? '');
        $data->catalogDescription = (string)($content['catalogDescription'] ?? '');
        $data->courseOutcomes = self::listToLines($content['courseOutcomes'] ?? []);

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
        return array_replace($this->preservedContent, [
            'creditHours' => $this->creditHours,
            'courseCoordinators' => self::linesToList($this->courseCoordinators),
            'creditCategorization' => trim($this->creditCategorization),
            'catalogDescription' => trim($this->catalogDescription),
            'courseOutcomes' => self::linesToList($this->courseOutcomes),
        ]);
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
