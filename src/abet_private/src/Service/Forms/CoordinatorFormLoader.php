<?php 
namespace App\Service\Forms;
use Psr\Log\LoggerInterface;
use Symfony\Component\BrowserKit\Response;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\ResponseInterface;
use App\Entity\User;
use App\Service\LegacyDB;

class CoordinatorFormLoader
{
    public LegacyDB $db;

    public function __construct(
        LegacyDB $db_instance,
    ) {
        $this->db = $db_instance;
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
    if (empty($_SESSION['program_id'])) {
        return null;
    }

    $pdo = $this->db->db();
    $program_id = (int) $_SESSION['program_id'];
    $pageName = normalizePageName($pageName);

    $program = getProgramInfo($pdo, $program_id);
    $data = getReportSections($pdo, $program_id);

    foreach ($data as $key => $value) {
        $data[$key] = decodeJsonIfNeeded($value);
    }

    switch ($pageName) {
        case 'programSelect':
            $selectedProgram = $_SESSION['selected_program'] ?? getProgramSelectionKey($program);
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
                SELECT freshman, transfer_12_23, transfer_24_primary, transfer_24_secondary
                FROM student_admission_requirements
                ORDER BY admission_id DESC
            ");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                if (($data['freshman'] ?? '') === '' && !empty($row['freshman'])) {
                    $data['freshman'] = $row['freshman'];
                }
                if (($data['transfer_12_23'] ?? '') === '' && !empty($row['transfer_12_23'])) {
                    $data['transfer_12_23'] = $row['transfer_12_23'];
                }
                if (($data['transfer_24_primary'] ?? '') === '' && !empty($row['transfer_24_primary'])) {
                    $data['transfer_24_primary'] = $row['transfer_24_primary'];
                }
                if (($data['transfer_24_secondary'] ?? '') === '' && !empty($row['transfer_24_secondary'])) {
                    $data['transfer_24_secondary'] = $row['transfer_24_secondary'];
                }
            }
            break;

