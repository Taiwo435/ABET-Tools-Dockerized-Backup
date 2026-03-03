--CREATE DATABASE IF NOT EXISTS abet_tools;
--USE abet_tools; 

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'faculty') NOT NULL DEFAULT 'faculty',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    last_login TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS user_profiles (
    profile_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    display_name VARCHAR(255),
    department VARCHAR(255),
    phone VARCHAR(50),
    office_location VARCHAR(255),
    bio VARCHAR(512),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);






--
-- Logging tables for audit and login events
--

CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    target_type VARCHAR(255) NOT NULL,
    target_id VARCHAR(255) NOT NULL,
    metadata JSON,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS login_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    email_attempted VARCHAR(255),
    result ENUM('success', 'failed_password', 'failed_mfa', 'locked') NOT NULL,
    reason VARCHAR(255),
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- -----------------------------------------------
-- REPORT GENERATION TABLES
-- -----------------------------------------------

-- ALL OUTCOMES FOR A COURSE
-- data from assignment_extraction.py, we don't need a form 
-- course_data is a JSON field that stores all the extracted data for a course, including outcomes, assignments, etc. 

CREATE TABLE IF NOT EXISTS courses (
    course_id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(50) NOT NULL,                                               -- the course code found in ASU class search e.g. 40803. NOT the end number in the canvas URL.
    course_term VARCHAR(50),
    professor_id INT NOT NULL,
    course_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (professor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- General Criteria: Students Adissions
-- Idea: store admission “rules text” once, then link it to one or many majors.

-- Table 1: programss
-- global anchor table
-- program_id keeps data separated per ABET program (so multiple programs don’t mix).

CREATE TABLE IF NOT EXISTS programs (
    program_id INT AUTO_INCREMENT PRIMARY KEY,
    program_name VARCHAR(255) NOT NULL,   -- e.g. "Computer Science"
    program_code VARCHAR(50) NOT NULL -- e.g. BS, BSE
);

-- Table 2: student_admission_requirements
-- One row = one “admissions criteria row” from the screenshot (the 4 text cells).
-- We keep the 4 criteria fields nullable because some programs/majors may not use a category (N/A).

CREATE TABLE IF NOT EXISTS student_admission_requirements (
    admission_id INT AUTO_INCREMENT PRIMARY KEY,
    freshman TEXT,
    transfer_12_23 TEXT,
    transfer_24_primary TEXT,
    transfer_24_secondary TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

);

-- Table 3: admission_major_map
-- Junction table to support “multiple majors in one cell”:
-- many majors can point to the same admission_id (shared criteria), and a major can be linked to a criteria row.

CREATE TABLE IF NOT EXISTS admission_major_map (
    admission_id INT NOT NULL,
    program_id INT NOT NULL,
    PRIMARY KEY(admission_id, program_id),
    FOREIGN KEY (admission_id) REFERENCES student_admission_requirements(admission_id) ON DELETE CASCADE,
    FOREIGN KEY (program_id)   REFERENCES programs(program_id) ON DELETE CASCADE
      
);

-- -----------------------------------------------
-- data that we retrieve from forms 
-- -----------------------------------------------

-- Example query to get faculty info for a user by email:
-- SELECT * FROM faculty_info WHERE user_id = (SELECT user_id FROM users WHERE email = 'test@asu.edu');

-- ranks: P = Professor ASC = Associate Professor AST = Assistant Professor I = Instructor A = Adjunct O = Other
-- academic appointments: T = Tenured TT = Tenure Track NTT = Non-Tenure Track
-- FT or PT
-- years of experience (gov/industry, teaching, this institution) - govt/industry could be decimal, has 0.5 value
-- professional registration (nullable, stuff like CISSP)
-- level of activity for (professional orgs, professional development, consulting/summer work in industry) - H/M/L
-- professional orgs name - e.g. "ACM, IEEE, IFIP"
-- highest degree (field and year) - e.g. "Ph.D., Computer Science, ASU, 2000"
-- unmarked table 1 (pg 79): COUNT from faculty_info grouped by program_id and faculty_rank
-- *unmarked table 2 (pg 80): JOIN faculty_info on itself basically — first_name, last_name, faculty_rank, areas_of_interest is all already there, no join even needed


CREATE TABLE IF NOT EXISTS faculty_info (
    faculty_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    program_id INT NOT NULL,
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255) NOT NULL,
    highest_degree VARCHAR(255) NOT NULL,
    asurite VARCHAR(255) NOT NULL,
    areas_of_interest TEXT,
    faculty_rank ENUM('P', 'ASC', 'AST', 'I', 'A', 'O') NOT NULL,
    academic_appointment ENUM('T', 'TT', 'NTT') NOT NULL,
    time_commitment ENUM('FT', 'PT') NOT NULL,
    years_experience_gov_industry DECIMAL(4,1) DEFAULT 0.0,
    years_experience_teaching DECIMAL(4,1) DEFAULT 0.0,
    years_experience_institution DECIMAL(4,1) DEFAULT 0.0,
    professional_registration VARCHAR(255),
    activity_prof_orgs ENUM('H','M','L','NA') NOT NULL DEFAULT 'NA',
    activity_prof_dev  ENUM('H','M','L','NA') NOT NULL DEFAULT 'NA',
    activity_consulting ENUM('H','M','L','NA') NOT NULL DEFAULT 'NA',
    professional_orgs_names TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE CASCADE
);

-- table 6-2
CREATE TABLE IF NOT EXISTS faculty_workload (
    workload_id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_id INT NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    pt_or_ft ENUM('FT', 'PT') NOT NULL,
    classes_taught TEXT,
    teaching_pct INT NOT NULL,
    research_or_scholarship_pct INT NOT NULL,
    other_pct INT NOT NULL,
    pct_time_devoted_to_program INT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (faculty_id) REFERENCES faculty_info(faculty_id) ON DELETE CASCADE,
    CONSTRAINT chk_workload_pct_range
        CHECK (
            teaching_pct <= 100 AND
            research_or_scholarship_pct <= 100 AND
            other_pct <= 100 AND
            pct_time_devoted_to_program <= 100
        ),   
    CONSTRAINT chk_workload_sum
      CHECK (teaching_pct + research_or_scholarship_pct + other_pct = 100)

);

-- One row per faculty member.
-- Foreign keys to users so vitae is tied to a specific faculty account.
-- All fields are TEXT since they are long form input fields in the form.
-- Use join (faculty_info) if we want names

CREATE TABLE IF NOT EXISTS faculty_vitae (
    vitae_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    department TEXT,
    education TEXT,
    academic_experience TEXT,
    non_academic_experience TEXT,
    certification TEXT,
    professional_memberships TEXT,
    honors_and_awards TEXT,
    service_activities TEXT,
    publications_presentations TEXT,
    professional_development TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);



-- Criterion 2: Program Education Objectives. table 1-1
-- the title changes based on which program is being worked on so program_id as foreign key

CREATE TABLE IF NOT EXISTS peo_review (
    peo_review_id INT AUTO_INCREMENT PRIMARY KEY,
    input_method TEXT,
    schedule TEXT,
    constituencies TEXT,
    program_id INT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,    
    FOREIGN KEY (program_id)   REFERENCES programs(program_id) ON DELETE CASCADE
);

-- CRITERION 4. CONTINUOUS IMPROVEMENT: Student Outcomes. table 4-1
-- Rows = outcome numbers 1-7; outcome_number
-- Columns = course codes (CSE 301, CSE 320, etc.);course_name
-- Cells = assessment method text (Essay, Assignment, Report, etc.); assessment_method
-- Empty cells = just no row stored for that outcome/course combination

CREATE TABLE IF NOT EXISTS outcome_assessment (
    assessment_id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    outcome_number INT NOT NULL,
    course_name VARCHAR(100) NOT NULL,
    assessment_method TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id)   REFERENCES programs(program_id) ON DELETE CASCADE
);

