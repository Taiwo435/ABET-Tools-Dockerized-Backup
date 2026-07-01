<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

final class AdminTemplatesController extends AbstractController
{
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/templates', name: 'app_admin_templates', methods: ['GET'])]
    public function index(Connection $connection, Request $request): Response
    {
        [$templates, $loadError] = $this->loadExistingTemplates($connection);
        $storedCourses = $request->hasSession()
            ? $request->getSession()->get('class_data', [])
            : [];

        $drafts = $this->buildDraftsFromStoredCourses($storedCourses);

        return $this->render('tools/admin_panel/templates.html.twig', [
            'templates' => $templates,
            'drafts' => $drafts,
            'loadError' => $loadError,
        ]);
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: string|null}
     */
    private function loadExistingTemplates(Connection $connection): array
    {
        try {
            $rows = $connection->fetchAllAssociative(
                <<<'SQL'
                    SELECT
                        syllabus_id,
                        course_subject,
                        course_number,
                        course_name,
                        credits,
                        contact_hours,
                        course_type,
                        instructor_name,
                        updated_at
                    FROM course_syllabi
                    ORDER BY updated_at DESC
                    LIMIT 100
                SQL
            );
        } catch (Throwable) {
            return [[], 'Existing templates could not be loaded.'];
        }

        return [
            array_map(fn (array $row): array => [
                'id' => $row['syllabus_id'] ?? null,
                'courseSubject' => $row['course_subject'] ?? '',
                'courseNumber' => $row['course_number'] ?? '',
                'courseName' => $row['course_name'] ?? '',
                'credits' => $row['credits'] ?? '',
                'contactHours' => $row['contact_hours'] ?? '',
                'courseType' => $row['course_type'] ?? '',
                'instructors' => $this->decodeList($row['instructor_name'] ?? null),
                'updatedAt' => $row['updated_at'] ?? null,
            ], $rows),
            null,
        ];
    }

    /**
     * @param mixed $storedCourses
     * @return list<array<string, mixed>>
     */
    private function buildDraftsFromStoredCourses(mixed $storedCourses): array
    {
        if (!is_array($storedCourses)) {
            return [];
        }

        $drafts = [];
        foreach ($storedCourses as $course) {
            if (!is_array($course)) {
                continue;
            }

            $courseCode = (string)($course['course_code'] ?? '');
            [$subject, $number] = $this->parseCourseCode($courseCode);

            $drafts[] = [
                'courseSubject' => $subject,
                'courseNumber' => $number,
                'courseName' => (string)($course['name'] ?? ''),
                'courseCode' => $courseCode,
                'term' => (string)($course['term']['name'] ?? ''),
                'instructors' => $this->extractInstructors($course['teachers'] ?? []),
            ];
        }

        return $drafts;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseCourseCode(string $courseCode): array
    {
        if (preg_match('/^([A-Za-z]+)\s*([0-9A-Za-z-]+)/', $courseCode, $matches) === 1) {
            return [strtoupper($matches[1]), $matches[2]];
        }

        return ['', ''];
    }

    /**
     * @param mixed $teachers
     * @return list<string>
     */
    private function extractInstructors(mixed $teachers): array
    {
        if (!is_array($teachers)) {
            return [];
        }

        $instructors = [];
        foreach ($teachers as $teacher) {
            if (!is_array($teacher)) {
                continue;
            }

            $name = trim((string)($teacher['display_name'] ?? $teacher['name'] ?? ''));
            if ($name !== '') {
                $instructors[] = $name;
            }
        }

        return array_values(array_unique($instructors));
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private function decodeList(mixed $value): array
    {
        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return [$value];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $item): string => trim((string)$item),
            $decoded
        )));
    }
}