        case 'educationalObjectives':
            $stmt = $pdo->prepare("
                SELECT input_method, schedule, constituencies
                FROM peo_review
                WHERE program_id = :program_id
                ORDER BY peo_review_id ASC
            ");
            $stmt->execute(['program_id' => $program_id]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $data['peo_review_process'] = [];
            foreach ($rows as $row) {
                $data['peo_review_process'][] = [
                    'input_method' => $row['input_method'] ?? '',
                    'schedule' => $row['schedule'] ?? '',
                    'constituencies' => $row['constituencies'] ?? ''
                ];
            }
            break;

        case 'student_outcomes':
            $data['method_of_assessment'] = loadOutcomeAssessmentRows($pdo, $program_id);

            $stmt = $pdo->prepare("
                SELECT course_name, outcome_number, attainment_level
                FROM outcome_attainment_level
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
                    $grouped[$course]['outcome_' . $outcome] = $row['attainment_level'] ?? '';
                }
            }
            $data['level_of_attainment'] = array_values($grouped);

            $stmt = $pdo->prepare("
                SELECT num_assessments, criteria_for_meeting_outcome
                FROM outcome_attainment_criteria
                WHERE program_id = :program_id
                ORDER BY criteria_id ASC
            ");
            $stmt->execute(['program_id' => $program_id]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                if ((int)($row['num_assessments'] ?? 0) === 2) {
                    $data['2_assessment_criteria'] = $row['criteria_for_meeting_outcome'] ?? '';
                }
                if ((int)($row['num_assessments'] ?? 0) === 3) {
                    $data['3_assessment_criteria'] = $row['criteria_for_meeting_outcome'] ?? '';
                }
            }

            $data['assessment_results'] = loadAssessmentSummaryRows($pdo, $program_id);

            $stmt = $pdo->prepare("
                SELECT outcome_number, semesters_assessed, percentage_met, times_consecutive_not_met, percentage_met_secondary
                FROM outcome_met_percentages
                WHERE program_id = :program_id
                ORDER BY met_id ASC
            ");
            $stmt->execute(['program_id' => $program_id]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $data['percentages_met_table'] = [];
            foreach ($rows as $row) {
                $data['percentages_met_table'][] = [
                    'outcome' => $row['outcome_number'] ?? '',
                    'semesters_assessed' => $row['semesters_assessed'] ?? '',
                    'percentage_met' => $row['percentage_met'] ?? '',
                    'times_two_consecutive_semesters_not_met' => $row['times_consecutive_not_met'] ?? '',
                    'percentage_met_past_year' => $row['percentage_met_secondary'] ?? ''
                ];
            }
            break;

        case 'continuous_improvement':
            $stmt = $pdo->prepare("
                SELECT type, semester_year, source, problem_analysis, actions_plans, status_actions, result
                FROM continuous_improvement
                WHERE program_id = :program_id
                ORDER BY improvement_id ASC
            ");
            $stmt->execute(['program_id' => $program_id]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $type = $row['type'] ?? '';

                if ($type === 'hardware') {
                    $data['hardware_source'] = $row['source'] ?? '';
                    $data['hardware_problem_analysis'] = $row['problem_analysis'] ?? '';
                }

                if ($type === 'semester_improvement') {
                    $semester = strtolower(trim($row['semester_year'] ?? ''));
                    $prefix = null;

                    if (strpos($semester, 'fall 2024') !== false) $prefix = 'semester_fall_2024';
                    if (strpos($semester, 'spring 2025') !== false) $prefix = 'semester_spring_2025';
                    if (strpos($semester, 'fall 2025') !== false) $prefix = 'semester_fall_2025';
                    if (strpos($semester, 'spring 2026') !== false) $prefix = 'semester_spring_2026';

                    if ($prefix) {
                        $data[$prefix . '_source'] = $row['source'] ?? '';
                        $data[$prefix . '_problem_analysis'] = $row['problem_analysis'] ?? '';
                        $data[$prefix . '_actions_plans'] = $row['actions_plans'] ?? '';
                        $data[$prefix . '_status_actions'] = $row['status_actions'] ?? '';
                    }
                }

                if ($type === 'peo_update') {
                    $data['peo_update_source'] = $row['source'] ?? '';
                    $data['peo_update_problem_analysis'] = $row['problem_analysis'] ?? '';
                    $data['peo_update_actions_plans'] = $row['actions_plans'] ?? '';
                    $data['peo_update_result'] = $row['result'] ?? '';
                }

                if ($type === 'new_course') {
                    $data['new_course_source'] = $row['source'] ?? '';
                    $data['new_course_problem_analysis'] = $row['problem_analysis'] ?? '';
                    $data['new_course_actions_plans'] = $row['actions_plans'] ?? '';
                    $data['new_course_status_actions'] = $row['status_actions'] ?? '';
                }
            }

            $data['concentration_update'] = $data['concentration_update'] ?? [];
            $data['concentration_flowchart'] = $data['concentration_flowchart'] ?? [];
            break;

        case 'curriculum':
            $stmt = $pdo->prepare("
                SELECT course, course_type, credit_hours_other, concentration
                FROM curriculum
                WHERE program_id = :program_id
                ORDER BY curriculum_id ASC
            ");
            $stmt->execute(['program_id' => $program_id]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $data['program_curriculum'] = [];
            foreach ($rows as $row) {
                $data['program_curriculum'][] = [
                    'course_number_title' => $row['course'] ?? '',
                    'semester_offered' => '',
                    'course_type' => $row['course_type'] ?? '',
                    'credit_hours_math_science' => '',
                    'credit_hours_engineering' => '',
                    'credit_hours_other' => $row['credit_hours_other'] ?? '',
                    'significant_design' => '',
                    'last_two_terms' => '',
                    'max_section_enrollment' => ''
                ];
            }

            $stmt = $pdo->prepare("
                SELECT course_number, course_title, required_for
                FROM concentration_courses
                WHERE program_id = :program_id
                ORDER BY conc_course_id ASC
            ");
            $stmt->execute(['program_id' => $program_id]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $data['technical_electives'] = [];
            foreach ($rows as $row) {
                $data['technical_electives'][] = [
                    'course_number' => $row['course_number'] ?? '',
                    'course_title' => $row['course_title'] ?? '',
                    'required_for' => $row['required_for'] ?? ''
                ];
            }

            $data['peo_course_alignment'] = $data['peo_course_alignment'] ?? [];
            $data['abet_course_alignment'] = $data['abet_course_alignment'] ?? [];
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


public function isEmptyValue($v): bool {
    if ($v === null) return true;
    if (is_string($v)) return trim($v) === "";
    if (is_array($v)) return count($v) === 0;
    return false;
}

public function decodeGridRows($v): array {
    if (is_array($v)) return $v;
    if (is_string($v) && trim($v) !== "") {
        $decoded = json_decode($v, true);
        if (is_array($decoded)) return $decoded;
    }
    return [];
}

function loadValues(string $pageName): array {
    $data = $this->loadFormData($pageName);
    return is_array($data) ? $data : [];
}

function normalizeFields(array $formJson): array {
    $fields = $formJson["fields"] ?? [];
    $out = [];
    foreach ($fields as $field) {
        $type = $field["type"] ?? "";
        if ($type === "section-break" || $type === "section-label") continue;

        $name = $field["name"] ?? null;
        if (!$name) continue;

        $out[] = [
            "name" => $name,
            "type" => $type,
            "label" => $field["label"] ?? $name,
            "required" => (bool)($field["required"] ?? false),
        ];
    }
    return $out;
}


    
}