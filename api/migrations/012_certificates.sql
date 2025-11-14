-- Migration 012: Certificate Generation System
-- Phase 6: Quiz Tracking & Grading System
-- Created: 2025-11-14

-- This migration creates the certificate system including:
-- 1. Certificate templates with customizable designs
-- 2. Issued certificates tracking
-- 3. Certificate verification system

-- Create certificate_templates table
CREATE TABLE IF NOT EXISTS certificate_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    template_type ENUM('course_completion', 'module_completion', 'quiz_achievement', 'custom') NOT NULL DEFAULT 'course_completion',
    template_data JSON NOT NULL COMMENT 'Template design, layout, colors, fonts in JSON',
    requirements JSON NOT NULL COMMENT 'Completion requirements (min score, modules, etc)',
    is_active BOOLEAN NOT NULL DEFAULT 1,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_template_type (template_type, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create certificates table
CREATE TABLE IF NOT EXISTS certificates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    certificate_number VARCHAR(50) NOT NULL UNIQUE COMMENT 'Unique verification code',
    user_id INT NOT NULL,
    template_id INT NOT NULL,
    certificate_type ENUM('course_completion', 'module_completion', 'quiz_achievement', 'custom') NOT NULL,
    course_id INT NULL COMMENT 'For course completion certificates',
    module_id INT NULL COMMENT 'For module completion certificates',
    quiz_id INT NULL COMMENT 'For quiz achievement certificates',
    title VARCHAR(255) NOT NULL COMMENT 'Certificate title',
    description TEXT NULL COMMENT 'Achievement description',
    completion_date DATE NOT NULL COMMENT 'Date of completion',
    issue_date DATE NOT NULL COMMENT 'Date certificate was issued',
    metadata JSON NULL COMMENT 'Additional data (score, grade, honors, etc)',
    pdf_path VARCHAR(255) NULL COMMENT 'Path to generated PDF certificate',
    verification_url VARCHAR(255) NULL COMMENT 'Public verification URL',
    is_revoked BOOLEAN NOT NULL DEFAULT 0,
    revoked_at TIMESTAMP NULL,
    revoked_by INT NULL,
    revocation_reason TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES certificate_templates(id) ON DELETE RESTRICT,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE SET NULL,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE SET NULL,
    FOREIGN KEY (revoked_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_certificates (user_id, issue_date DESC),
    INDEX idx_certificate_number (certificate_number),
    INDEX idx_verification (certificate_number, is_revoked)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create certificate_verification_log table for tracking verification attempts
CREATE TABLE IF NOT EXISTS certificate_verification_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    certificate_number VARCHAR(50) NOT NULL,
    verified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    verification_result ENUM('valid', 'invalid', 'revoked') NOT NULL,
    INDEX idx_verification_attempts (certificate_number, verified_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default certificate template for course completion
INSERT INTO certificate_templates (name, description, template_type, template_data, requirements, created_by)
VALUES (
    'Sci-Bono AI Fluency Course Certificate',
    'Official certificate of completion for the AI Fluency course',
    'course_completion',
    JSON_OBJECT(
        'background_color', '#FFFFFF',
        'border_color', '#4B6EFB',
        'title_color', '#333333',
        'text_color', '#666666',
        'logo_position', 'top-center',
        'signature_lines', JSON_ARRAY('Instructor', 'Program Director'),
        'seal_position', 'bottom-right',
        'layout', 'classic'
    ),
    JSON_OBJECT(
        'min_course_completion', 100,
        'min_quiz_average', 70,
        'required_modules', JSON_ARRAY(1, 2, 3, 4, 5, 6),
        'time_limit_days', NULL
    ),
    1
);

-- Insert default certificate template for module completion
INSERT INTO certificate_templates (name, description, template_type, template_data, requirements, created_by)
VALUES (
    'Module Completion Certificate',
    'Certificate awarded for completing an individual module',
    'module_completion',
    JSON_OBJECT(
        'background_color', '#F8F9FA',
        'border_color', '#6E4BFB',
        'title_color', '#333333',
        'text_color', '#666666',
        'logo_position', 'top-left',
        'signature_lines', JSON_ARRAY('Instructor'),
        'seal_position', 'bottom-right',
        'layout', 'modern'
    ),
    JSON_OBJECT(
        'min_module_completion', 100,
        'min_quiz_score', 70
    ),
    1
);

-- Migration verification
SELECT 'Migration 012 completed successfully' AS status;
