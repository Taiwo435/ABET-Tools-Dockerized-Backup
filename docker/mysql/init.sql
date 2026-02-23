CREATE DATABASE IF NOT EXISTS abet_tools;
USE abet_tools; 

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
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    FOREIGN KEY (professor_id) REFERENCES users(id) ON DELETE CASCADE
);


-- -----------------------------------------------
-- data that we retrieve from forms 
-- -----------------------------------------------

-- Example query to get faculty info for a user by email:
-- SELECT * FROM faculty_info WHERE user_id = (SELECT user_id FROM users WHERE email = 'test@asu.edu');

-- ranks: P = Professor ASC = Associate Professor AST = Assistant Professor I = Instructor A = Adjunct O = Other
-- academic appointments: T = Tenured TT = Tenure Track NTT = Non-Tenure Track
-- FT or PT
-- years of experience (gov/industry, teaching, this institution)
-- professional registration (nullable, stuff like CISSP)
-- level of activity for (professional orgs, professional development, consulting/summer work in industry) - H/M/L

CREATE TABLE IF NOT EXISTS faculty_info (
    faculty_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    first_name VARCHAR(255),
    last_name VARCHAR(255),
    rank ENUM('P', 'ASC', 'AST', 'I', 'A', 'O'),
    academic_appointment ENUM('T', 'TT', 'NTT'),
    time_commitment ENUM('FT', 'PT'),
    years_experience_gov_industry INT,
    years_experience_teaching INT,
    years_experience_institution INT,
    professional_registration VARCHAR(255),
    activity_prof_orgs ENUM('H', 'M', 'L'),
    activity_prof_dev ENUM('H', 'M', 'L'),
    activity_consulting ENUM('H', 'M', 'L'),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);


CREATE TABLE IF NOT EXISTS cv_information (
    cv_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);