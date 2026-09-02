<?php 
namespace App\Service\Forms;
use Psr\Log\LoggerInterface;
use Symfony\Component\BrowserKit\Response;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Entity\User;
use App\Service\LegacyDB;

class CoordinatorFormSaver
{
    public LegacyDB $db;
    public RequestStack $requestStack;

    public function __construct(
        LegacyDB $db_instance,
        RequestStack $stack,
    ) {
        $this->db = $db_instance;
        $this->requestStack = $stack;
    }

    public function handleSaveError($message, $loggedMessage = '')
    {
        $request = $this->requestStack->getCurrentRequest();
        $session = $request->getSession();

        $session->set('coordinator_form_error_flag', true);
        $session->set('coordinator_form_old', $request->request->all());
        $session->set('coordinator_form_error_message', $message);

        $url = '/coordinator-form/edit/?page=' . $request->request->get('current_page_number');

        header('Location: ' . $url);

        if ($loggedMessage) {
            error_log(
                'COORDINATOR FORM SAVE ERROR FOR USER WITH ID '
                . $session->get('user_id')
                . ': '
                . $loggedMessage
            );
        }

        die();
    }


/**
 * The JS submits grid data as a JSON string (via JSON.stringify).
 * This helper decodes it back to an array so PHP can iterate over it.
 */
function getGridRows($key) {
    $raw = $_POST[$key] ?? [];
    if (is_string($raw) && !empty($raw)) {
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
    return is_array($raw) ? $raw : [];
}

function getCurrentProgramYear() {
    return date('Y');
}

function getProgramSelectionMap() {
    return [
        'computer_science-bse' => [
            'program_name' => 'Computer Science',
            'program_code' => 'BSE'
        ],
        'computer_systems_engineering-bse' => [
            'program_name' => 'Computer Systems Engineering',
            'program_code' => 'BSE'
        ]
    ];
}

function findOrCreateProgram(\PDO $pdo, string $programName, string $programCode = '', ?string $programYear = null) {
    $programYear = $programYear ?: getCurrentProgramYear();

    $stmt = $pdo->prepare("
        SELECT program_id
        FROM programs
        WHERE program_name = :program_name
          AND program_code = :program_code
        ORDER BY CAST(COALESCE(NULLIF(program_year, ''), '0') AS UNSIGNED) DESC, program_id DESC
        LIMIT 1
    ");
    $stmt->execute([
        'program_name' => $programName,
        'program_code' => $programCode
    ]);
    $result = $stmt->fetch();

    if ($result) {
        return (int) $result['program_id'];
    }

    $stmt = $pdo->prepare("
        INSERT INTO programs (program_name, program_code, program_year)
        VALUES (:program_name, :program_code, :program_year)
    ");
    $stmt->execute([
        'program_name' => $programName,
        'program_code' => $programCode,
        'program_year' => $programYear
    ]);

    return (int) $pdo->lastInsertId();
}

///////////////////////////////////////////////
// giant function 
///////////////////////////////////////////////

public function handle_save() {

    $pdo = $this->db->db();

    $genericErrorMessage = "Something went wrong while saving the form. Please contact sdosburn@asu.edu if the problem persists.";

    switch ($_POST['page_name']) {
    case 'programSelect':
        try {
            $selectedProgram = $_POST['program'] ?? '';
            $programMap = getProgramSelectionMap();

            if (!isset($programMap[$selectedProgram])) {
                $message = "Please select a valid program before continuing.";
                handleSaveError($message, $message);
                die();
            }

            $program = $programMap[$selectedProgram];
            $program_id = findOrCreateProgram($pdo, $program['program_name'], $program['program_code']);

            $_SESSION['program_id'] = $program_id;
            $_SESSION['selected_program'] = $selectedProgram;

        } catch(\PDOException $e) {
            handleSaveError($genericErrorMessage, $e->getMessage());
            die();
        }
        break;

    case 'background':
        $program_id = null;
        try {
            if (!empty($_SESSION['program_id'])) {
                $program_id = (int) $_SESSION['program_id'];
            } else {
                $departmentValue = trim($_POST['department'] ?? '');
                $department = array_map('trim', explode('-', $departmentValue, 2));

                if (count($department) != 2 || $department[0] === '' || $department[1] === '') {
                    $message = "Select a program on the first page before saving Background Information.";
                    handleSaveError($message, $message);
                    die();
                }

                $program_id = findOrCreateProgram($pdo, $department[0], $department[1]);
                $_SESSION['program_id'] = $program_id;
            }

        } catch(\PDOException $e) {
            handleSaveError($genericErrorMessage, $e->getMessage());
            die();
        }

        $section_keys = [
            'contact_information',
            'program_history',
            'options',
            'program_delivery_modes',
            'program_locations',
            'public_disclosure',
            'deficiencies_weaknesses_or_concerns'
        ];

        foreach ($section_keys as $key) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO report_sections (program_id, section_key, content)
                    VALUES (:program_id, :section_key, :content)
                    ON DUPLICATE KEY UPDATE content = VALUES(content)
                ");
                $stmt->execute([
                    'program_id' => $program_id,
                    'section_key' => $key,
                    'content' => $_POST[$key] ?? ''
                ]);
            } catch(\PDOException $e) {
                handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }
        break;

    case 'generalCriteria':
        $program_id = $_SESSION['program_id'];

        // Plain text fields saved to report_sections
        $section_keys = [
            'change_of_major_admissions',
            'evaluating_student_performance',
            'dars_critical_courses_off_track_eadvisor',
            'course_prerequisites',
            'academic_status_reports',
            'academic_standing',
            'deans_list',
            'university_academic_warning',
            'fulton_probation',
            'conditions_for_fulton_probation',
            'fulton_continuing_probation',
            'fulton_ineligibility',
            'fulton_disqualification',
            'prerequisite_purging',
            'major_map_and_academic_advising_curriculum_flowchart',
            'transfer_student_admission_requirements',
            'how_credits_transfer_to_asu',
            'currency_of_course_work',
            'course_equivalency_guide_and_arizona_community_colleges',
            'faculty_advising',
            'advising_staff',
            'advising_frequency',
            'first_year_student_orientation',
            'advising_formats',
            'special_programs',
            'work_in_lieu_of_courses',
            'independent_study_research_and_internship_credit',
            'graduation_requirements',
            'flowchart_and_dars_audit',
            'records_of_student_work_transcripts'
        ];

        foreach ($section_keys as $key) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO report_sections (program_id, section_key, content)
                    VALUES (:program_id, :section_key, :content)
                    ON DUPLICATE KEY UPDATE content = VALUES(content)
                ");
                $stmt->execute([
                    'program_id' => $program_id,
                    'section_key' => $key,
                    'content' => $_POST[$key] ?? ''
                ]);
            } catch(\PDOException $e) {
                handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM student_admission_requirements");
            $stmt->execute();
        } catch(\PDOException $e) {
            handleSaveError($genericErrorMessage, $e->getMessage());
            die();
        }

        try {
            $freshman = $_POST['freshman'] ?? '';
            if ($freshman !== '') {
                $stmt = $pdo->prepare("
                    INSERT INTO student_admission_requirements (freshman)
                    VALUES (:freshman)
                ");
                $stmt->execute([
                    'freshman' => $freshman
                ]);
            }

            $transfer_12_23 = $_POST['transfer_12_23'] ?? '';
            if ($transfer_12_23 !== '') {
                $stmt = $pdo->prepare("
                    INSERT INTO student_admission_requirements (transfer_12_23)
                    VALUES (:transfer_12_23)
                ");
                $stmt->execute([
                    'transfer_12_23' => $transfer_12_23
                ]);
            }

            $transfer_24_primary = $_POST['transfer_24_primary'] ?? '';
            $transfer_24_secondary = $_POST['transfer_24_secondary'] ?? '';
            if ($transfer_24_primary !== '' || $transfer_24_secondary !== '') {
                $stmt = $pdo->prepare("
                    INSERT INTO student_admission_requirements (transfer_24_primary, transfer_24_secondary)
                    VALUES (:primary, :secondary)
                ");
                $stmt->execute([
                    'primary' => $transfer_24_primary,
                    'secondary' => $transfer_24_secondary
                ]);
            }
        } catch(\PDOException $e) {
            handleSaveError($genericErrorMessage, $e->getMessage());
            die();
        }
        break;

    case 'educationalObjectives':
        $program_id = $_SESSION['program_id'];

        $section_keys = [
            'mission_statement',
            'program_educational_objectives',
            'public_availability_of_peos',
            'consistency_with_mission',
            'program_constituencies',
            'how_peos_meet_constituencies'
        ];

        foreach ($section_keys as $key) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO report_sections (program_id, section_key, content)
                    VALUES (:program_id, :section_key, :content)
                    ON DUPLICATE KEY UPDATE content = VALUES(content)
                ");
                $stmt->execute([
                    'program_id' => $program_id,
                    'section_key' => $key,
                    'content' => $_POST[$key] ?? ''
                ]);
            } catch(\PDOException $e) {
                handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $rows = getGridRows('peo_review_process');
        try {
            $stmt = $pdo->prepare("DELETE FROM peo_review WHERE program_id = :program_id");
            $stmt->execute(['program_id' => $program_id]);
        } catch(\PDOException $e) {
            handleSaveError($genericErrorMessage, $e->getMessage());
            die();
        }

        foreach ($rows as $row) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO peo_review (program_id, input_method, schedule, constituencies)
                    VALUES (:program_id, :input_method, :schedule, :constituencies)
                ");
                $stmt->execute([
                    'program_id' => $program_id,
                    'input_method' => $row['input_method'] ?? '',
                    'schedule' => $row['schedule'] ?? '',
                    'constituencies' => $row['constituencies'] ?? ''
                ]);
            } catch(\PDOException $e) {
                handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }
        break;

    case 'student_outcomes':
        $program_id = $_SESSION['program_id'];

        $section_keys = [
            'student_outcomes_definition',
            'relationship_to_peos'
        ];

        foreach ($section_keys as $key) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO report_sections (program_id, section_key, content)
                    VALUES (:program_id, :section_key, :content)
                    ON DUPLICATE KEY UPDATE content = VALUES(content)
                ");
                $stmt->execute([
                    'program_id' => $program_id,
                    'section_key' => $key,
                    'content' => $_POST[$key] ?? ''
                ]);
            } catch(\PDOException $e) {
                handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $rows = getGridRows('courses_and_methods_of_assessment');
        try {
            $stmt = $pdo->prepare("DELETE FROM outcome_assessment WHERE program_id = :program_id");
            $stmt->execute(['program_id' => $program_id]);
        } catch(\PDOException $e) {
            handleSaveError($genericErrorMessage, $e->getMessage());
            die();
        }
        foreach ($rows as $row) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO outcome_assessment (program_id, outcome_number, course_name, assessment_method)
                    VALUES (:program_id, :outcome_number, :course_name, :assessment_method)
                ");
                $stmt->execute([
                    'program_id' => $program_id,
                    'outcome_number' => $row['abet_outcome'] ?? '',
                    'course_name' => $row['class_name'] ?? '',
                    'assessment_method' => $row['assessment_method'] ?? ''
                ]);
            } catch(\PDOException $e) {
                handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $rows = getGridRows('assessment_results_summary');
        try {
            $stmt = $pdo->prepare("DELETE FROM assessment_summary WHERE program_id = :program_id");
            $stmt->execute(['program_id' => $program_id]);
        } catch(\PDOException $e) {
            handleSaveError($genericErrorMessage, $e->getMessage());
            die();
        }
        foreach ($rows as $row) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO assessment_summary (program_id, outcome_number, semester, result)
                    VALUES (:program_id, :outcome_number, :semester, :result)
                ");
                $stmt->execute([
                    'program_id' => $program_id,
                    'outcome_number' => $row['outcome'] ?? '',
                    'semester' => '',
                    'result' => $row['summary'] ?? ''
                ]);
            } catch(\PDOException $e) {
                handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $rows = getGridRows('percentages_met_table');
        try {
            $stmt = $pdo->prepare("DELETE FROM outcome_met_percentages WHERE program_id = :program_id");
            $stmt->execute(['program_id' => $program_id]);
        } catch(\PDOException $e) {
            handleSaveError($genericErrorMessage, $e->getMessage());
            die();
        }
        foreach ($rows as $row) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO outcome_met_percentages 
                    (program_id, outcome_number, semesters_assessed, percentage_met, times_consecutive_not_met, percentage_met_secondary)
                    VALUES (:program_id, :outcome_number, :semesters_assessed, :percentage_met, :times_consecutive_not_met, :percentage_met_secondary)
                ");
                $stmt->execute([
                    'program_id' => $program_id,
                    'outcome_number' => $row['outcome'] ?? '',
                    'semesters_assessed' => $row['semesters_assessed'] ?? '',
                    'percentage_met' => $row['percentage_met'] ?? '',
                    'times_consecutive_not_met' => $row['times_two_consecutive_semesters_not_met'] ?? '',
                    'percentage_met_secondary' => $row['percentage_met_past_year'] ?? ''
                ]);
            } catch(\PDOException $e) {
                handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }
        break;

    case 'continuous_improvement':
        $program_id = $_SESSION['program_id'];

        $section_keys = [
            'student_outcomes_assessment',
            'continuous_improvement_process',
            'additional_information'
        ];

        foreach ($section_keys as $key) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO report_sections (program_id, section_key, content)
                    VALUES (:program_id, :section_key, :content)
                    ON DUPLICATE KEY UPDATE content = VALUES(content)
                ");
                $stmt->execute([
                    'program_id' => $program_id,
                    'section_key' => $key,
                    'content' => $_POST[$key] ?? ''
                ]);
            } catch(\PDOException $e) {
                handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $rows = getGridRows('hardware_sequence_consideration');
        try {
            $stmt = $pdo->prepare("DELETE FROM continuous_improvement 
                WHERE program_id = :program_id AND type = 'hardware'");
            $stmt->execute(['program_id' => $program_id]);
        } catch(\PDOException $e) {
            handleSaveError($genericErrorMessage, $e->getMessage());
            die();
        }
        foreach ($rows as $row) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO continuous_improvement 
                    (program_id, type, source, problem_analysis)
                    VALUES (:program_id, 'hardware', :source, :problem_analysis)
                ");
                $stmt->execute([
                    'program_id' => $program_id,
                    'source' => $row['sources'] ?? '',
                    'problem_analysis' => $row['problem_analysis'] ?? ''
                ]);
            } catch(\PDOException $e) {
                handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $rows = getGridRows('semester_year_assessment_improvements');
        try {
            $stmt = $pdo->prepare("DELETE FROM continuous_improvement 
                WHERE program_id = :program_id AND type = 'semester_improvement'");
            $stmt->execute(['program_id' => $program_id]);
        } catch(\PDOException $e) {
            handleSaveError($genericErrorMessage, $e->getMessage());
            die();
        }
        foreach ($rows as $row) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO continuous_improvement 
                    (program_id, type, semester_year, source, problem_analysis, actions_plans, status_actions)
                    VALUES (:program_id, 'semester_improvement', :semester_year, :source, :problem_analysis, :actions_plans, :status_actions)
                ");
                $stmt->execute([
                    'program_id' => $program_id,
                    'semester_year' => $row['semester_year'] ?? '',
                    'source' => $row['source'] ?? '',
                    'problem_analysis' => $row['problem_analysis'] ?? '',
                    'actions_plans' => $row['actions_plans'] ?? '',
                    'status_actions' => $row['status_of_actions'] ?? ''
                ]);
            } catch(\PDOException $e) {
                handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $rows = getGridRows('program_educational_objectives_update');
        try {
            $stmt = $pdo->prepare("DELETE FROM continuous_improvement 
                WHERE program_id = :program_id AND type = 'peo_update'");
            $stmt->execute(['program_id' => $program_id]);
        } catch(\PDOException $e) {
            handleSaveError($genericErrorMessage, $e->getMessage());
            die();
        }
        foreach ($rows as $row) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO continuous_improvement 
                    (program_id, type, source, problem_analysis, actions_plans, result)
                    VALUES (:program_id, 'peo_update', :source, :problem_analysis, :actions_plans, :result)
                ");
                $stmt->execute([
                    'program_id' => $program_id,
                    'source' => $row['source'] ?? '',
                    'problem_analysis' => $row['problem_analysis'] ?? '',
                    'actions_plans' => $row['actions'] ?? '',
                    'result' => $row['result'] ?? ''
                ]);
            } catch(\PDOException $e) {
                handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }
        break;

    case 'curriculum':
        $program_id = $_SESSION['program_id'];

        $section_keys = [
            'program_curriculum',
            'course_syllabi'
        ];

        foreach ($section_keys as $key) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO report_sections (program_id, section_key, content)
                    VALUES (:program_id, :section_key, :content)
                    ON DUPLICATE KEY UPDATE content = VALUES(content)
                ");
                $stmt->execute([
                    'program_id' => $program_id,
                    'section_key' => $key,
                    'content' => $_POST[$key] ?? ''
                ]);
            } catch(\PDOException $e) {
                handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $rows = getGridRows('table_5_1_curriculum');
        try {
            $stmt = $pdo->prepare("DELETE FROM curriculum 
                WHERE program_id = :program_id AND concentration IS NULL");
            $stmt->execute(['program_id' => $program_id]);
        } catch(\PDOException $e) {
            handleSaveError($genericErrorMessage, $e->getMessage());
            die();
        }
        foreach ($rows as $row) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO curriculum 
                    (program_id, course, course_type, credit_hours_other)
                    VALUES (:program_id, :course, 'R', :credit_hours)
                ");
                $stmt->execute([
                    'program_id' => $program_id,
                    'course' => ($row['course'] ?? '') . ' ' . ($row['title'] ?? ''),
                    'credit_hours' => $row['credit_hours'] ?? 0
                ]);
            } catch(\PDOException $e) {
                handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $rows = getGridRows('table_5_1a_curriculum');
        try {
            $stmt = $pdo->prepare("DELETE FROM curriculum 
                WHERE program_id = :program_id AND concentration = 'Cybersecurity'");
            $stmt->execute(['program_id' => $program_id]);
        } catch(\PDOException $e) {
            handleSaveError($genericErrorMessage, $e->getMessage());
            die();
        }
        foreach ($rows as $row) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO curriculum 
                    (program_id, concentration, course, course_type, credit_hours_other)
                    VALUES (:program_id, 'Cybersecurity', :course, 'R', :credit_hours)
                ");
                $stmt->execute([
                    'program_id' => $program_id,
                    'course' => ($row['course'] ?? '') . ' ' . ($row['title'] ?? ''),
                    'credit_hours' => $row['credit_hours'] ?? 0
                ]);
            } catch(\PDOException $e) {
                handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $rows = getGridRows('cse_400_level_courses');
        try {
            $stmt = $pdo->prepare("DELETE FROM concentration_courses WHERE program_id = :program_id");
            $stmt->execute(['program_id' => $program_id]);
        } catch(\PDOException $e) {
            handleSaveError($genericErrorMessage, $e->getMessage());
            die();
        }
        foreach ($rows as $row) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO concentration_courses 
                    (program_id, department, course_number, course_title, required_for)
                    VALUES (:program_id, 'CSE', :course_number, :course_title, :required_for)
                ");
                $stmt->execute([
                    'program_id' => $program_id,
                    'course_number' => $row['course_number'] ?? '',
                    'course_title' => $row['course_title'] ?? '',
                    'required_for' => ($row['required_for_cbs'] === 'yes') ? 'CbS' : ''
                ]);
            } catch(\PDOException $e) {
                handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }
        break;

    case 'faculty':
        $program_id = $_SESSION['program_id'];

        $section_keys = [
            'faculty_qualifications_narrative',
            'faculty_workload',
            'faculty_size',
            'professional_development',
            'faculty_authority_and_responsibility'
        ];

        foreach ($section_keys as $key) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO report_sections (program_id, section_key, content)
                    VALUES (:program_id, :section_key, :content)
                    ON DUPLICATE KEY UPDATE content = VALUES(content)
                ");
                $stmt->execute([
                    'program_id' => $program_id,
                    'section_key' => $key,
                    'content' => $_POST[$key] ?? ''
                ]);
            } catch(\PDOException $e) {
                handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $rows = getGridRows('faculty_qualifications_by_program');
        try {
            $stmt = $pdo->prepare("DELETE FROM faculty_qualifications_by_program 
                WHERE program_id = :program_id");
            $stmt->execute(['program_id' => $program_id]);
        } catch(\PDOException $e) {
            handleSaveError($genericErrorMessage, $e->getMessage());
            die();
        }
        foreach ($rows as $row) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO faculty_qualifications_by_program 
                    (program_id, program_label, professors, associate_professors, assistant_professors, lecturers_pop)
                    VALUES (:program_id, :program_label, :professors, :associate_professors, :assistant_professors, :lecturers_pop)
                ");
                $stmt->execute([
                    'program_id' => $program_id,
                    'program_label' => $row['academic_program'] ?? '',
                    'professors' => $row['professors'] ?? '',
                    'associate_professors' => $row['associate_professors'] ?? '',
                    'assistant_professors' => $row['assistant_professors'] ?? '',
                    'lecturers_pop' => $row['lecturers_pop'] ?? ''
                ]);
            } catch(\PDOException $e) {
                handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $workloadRows = getGridRows('faculty_workload_summary');
        try {
            $stmt = $pdo->prepare("
                INSERT INTO report_sections (program_id, section_key, content)
                VALUES (:program_id, 'faculty_workload_summary', :content)
                ON DUPLICATE KEY UPDATE content = VALUES(content)
            ");
            $stmt->execute([
                'program_id' => $program_id,
                'content' => json_encode($workloadRows)
            ]);
        } catch(\PDOException $e) {
            handleSaveError($genericErrorMessage, $e->getMessage());
            die();
        }
        break;

    case 'facilities':
        $program_id = $_SESSION['program_id'];

        $section_keys = [
            'offices_classrooms_labs',
            'guidance',
            'maintenance_and_upgrading',
            'library_services',
            'overall_comments'
        ];

        foreach ($section_keys as $key) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO report_sections (program_id, section_key, content)
                    VALUES (:program_id, :section_key, :content)
                    ON DUPLICATE KEY UPDATE content = VALUES(content)
                ");
                $stmt->execute([
                    'program_id' => $program_id,
                    'section_key' => $key,
                    'content' => $_POST[$key] ?? ''
                ]);
            } catch(\PDOException $e) {
                handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $rows = getGridRows('computer_resources_table');
        try {
            $stmt = $pdo->prepare("DELETE FROM facility_rooms");
            $stmt->execute();
        } catch(\PDOException $e) {
            handleSaveError($genericErrorMessage, $e->getMessage());
            die();
        }
        foreach ($rows as $row) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO facility_rooms 
                    (bldg_code, room_number, capacity, use_description, zoom_level)
                    VALUES (:bldg_code, :room_number, :capacity, :use_description, :zoom_level)
                ");
                $stmt->execute([
                    'bldg_code' => $row['building_code'] ?? '',
                    'room_number' => $row['room_number'] ?? '',
                    'capacity' => $row['capacity'] ?? '',
                    'use_description' => $row['use'] ?? '',
                    'zoom_level' => $row['zoom_level'] ?? ''
                ]);
            } catch(\PDOException $e) {
                handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }
        break;

    case 'institutional_support_staffing':
        $program_id = $_SESSION['program_id'];

        $section_keys = [
            'staffing_narrative'
        ];

        foreach ($section_keys as $key) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO report_sections (program_id, section_key, content)
                    VALUES (:program_id, :section_key, :content)
                    ON DUPLICATE KEY UPDATE content = VALUES(content)
                ");
                $stmt->execute([
                    'program_id' => $program_id,
                    'section_key' => $key,
                    'content' => $_POST[$key] ?? ''
                ]);
            } catch(\PDOException $e) {
                handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $rows = getGridRows('scai_staff');
        try {
            $stmt = $pdo->prepare("DELETE FROM scai_staff");
            $stmt->execute();
        } catch(\PDOException $e) {
            handleSaveError($genericErrorMessage, $e->getMessage());
            die();
        }
        foreach ($rows as $row) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO scai_staff (function_description, manager, staff_size)
                    VALUES (:function_description, :manager, :staff_size)
                ");
                $stmt->execute([
                    'function_description' => $row['function'] ?? '',
                    'manager' => $row['manager'] ?? '',
                    'staff_size' => $row['staff_size'] ?? ''
                ]);
            } catch(\PDOException $e) {
                handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $supportRows = getGridRows('support_staffing_summary');
        try {
            $stmt = $pdo->prepare("
                INSERT INTO report_sections (program_id, section_key, content)
                VALUES (:program_id, 'support_staffing_summary', :content)
                ON DUPLICATE KEY UPDATE content = VALUES(content)
            ");
            $stmt->execute([
                'program_id' => $program_id,
                'content' => json_encode($supportRows)
            ]);
        } catch(\PDOException $e) {
            handleSaveError($genericErrorMessage, $e->getMessage());
            die();
        }
        break;

    case 'appendix_c_equipment':
        $program_id = $_SESSION['program_id'];

        $section_keys = [
            'equipment_overview'
        ];

        foreach ($section_keys as $key) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO report_sections (program_id, section_key, content)
                    VALUES (:program_id, :section_key, :content)
                    ON DUPLICATE KEY UPDATE content = VALUES(content)
                ");
                $stmt->execute([
                    'program_id' => $program_id,
                    'section_key' => $key,
                    'content' => $_POST[$key] ?? ''
                ]);
            } catch(\PDOException $e) {
                handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $rows = getGridRows('uto_lab_computers');
        try {
            $stmt = $pdo->prepare("DELETE FROM uto_lab_computers");
            $stmt->execute();
        } catch(\PDOException $e) {
            handleSaveError($genericErrorMessage, $e->getMessage());
            die();
        }
        foreach ($rows as $row) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO uto_lab_computers (pc_workstation, quantity)
                    VALUES (:pc_workstation, :quantity)
                ");
                $stmt->execute([
                    'pc_workstation' => $row['equipment_item'] ?? '',
                    'quantity' => $row['quantity'] ?? ''
                ]);
            } catch(\PDOException $e) {
                handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $rows = getGridRows('uto_lab_software');
        try {
            $stmt = $pdo->prepare("DELETE FROM uto_lab_software");
            $stmt->execute();
        } catch(\PDOException $e) {
            handleSaveError($genericErrorMessage, $e->getMessage());
            die();
        }
        foreach ($rows as $row) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO uto_lab_software (program_name, installed_windows, installed_osx, installed_citrix)
                    VALUES (:program_name, :installed_windows, :installed_osx, :installed_citrix)
                ");
                $stmt->execute([
                    'program_name' => $row['program'] ?? '',
                    'installed_windows' => !empty($row['installed_on_windows']) ? 1 : 0,
                    'installed_osx' => !empty($row['version_installed_on_osx']) ? 1 : 0,
                    'installed_citrix' => !empty($row['version_installed_on_citrix']) ? 1 : 0
                ]);
            } catch(\PDOException $e) {
                handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $rows = getGridRows('scia_instructional_lab_equipment');
        try {
            $stmt = $pdo->prepare("DELETE FROM scia_lab_computers");
            $stmt->execute();
        } catch(\PDOException $e) {
            handleSaveError($genericErrorMessage, $e->getMessage());
            die();
        }
        foreach ($rows as $row) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO scia_lab_computers (pc_workstation, quantity)
                    VALUES (:pc_workstation, :quantity)
                ");
                $stmt->execute([
                    'pc_workstation' => $row['equipment_item'] ?? '',
                    'quantity' => $row['quantity'] ?? ''
                ]);
            } catch(\PDOException $e) {
                handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $rows = getGridRows('printers');
        try {
            $stmt = $pdo->prepare("DELETE FROM scia_printers");
            $stmt->execute();
        } catch(\PDOException $e) {
            handleSaveError($genericErrorMessage, $e->getMessage());
            die();
        }
        foreach ($rows as $row) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO scia_printers (printer_description, quantity)
                    VALUES (:printer_description, :quantity)
                ");
                $stmt->execute([
                    'printer_description' => $row['printer'] ?? '',
                    'quantity' => $row['details'] ?? ''
                ]);
            } catch(\PDOException $e) {
                handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $rows = getGridRows('scia_brickyard_software_list');
        try {
            $stmt = $pdo->prepare("DELETE FROM scia_brickyard_software");
            $stmt->execute();
        } catch(\PDOException $e) {
            handleSaveError($genericErrorMessage, $e->getMessage());
            die();
        }
        foreach ($rows as $row) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO scia_brickyard_software 
                    (software_name, version_num, windows_version, linux, macos, vdi_lab)
                    VALUES (:software_name, :version_num, :windows_version, :linux, :macos, :vdi_lab)
                ");
                $stmt->execute([
                    'software_name' => $row['software_name'] ?? '',
                    'version_num' => $row['version'] ?? '',
                    'windows_version' => !empty($row['windows']) ? 1 : 0,
                    'linux' => !empty($row['linux']) ? 1 : 0,
                    'macos' => !empty($row['macos']) ? 1 : 0,
                    'vdi_lab' => !empty($row['vdi_lab']) ? 1 : 0
                ]);
            } catch(\PDOException $e) {
                handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }
        break;

    case 'institutional_summary':
        $program_id = $_SESSION['program_id'];

        $section_keys = [
            'institution',
            'type_of_control',
            'educational_unit',
            'academic_support_units',
            'non_academic_support_units',
            'credit_unit'
        ];

        foreach ($section_keys as $key) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO report_sections (program_id, section_key, content)
                    VALUES (:program_id, :section_key, :content)
                    ON DUPLICATE KEY UPDATE content = VALUES(content)
                ");
                $stmt->execute([
                    'program_id' => $program_id,
                    'section_key' => $key,
                    'content' => $_POST[$key] ?? ''
                ]);
            } catch(\PDOException $e) {
                handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $rows = getGridRows('program_enrollment_degree_data');
        try {
            $stmt = $pdo->prepare("DELETE FROM program_enrollment WHERE program_id = :program_id");
            $stmt->execute(['program_id' => $program_id]);
        } catch(\PDOException $e) {
            handleSaveError($genericErrorMessage, $e->getMessage());
            die();
        }
        foreach ($rows as $row) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO program_enrollment 
                    (program_id, academic_year, enrollment_ft, enrollment_pt,
                    enrollment_1st, enrollment_2nd, enrollment_3rd, enrollment_4th, enrollment_5th,
                    total_undergrad, total_grad, degrees_associates, degrees_bachelors, degrees_masters, degrees_doctorates)
                    VALUES 
                    (:program_id, :academic_year, :enrollment_ft, :enrollment_pt,
                    :enrollment_1st, :enrollment_2nd, :enrollment_3rd, :enrollment_4th, :enrollment_5th,
                    :total_undergrad, :total_grad, :degrees_associates, :degrees_bachelors, :degrees_masters, :degrees_doctorates)
                ");
                $stmt->execute([
                    'program_id' => $program_id,
                    'academic_year' => $row['academic_year'] ?? '',
                    'enrollment_ft' => ($row['enrollment_status'] ?? '') === 'FT' ? $row['first_year'] ?? '' : '',
                    'enrollment_pt' => ($row['enrollment_status'] ?? '') === 'PT' ? $row['first_year'] ?? '' : '',
                    'enrollment_1st' => $row['first_year'] ?? '',
                    'enrollment_2nd' => $row['second_year'] ?? '',
                    'enrollment_3rd' => $row['third_year'] ?? '',
                    'enrollment_4th' => $row['fourth_year'] ?? '',
                    'enrollment_5th' => $row['fifth_year'] ?? '',
                    'total_undergrad' => $row['total_undergrad'] ?? '',
                    'total_grad' => $row['total_grad'] ?? '',
                    'degrees_associates' => $row['associates'] ?? '',
                    'degrees_bachelors' => $row['bachelors'] ?? '',
                    'degrees_masters' => $row['masters'] ?? '',
                    'degrees_doctorates' => $row['doctorates'] ?? ''
                ]);
            } catch(\PDOException $e) {
                handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $rows = getGridRows('personnel');
        try {
            $stmt = $pdo->prepare("DELETE FROM personnel WHERE program_id = :program_id");
            $stmt->execute(['program_id' => $program_id]);
        } catch(\PDOException $e) {
            handleSaveError($genericErrorMessage, $e->getMessage());
            die();
        }
        foreach ($rows as $row) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO personnel (program_id, category, headcount_ft, headcount_pt, fte)
                    VALUES (:program_id, :category, :headcount_ft, :headcount_pt, :fte)
                ");
                $stmt->execute([
                    'program_id' => $program_id,
                    'category' => ($row['category'] ?? '') . ' ' . ($row['subcategory'] ?? ''),
                    'headcount_ft' => ($row['ft_pt'] ?? '') === 'FT' ? $row['head_count'] ?? '' : '',
                    'headcount_pt' => ($row['ft_pt'] ?? '') === 'PT' ? $row['head_count'] ?? '' : '',
                    'fte' => $row['fte'] ?? ''
                ]);
            } catch(\PDOException $e) {
                handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }
        break;

    case 'assessment_plan':
        $program_id = $_SESSION['program_id'];

        $section_keys = [
            'introduction',
            'assessment_details',
            'hardware_sequence_improvement_process'
        ];

        foreach ($section_keys as $key) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO report_sections (program_id, section_key, content)
                    VALUES (:program_id, :section_key, :content)
                    ON DUPLICATE KEY UPDATE content = VALUES(content)
                ");
                $stmt->execute([
                    'program_id' => $program_id,
                    'section_key' => $key,
                    'content' => $_POST[$key] ?? ''
                ]);
            } catch(\PDOException $e) {
                handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $rows = getGridRows('roles_and_responsibilities');
        try {
            $stmt = $pdo->prepare("DELETE FROM assessment_roles");
            $stmt->execute();
        } catch(\PDOException $e) {
            handleSaveError($genericErrorMessage, $e->getMessage());
            die();
        }
        foreach ($rows as $row) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO assessment_roles (role, responsibilities)
                    VALUES (:role, :responsibilities)
                ");
                $stmt->execute([
                    'role' => $row['role'] ?? '',
                    'responsibilities' => $row['responsibilities'] ?? ''
                ]);
            } catch(\PDOException $e) {
                handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $rows = getGridRows('constituency_input');
        try {
            $stmt = $pdo->prepare("DELETE FROM assessment_constituency");
            $stmt->execute();
        } catch(\PDOException $e) {
            handleSaveError($genericErrorMessage, $e->getMessage());
            die();
        }
        foreach ($rows as $row) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO assessment_constituency (constituency, method)
                    VALUES (:constituency, :method)
                ");
                $stmt->execute([
                    'constituency' => $row['constituency'] ?? '',
                    'method' => json_encode([
                        'method' => $row['method'] ?? '',
                        'frequency' => $row['frequency'] ?? '',
                        'use_of_input' => $row['use_of_input'] ?? ''
                    ])
                ]);
            } catch(\PDOException $e) {
                handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $processRows = getGridRows('assessment_processes');
        try {
            $stmt = $pdo->prepare("
                INSERT INTO report_sections (program_id, section_key, content)
                VALUES (:program_id, 'assessment_processes', :content)
                ON DUPLICATE KEY UPDATE content = VALUES(content)
            ");
            $stmt->execute([
                'program_id' => $program_id,
                'content' => json_encode($processRows)
            ]);
        } catch(\PDOException $e) {
            handleSaveError($genericErrorMessage, $e->getMessage());
            die();
        }
        break;

    default:
        handleSaveError($genericErrorMessage, "Page " . $_POST['page_name'] . " not recognized.");
        break;
    }
        
}

}