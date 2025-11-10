-- Migration 005: Create Certificates and Submissions Tables
-- Purpose: Certificate generation and project submissions
-- Run: mysql -u user -p database_name < 005_create_certificates_submissions.sql

-- ============================================================================
-- CERTIFICATES TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Foreign keys
    user_id INT NOT NULL,
    course_id INT NOT NULL,

    -- Certificate details
    certificate_number VARCHAR(255) UNIQUE NOT NULL,
    issued_date DATE NOT NULL,

    -- File storage
    certificate_url VARCHAR(255) DEFAULT NULL,

    -- Verification
    verification_code VARCHAR(255) UNIQUE NOT NULL,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Foreign key constraints
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,

    -- Unique constraint: one certificate per user per course
    UNIQUE KEY unique_certificate (user_id, course_id),

    -- Indexes
    INDEX idx_user_id (user_id),
    INDEX idx_course_id (course_id),
    INDEX idx_verification_code (verification_code),
    INDEX idx_certificate_number (certificate_number)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PROJECTS TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Foreign key
    module_id INT NOT NULL,

    -- Project details
    title VARCHAR(255) NOT NULL,
    description TEXT,
    instructions TEXT,

    -- Requirements
    requirements TEXT,

    -- Grading
    max_score INT DEFAULT 100,

    -- Deadlines
    due_date DATE DEFAULT NULL,

    -- Publishing
    is_published BOOLEAN DEFAULT TRUE,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign key constraint
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,

    -- Indexes
    INDEX idx_module_id (module_id),
    INDEX idx_is_published (is_published)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PROJECT_SUBMISSIONS TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS project_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Foreign keys
    user_id INT NOT NULL,
    project_id INT NOT NULL,

    -- Submission content
    submission_text TEXT,
    submission_file_url VARCHAR(255) DEFAULT NULL,

    -- Grading
    status ENUM('submitted', 'graded', 'returned') DEFAULT 'submitted',
    score DECIMAL(5,2) DEFAULT NULL,
    feedback TEXT DEFAULT NULL,

    -- Graded by
    graded_by INT DEFAULT NULL,

    -- Timestamps
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    graded_at TIMESTAMP NULL DEFAULT NULL,

    -- Foreign key constraints
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (graded_by) REFERENCES users(id) ON DELETE SET NULL,

    -- Indexes
    INDEX idx_user_id (user_id),
    INDEX idx_project_id (project_id),
    INDEX idx_status (status)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verify tables created
SELECT 'Certificates and submissions tables created successfully' AS status;
SHOW TABLES LIKE '%certificates%';
SHOW TABLES LIKE '%projects%';
SHOW TABLES LIKE '%submissions%';
