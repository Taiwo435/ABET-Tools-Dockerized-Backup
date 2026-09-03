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

        $url = '/tool/coordinator-form/edit/' . $request->request->get('current_page_number');

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
private function getGridRows(string $key): array {
    $request = $this->requestStack->getCurrentRequest();

    if ($request === null) {
        return [];
    }

    $post = $request->request->all();
    $raw = $post[$key] ?? [];

    if (is_string($raw) && $raw !== '') {
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
    $programYear = $programYear ?: $this->getCurrentProgramYear();

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

// This lets the new Symfony form names reach the existing saver cases without rewriting those cases yet.
private function normalizePageName(string $pageName): string
{
    return match ($pageName) {
        'studentOutcomes' => 'student_outcomes',
        'continuousImprovement' => 'continuous_improvement',
        'institutionalSupport' => 'institutional_support_staffing',
        'equipment' => 'appendix_c_equipment',
        'institutionalSummary' => 'institutional_summary',
        'assessmentPlan' => 'assessment_plan',
        default => $pageName,
    };
}


/**
 * Finds the database program_id that matches a major name from the admissions form.
 *
 * The admissions grid stores majors as human-readable names, but
 * admission_major_map links admissions records to programs using program_id.
 *
 * If multiple program records have the same name, prefer the one from the
 * current program year so the admissions data is linked to the correct version
 * of that program.
 *
 * Returns null if no matching program can be found.
 */
private function findAdmissionProgramIdByName(
    \PDO $pdo,
    string $majorName,
    int $currentProgramId
): ?int {
    $yearStmt = $pdo->prepare("
        SELECT program_year
        FROM programs
        WHERE program_id = :program_id
    ");
    $yearStmt->execute(['program_id' => $currentProgramId]);
    $programYear = (string) ($yearStmt->fetchColumn() ?: '');

    $stmt = $pdo->prepare("
        SELECT program_id
        FROM programs
        WHERE LOWER(TRIM(program_name)) = LOWER(TRIM(:program_name))
        ORDER BY
            CASE WHEN program_year = :program_year THEN 0 ELSE 1 END,
            CAST(COALESCE(NULLIF(program_year, ''), '0') AS UNSIGNED) DESC,
            program_id DESC
        LIMIT 1
    ");

    $stmt->execute([
        'program_name' => $majorName,
        'program_year' => $programYear
    ]);

    $programId = $stmt->fetchColumn();

    return $programId === false ? null : (int) $programId;
}

///////////////////////////////////////////////
// giant function 
///////////////////////////////////////////////

public function handle_save() {
    $request = $this->requestStack->getCurrentRequest();

    if ($request === null) {
        throw new \LogicException('CoordinatorFormSaver requires an active HTTP request.');
    }

    $session = $request->getSession();
    $post = $request->request->all();

    $pdo = $this->db->db();

    $genericErrorMessage = "Something went wrong while saving the form. Please contact sdosburn@asu.edu if the problem persists.";

    $pageName = $this->normalizePageName((string) ($post['page_name'] ?? ''));

    switch ($pageName) {
    case 'programSelect':
        try {
            $selectedProgram = $post['program'] ?? '';
            $programMap = $this->getProgramSelectionMap();

            if (!isset($programMap[$selectedProgram])) {
                $message = "Please select a valid program before continuing.";
                $this->handleSaveError($message, $message);
                die();
            }

            $program = $programMap[$selectedProgram];
            $program_id = $this->findOrCreateProgram($pdo, $program['program_name'], $program['program_code']);

            $session->set('program_id', $program_id);
            $session->set('selected_program', $selectedProgram);

        } catch(\PDOException $e) {
            $this->handleSaveError($genericErrorMessage, $e->getMessage());
            die();
        }
        break;

    case 'background':
        $program_id = null;
        try {
            if ($session->has('program_id')) {
                $program_id = (int) $session->get('program_id');
            } else {
                $departmentValue = trim($post['department'] ?? '');
                $department = array_map('trim', explode('-', $departmentValue, 2));

                if (count($department) != 2 || $department[0] === '' || $department[1] === '') {
                    $message = "Select a program on the first page before saving Background Information.";
                    $this->handleSaveError($message, $message);
                    die();
                }

                $program_id = $this->findOrCreateProgram($pdo, $department[0], $department[1]);
                $session->set('program_id', $program_id);
            }

        } catch(\PDOException $e) {
            $this->handleSaveError($genericErrorMessage, $e->getMessage());
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
                    'content' => $post[$key] ?? ''
                ]);
            } catch(\PDOException $e) {
                $this->handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }
        break;

    case 'generalCriteria':
        $program_id = (int) $session->get('program_id');

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
            'records_of_student_work_transcripts',
            'student_addmissions_text_above_table'
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
                    'content' => $post[$key] ?? ''
                ]);
            } catch(\PDOException $e) {
                $this->handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $rows = $this->getGridRows('student_addmissions_table');

        try {
            $pdo->beginTransaction();

            // This table represents the full shared admissions grid.
            // admission_major_map rows are removed automatically by ON DELETE CASCADE.
            $pdo->exec("DELETE FROM student_admission_requirements");

            $insertRequirement = $pdo->prepare("
                INSERT INTO student_admission_requirements
                    (freshman, transfer_12_23, transfer_24_primary, transfer_24_secondary)
                VALUES
                    (:freshman, :transfer_12_23, :transfer_24_primary, :transfer_24_secondary)
            ");

            $insertMajorMap = $pdo->prepare("
                INSERT INTO admission_major_map (admission_id, program_id)
                VALUES (:admission_id, :program_id)
            ");

            foreach ($rows as $row) {
                $major = trim((string) ($row['major'] ?? ''));
                $freshman = trim((string) ($row['freshman_admission_criteria'] ?? ''));
                $transfer12 = trim((string) ($row['transfer_admission_criteria_12_13'] ?? ''));
                $transfer24Primary = trim((string) ($row['transfer_admission_criteria_primary_24'] ?? ''));
                $transfer24Secondary = trim((string) ($row['transfer_admission_criteria_secondary_24'] ?? ''));

                // Ignore completely empty rows.
                if (
                    $major === ''
                    && $freshman === ''
                    && $transfer12 === ''
                    && $transfer24Primary === ''
                    && $transfer24Secondary === ''
                ) {
                    continue;
                }

                $insertRequirement->execute([
                    'freshman' => $freshman,
                    'transfer_12_23' => $transfer12,
                    'transfer_24_primary' => $transfer24Primary,
                    'transfer_24_secondary' => $transfer24Secondary
                ]);

                $admissionId = (int) $pdo->lastInsertId();

                // The schema supports multiple majors in one cell.
                $majorNames = array_unique(array_filter(array_map(
                    'trim',
                    explode(',', $major)
                )));

                foreach ($majorNames as $majorName) {
                    $majorProgramId = $this->findAdmissionProgramIdByName(
                        $pdo,
                        $majorName,
                        $program_id
                    );

                    if ($majorProgramId === null) {
                        throw new \InvalidArgumentException(
                            "Admissions major '{$majorName}' does not match a program in the database."
                        );
                    }

                    $insertMajorMap->execute([
                        'admission_id' => $admissionId,
                        'program_id' => $majorProgramId
                    ]);
                }
            }

            $pdo->commit();

        } catch (\InvalidArgumentException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $this->handleSaveError($e->getMessage(), $e->getMessage());

        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $this->handleSaveError($genericErrorMessage, $e->getMessage());
        }



        break;

    case 'educationalObjectives':
        $program_id = (int) $session->get('program_id');

        $section_keys = [
            'mission_statement',
            'program_education_objectives',
            'blue-box',
            'consistency_program_education_object_mission_institution',
            'program_constituencies_text_above_subheaders',
            'industry',
            'alumni',
            'students',
            'faculty',
            'process_reivew_program_education_objectives_text',
            'engagement_constituencies',
            'industry_engagement',
            'alumni_engagement',
            'studnet_engagement',
            'faculty_engagement',
            'ensuring_consistency'
        ];

        $rows = $this->getGridRows('table_1-1');

        try {
            $pdo->beginTransaction();

            $saveSection = $pdo->prepare("
                INSERT INTO report_sections (program_id, section_key, content)
                VALUES (:program_id, :section_key, :content)
                ON DUPLICATE KEY UPDATE content = VALUES(content)
            ");

            foreach ($section_keys as $key) {
                $saveSection->execute([
                    'program_id' => $program_id,
                    'section_key' => $key,
                    'content' => $post[$key] ?? ''
                ]);
            }

            $delete = $pdo->prepare("
                DELETE FROM peo_review
                WHERE program_id = :program_id
            ");
            $delete->execute([
                'program_id' => $program_id
            ]);

            $insert = $pdo->prepare("
                INSERT INTO peo_review
                    (program_id, input_method, schedule, constituencies)
                VALUES
                    (:program_id, :input_method, :schedule, :constituencies)
            ");

            foreach ($rows as $row) {
                $inputMethod = trim((string) ($row['input_method'] ?? ''));
                $schedule = trim((string) ($row['schedule'] ?? ''));
                $constituencies = trim((string) ($row['constituencies'] ?? ''));

                if (
                    $inputMethod === ''
                    && $schedule === ''
                    && $constituencies === ''
                ) {
                    continue;
                }

                $insert->execute([
                    'program_id' => $program_id,
                    'input_method' => $inputMethod,
                    'schedule' => $schedule,
                    'constituencies' => $constituencies
                ]);
            }

            $pdo->commit();

        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $this->handleSaveError(
                $genericErrorMessage,
                $e->getMessage()
            );
        }

        break;

    case 'student_outcomes':
        $program_id = (int) $session->get('program_id');

        // Current Criterion 3 text fields from studentOutcomes.json.
        // These are stored as free-text report sections.
        $section_keys = [
            'student_outcomes_text',
            'student_outcomes_one',
            'student_outcomes_two',
            'student_outcomes_three',
            'student_outcomes_four',
            'student_outcomes_five',
            'student_outcomes_six',
            'student_outcomes_seven',
            'student_outcome_to_program_education_objects_one',
            'student_outcome_to_program_education_objects_two',
            'student_outcome_to_program_education_objects_three',
            'student_outcome_to_program_education_objects_four'
        ];

        // Each grid corresponds to one Program Educational Objective.
        $topicGrids = [
            1 => 'topics_answered_by_objective_one',
            2 => 'topics_answered_by_objective_two',
            3 => 'topics_answered_by_objective_three',
            4 => 'topics_answered_by_objective_four'
        ];

        try {
            $pdo->beginTransaction();

            // Save the narrative/text fields.
            $saveSection = $pdo->prepare("
                INSERT INTO report_sections (program_id, section_key, content)
                VALUES (:program_id, :section_key, :content)
                ON DUPLICATE KEY UPDATE content = VALUES(content)
            ");

            foreach ($section_keys as $key) {
                $saveSection->execute([
                    'program_id' => $program_id,
                    'section_key' => $key,
                    'content' => $post[$key] ?? ''
                ]);
            }

            // Replace the existing Criterion 3 topic rows for this program
            // with the current contents of the four expandable grids.
            $deleteTopics = $pdo->prepare("
                DELETE FROM student_outcome_peo_topics
                WHERE program_id = :program_id
            ");

            $deleteTopics->execute([
                'program_id' => $program_id
            ]);

            $insertTopic = $pdo->prepare("
                INSERT INTO student_outcome_peo_topics
                    (program_id, objective_number, topic_number, topic)
                VALUES
                    (:program_id, :objective_number, :topic_number, :topic)
            ");

            foreach ($topicGrids as $objectiveNumber => $gridName) {
                $rows = $this->getGridRows($gridName);

                foreach ($rows as $row) {
                    $topicNumber = trim((string) ($row['topic_number'] ?? ''));
                    $topic = trim((string) ($row['topic'] ?? ''));

                    // Ignore completely empty rows.
                    if ($topicNumber === '' && $topic === '') {
                        continue;
                    }

                    $insertTopic->execute([
                        'program_id' => $program_id,
                        'objective_number' => $objectiveNumber,
                        'topic_number' => $topicNumber,
                        'topic' => $topic
                    ]);
                }
            }

            $pdo->commit();

        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $this->handleSaveError(
                $genericErrorMessage,
                $e->getMessage()
            );
        }

        break;

    case 'continuous_improvement':
        $program_id = (int) $session->get('program_id');

        // Current Criterion 4 narrative/text fields.
        $section_keys = [
            'criterion4_continuou_improvement_text',
            'frequency',
            'level_of_attainment',
            'summaries',
            'text_above_table_4-6',
            'document_maintained',
            'continuous_improvement_text',
            'continuous_improvement_process_overview',
            'problem_identification',
            'implementation',
            'evalutation',
            'closing_the_loop',
            'additional_information'
        ];

        try {
            $pdo->beginTransaction();

            /*
            * Save normal narrative fields to report_sections.
            */
            $saveSection = $pdo->prepare("
                INSERT INTO report_sections (program_id, section_key, content)
                VALUES (:program_id, :section_key, :content)
                ON DUPLICATE KEY UPDATE content = VALUES(content)
            ");

            foreach ($section_keys as $key) {
                $saveSection->execute([
                    'program_id' => $program_id,
                    'section_key' => $key,
                    'content' => $post[$key] ?? ''
                ]);
            }

            /*
            * Smaller/general-purpose grids do not have dedicated normalized
            * database tables, so preserve their complete row structure as JSON.
            */
            $jsonGrids = [
                'topics',
                'outcome_not_met',
                'data_collection',
                'constituency_improvement_tables',
                'improvements_underway'
            ];

            foreach ($jsonGrids as $gridName) {
                $rows = $this->getGridRows($gridName);

                $saveSection->execute([
                    'program_id' => $program_id,
                    'section_key' => $gridName,
                    'content' => json_encode($rows)
                ]);
            }

            /*
            * Table 4-1 — Courses and method of assessment for each outcome.
            *
            * The form displays one row per ABET outcome and one column per
            * course. The database stores one row per outcome/course combination.
            */
            $courseColumns = [
                'class_one'   => 'CSE 301',
                'class_two'   => 'CSE 320',
                'class_three' => 'CSE 325',
                'class_four'  => 'CSE 360',
                'class_five'  => 'CSE 365',
                'class_six'   => 'CSE 423',
                'class_seven' => 'CSE 424',
                'class_eight' => 'CSE 434',
                'class_nine'  => 'IEE 380'
            ];

            // Spaces in submitted field names are normalized to underscores by PHP.
            $table41Rows = $this->getGridRows('Table_4-1');

            $delete = $pdo->prepare("
                DELETE FROM outcome_assessment
                WHERE program_id = :program_id
            ");
            $delete->execute(['program_id' => $program_id]);

            $insert = $pdo->prepare("
                INSERT INTO outcome_assessment
                    (program_id, outcome_number, course_name, assessment_method)
                VALUES
                    (:program_id, :outcome_number, :course_name, :assessment_method)
            ");

            foreach ($table41Rows as $row) {
                $outcomeNumber = trim((string) ($row['abet_number'] ?? ''));

                if ($outcomeNumber === '') {
                    continue;
                }

                foreach ($courseColumns as $column => $courseName) {
                    $method = trim((string) ($row[$column] ?? ''));

                    if ($method === '') {
                        continue;
                    }

                    $insert->execute([
                        'program_id' => $program_id,
                        'outcome_number' => $outcomeNumber,
                        'course_name' => $courseName,
                        'assessment_method' => $method
                    ]);
                }
            }

            /*
            * Table 4-2 — Required level of attainment for each assessment.
            *
            * Uses the same course-column layout as Table 4-1.
            */
            $table42Rows = $this->getGridRows('Table_4-2');

            $delete = $pdo->prepare("
                DELETE FROM outcome_attainment_level
                WHERE program_id = :program_id
            ");
            $delete->execute(['program_id' => $program_id]);

            $insert = $pdo->prepare("
                INSERT INTO outcome_attainment_level
                    (program_id, outcome_number, course_name, attainment_level)
                VALUES
                    (:program_id, :outcome_number, :course_name, :attainment_level)
            ");

            foreach ($table42Rows as $row) {
                $outcomeNumber = trim((string) ($row['abet_number'] ?? ''));

                if ($outcomeNumber === '') {
                    continue;
                }

                foreach ($courseColumns as $column => $courseName) {
                    $attainmentLevel = trim((string) ($row[$column] ?? ''));

                    if ($attainmentLevel === '') {
                        continue;
                    }

                    $insert->execute([
                        'program_id' => $program_id,
                        'outcome_number' => $outcomeNumber,
                        'course_name' => $courseName,
                        'attainment_level' => $attainmentLevel
                    ]);
                }
            }

            /*
            * Table 4-3 — Summary of assessment results.
            *
            * The form has one column per semester while the database stores
            * one row per outcome/semester combination.
            */
            $semesterColumns = [
                'semester_one'   => 'F21',
                'semester_two'   => 'S22',
                'semester_three' => 'F22',
                'semester_four'  => 'S23',
                'semester_five'  => 'F23',
                'semester_six'   => 'S24',
                'semester_seven' => 'F24',
                'semester_eight' => 'S25'
            ];

            $table43Rows = $this->getGridRows('Table_4-3');

            $delete = $pdo->prepare("
                DELETE FROM assessment_summary
                WHERE program_id = :program_id
            ");
            $delete->execute(['program_id' => $program_id]);

            $insert = $pdo->prepare("
                INSERT INTO assessment_summary
                    (program_id, outcome_number, semester, result)
                VALUES
                    (:program_id, :outcome_number, :semester, :result)
            ");

            foreach ($table43Rows as $row) {
                $outcomeNumber = trim((string) ($row['abet_number'] ?? ''));

                if ($outcomeNumber === '') {
                    continue;
                }

                foreach ($semesterColumns as $column => $semester) {
                    $result = trim((string) ($row[$column] ?? ''));

                    if ($result === '') {
                        continue;
                    }

                    $insert->execute([
                        'program_id' => $program_id,
                        'outcome_number' => $outcomeNumber,
                        'semester' => $semester,
                        'result' => $result
                    ]);
                }
            }

            /*
            * Table 4-6 — Percentage of outcomes met.
            */
            $table46Rows = $this->getGridRows('table_4-6');

            $delete = $pdo->prepare("
                DELETE FROM outcome_met_percentages
                WHERE program_id = :program_id
            ");
            $delete->execute(['program_id' => $program_id]);

            $insert = $pdo->prepare("
                INSERT INTO outcome_met_percentages
                    (
                        program_id,
                        outcome_number,
                        semesters_assessed,
                        percentage_met,
                        times_consecutive_not_met,
                        percentage_met_secondary
                    )
                VALUES
                    (
                        :program_id,
                        :outcome_number,
                        :semesters_assessed,
                        :percentage_met,
                        :times_consecutive_not_met,
                        :percentage_met_secondary
                    )
            ");

            foreach ($table46Rows as $row) {
                $outcomeNumber = trim((string) ($row['outcome'] ?? ''));
                $semestersAssessed = trim((string) ($row['semesters_assessed'] ?? ''));
                $percentageMet = trim((string) ($row['precentage_met'] ?? ''));
                $timesNotMet = trim((string) ($row['time_of_2_consecutive_sem_not_met'] ?? ''));
                $percentagePastYear = trim((string) ($row['precentage_met_in_past_year'] ?? ''));

                if (
                    $outcomeNumber === ''
                    && $semestersAssessed === ''
                    && $percentageMet === ''
                    && $timesNotMet === ''
                    && $percentagePastYear === ''
                ) {
                    continue;
                }

                $insert->execute([
                    'program_id' => $program_id,
                    'outcome_number' => $outcomeNumber,
                    'semesters_assessed' => $semestersAssessed,
                    'percentage_met' => $percentageMet,
                    'times_consecutive_not_met' => $timesNotMet,
                    'percentage_met_secondary' => $percentagePastYear
                ]);
            }

            /*
            * Improvement of hardware sequence consideration.
            */
            $hardwareRows = $this->getGridRows('hardware_sequence_consideration');

            $delete = $pdo->prepare("
                DELETE FROM continuous_improvement
                WHERE program_id = :program_id
                AND type = 'hardware'
            ");
            $delete->execute(['program_id' => $program_id]);

            $insert = $pdo->prepare("
                INSERT INTO continuous_improvement
                    (program_id, type, source, problem_analysis)
                VALUES
                    (:program_id, 'hardware', :source, :problem_analysis)
            ");

            foreach ($hardwareRows as $row) {
                $source = trim((string) ($row['sources'] ?? ''));
                $problemAnalysis = trim((string) ($row['problem_analysis'] ?? ''));

                if ($source === '' && $problemAnalysis === '') {
                    continue;
                }

                $insert->execute([
                    'program_id' => $program_id,
                    'source' => $source,
                    'problem_analysis' => $problemAnalysis
                ]);
            }

            /*
            * Improvements based on semesters where an assessment outcome
            * was not met.
            */
            $assessmentOutcomeRows = $this->getGridRows('assessment_outcome');

            $delete = $pdo->prepare("
                DELETE FROM continuous_improvement
                WHERE program_id = :program_id
                AND type = 'semester_improvement'
            ");
            $delete->execute(['program_id' => $program_id]);

            $insert = $pdo->prepare("
                INSERT INTO continuous_improvement
                    (
                        program_id,
                        type,
                        semester_year,
                        source,
                        problem_analysis,
                        actions_plans,
                        status_actions
                    )
                VALUES
                    (
                        :program_id,
                        'semester_improvement',
                        :semester_year,
                        :source,
                        :problem_analysis,
                        :actions_plans,
                        :status_actions
                    )
            ");

            foreach ($assessmentOutcomeRows as $row) {
                $semester = trim((string) ($row['semester'] ?? ''));
                $source = trim((string) ($row['sources'] ?? ''));
                $problemAnalysis = trim((string) ($row['problem_analysis'] ?? ''));
                $actions = trim((string) ($row['actions'] ?? ''));
                $status = trim((string) ($row['status_of_actions'] ?? ''));

                if (
                    $semester === ''
                    && $source === ''
                    && $problemAnalysis === ''
                    && $actions === ''
                    && $status === ''
                ) {
                    continue;
                }

                $insert->execute([
                    'program_id' => $program_id,
                    'semester_year' => $semester,
                    'source' => $source,
                    'problem_analysis' => $problemAnalysis,
                    'actions_plans' => $actions,
                    'status_actions' => $status
                ]);
            }

            /*
            * Program Educational Objectives update.
            */
            $peoRows = $this->getGridRows('education_objectives_update');

            $delete = $pdo->prepare("
                DELETE FROM continuous_improvement
                WHERE program_id = :program_id
                AND type = 'peo_update'
            ");
            $delete->execute(['program_id' => $program_id]);

            $insert = $pdo->prepare("
                INSERT INTO continuous_improvement
                    (
                        program_id,
                        type,
                        source,
                        problem_analysis,
                        actions_plans,
                        result
                    )
                VALUES
                    (
                        :program_id,
                        'peo_update',
                        :source,
                        :problem_analysis,
                        :actions_plans,
                        :result
                    )
            ");

            foreach ($peoRows as $row) {
                $source = trim((string) ($row['sources'] ?? ''));
                $problemAnalysis = trim((string) ($row['problem_analysis'] ?? ''));
                $actions = trim((string) ($row['actions'] ?? ''));
                $result = trim((string) ($row['result'] ?? ''));

                if (
                    $source === ''
                    && $problemAnalysis === ''
                    && $actions === ''
                    && $result === ''
                ) {
                    continue;
                }

                $insert->execute([
                    'program_id' => $program_id,
                    'source' => $source,
                    'problem_analysis' => $problemAnalysis,
                    'actions_plans' => $actions,
                    'result' => $result
                ]);
            }

            $pdo->commit();

        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $this->handleSaveError(
                $genericErrorMessage,
                $e->getMessage()
            );
        }

        break;

    case 'curriculum':
        $program_id = (int) $session->get('program_id');

        try {
            $pdo->beginTransaction();

            /*
            * Technical Elective List
            *
            * One form row maps to one concentration_courses row.
            */
            $technicalElectiveRows = $this->getGridRows('technical_electives');

            $delete = $pdo->prepare("
                DELETE FROM concentration_courses
                WHERE program_id = :program_id
            ");
            $delete->execute([
                'program_id' => $program_id
            ]);

            $insertTechnicalElective = $pdo->prepare("
                INSERT INTO concentration_courses
                    (
                        program_id,
                        department,
                        course_number,
                        course_title,
                        required_for
                    )
                VALUES
                    (
                        :program_id,
                        :department,
                        :course_number,
                        :course_title,
                        :required_for
                    )
            ");

            foreach ($technicalElectiveRows as $row) {
                $courseNumber = trim((string) ($row['course_number'] ?? ''));
                $courseTitle = trim((string) ($row['course_title'] ?? ''));
                $requiredFor = trim((string) ($row['required_for'] ?? ''));

                if (
                    $courseNumber === ''
                    && $courseTitle === ''
                    && $requiredFor === ''
                ) {
                    continue;
                }

                $insertTechnicalElective->execute([
                    'program_id' => $program_id,
                    'department' => 'CSE',
                    'course_number' => $courseNumber,
                    'course_title' => $courseTitle,
                    'required_for' => $requiredFor
                ]);
            }


            /*
            * Full Curriculum for the Program
            *
            * The current form maps directly onto the curriculum table.
            */
            $curriculumRows = $this->getGridRows('program_curriculum');

            $delete = $pdo->prepare("
                DELETE FROM curriculum
                WHERE program_id = :program_id
            ");
            $delete->execute([
                'program_id' => $program_id
            ]);

            $insertCurriculum = $pdo->prepare("
                INSERT INTO curriculum
                    (
                        program_id,
                        concentration,
                        semester_year,
                        course,
                        course_type,
                        credit_hours_math_science,
                        credit_hours_engineering,
                        credit_hours_other,
                        significant_design,
                        last_two_terms,
                        max_section_enrollment
                    )
                VALUES
                    (
                        :program_id,
                        NULL,
                        :semester_year,
                        :course,
                        :course_type,
                        :credit_hours_math_science,
                        :credit_hours_engineering,
                        :credit_hours_other,
                        :significant_design,
                        :last_two_terms,
                        :max_section_enrollment
                    )
            ");

            foreach ($curriculumRows as $row) {
                $course = trim((string) ($row['course_number_title'] ?? ''));
                $semester = trim((string) ($row['semester_offered'] ?? ''));
                $courseType = trim((string) ($row['course_type'] ?? ''));

                $mathScience = trim((string) ($row['credit_hours_math_science'] ?? ''));
                $engineering = trim((string) ($row['credit_hours_engineering'] ?? ''));
                $other = trim((string) ($row['credit_hours_other'] ?? ''));

                $significantDesign = trim((string) ($row['significant_design'] ?? ''));
                $lastTwoTerms = trim((string) ($row['last_two_terms'] ?? ''));
                $maxEnrollment = trim((string) ($row['max_section_enrollment'] ?? ''));

                if (
                    $course === ''
                    && $semester === ''
                    && $courseType === ''
                    && $mathScience === ''
                    && $engineering === ''
                    && $other === ''
                    && $significantDesign === ''
                    && $lastTwoTerms === ''
                    && $maxEnrollment === ''
                ) {
                    continue;
                }

                $insertCurriculum->execute([
                    'program_id' => $program_id,
                    'semester_year' => $semester,
                    'course' => $course,
                    'course_type' => $courseType,
                    'credit_hours_math_science' => $mathScience === '' ? null : $mathScience,
                    'credit_hours_engineering' => $engineering === '' ? null : $engineering,
                    'credit_hours_other' => $other === '' ? null : $other,
                    'significant_design' =>
                        $significantDesign === ''
                            ? null
                            : ($significantDesign === 'yes' ? 1 : 0),
                    'last_two_terms' => $lastTwoTerms,
                    'max_section_enrollment' =>
                        $maxEnrollment === '' ? null : $maxEnrollment
                ]);
            }


            /*
            * Course Alignment with Program Educational Objectives
            *
            * The form uses one wide row per PEO.
            * The database stores one row per PEO/year-level combination.
            */
            $peoRows = $this->getGridRows('peo_course_alignment');

            $delete = $pdo->prepare("
                DELETE FROM curriculum_peo_alignment
                WHERE program_id = :program_id
            ");
            $delete->execute([
                'program_id' => $program_id
            ]);

            $insertPeoAlignment = $pdo->prepare("
                INSERT INTO curriculum_peo_alignment
                    (
                        program_id,
                        objective_number,
                        year_level,
                        courses
                    )
                VALUES
                    (
                        :program_id,
                        :objective_number,
                        :year_level,
                        :courses
                    )
            ");

            $yearColumns = [
                'freshman_courses' => 'Freshman',
                'sophomore_courses' => 'Sophomore',
                'junior_courses' => 'Junior',
                'senior_courses' => 'Senior'
            ];

            foreach ($peoRows as $row) {
                $objectiveNumber = trim((string) ($row['objective_number'] ?? ''));

                foreach ($yearColumns as $column => $yearLevel) {
                    $courses = trim((string) ($row[$column] ?? ''));

                    if ($objectiveNumber === '' || $courses === '') {
                        continue;
                    }

                    $insertPeoAlignment->execute([
                        'program_id' => $program_id,
                        'objective_number' => $objectiveNumber,
                        'year_level' => $yearLevel,
                        'courses' => $courses
                    ]);
                }
            }


            /*
            * Course Alignment with ABET Student Outcomes
            *
            * Same normalized approach as the PEO alignment table.
            */
            $outcomeRows = $this->getGridRows('abet_course_alignment');

            $delete = $pdo->prepare("
                DELETE FROM curriculum_outcome_alignment
                WHERE program_id = :program_id
            ");
            $delete->execute([
                'program_id' => $program_id
            ]);

            $insertOutcomeAlignment = $pdo->prepare("
                INSERT INTO curriculum_outcome_alignment
                    (
                        program_id,
                        student_outcome,
                        year_level,
                        courses
                    )
                VALUES
                    (
                        :program_id,
                        :student_outcome,
                        :year_level,
                        :courses
                    )
            ");

            foreach ($outcomeRows as $row) {
                $studentOutcome = trim((string) ($row['student_outcome'] ?? ''));

                foreach ($yearColumns as $column => $yearLevel) {
                    $courses = trim((string) ($row[$column] ?? ''));

                    if ($studentOutcome === '' || $courses === '') {
                        continue;
                    }

                    $insertOutcomeAlignment->execute([
                        'program_id' => $program_id,
                        'student_outcome' => $studentOutcome,
                        'year_level' => $yearLevel,
                        'courses' => $courses
                    ]);
                }
            }


            /*
            * Math Pre/Co-Requisites
            *
            * The schema now stores both the course and its prerequisite.
            */
            $prerequisiteRows = $this->getGridRows('math_pre_co_requisites');

            $delete = $pdo->prepare("
                DELETE FROM course_pre_co_requisite
                WHERE program_id = :program_id
            ");
            $delete->execute([
                'program_id' => $program_id
            ]);

            $insertPrerequisite = $pdo->prepare("
                INSERT INTO course_pre_co_requisite
                    (
                        program_id,
                        course_number,
                        pre_co_requisite
                    )
                VALUES
                    (
                        :program_id,
                        :course_number,
                        :pre_co_requisite
                    )
            ");

            foreach ($prerequisiteRows as $row) {
                $courseNumber = trim((string) ($row['course_number'] ?? ''));
                $prerequisite = trim((string) ($row['pre_co_requisite'] ?? ''));

                if ($courseNumber === '' && $prerequisite === '') {
                    continue;
                }

                $insertPrerequisite->execute([
                    'program_id' => $program_id,
                    'course_number' => $courseNumber,
                    'pre_co_requisite' => $prerequisite
                ]);
            }


            $pdo->commit();

        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $this->handleSaveError(
                $genericErrorMessage,
                $e->getMessage()
            );
        }

        break;

    case 'faculty':
        $program_id = (int) $session->get('program_id');

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
                    'content' => $post[$key] ?? ''
                ]);
            } catch(\PDOException $e) {
                $this->handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $rows = $this->getGridRows('faculty_qualifications_by_program');
        try {
            $stmt = $pdo->prepare("DELETE FROM faculty_qualifications_by_program 
                WHERE program_id = :program_id");
            $stmt->execute(['program_id' => $program_id]);
        } catch(\PDOException $e) {
            $this->handleSaveError($genericErrorMessage, $e->getMessage());
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
                $this->handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $workloadRows = $this->getGridRows('faculty_workload_summary');
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
            $this->handleSaveError($genericErrorMessage, $e->getMessage());
            die();
        }
        break;

    case 'facilities':
        $program_id = (int) $session->get('program_id');

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
                    'content' => $post[$key] ?? ''
                ]);
            } catch(\PDOException $e) {
                $this->handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $rows = $this->getGridRows('computer_resources_table');
        try {
            $stmt = $pdo->prepare("DELETE FROM facility_rooms");
            $stmt->execute();
        } catch(\PDOException $e) {
            $this->handleSaveError($genericErrorMessage, $e->getMessage());
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
                $this->handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }
        break;

    case 'institutional_support_staffing':
        $program_id = (int) $session->get('program_id');

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
                    'content' => $post[$key] ?? ''
                ]);
            } catch(\PDOException $e) {
                $this->handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $rows = $this->getGridRows('scai_staff');
        try {
            $stmt = $pdo->prepare("DELETE FROM scai_staff");
            $stmt->execute();
        } catch(\PDOException $e) {
            $this->handleSaveError($genericErrorMessage, $e->getMessage());
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
                $this->handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $supportRows = $this->getGridRows('support_staffing_summary');
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
            $this->handleSaveError($genericErrorMessage, $e->getMessage());
            die();
        }
        break;

    case 'appendix_c_equipment':
        $program_id = (int) $session->get('program_id');

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
                    'content' => $post[$key] ?? ''
                ]);
            } catch(\PDOException $e) {
                $this->handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $rows = $this->getGridRows('uto_lab_computers');
        try {
            $stmt = $pdo->prepare("DELETE FROM uto_lab_computers");
            $stmt->execute();
        } catch(\PDOException $e) {
            $this->handleSaveError($genericErrorMessage, $e->getMessage());
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
                $this->handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $rows = $this->getGridRows('uto_lab_software');
        try {
            $stmt = $pdo->prepare("DELETE FROM uto_lab_software");
            $stmt->execute();
        } catch(\PDOException $e) {
            $this->handleSaveError($genericErrorMessage, $e->getMessage());
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
                $this->handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $rows = $this->getGridRows('scia_instructional_lab_equipment');
        try {
            $stmt = $pdo->prepare("DELETE FROM scia_lab_computers");
            $stmt->execute();
        } catch(\PDOException $e) {
            $this->handleSaveError($genericErrorMessage, $e->getMessage());
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
                $this->handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $rows = $this->getGridRows('printers');
        try {
            $stmt = $pdo->prepare("DELETE FROM scia_printers");
            $stmt->execute();
        } catch(\PDOException $e) {
            $this->handleSaveError($genericErrorMessage, $e->getMessage());
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
                $this->handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $rows = $this->getGridRows('scia_brickyard_software_list');
        try {
            $stmt = $pdo->prepare("DELETE FROM scia_brickyard_software");
            $stmt->execute();
        } catch(\PDOException $e) {
            $this->handleSaveError($genericErrorMessage, $e->getMessage());
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
                $this->handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }
        break;

    case 'institutional_summary':
        $program_id = (int) $session->get('program_id');

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
                    'content' => $post[$key] ?? ''
                ]);
            } catch(\PDOException $e) {
                $this->handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $rows = $this->getGridRows('program_enrollment_degree_data');
        try {
            $stmt = $pdo->prepare("DELETE FROM program_enrollment WHERE program_id = :program_id");
            $stmt->execute(['program_id' => $program_id]);
        } catch(\PDOException $e) {
            $this->handleSaveError($genericErrorMessage, $e->getMessage());
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
                $this->handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $rows = $this->getGridRows('personnel');
        try {
            $stmt = $pdo->prepare("DELETE FROM personnel WHERE program_id = :program_id");
            $stmt->execute(['program_id' => $program_id]);
        } catch(\PDOException $e) {
            $this->handleSaveError($genericErrorMessage, $e->getMessage());
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
                $this->handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }
        break;

    case 'assessment_plan':
        $program_id = (int) $session->get('program_id');

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
                    'content' => $post[$key] ?? ''
                ]);
            } catch(\PDOException $e) {
                $this->handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $rows = $this->getGridRows('roles_and_responsibilities');
        try {
            $stmt = $pdo->prepare("DELETE FROM assessment_roles");
            $stmt->execute();
        } catch(\PDOException $e) {
            $this->handleSaveError($genericErrorMessage, $e->getMessage());
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
                $this->handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $rows = $this->getGridRows('constituency_input');
        try {
            $stmt = $pdo->prepare("DELETE FROM assessment_constituency");
            $stmt->execute();
        } catch(\PDOException $e) {
            $this->handleSaveError($genericErrorMessage, $e->getMessage());
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
                $this->handleSaveError($genericErrorMessage, $e->getMessage());
                die();
            }
        }

        $processRows = $this->getGridRows('assessment_processes');
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
            $this->handleSaveError($genericErrorMessage, $e->getMessage());
            die();
        }
        break;

    default:
        $this->handleSaveError($genericErrorMessage, "Page " . ($post['page_name'] ?? '') . " not recognized.");
        break;
    }
        
}

}