-- Unmarked table pg 40
-- Number of assessments and criteria for meeting outcome

CREATE TABLE IF NOT EXISTS outcome_attainment_criteria (
    criteria_id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    num_assessments INT NOT NULL,
    criteria_for_meeting_outcome TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE CASCADE
);

-- Table 4-2: Required level of attainment per outcome per course

CREATE TABLE IF NOT EXISTS outcome_attainment_level (
    attainment_id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    outcome_number INT NOT NULL,
    course_name VARCHAR(100) NOT NULL,
    attainment_level VARCHAR(20),           -- e.g. '70/70', empty cell = no row stored
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE CASCADE
);


-- Table 4-3: Summary of assessment results

CREATE TABLE IF NOT EXISTS assessment_summary (
    summary_id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    outcome_number INT NOT NULL,
    semester VARCHAR(10) NOT NULL,    -- e.g. 'F21', 'S22'
    result ENUM('Met', 'Not Met') NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE CASCADE
);

-- Table 4-6: Percentages of met outcomes and consecutive not met semesters

CREATE TABLE IF NOT EXISTS outcome_met_percentages (
    met_id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    outcome_number INT NOT NULL,
    semesters_assessed TEXT,
    percentage_met TEXT,
    times_consecutive_not_met TEXT,
    percentage_met_secondary TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE CASCADE
);

