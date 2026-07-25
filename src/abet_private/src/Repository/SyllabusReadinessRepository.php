<?php

declare(strict_types=1);

namespace App\Repository;

use App\ReadModel\SyllabusReadiness;
use App\ReadModel\SyllabusReadinessState;
use Doctrine\DBAL\Connection;
use DateTimeImmutable;

class SyllabusReadinessRepository
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Projects syllabus submissions and templates for a program into readiness rows.
     *
     * @param int|string $programId
     * @param string|null $filter
     * @return SyllabusReadiness[]
     */
    public function getReadinessRowsForProgram(int|string $programId, ?string $filter = null): array
    {
        // 1. Fetch all curriculum records for this program
        $curriculumRows = $this->connection->fetchAllAssociative(
            'SELECT curriculum_id, course FROM curriculum WHERE program_id = :program_id ORDER BY curriculum_id ASC',
            ['program_id' => $programId]
        );

        // 2. Fetch all course syllabi for this program
        $syllabusRows = $this->connection->fetchAllAssociative(
            'SELECT * FROM course_syllabi WHERE program_id = :program_id',
            ['program_id' => $programId]
        );

        // Group syllabus rows by course key (subject + number) and type (template vs draft)
        $groupedSyllabi = [];
        foreach ($syllabusRows as $row) {
            $subject = trim((string)($row['course_subject'] ?? ''));
            $number = trim((string)($row['course_number'] ?? ''));
            $key = strtolower($subject . '_' . $number);

            $isTemplate = (bool)($row['is_template'] ?? false);
            
            // Decode JSON fields if they are JSON strings
            $row['specific_goals'] = $this->decodeJson($row['specific_goals'] ?? null);
            $row['student_outcomes'] = $this->decodeJson($row['student_outcomes'] ?? null);
            $row['topics_covered'] = $this->decodeJson($row['topics_covered'] ?? null);
            $row['instructor_name'] = $this->decodeJson($row['instructor_name'] ?? null);
            $row['textbook'] = $this->decodeJson($row['textbook'] ?? null);

            // Structure to match SyllabusReadiness expected format
            if ($isTemplate) {
                $groupedSyllabi[$key]['template'] = [
                    'id' => (int)$row['syllabus_id'],
                    'is_published' => (bool)($row['is_published'] ?? false),
                    'catalog_description' => $row['catalog_description'] ?? null,
                    'course_type' => $row['course_type'] ?? null,
                    'credits' => isset($row['credits']) ? (int)$row['credits'] : null,
                    'contact_hours' => $row['contact_hours'] ?? null,
                    'specific_goals' => $row['specific_goals'],
                    'student_outcomes' => $row['student_outcomes'],
                    'topics_covered' => $row['topics_covered'],
                ];
            } else {
                $groupedSyllabi[$key]['draft'] = [
                    'id' => (int)$row['syllabus_id'],
                    'is_submitted' => (bool)($row['is_submitted'] ?? false),
                    'is_approved' => (bool)($row['is_approved'] ?? false),
                    'denial_feedback' => $row['denial_feedback'] ?? null,
                    'updated_at' => $row['updated_at'] ?? null,
                ];
            }
        }

        // 3. Project each curriculum course into a SyllabusReadiness read model
        $readinessRows = [];
        foreach ($curriculumRows as $curriculum) {
            $courseField = trim($curriculum['course'] ?? '');
            if ($courseField === '') {
                continue;
            }

            // Parse course code and title
            $parsed = $this->parseCourse($courseField);
            $subject = $parsed['subject'];
            $number = $parsed['number'];
            $title = $parsed['title'];

            $courseKey = strtolower($subject . '_' . $number);

            $courseInfo = [
                'program_id' => $programId,
                'course_id' => $curriculum['curriculum_id'],
                'course_code' => trim($subject . ' ' . $number),
                'course_title' => $title,
            ];

            $templateInfo = $groupedSyllabi[$courseKey]['template'] ?? null;
            $draftInfo = $groupedSyllabi[$courseKey]['draft'] ?? null;

            $readinessRows[] = SyllabusReadiness::fromDomainState($courseInfo, $templateInfo, $draftInfo);
        }

        if ($filter !== null && $filter !== '') {
            $readinessRows = array_values(array_filter($readinessRows, function (SyllabusReadiness $row) use ($filter) {
                if (strcasecmp($row->getState()->getCategory(), $filter) === 0) {
                    return true;
                }
                if (strcasecmp($row->getState()->value, $filter) === 0) {
                    return true;
                }
                return false;
            }));
        }

        return $readinessRows;
    }

    /**
     * Parses a curriculum course string into subject, number, and title.
     * Fallbacks are handled gracefully.
     *
     * @param string $courseStr
     * @return array{subject: string, number: string, title: string}
     */
    public function parseCourse(string $courseStr): array
    {
        // Matches e.g. "CSE 310 Data Structures" or "MAT 243 Discrete Math" or "CSE 485"
        // Also supports numbers with suffixes like "101L"
        if (preg_match('/^([A-Za-z]{2,5})\s*(\d{3}[A-Za-z]?)\s*(.*)$/', $courseStr, $matches)) {
            return [
                'subject' => $matches[1],
                'number' => $matches[2],
                'title' => trim($matches[3]),
            ];
        }

        // Fallback: If it doesn't match the standard university code format, split by space
        $parts = explode(' ', $courseStr, 2);
        if (count($parts) === 2) {
            return [
                'subject' => $parts[0],
                'number' => '',
                'title' => trim($parts[1]),
            ];
        }

        return [
            'subject' => '',
            'number' => '',
            'title' => $courseStr,
        ];
    }

    private function decodeJson(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : null;
        }
        return null;
    }

    /**
     * @return array<array{program_id: int|string, program_name: string, program_code: string, program_year: string}>
     */
    public function getAllPrograms(): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT program_id, program_name, program_code, program_year FROM programs ORDER BY program_name ASC, program_year DESC'
        );
    }
}
