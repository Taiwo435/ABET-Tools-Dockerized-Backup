<?php

namespace App\Entity\SyllabusTemplate;

final class TemplateContentCompleteness
{
    /** @var list<string> */
    public const REQUIRED_FIELDS = [
        'credits',
        'course_coordinators',
        'credit_category',
    ];

    /**
     * @return array{status: CompletenessStatus, missingFields: list<string>}
     */
    public static function assess(array $content): array
    {
        $missingFields = [];

        foreach (self::REQUIRED_FIELDS as $field) {
            $value = $content[$field] ?? null;
            $isMissing = match ($field) {
                'course_coordinators' => !is_array($value) || array_filter(
                    $value,
                    static fn (mixed $coordinator): bool => trim((string)$coordinator) !== '',
                ) === [],
                default => $value === null || trim((string)$value) === '',
            };

            if ($isMissing) {
                $missingFields[] = $field;
            }
        }

        return [
            'status' => $missingFields === [] ? CompletenessStatus::Complete : CompletenessStatus::Incomplete,
            'missingFields' => $missingFields,
        ];
    }
}
