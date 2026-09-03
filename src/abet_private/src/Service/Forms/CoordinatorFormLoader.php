<?php 
namespace App\Service\Forms;
use Psr\Log\LoggerInterface;
use Symfony\Component\BrowserKit\Response;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Entity\User;
use App\Service\LegacyDB;

class CoordinatorFormLoader
{
    public LegacyDB $db;
    public FormFunctions $helper;
    public RequestStack $requestStack;

    public function __construct(
        LegacyDB $db_instance,
        FormFunctions $formFunctions,
        RequestStack $requestStack,
    ) {
        $this->db = $db_instance;
        $this->helper = $formFunctions;
        $this->requestStack = $requestStack;
    }

function getReportSections(\PDO $pdo, int $program_id): array {
    $stmt = $pdo->prepare("
        SELECT section_key, content
        FROM report_sections
        WHERE program_id = :program_id
    ");
    $stmt->execute(['program_id' => $program_id]);

    $data = [];
    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        $data[$row['section_key']] = $row['content'];
    }

    return $data;
}

function getProgramInfo(\PDO $pdo, int $program_id): ?array {
    $stmt = $pdo->prepare("
        SELECT program_id, program_name, program_code, program_year
        FROM programs
        WHERE program_id = :program_id
        LIMIT 1
    ");
    $stmt->execute(['program_id' => $program_id]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);

    return $row ?: null;
}

function getProgramSelectionKey(?array $program): ?string {
    if (!$program) {
        return null;
    }

    $program_name = strtolower(str_replace(' ', '_', $program['program_name'] ?? ''));
    $program_code = strtolower($program['program_code'] ?? '');

    if ($program_name === '' && $program_code === '') {
        return null;
    }

    return $program_name . '-' . $program_code;
}

function decodeJsonIfNeeded($value) {
    if (!is_string($value) || $value === '') {
        return $value;
    }

    $decoded = json_decode($value, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
}

function normalizePageName(string $pageName): string {
    $aliases = [
        'programSelect' => 'programSelect',
        'background' => 'background',
        'generalCriteria' => 'generalCriteria',
        'educationalObjectives' => 'educationalObjectives',
        'studentOutcomes' => 'student_outcomes',
        'continuousImprovement' => 'continuous_improvement',
        'curriculum' => 'curriculum',
        'faculty' => 'faculty',
        'facilities' => 'facilities',
        'staffing' => 'institutional_support_staffing',
        'equipment' => 'appendix_c_equipment',
        'institutionalSummary' => 'institutional_summary',
        'assessmentPlan' => 'assessment_plan',

        'student_outcomes' => 'student_outcomes',
        'continuous_improvement' => 'continuous_improvement',
        'institutional_support_staffing' => 'institutional_support_staffing',
        'appendix_c_equipment' => 'appendix_c_equipment',
        'institutional_summary' => 'institutional_summary',
        'assessment_plan' => 'assessment_plan'
    ];

    return $aliases[$pageName] ?? $pageName;
}

function loadOutcomeAssessmentRows(\PDO $pdo, int $program_id): array {
    $stmt = $pdo->prepare("
        SELECT outcome_number, course_name, assessment_method
        FROM outcome_assessment
        WHERE program_id = :program_id
        ORDER BY course_name ASC, outcome_number ASC
    ");
    $stmt->execute(['program_id' => $program_id]);
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $grouped = [];
    foreach ($rows as $row) {
        $course = $row['course_name'] ?? '';
        if (!isset($grouped[$course])) {
            $grouped[$course] = [
                'course_name' => $course,
                'outcome_1' => '',
                'outcome_2' => '',
                'outcome_3' => '',
                'outcome_4' => '',
                'outcome_5' => '',
                'outcome_6' => '',
                'outcome_7' => ''
            ];
        }

        $outcome = (string)($row['outcome_number'] ?? '');
        if (in_array($outcome, ['1', '2', '3', '4', '5', '6', '7'], true)) {
            $grouped[$course]['outcome_' . $outcome] = $row['assessment_method'] ?? '';
        }
    }

    return array_values($grouped);
}

function loadAssessmentSummaryRows(\PDO $pdo, int $program_id): array {
    $stmt = $pdo->prepare("
        SELECT outcome_number, semester, result
        FROM assessment_summary
        WHERE program_id = :program_id
        ORDER BY semester ASC, outcome_number ASC
    ");
    $stmt->execute(['program_id' => $program_id]);
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $grouped = [];
    foreach ($rows as $row) {
        $semester = $row['semester'] ?? '';
        if (!isset($grouped[$semester])) {
            $grouped[$semester] = [
                'semester' => $semester,
                'outcome_1' => '',
                'outcome_2' => '',
                'outcome_3' => '',
                'outcome_4' => '',
                'outcome_5' => '',
                'outcome_6' => '',
                'outcome_7' => ''
            ];
        }

        $outcome = (string)($row['outcome_number'] ?? '');
        if (in_array($outcome, ['1', '2', '3', '4', '5', '6', '7'], true)) {
            $grouped[$semester]['outcome_' . $outcome] = $row['result'] ?? '';
        }
    }

    return array_values($grouped);
}

function loadFormData($pageName) {
    $request = $this->requestStack->getCurrentRequest();

    if ($request === null) {
        return null;
    }

    $session = $request->getSession();

    if (!$session->has('program_id')) {
        return null;
    }

    $pdo = $this->db->db();

    $program_id = (int) $session->get('program_id');
    $pageName = $this->normalizePageName($pageName);

    $program = $this->getProgramInfo($pdo, $program_id);
    $data = $this->getReportSections($pdo, $program_id);

    foreach ($data as $key => $value) {
        $data[$key] = $this->decodeJsonIfNeeded($value);
    }

    switch ($pageName) {
        case 'programSelect':
            $selectedProgram = $session->get('selected_program') ?? $this->getProgramSelectionKey($program);
            if ($selectedProgram) {
                $data['program'] = $selectedProgram;
            }
            break;

        case 'background':
            if ($program) {
                $data['department'] = ($program['program_name'] ?? '') . '-' . ($program['program_code'] ?? '');
            }
            break;

        case 'generalCriteria':
            $stmt = $pdo->query("
                SELECT
                    s.admission_id,
                    COALESCE(
                        GROUP_CONCAT(
                            DISTINCT p.program_name
                            ORDER BY p.program_name
                            SEPARATOR ', '
                        ),
                        ''
                    ) AS major,
                    s.freshman,
                    s.transfer_12_23,
                    s.transfer_24_primary,
                    s.transfer_24_secondary
                FROM student_admission_requirements s
                LEFT JOIN admission_major_map am
                    ON s.admission_id = am.admission_id
                LEFT JOIN programs p
                    ON am.program_id = p.program_id
                GROUP BY
                    s.admission_id,
                    s.freshman,
                    s.transfer_12_23,
                    s.transfer_24_primary,
                    s.transfer_24_secondary
                ORDER BY s.admission_id ASC
            ");

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $data['student_addmissions_table'] = [];

            foreach ($rows as $row) {
                $data['student_addmissions_table'][] = [
                    'major' => $row['major'] ?? '',
                    'freshman_admission_criteria' => $row['freshman'] ?? '',
                    'transfer_admission_criteria_12_13' => $row['transfer_12_23'] ?? '',
                    'transfer_admission_criteria_primary_24' => $row['transfer_24_primary'] ?? '',
                    'transfer_admission_criteria_secondary_24' => $row['transfer_24_secondary'] ?? ''
                ];
            }

            break;

        case 'educationalObjectives':
            $stmt = $pdo->prepare("
                SELECT input_method, schedule, constituencies
                FROM peo_review
                WHERE program_id = :program_id
                ORDER BY peo_review_id ASC
            ");

            $stmt->execute([
                'program_id' => $program_id
            ]);

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $data['table 1-1'] = [];

            foreach ($rows as $row) {
                $data['table 1-1'][] = [
                    'input_method' => $row['input_method'] ?? '',
                    'schedule' => $row['schedule'] ?? '',
                    'constituencies' => $row['constituencies'] ?? ''
                ];
            }

            break;

        case 'student_outcomes':
            // Criterion 3 text fields are already loaded automatically
            // from report_sections before the switch.

            $topicGrids = [
                1 => 'topics_answered_by_objective_one',
                2 => 'topics_answered_by_objective_two',
                3 => 'topics_answered_by_objective_three',
                4 => 'topics_answered_by_objective_four'
            ];

            // Initialize all four grids so the form receives an empty array
            // when an objective does not yet have any saved topics.
            foreach ($topicGrids as $gridName) {
                $data[$gridName] = [];
            }

            $stmt = $pdo->prepare("
                SELECT objective_number, topic_number, topic
                FROM student_outcome_peo_topics
                WHERE program_id = :program_id
                ORDER BY objective_number ASC, topic_id ASC
            ");

            $stmt->execute([
                'program_id' => $program_id
            ]);

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $objectiveNumber = (int) ($row['objective_number'] ?? 0);

                if (!isset($topicGrids[$objectiveNumber])) {
                    continue;
                }

                $gridName = $topicGrids[$objectiveNumber];

                $data[$gridName][] = [
                    'topic_number' => $row['topic_number'] ?? '',
                    'topic' => $row['topic'] ?? ''
                ];
            }

            break;

        case 'continuous_improvement':

            /*
            * JSON-backed grids such as topics, outcome_not_met,
            * data_collection, constituency_improvement_tables, and
            * improvements_underway are already loaded from report_sections
            * before this switch and decoded by decodeJsonIfNeeded().
            */

            /*
            * Table 4-1 — Courses and method of assessment for each outcome.
            * Convert normalized database rows back into the wide grid used
            * by the form.
            */
            $courseColumns = [
                'CSE 301' => 'class_one',
                'CSE 320' => 'class_two',
                'CSE 325' => 'class_three',
                'CSE 360' => 'class_four',
                'CSE 365' => 'class_five',
                'CSE 423' => 'class_six',
                'CSE 424' => 'class_seven',
                'CSE 434' => 'class_eight',
                'IEE 380' => 'class_nine'
            ];

            $stmt = $pdo->prepare("
                SELECT outcome_number, course_name, assessment_method
                FROM outcome_assessment
                WHERE program_id = :program_id
                ORDER BY outcome_number ASC, assessment_id ASC
            ");

            $stmt->execute([
                'program_id' => $program_id
            ]);

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $grouped = [];

            foreach ($rows as $row) {
                $outcomeNumber = (string) ($row['outcome_number'] ?? '');

                if (!isset($grouped[$outcomeNumber])) {
                    $grouped[$outcomeNumber] = [
                        'abet_number' => $outcomeNumber,
                        'class_one' => '',
                        'class_two' => '',
                        'class_three' => '',
                        'class_four' => '',
                        'class_five' => '',
                        'class_six' => '',
                        'class_seven' => '',
                        'class_eight' => '',
                        'class_nine' => ''
                    ];
                }

                $courseName = $row['course_name'] ?? '';

                if (isset($courseColumns[$courseName])) {
                    $column = $courseColumns[$courseName];
                    $grouped[$outcomeNumber][$column] = $row['assessment_method'] ?? '';
                }
            }

            $data['Table 4-1'] = array_values($grouped);


            /*
            * Table 4-2 — Required level of attainment.
            */
            $stmt = $pdo->prepare("
                SELECT outcome_number, course_name, attainment_level
                FROM outcome_attainment_level
                WHERE program_id = :program_id
                ORDER BY outcome_number ASC, attainment_id ASC
            ");

            $stmt->execute([
                'program_id' => $program_id
            ]);

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $grouped = [];

            foreach ($rows as $row) {
                $outcomeNumber = (string) ($row['outcome_number'] ?? '');

                if (!isset($grouped[$outcomeNumber])) {
                    $grouped[$outcomeNumber] = [
                        'abet_number' => $outcomeNumber,
                        'class_one' => '',
                        'class_two' => '',
                        'class_three' => '',
                        'class_four' => '',
                        'class_five' => '',
                        'class_six' => '',
                        'class_seven' => '',
                        'class_eight' => '',
                        'class_nine' => ''
                    ];
                }

                $courseName = $row['course_name'] ?? '';

                if (isset($courseColumns[$courseName])) {
                    $column = $courseColumns[$courseName];
                    $grouped[$outcomeNumber][$column] = $row['attainment_level'] ?? '';
                }
            }

            $data['Table 4-2'] = array_values($grouped);


            /*
            * Table 4-3 — Summary of assessment results.
            */
            $semesterColumns = [
                'F21' => 'semester_one',
                'S22' => 'semester_two',
                'F22' => 'semester_three',
                'S23' => 'semester_four',
                'F23' => 'semester_five',
                'S24' => 'semester_six',
                'F24' => 'semester_seven',
                'S25' => 'semester_eight'
            ];

            $stmt = $pdo->prepare("
                SELECT outcome_number, semester, result
                FROM assessment_summary
                WHERE program_id = :program_id
                ORDER BY outcome_number ASC, summary_id ASC
            ");

            $stmt->execute([
                'program_id' => $program_id
            ]);

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $grouped = [];

            foreach ($rows as $row) {
                $outcomeNumber = (string) ($row['outcome_number'] ?? '');

                if (!isset($grouped[$outcomeNumber])) {
                    $grouped[$outcomeNumber] = [
                        'abet_number' => $outcomeNumber,
                        'semester_one' => '',
                        'semester_two' => '',
                        'semester_three' => '',
                        'semester_four' => '',
                        'semester_five' => '',
                        'semester_six' => '',
                        'semester_seven' => '',
                        'semester_eight' => ''
                    ];
                }

                $semester = $row['semester'] ?? '';

                if (isset($semesterColumns[$semester])) {
                    $column = $semesterColumns[$semester];
                    $grouped[$outcomeNumber][$column] = $row['result'] ?? '';
                }
            }

            $data['Table 4-3'] = array_values($grouped);


            /*
            * Table 4-6 — Percentages of outcomes met.
            */
            $stmt = $pdo->prepare("
                SELECT
                    outcome_number,
                    semesters_assessed,
                    percentage_met,
                    times_consecutive_not_met,
                    percentage_met_secondary
                FROM outcome_met_percentages
                WHERE program_id = :program_id
                ORDER BY met_id ASC
            ");

            $stmt->execute([
                'program_id' => $program_id
            ]);

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $data['table_4-6'] = [];

            foreach ($rows as $row) {
                $data['table_4-6'][] = [
                    'outcome' => $row['outcome_number'] ?? '',
                    'semesters_assessed' => $row['semesters_assessed'] ?? '',
                    'precentage_met' => $row['percentage_met'] ?? '',
                    'time_of_2_consecutive_sem_not_met' => $row['times_consecutive_not_met'] ?? '',
                    'precentage_met_in_past_year' => $row['percentage_met_secondary'] ?? ''
                ];
            }


            /*
            * Load the structured Continuous Improvement grids.
            */
            $data['hardware_sequence_consideration'] = [];
            $data['assessment_outcome'] = [];
            $data['education_objectives_update'] = [];

            $stmt = $pdo->prepare("
                SELECT
                    type,
                    semester_year,
                    source,
                    problem_analysis,
                    actions_plans,
                    status_actions,
                    result
                FROM continuous_improvement
                WHERE program_id = :program_id
                ORDER BY improvement_id ASC
            ");

            $stmt->execute([
                'program_id' => $program_id
            ]);

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $type = $row['type'] ?? '';

                if ($type === 'hardware') {
                    $data['hardware_sequence_consideration'][] = [
                        'sources' => $row['source'] ?? '',
                        'problem_analysis' => $row['problem_analysis'] ?? ''
                    ];
                }

                if ($type === 'semester_improvement') {
                    $data['assessment_outcome'][] = [
                        'semester' => $row['semester_year'] ?? '',
                        'sources' => $row['source'] ?? '',
                        'problem_analysis' => $row['problem_analysis'] ?? '',
                        'actions' => $row['actions_plans'] ?? '',
                        'status_of_actions' => $row['status_actions'] ?? ''
                    ];
                }

                if ($type === 'peo_update') {
                    $data['education_objectives_update'][] = [
                        'sources' => $row['source'] ?? '',
                        'problem_analysis' => $row['problem_analysis'] ?? '',
                        'actions' => $row['actions_plans'] ?? '',
                        'result' => $row['result'] ?? ''
                    ];
                }
            }


            /*
            * Ensure JSON-backed grids always receive arrays even when nothing
            * has been saved yet.
            */
            foreach ([
                'topics',
                'outcome_not_met',
                'data_collection',
                'constituency_improvement_tables',
                'improvements_underway'
            ] as $gridName) {
                if (!isset($data[$gridName]) || !is_array($data[$gridName])) {
                    $data[$gridName] = [];
                }
            }

            break;

        case 'curriculum':

            /*
            * Technical Elective List
            */
            $stmt = $pdo->prepare("
                SELECT course_number, course_title, required_for
                FROM concentration_courses
                WHERE program_id = :program_id
                ORDER BY conc_course_id ASC
            ");

            $stmt->execute([
                'program_id' => $program_id
            ]);

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $data['technical_electives'] = [];

            foreach ($rows as $row) {
                $data['technical_electives'][] = [
                    'course_number' => $row['course_number'] ?? '',
                    'course_title' => $row['course_title'] ?? '',
                    'required_for' => $row['required_for'] ?? ''
                ];
            }


            /*
            * Full Curriculum for the Program
            */
            $stmt = $pdo->prepare("
                SELECT
                    semester_year,
                    course,
                    course_type,
                    credit_hours_math_science,
                    credit_hours_engineering,
                    credit_hours_other,
                    significant_design,
                    last_two_terms,
                    max_section_enrollment
                FROM curriculum
                WHERE program_id = :program_id
                ORDER BY curriculum_id ASC
            ");

            $stmt->execute([
                'program_id' => $program_id
            ]);

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $data['program_curriculum'] = [];

            foreach ($rows as $row) {
                $significantDesign = '';

                if ($row['significant_design'] !== null) {
                    $significantDesign = ((int) $row['significant_design'] === 1)
                        ? 'yes'
                        : 'no';
                }

                $data['program_curriculum'][] = [
                    'course_number_title' => $row['course'] ?? '',
                    'semester_offered' => $row['semester_year'] ?? '',
                    'course_type' => $row['course_type'] ?? '',
                    'credit_hours_math_science' => $row['credit_hours_math_science'] ?? '',
                    'credit_hours_engineering' => $row['credit_hours_engineering'] ?? '',
                    'credit_hours_other' => $row['credit_hours_other'] ?? '',
                    'significant_design' => $significantDesign,
                    'last_two_terms' => $row['last_two_terms'] ?? '',
                    'max_section_enrollment' => $row['max_section_enrollment'] ?? ''
                ];
            }


            /*
            * Course Alignment with Program Educational Objectives.
            *
            * Database rows are stored one PEO/year combination at a time,
            * so rebuild the wide form row for each PEO.
            */
            $stmt = $pdo->prepare("
                SELECT objective_number, year_level, courses
                FROM curriculum_peo_alignment
                WHERE program_id = :program_id
                ORDER BY objective_number ASC, alignment_id ASC
            ");

            $stmt->execute([
                'program_id' => $program_id
            ]);

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $peoGrouped = [];

            foreach ($rows as $row) {
                $objectiveNumber = (string) ($row['objective_number'] ?? '');

                if (!isset($peoGrouped[$objectiveNumber])) {
                    $peoGrouped[$objectiveNumber] = [
                        'objective_number' => $objectiveNumber,
                        'freshman_courses' => '',
                        'sophomore_courses' => '',
                        'junior_courses' => '',
                        'senior_courses' => ''
                    ];
                }

                $yearLevel = $row['year_level'] ?? '';
                $courses = $row['courses'] ?? '';

                if ($yearLevel === 'Freshman') {
                    $peoGrouped[$objectiveNumber]['freshman_courses'] = $courses;
                } elseif ($yearLevel === 'Sophomore') {
                    $peoGrouped[$objectiveNumber]['sophomore_courses'] = $courses;
                } elseif ($yearLevel === 'Junior') {
                    $peoGrouped[$objectiveNumber]['junior_courses'] = $courses;
                } elseif ($yearLevel === 'Senior') {
                    $peoGrouped[$objectiveNumber]['senior_courses'] = $courses;
                }
            }

            $data['peo_course_alignment'] = array_values($peoGrouped);


            /*
            * Course Alignment with ABET Student Outcomes.
            */
            $stmt = $pdo->prepare("
                SELECT student_outcome, year_level, courses
                FROM curriculum_outcome_alignment
                WHERE program_id = :program_id
                ORDER BY alignment_id ASC
            ");

            $stmt->execute([
                'program_id' => $program_id
            ]);

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $outcomeGrouped = [];

            foreach ($rows as $row) {
                $studentOutcome = (string) ($row['student_outcome'] ?? '');

                if (!isset($outcomeGrouped[$studentOutcome])) {
                    $outcomeGrouped[$studentOutcome] = [
                        'student_outcome' => $studentOutcome,
                        'freshman_courses' => '',
                        'sophomore_courses' => '',
                        'junior_courses' => '',
                        'senior_courses' => ''
                    ];
                }

                $yearLevel = $row['year_level'] ?? '';
                $courses = $row['courses'] ?? '';

                if ($yearLevel === 'Freshman') {
                    $outcomeGrouped[$studentOutcome]['freshman_courses'] = $courses;
                } elseif ($yearLevel === 'Sophomore') {
                    $outcomeGrouped[$studentOutcome]['sophomore_courses'] = $courses;
                } elseif ($yearLevel === 'Junior') {
                    $outcomeGrouped[$studentOutcome]['junior_courses'] = $courses;
                } elseif ($yearLevel === 'Senior') {
                    $outcomeGrouped[$studentOutcome]['senior_courses'] = $courses;
                }
            }

            $data['abet_course_alignment'] = array_values($outcomeGrouped);


            /*
            * Math Pre/Co-Requisites
            */
            $stmt = $pdo->prepare("
                SELECT course_number, pre_co_requisite
                FROM course_pre_co_requisite
                WHERE program_id = :program_id
                ORDER BY pre_co_requisite_id ASC
            ");

            $stmt->execute([
                'program_id' => $program_id
            ]);

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $data['math_pre_co_requisites'] = [];

            foreach ($rows as $row) {
                $data['math_pre_co_requisites'][] = [
                    'course_number' => $row['course_number'] ?? '',
                    'pre_co_requisite' => $row['pre_co_requisite'] ?? ''
                ];
            }

            break;

        case 'faculty':
            $stmt = $pdo->prepare("
                SELECT program_label, professors, associate_professors, assistant_professors, lecturers_pop
                FROM faculty_qualifications_by_program
                WHERE program_id = :program_id
                ORDER BY qual_program_id ASC
            ");
            $stmt->execute(['program_id' => $program_id]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (!empty($rows)) {
                $first = $rows[0];
                $data['num_professors'] = $first['professors'] ?? '';
                $data['num_associate_professors'] = $first['associate_professors'] ?? '';
                $data['num_assistant_professors'] = $first['assistant_professors'] ?? '';
                $data['num_lecturerers_pop'] = $first['lecturers_pop'] ?? '';
            }

            $data['faculty_list'] = $data['faculty_list'] ?? [];
            break;

        case 'facilities':
            $stmt = $pdo->query("
                SELECT bldg_code, room_number, capacity, use_description, zoom_level
                FROM facility_rooms
                ORDER BY room_id ASC
            ");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $data['computer_resources_table'] = [];
            foreach ($rows as $row) {
                $data['computer_resources_table'][] = [
                    'building_code' => $row['bldg_code'] ?? '',
                    'room_number' => $row['room_number'] ?? '',
                    'capacity' => $row['capacity'] ?? '',
                    'use' => $row['use_description'] ?? '',
                    'zoom_level' => $row['zoom_level'] ?? ''
                ];
            }
            break;

        case 'institutional_support_staffing':
            $stmt = $pdo->query("
                SELECT function_description, manager, staff_size
                FROM scai_staff
                ORDER BY staff_id ASC
            ");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $data['scai_staff'] = [];
            foreach ($rows as $row) {
                $data['scai_staff'][] = [
                    'function' => $row['function_description'] ?? '',
                    'manager' => $row['manager'] ?? '',
                    'staff_size' => $row['staff_size'] ?? ''
                ];
            }

            $data['support_staffing_summary'] = $data['support_staffing_summary'] ?? [];
            break;

        case 'appendix_c_equipment':
            $stmt = $pdo->query("
                SELECT pc_workstation, quantity
                FROM uto_lab_computers
                ORDER BY computer_id ASC
            ");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $data['uto_lab_computers'] = [];
            foreach ($rows as $row) {
                $data['uto_lab_computers'][] = [
                    'pc_workstation' => $row['pc_workstation'] ?? '',
                    'quantity' => $row['quantity'] ?? ''
                ];
            }

            $stmt = $pdo->query("
                SELECT program_name, installed_windows, installed_osx, installed_citrix
                FROM uto_lab_software
                ORDER BY software_id ASC
            ");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $data['uto_lab_software'] = [];
            foreach ($rows as $row) {
                $data['uto_lab_software'][] = [
                    'program' => $row['program_name'] ?? '',
                    'installed_windows' => !empty($row['installed_windows']) ? 1 : 0,
                    'installed_osx' => !empty($row['installed_osx']) ? 1 : 0,
                    'installed_citrix' => !empty($row['installed_citrix']) ? 1 : 0
                ];
            }

            $stmt = $pdo->query("
                SELECT pc_workstation, quantity
                FROM scia_lab_computers
                ORDER BY scia_computer_id ASC
            ");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $data['scia_instructional_lab_equipment'] = [];
            foreach ($rows as $row) {
                $data['scia_instructional_lab_equipment'][] = [
                    'pc_workstation' => $row['pc_workstation'] ?? '',
                    'quantity' => $row['quantity'] ?? ''
                ];
            }

            $stmt = $pdo->query("
                SELECT printer_description, quantity
                FROM scia_printers
                ORDER BY printer_id ASC
            ");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $data['printers'] = [];
            foreach ($rows as $row) {
                $data['printers'][] = [
                    'printer_description' => $row['printer_description'] ?? '',
                    'quantity' => $row['quantity'] ?? ''
                ];
            }

            $stmt = $pdo->query("
                SELECT software_name, version_num, windows_version, linux, macos, vdi_lab
                FROM scia_brickyard_software
                ORDER BY brickyard_software_id ASC
            ");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $data['scia_brickyard_software_list'] = [];
            foreach ($rows as $row) {
                $data['scia_brickyard_software_list'][] = [
                    'software_name' => $row['software_name'] ?? '',
                    'version_num' => $row['version_num'] ?? '',
                    'windows_version' => !empty($row['windows_version']) ? 1 : 0,
                    'linux' => !empty($row['linux']) ? 1 : 0,
                    'macos' => !empty($row['macos']) ? 1 : 0,
                    'vdi_lab' => !empty($row['vdi_lab']) ? 1 : 0
                ];
            }
            break;

        case 'institutional_summary':
            $stmt = $pdo->prepare("
                SELECT academic_year, enrollment_ft, enrollment_pt,
                       enrollment_1st, enrollment_2nd, enrollment_3rd, enrollment_4th, enrollment_5th,
                       total_undergrad, total_grad, degrees_associates, degrees_bachelors, degrees_masters, degrees_doctorates
                FROM program_enrollment
                WHERE program_id = :program_id
                ORDER BY enrollment_id ASC
            ");
            $stmt->execute(['program_id' => $program_id]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $data['program_enrollment_degree_data'] = [];
            foreach ($rows as $row) {
                $status = '';
                if ($row['enrollment_ft'] !== null && $row['enrollment_ft'] !== '') $status = 'enrollment_ft';
                if ($row['enrollment_pt'] !== null && $row['enrollment_pt'] !== '') $status = 'enrollment_pt';

                $data['program_enrollment_degree_data'][] = [
                    'academic_year' => $row['academic_year'] ?? '',
                    'enrollment_status' => $status,
                    'enrollment_1st' => $row['enrollment_1st'] ?? '',
                    'enrollment_2nd' => $row['enrollment_2nd'] ?? '',
                    'enrollment_3rd' => $row['enrollment_3rd'] ?? '',
                    'enrollment_4th' => $row['enrollment_4th'] ?? '',
                    'enrollment_5th' => $row['enrollment_5th'] ?? '',
                    'total_undergrad' => $row['total_undergrad'] ?? '',
                    'total_grad' => $row['total_grad'] ?? '',
                    'degrees_associates' => $row['degrees_associates'] ?? '',
                    'degrees_bachelors' => $row['degrees_bachelors'] ?? '',
                    'degrees_masters' => $row['degrees_masters'] ?? '',
                    'degrees_doctorates' => $row['degrees_doctorates'] ?? ''
                ];
            }

            $stmt = $pdo->prepare("
                SELECT category, headcount_ft, headcount_pt, fte
                FROM personnel
                WHERE program_id = :program_id
                ORDER BY personnel_id ASC
            ");
            $stmt->execute(['program_id' => $program_id]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $data['personnel'] = [];
            foreach ($rows as $row) {
                $data['personnel'][] = [
                    'category' => $row['category'] ?? '',
                    'headcount_ft' => $row['headcount_ft'] ?? '',
                    'headcount_pt' => $row['headcount_pt'] ?? '',
                    'fte' => $row['fte'] ?? ''
                ];
            }
            break;

        case 'assessment_plan':
            $stmt = $pdo->query("
                SELECT role, responsibilities
                FROM assessment_roles
                ORDER BY role_id ASC
            ");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $data['roles_and_responsibilities'] = [];
            foreach ($rows as $row) {
                $data['roles_and_responsibilities'][] = [
                    'role' => $row['role'] ?? '',
                    'responsibilities' => $row['responsibilities'] ?? ''
                ];
            }

            $stmt = $pdo->query("
                SELECT constituency, method
                FROM assessment_constituency
                ORDER BY constituency_id ASC
            ");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $data['constituency_input'] = [];
            foreach ($rows as $row) {
                $methodData = json_decode($row['method'] ?? '', true);
                $data['constituency_input'][] = [
                    'constituency' => $row['constituency'] ?? '',
                    'method' => is_array($methodData) ? ($methodData['method'] ?? '') : ($row['method'] ?? ''),
                    'frequency' => is_array($methodData) ? ($methodData['frequency'] ?? '') : '',
                    'use_of_input' => is_array($methodData) ? ($methodData['use_of_input'] ?? '') : ''
                ];
            }

            $data['assessment_processes'] = $data['assessment_processes'] ?? [];
            break;
    }

    return $data;
}

////////////////////////////////////////////////////
// HELPER FUNCTIONS WITHIN THE CONTROLLER PHP GUY
// NO SPACE I GUESS
////////////////////////////////////////////////////


function loadValues(string $pageName): array {
    $data = $this->loadFormData($pageName);
    return is_array($data) ? $data : [];
}


    
}