-- Continuous improvement tables (pg 46-52)
-- One generic table for all improvement types

CREATE TABLE IF NOT EXISTS continuous_improvement (
    improvement_id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    type ENUM('hardware', 'semester_improvement', 'peo_update', 'new_course', 'concentration_update', 'concentration_flowchart', 'adhoc') NOT NULL,
    semester_year VARCHAR(50),              -- only relevant for semester_improvement type
    source TEXT,
    problem_analysis TEXT,
    actions_plans TEXT,
    status_actions TEXT,
    result TEXT,                            -- only relevant for peo_update type
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE CASCADE
);

-- Unmarked concentration table pg 55
-- Courses required for each concentration
CREATE TABLE IF NOT EXISTS concentration_courses (
    conc_course_id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    department VARCHAR(50) NOT NULL,       -- e.g. 'CSE'
    course_number VARCHAR(20) NOT NULL,    -- e.g. '365'
    course_title TEXT,
    required_for VARCHAR(100),             -- e.g. 'CbS'
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE CASCADE
);

-- Table 5-1 / 5-1a Curriculum
CREATE TABLE IF NOT EXISTS curriculum (
    curriculum_id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    concentration VARCHAR(100),            
    semester_year VARCHAR(50),             -- e.g. 'Semester 1', 'Year 1'
    course VARCHAR(255) NOT NULL,
    course_type ENUM('R', 'E', 'SE') NOT NULL,  -- Required, Elective, Selected Elective
    credit_hours_math_science DECIMAL(4,1),
    credit_hours_engineering DECIMAL(4,1),
    credit_hours_other DECIMAL(4,1),
    last_two_terms VARCHAR(100),
    max_section_enrollment INT,            
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE CASCADE
);

-- Table 5-2: Course alignment with program educational objectives
CREATE TABLE IF NOT EXISTS curriculum_peo_alignment (
    alignment_id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    objective_number INT NOT NULL,         -- 1-4, could be auto increment too
    year_level VARCHAR(255) NOT NULL,
    courses TEXT,                          -- e.g. 'CSE220, CSE230'
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE CASCADE
);

-- Table 5-3: Course alignment with ABET student outcomes
CREATE TABLE IF NOT EXISTS curriculum_outcome_alignment (
    alignment_id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    student_outcome TEXT NOT NULL,           -- 1-7 with theor outcome description
    year_level VARCHAR(255) NOT NULL,
    courses TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE CASCADE
);

-- unmarked table pg 75
CREATE TABLE IF NOT EXISTS course_pre_co_requisite (
    pre_co_requisite_id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    pre_co_requisite TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS cv_information (
    cv_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);