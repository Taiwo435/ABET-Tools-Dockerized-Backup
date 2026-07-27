<?php

namespace App\Entity\SyllabusTemplate;

final class SyllabusContentV1
{
    public const VERSION = '1.0';

    /** @var array<string, string> */
    private const LEGACY_ALIASES = [
        'creditHours' => 'credits',
        'contactHours' => 'contact_hours',
        'creditCategorization' => 'credit_category',
        'courseCoordinators' => 'course_coordinators',
        'catalogDescription' => 'catalog_description',
        'courseOutcomes' => 'course_outcomes',
        'deliveryType' => 'delivery_type',
        'specificGoals' => 'specific_goals',
        'studentOutcomes' => 'student_outcomes',
        'topics' => 'topics_covered',
        'topicsCovered' => 'topics_covered',
    ];

    /** @var list<string> */
    private const LIST_FIELDS = [
        'course_coordinators',
        'instructors',
        'textbooks',
        'specific_goals',
        'course_outcomes',
        'student_outcomes',
        'topics_covered',
    ];

    /**
     * Normalize persisted revision content without discarding extension fields.
     *
     * Legacy camelCase aliases are removed after their value has been copied to
     * the canonical key. An already-present canonical value always wins.
     *
     * @param array<string, mixed> $content
     * @return array<string, mixed>
     */
    public static function normalize(array $content): array
    {
        foreach (self::LEGACY_ALIASES as $legacy => $canonical) {
            if (!array_key_exists($canonical, $content) && array_key_exists($legacy, $content)) {
                $content[$canonical] = $content[$legacy];
            }

            unset($content[$legacy]);
        }

        $content['schema_version'] = self::VERSION;

        foreach (self::LIST_FIELDS as $field) {
            if (!array_key_exists($field, $content)) {
                continue;
            }

            $content[$field] = self::normalizeList($content[$field]);
        }

        if (array_key_exists('credits', $content) && is_numeric($content['credits'])) {
            $content['credits'] = (float)$content['credits'];
        }

        foreach ([
            'contact_hours',
            'credit_category',
            'catalog_description',
            'prerequisites',
            'course_type',
            'delivery_type',
        ] as $field) {
            if (isset($content[$field]) && is_scalar($content[$field])) {
                $content[$field] = trim((string)$content[$field]);
            }
        }

        return $content;
    }

    public static function canonicalFieldName(string $field): string
    {
        return self::LEGACY_ALIASES[$field] ?? $field;
    }

    /**
     * @param array<int, mixed> $fields
     * @return list<string>
     */
    public static function normalizeFieldNames(array $fields): array
    {
        return array_values(array_unique(array_map(
            static fn (mixed $field): string => self::canonicalFieldName((string)$field),
            $fields,
        )));
    }

    /** @return list<string> */
    private static function normalizeList(mixed $value): array
    {
        if (!is_array($value)) {
            $value = $value === null ? [] : [$value];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $item): string => trim((string)$item),
            $value,
        ), static fn (string $item): bool => $item !== '')));
    }
}
