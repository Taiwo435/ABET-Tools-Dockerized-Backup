<?php

namespace App\Entity\SyllabusTemplate;

final class TemplateContentCompleteness
{
    /** @var array<string, list<string>> */
    private const REQUIRED_FIELDS = [
        SyllabusCompletenessPurpose::DraftSaveable->value => [],
        SyllabusCompletenessPurpose::FacultySubmittable->value => [
            'credits',
            'course_coordinators',
            'credit_category',
        ],
        SyllabusCompletenessPurpose::CoordinatorPublishable->value => [
            'credits',
            'course_coordinators',
            'credit_category',
        ],
        SyllabusCompletenessPurpose::AppendixAReady->value => [
            'credits',
            'contact_hours',
            'credit_category',
            'instructors',
            'catalog_description',
            'course_type',
            'specific_goals',
            'student_outcomes',
            'topics_covered',
        ],
    ];

    /**
     * Course identity and delivery type are evaluated by the owning common
     * course or offering. This evaluator covers versioned revision content.
     *
     * @param array<string, mixed> $content
     * @return array{
     *     purpose: SyllabusCompletenessPurpose,
     *     status: CompletenessStatus,
     *     missingFields: list<string>,
     *     invalidFields: list<string>,
     *     blockingFields: list<string>,
     *     warnings: list<string>
     * }
     */
    public static function assess(
        array $content,
        SyllabusCompletenessPurpose $purpose = SyllabusCompletenessPurpose::CoordinatorPublishable,
    ): array
    {
        $content = SyllabusContentV1::normalize($content);
        $missingFields = [];
        $invalidFields = [];
        $requiredFields = self::REQUIRED_FIELDS[$purpose->value];

        foreach ($requiredFields as $field) {
            $value = $content[$field] ?? null;
            if (self::isMissing($value)) {
                $missingFields[] = $field;
            }
        }

        if (in_array('credits', $requiredFields, true)
            && array_key_exists('credits', $content)
            && !self::isMissing($content['credits'])
            && (!is_numeric($content['credits']) || (float)$content['credits'] <= 0)) {
            $invalidFields[] = 'credits';
        }

        if (in_array('course_type', $requiredFields, true)
            && isset($content['course_type'])
            && trim((string)$content['course_type']) !== ''
            && !in_array($content['course_type'], ['R', 'E', 'SE'], true)) {
            $invalidFields[] = 'course_type';
        }

        $blockingFields = array_values(array_unique([...$missingFields, ...$invalidFields]));

        return [
            'purpose' => $purpose,
            'status' => $blockingFields === [] ? CompletenessStatus::Complete : CompletenessStatus::Incomplete,
            'missingFields' => $missingFields,
            'invalidFields' => $invalidFields,
            'blockingFields' => $blockingFields,
            'warnings' => [],
        ];
    }

    private static function isMissing(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_string($value)) {
            return trim($value) === '';
        }
        if (is_array($value)) {
            return array_filter(
                $value,
                static fn (mixed $item): bool => trim((string)$item) !== '',
            ) === [];
        }

        return false;
    }
}
