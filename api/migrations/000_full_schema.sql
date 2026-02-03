-- =====================================================
-- AI FLUENCY LMS - Complete Database Schema
-- =====================================================
-- Project: Sci-Bono AI Fluency Learning Management System
-- Version: 1.0
-- Database: ai_fluency_lms
-- Charset: utf8mb4 (full Unicode support including emojis)
-- Collation: utf8mb4_unicode_ci
--
-- Description:
--   Complete database schema for the AI Fluency LMS platform.
--   This file creates all tables, indexes, views, triggers, and
--   inserts default data required for a fresh installation.
--
-- Usage:
--   mysql -u root -p < 000_full_schema.sql
--   OR
--   mysql -u root -p ai_fluency_lms < 000_full_schema.sql
--
-- Features:
--   - User authentication and profiles
--   - Course/Module/Lesson hierarchy
--   - Quiz system with question bank
--   - Progress tracking and enrollments
--   - Certificate generation
--   - Achievement/badge system
--   - Project submissions
--   - Student notes and bookmarks
--   - Analytics views and optimizations
--   - Profile viewing and social features
--
-- Consolidated from migrations: 001-021
-- =====================================================

-- =====================================================
-- SECTION 0: Database Setup
-- =====================================================

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS ai_fluency_lms
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE ai_fluency_lms;

-- Enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- SECTION 1: Core Tables (No Foreign Keys)
-- =====================================================

-- -----------------------------------------------------
-- Table: users
-- Purpose: User authentication, profiles, and account management
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Authentication
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,

    -- Basic Profile
    name VARCHAR(255) NOT NULL,
    profile_picture_url VARCHAR(255) DEFAULT NULL,

    -- Extended Profile (Migration 020)
    bio TEXT DEFAULT NULL COMMENT 'User biography/introduction (max 5000 chars)',
    headline VARCHAR(255) DEFAULT NULL COMMENT 'Professional headline (max 255 chars)',
    location VARCHAR(255) DEFAULT NULL COMMENT 'City, Country',
    website_url VARCHAR(255) DEFAULT NULL COMMENT 'Personal website URL',
    github_url VARCHAR(255) DEFAULT NULL COMMENT 'GitHub profile URL',
    linkedin_url VARCHAR(255) DEFAULT NULL COMMENT 'LinkedIn profile URL',
    twitter_url VARCHAR(255) DEFAULT NULL COMMENT 'Twitter/X profile URL',

    -- Profile Privacy Settings
    is_public_profile BOOLEAN DEFAULT TRUE COMMENT 'Profile visibility toggle',
    show_email BOOLEAN DEFAULT FALSE COMMENT 'Email visibility in public profile',
    show_achievements BOOLEAN DEFAULT TRUE COMMENT 'Achievements visibility toggle',
    show_certificates BOOLEAN DEFAULT TRUE COMMENT 'Certificates visibility toggle',
    profile_views_count INT DEFAULT 0 COMMENT 'Total profile view count',
    last_profile_updated TIMESTAMP NULL DEFAULT NULL COMMENT 'Last profile edit timestamp',

    -- Role-based access control
    role ENUM('student', 'instructor', 'admin') DEFAULT 'student' NOT NULL,

    -- Account status
    is_active BOOLEAN DEFAULT TRUE,
    is_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(255) DEFAULT NULL,

    -- Password reset
    reset_token VARCHAR(255) DEFAULT NULL,
    reset_token_expires TIMESTAMP NULL DEFAULT NULL,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login_at TIMESTAMP NULL DEFAULT NULL,

    -- Indexes for performance
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_is_active (is_active),
    INDEX idx_is_public_profile (is_public_profile),
    INDEX idx_last_profile_updated (last_profile_updated DESC),
    INDEX idx_profile_views_count (profile_views_count DESC),
    INDEX idx_created_at (created_at),
    INDEX idx_last_login_at (last_login_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: courses
-- Purpose: Course catalog and metadata
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Course metadata
    title VARCHAR(255) NOT NULL,
    description TEXT,
    difficulty_level ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'intermediate',
    duration_hours INT DEFAULT 0,

    -- Media
    thumbnail_url VARCHAR(255) DEFAULT NULL,

    -- Publishing
    is_published BOOLEAN DEFAULT FALSE,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_is_published (is_published)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: achievement_categories
-- Purpose: Categorize achievements/badges
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS achievement_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    icon VARCHAR(50) NULL COMMENT 'Font Awesome icon class',
    color VARCHAR(7) NULL COMMENT 'Hex color code',
    display_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: schema_migrations
-- Purpose: Track applied migrations
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(50) PRIMARY KEY,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SECTION 2: Course Hierarchy Tables
-- =====================================================

-- -----------------------------------------------------
-- Table: modules
-- Purpose: Course modules (chapters/sections)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS modules (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Foreign key
    course_id INT NOT NULL,

    -- Module metadata
    title VARCHAR(255) NOT NULL,
    description TEXT,
    order_index INT NOT NULL,

    -- Media
    thumbnail_url VARCHAR(255) DEFAULT NULL,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign key constraint
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,

    -- Indexes
    INDEX idx_course_id (course_id),
    INDEX idx_order (course_id, order_index)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: lessons
-- Purpose: Individual lesson content within modules
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS lessons (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Foreign key
    module_id INT NOT NULL,

    -- Lesson metadata
    title VARCHAR(255) NOT NULL,
    subtitle VARCHAR(255) DEFAULT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,

    -- Content
    content LONGTEXT,

    -- Organization
    order_index INT NOT NULL,
    duration_minutes INT DEFAULT 15,

    -- Publishing
    is_published BOOLEAN DEFAULT TRUE,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign key constraint
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,

    -- Indexes
    INDEX idx_module_id (module_id),
    INDEX idx_slug (slug),
    INDEX idx_order (module_id, order_index),
    INDEX idx_is_published (is_published)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SECTION 3: Quiz System
-- =====================================================

-- -----------------------------------------------------
-- Table: quizzes
-- Purpose: Quiz metadata and settings
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Foreign key
    module_id INT NOT NULL,

    -- Quiz metadata
    title VARCHAR(255) NOT NULL,
    description TEXT,

    -- Quiz settings
    passing_score INT DEFAULT 70,
    time_limit_minutes INT DEFAULT NULL,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign key constraint
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,

    -- Indexes
    INDEX idx_module_id (module_id)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: quiz_questions
-- Purpose: Question bank for quizzes
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS quiz_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Foreign key
    quiz_id INT NOT NULL,

    -- Question metadata
    question_type ENUM('multiple_choice', 'true_false', 'short_answer', 'essay') NOT NULL DEFAULT 'multiple_choice',
    question_text TEXT NOT NULL,

    -- Question data (JSON format)
    -- For multiple choice: {"options": ["A", "B", "C", "D"], "correct_option": 0, "explanation": "..."}
    question_data JSON NOT NULL COMMENT 'Options, correct answer, explanation in JSON format',

    -- Legacy fields (for backward compatibility)
    options JSON NULL,
    correct_option INT NULL,
    explanation TEXT NULL,

    -- Grading
    points DECIMAL(5,2) NOT NULL DEFAULT 1.00,

    -- Order
    order_index INT NOT NULL DEFAULT 0 COMMENT 'Display order',

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign key constraint
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,

    -- Indexes
    INDEX idx_quiz_id (quiz_id),
    INDEX idx_order (quiz_id, order_index),
    INDEX idx_quiz_questions (quiz_id, order_index),

    -- Constraints (for legacy fields)
    CONSTRAINT chk_correct_option CHECK (correct_option IS NULL OR (correct_option >= 0 AND correct_option <= 10))

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SECTION 4: Enrollment and Progress Tracking
-- =====================================================

-- -----------------------------------------------------
-- Table: enrollments
-- Purpose: Track student course enrollments
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Foreign keys
    user_id INT NOT NULL,
    course_id INT NOT NULL,

    -- Enrollment status
    status ENUM('active', 'completed', 'dropped') DEFAULT 'active',

    -- Progress tracking
    progress_percentage DECIMAL(5,2) DEFAULT 0.00,

    -- Timestamps
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    last_accessed_at TIMESTAMP NULL DEFAULT NULL,

    -- Foreign key constraints
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,

    -- Unique constraint: one enrollment per user per course
    UNIQUE KEY unique_enrollment (user_id, course_id),

    -- Indexes
    INDEX idx_user_id (user_id),
    INDEX idx_course_id (course_id),
    INDEX idx_status (status),
    INDEX idx_enrolled_at (enrolled_at),
    INDEX idx_completed_at (completed_at),
    INDEX idx_last_accessed_at (last_accessed_at),
    INDEX idx_last_accessed (last_accessed_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: lesson_progress
-- Purpose: Track individual lesson completion
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS lesson_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Foreign keys
    user_id INT NOT NULL,
    lesson_id INT NOT NULL,

    -- Progress status
    status ENUM('not_started', 'in_progress', 'completed') DEFAULT 'not_started',

    -- Time tracking
    time_spent_minutes INT DEFAULT 0,

    -- Completion
    completed_at TIMESTAMP NULL DEFAULT NULL,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign key constraints
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,

    -- Unique constraint: one progress record per user per lesson
    UNIQUE KEY unique_progress (user_id, lesson_id),

    -- Indexes
    INDEX idx_user_id (user_id),
    INDEX idx_lesson_id (lesson_id),
    INDEX idx_status (status),
    INDEX idx_updated_at (updated_at),
    INDEX idx_completed_at_with_user (user_id, completed_at),
    INDEX idx_completed_at_detail (completed_at, status)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: quiz_attempts
-- Purpose: Track quiz submissions and scores
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS quiz_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Foreign keys
    user_id INT NOT NULL,
    quiz_id INT NOT NULL,

    -- Attempt tracking
    attempt_number INT NOT NULL DEFAULT 1 COMMENT 'Sequential attempt number for this user/quiz',

    -- Attempt details
    score DECIMAL(5,2) NOT NULL,
    total_questions INT NOT NULL,
    correct_answers INT NOT NULL,

    -- Answers stored as JSON
    -- Example: [{"question_id": 1, "selected_option": 2, "is_correct": true}, ...]
    answers JSON,

    -- Pass/fail
    passed BOOLEAN DEFAULT FALSE,

    -- Time tracking
    time_taken_minutes INT DEFAULT NULL,
    time_started TIMESTAMP NULL COMMENT 'When the attempt began',
    time_completed TIMESTAMP NULL COMMENT 'When the attempt was submitted',
    time_spent_seconds INT NULL COMMENT 'Total time spent in seconds',

    -- Metadata
    ip_address VARCHAR(45) NULL COMMENT 'IP address for academic integrity',
    user_agent TEXT NULL COMMENT 'Browser user agent',

    -- Instructor grading
    instructor_score DECIMAL(5,2) NULL COMMENT 'Override score set by instructor',
    instructor_feedback TEXT NULL COMMENT 'Instructor feedback on attempt',
    graded_by INT NULL COMMENT 'Instructor who graded/reviewed',
    graded_at TIMESTAMP NULL COMMENT 'When instructor grading occurred',

    -- Status
    status ENUM('in_progress', 'submitted', 'graded', 'reviewed') DEFAULT 'submitted' COMMENT 'Attempt status',

    -- Timestamps
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    submitted_at TIMESTAMP NULL DEFAULT NULL,

    -- Foreign key constraints
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    CONSTRAINT fk_graded_by FOREIGN KEY (graded_by) REFERENCES users(id) ON DELETE SET NULL,

    -- Indexes
    INDEX idx_user_id (user_id),
    INDEX idx_quiz_id (quiz_id),
    INDEX idx_passed (passed),
    INDEX idx_attempt_status (status),
    INDEX idx_graded_by (graded_by),
    INDEX idx_user_quiz_attempts (user_id, quiz_id, attempt_number),
    INDEX idx_completion_time (time_completed),
    INDEX idx_user_quiz_time (user_id, quiz_id, time_completed),
    INDEX idx_quiz_time (quiz_id, time_completed)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: quiz_attempt_answers
-- Purpose: Question-by-question tracking for quiz attempts
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS quiz_attempt_answers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    question_text TEXT NOT NULL COMMENT 'Snapshot of question at time of attempt',
    user_answer TEXT NOT NULL COMMENT 'Student answer (could be text, multiple choice index, etc)',
    correct_answer TEXT NOT NULL COMMENT 'Correct answer at time of attempt',
    is_correct BOOLEAN NOT NULL DEFAULT 0,
    points_awarded DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    points_possible DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    time_spent_seconds INT NULL COMMENT 'Time spent on this question',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE,
    INDEX idx_attempt_answers (attempt_id),
    INDEX idx_question_performance (question_id, is_correct)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SECTION 5: Projects and Submissions
-- =====================================================

-- -----------------------------------------------------
-- Table: projects
-- Purpose: Course/module projects and assignments
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Foreign keys (Migration 019)
    course_id INT NOT NULL,
    module_id INT NOT NULL,

    -- Project details
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    description TEXT,
    instructions TEXT,

    -- Requirements
    requirements TEXT,

    -- Organization
    `order` INT DEFAULT 0,

    -- Grading
    max_score INT DEFAULT 100,

    -- Deadlines
    due_date DATE DEFAULT NULL,

    -- Publishing
    is_published BOOLEAN DEFAULT TRUE,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign key constraints
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    CONSTRAINT fk_projects_course_id FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,

    -- Unique constraints
    CONSTRAINT unique_project_slug_per_course UNIQUE KEY (course_id, slug),

    -- Indexes
    INDEX idx_module_id (module_id),
    INDEX idx_is_published (is_published),
    INDEX idx_course_id (course_id),
    INDEX idx_course_order (course_id, `order`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: uploaded_files
-- Purpose: Track all uploaded files (avatars, projects, documents)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS uploaded_files (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(512) NOT NULL,
    file_type ENUM('avatar', 'project', 'document') NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT NOT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_type (user_id, file_type),
    INDEX idx_created (created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: project_submissions
-- Purpose: Student project submissions and grading
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS project_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Foreign keys
    user_id INT NOT NULL,
    project_id INT NOT NULL,

    -- Submission content
    submission_text TEXT,
    submission_file_url VARCHAR(255) DEFAULT NULL,
    uploaded_file_id INT NULL,

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
    CONSTRAINT fk_project_submissions_uploaded_file FOREIGN KEY (uploaded_file_id) REFERENCES uploaded_files(id) ON DELETE SET NULL,

    -- Indexes
    INDEX idx_user_id (user_id),
    INDEX idx_project_id (project_id),
    INDEX idx_status (status),
    INDEX idx_uploaded_file_id (uploaded_file_id),
    INDEX idx_submitted_at (submitted_at),
    INDEX idx_graded_at (graded_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SECTION 6: Certificate System
-- =====================================================

-- -----------------------------------------------------
-- Table: certificate_templates
-- Purpose: Customizable certificate designs
-- -----------------------------------------------------
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

-- -----------------------------------------------------
-- Table: certificates
-- Purpose: Issued certificates and verification
-- -----------------------------------------------------
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
    issued_date DATE NOT NULL COMMENT 'Date certificate was issued',
    metadata JSON NULL COMMENT 'Additional data (score, grade, honors, etc)',

    -- Legacy fields (backward compatibility)
    certificate_url VARCHAR(255) DEFAULT NULL,
    verification_code VARCHAR(255) NULL,

    -- New fields
    pdf_path VARCHAR(255) NULL COMMENT 'Path to generated PDF certificate',
    verification_url VARCHAR(255) NULL COMMENT 'Public verification URL',

    -- Revocation
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

    -- Unique constraint (allow multiple certs from templates, but not from course/user combo)
    UNIQUE KEY unique_certificate (user_id, course_id),

    INDEX idx_user_id (user_id),
    INDEX idx_course_id (course_id),
    INDEX idx_verification_code (verification_code),
    INDEX idx_certificate_number (certificate_number),
    INDEX idx_user_certificates (user_id, issued_date DESC),
    INDEX idx_verification (certificate_number, is_revoked),
    INDEX idx_issued_date (issued_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: certificate_verification_log
-- Purpose: Track certificate verification attempts
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS certificate_verification_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    certificate_number VARCHAR(50) NOT NULL,
    verified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    verification_result ENUM('valid', 'invalid', 'revoked') NOT NULL,
    INDEX idx_verification_attempts (certificate_number, verified_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SECTION 7: Achievement/Badge System
-- =====================================================

-- -----------------------------------------------------
-- Table: achievements
-- Purpose: Achievement definitions and unlock criteria
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS achievements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    badge_icon VARCHAR(50) NOT NULL COMMENT 'Font Awesome icon or image filename',
    badge_color VARCHAR(7) NOT NULL DEFAULT '#4B6EFB' COMMENT 'Hex color code',
    tier ENUM('bronze', 'silver', 'gold', 'platinum') NOT NULL DEFAULT 'bronze',
    points INT NOT NULL DEFAULT 10 COMMENT 'Points awarded for earning this achievement',
    unlock_criteria JSON NOT NULL COMMENT 'Criteria for unlocking (quiz scores, completion, etc)',
    is_secret BOOLEAN NOT NULL DEFAULT 0 COMMENT 'Hidden until unlocked',
    is_active BOOLEAN NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES achievement_categories(id) ON DELETE CASCADE,
    INDEX idx_tier (tier, is_active),
    INDEX idx_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: user_achievements
-- Purpose: Track unlocked achievements per user
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS user_achievements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    achievement_id INT NOT NULL,
    unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    progress_data JSON NULL COMMENT 'Progress towards achievement if partially complete',
    notification_sent BOOLEAN NOT NULL DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_achievement (user_id, achievement_id),
    INDEX idx_user_achievements (user_id, unlocked_at DESC),
    INDEX idx_recent_achievements (unlocked_at DESC),
    INDEX idx_achievement_unlocked (achievement_id, unlocked_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: user_achievement_points
-- Purpose: Leaderboard and point tracking
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS user_achievement_points (
    user_id INT PRIMARY KEY,
    total_points INT NOT NULL DEFAULT 0,
    achievements_count INT NOT NULL DEFAULT 0,
    bronze_count INT NOT NULL DEFAULT 0,
    silver_count INT NOT NULL DEFAULT 0,
    gold_count INT NOT NULL DEFAULT 0,
    platinum_count INT NOT NULL DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_leaderboard (total_points DESC, achievements_count DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SECTION 8: Engagement Features
-- =====================================================

-- -----------------------------------------------------
-- Table: student_notes
-- Purpose: Student note-taking on lessons
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS student_notes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    lesson_id INT NOT NULL,
    note_content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
    INDEX idx_user_notes (user_id, lesson_id),
    INDEX idx_lesson_notes (lesson_id),
    INDEX idx_user_created (user_id, created_at DESC),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: bookmarks
-- Purpose: Bookmark lessons for quick access
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS bookmarks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    lesson_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_lesson (user_id, lesson_id),
    INDEX idx_user_bookmarks (user_id, created_at DESC),
    INDEX idx_lesson_bookmarks (lesson_id),
    INDEX idx_bookmark_lookup (user_id, lesson_id),
    INDEX idx_created_at_with_user (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: profile_views
-- Purpose: Track profile views for analytics
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS profile_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    viewer_user_id INT NOT NULL COMMENT 'User who viewed the profile',
    viewed_user_id INT NOT NULL COMMENT 'User whose profile was viewed',
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IP address for analytics (supports IPv6)',
    user_agent TEXT DEFAULT NULL COMMENT 'Browser user agent string',

    CONSTRAINT fk_profile_views_viewer
        FOREIGN KEY (viewer_user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_profile_views_viewed
        FOREIGN KEY (viewed_user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    INDEX idx_viewed_user (viewed_user_id, viewed_at DESC),
    INDEX idx_viewer_user (viewer_user_id, viewed_at DESC),
    INDEX idx_viewed_at (viewed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Track profile views for analytics';

-- =====================================================
-- SECTION 9: Security and Utility Tables
-- =====================================================

-- -----------------------------------------------------
-- Table: token_blacklist
-- Purpose: JWT logout functionality
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS token_blacklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(255) NOT NULL COMMENT 'SHA-256 hash of JWT token',
    user_id INT NOT NULL COMMENT 'User who owns this token',
    expires_at TIMESTAMP NOT NULL COMMENT 'When token expires (no need to check after this)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When token was blacklisted',

    INDEX idx_token (token),
    INDEX idx_expires (expires_at),
    INDEX idx_user (user_id),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stores blacklisted JWT tokens for logout functionality';

-- =====================================================
-- SECTION 10: Triggers
-- =====================================================

DELIMITER //

-- Trigger: Update user_achievement_points after achievement unlock
CREATE TRIGGER IF NOT EXISTS after_user_achievement_insert
AFTER INSERT ON user_achievements
FOR EACH ROW
BEGIN
    DECLARE achievement_points INT;
    DECLARE achievement_tier VARCHAR(20);

    -- Get achievement details
    SELECT points, tier INTO achievement_points, achievement_tier
    FROM achievements
    WHERE id = NEW.achievement_id;

    -- Insert or update user points
    INSERT INTO user_achievement_points (user_id, total_points, achievements_count, bronze_count, silver_count, gold_count, platinum_count)
    VALUES (
        NEW.user_id,
        achievement_points,
        1,
        CASE WHEN achievement_tier = 'bronze' THEN 1 ELSE 0 END,
        CASE WHEN achievement_tier = 'silver' THEN 1 ELSE 0 END,
        CASE WHEN achievement_tier = 'gold' THEN 1 ELSE 0 END,
        CASE WHEN achievement_tier = 'platinum' THEN 1 ELSE 0 END
    )
    ON DUPLICATE KEY UPDATE
        total_points = total_points + achievement_points,
        achievements_count = achievements_count + 1,
        bronze_count = bronze_count + CASE WHEN achievement_tier = 'bronze' THEN 1 ELSE 0 END,
        silver_count = silver_count + CASE WHEN achievement_tier = 'silver' THEN 1 ELSE 0 END,
        gold_count = gold_count + CASE WHEN achievement_tier = 'gold' THEN 1 ELSE 0 END,
        platinum_count = platinum_count + CASE WHEN achievement_tier = 'platinum' THEN 1 ELSE 0 END;
END//

DELIMITER ;

-- =====================================================
-- SECTION 11: Analytics Views
-- =====================================================

-- View: Student Engagement Metrics
CREATE OR REPLACE VIEW v_student_engagement AS
SELECT
    e.user_id,
    e.course_id,
    u.name as user_name,
    u.email,
    COUNT(DISTINCT lp.lesson_id) as lessons_accessed,
    SUM(IFNULL(lp.time_spent_minutes, 0)) as total_time_minutes,
    COUNT(DISTINCT CASE WHEN lp.status = 'completed' THEN lp.lesson_id END) as lessons_completed,
    COUNT(DISTINCT sn.id) as notes_created,
    COUNT(DISTINCT b.id) as bookmarks_created,
    MAX(lp.updated_at) as last_lesson_activity,
    e.enrolled_at,
    e.progress_percentage,
    e.status as enrollment_status
FROM enrollments e
INNER JOIN users u ON e.user_id = u.id
LEFT JOIN lesson_progress lp ON e.user_id = lp.user_id
LEFT JOIN student_notes sn ON e.user_id = sn.user_id
LEFT JOIN bookmarks b ON e.user_id = b.user_id
GROUP BY e.user_id, e.course_id, u.name, u.email, e.enrolled_at, e.progress_percentage, e.status;

-- View: Quiz Performance Summary
CREATE OR REPLACE VIEW v_quiz_performance AS
SELECT
    qa.quiz_id,
    q.title as quiz_title,
    q.module_id,
    m.title as module_title,
    COUNT(DISTINCT qa.user_id) as unique_students,
    COUNT(qa.id) as total_attempts,
    AVG(qa.score) as average_score,
    MIN(qa.score) as min_score,
    MAX(qa.score) as max_score,
    SUM(CASE WHEN qa.passed = 1 THEN 1 ELSE 0 END) as passed_count,
    SUM(CASE WHEN qa.passed = 0 THEN 1 ELSE 0 END) as failed_count,
    AVG(qa.time_spent_seconds) as avg_time_seconds,
    MIN(qa.time_completed) as first_attempt_date,
    MAX(qa.time_completed) as last_attempt_date
FROM quiz_attempts qa
INNER JOIN quizzes q ON qa.quiz_id = q.id
INNER JOIN modules m ON q.module_id = m.id
WHERE qa.status = 'submitted' OR qa.status = 'graded'
GROUP BY qa.quiz_id, q.title, q.module_id, m.title;

-- View: Enrollment Trends
CREATE OR REPLACE VIEW v_enrollment_trends AS
SELECT
    DATE(enrolled_at) as enrollment_date,
    DATE_FORMAT(enrolled_at, '%Y-%m') as enrollment_month,
    course_id,
    c.title as course_title,
    COUNT(*) as enrollments_count,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
    SUM(CASE WHEN status = 'dropped' THEN 1 ELSE 0 END) as dropped_count
FROM enrollments e
INNER JOIN courses c ON e.course_id = c.id
GROUP BY DATE(enrolled_at), DATE_FORMAT(enrolled_at, '%Y-%m'), course_id, c.title;

-- View: User Acquisition Metrics
CREATE OR REPLACE VIEW v_user_acquisition AS
SELECT
    DATE(created_at) as signup_date,
    DATE_FORMAT(created_at, '%Y-%m') as signup_month,
    role,
    COUNT(*) as new_users_count,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users_count
FROM users
GROUP BY DATE(created_at), DATE_FORMAT(created_at, '%Y-%m'), role;

-- View: Achievement Distribution
CREATE OR REPLACE VIEW v_achievement_distribution AS
SELECT
    a.id as achievement_id,
    a.name as achievement_title,
    a.category_id,
    ac.name as category_name,
    a.tier,
    a.points,
    COUNT(ua.id) as unlock_count,
    MIN(ua.unlocked_at) as first_unlock_date,
    MAX(ua.unlocked_at) as last_unlock_date,
    COUNT(DISTINCT DATE_FORMAT(ua.unlocked_at, '%Y-%m')) as active_months
FROM achievements a
LEFT JOIN user_achievements ua ON a.id = ua.achievement_id
INNER JOIN achievement_categories ac ON a.category_id = ac.id
GROUP BY a.id, a.name, a.category_id, ac.name, a.tier, a.points;

-- View: Certificate Issuance Trends
CREATE OR REPLACE VIEW v_certificate_trends AS
SELECT
    DATE(issued_date) as issue_date_day,
    DATE_FORMAT(issued_date, '%Y-%m') as issue_month,
    course_id,
    c.title as course_title,
    COUNT(*) as certificates_issued
FROM certificates cert
INNER JOIN courses c ON cert.course_id = c.id
GROUP BY DATE(issued_date), DATE_FORMAT(issued_date, '%Y-%m'), course_id, c.title;

-- View: At-Risk Students
CREATE OR REPLACE VIEW v_at_risk_students AS
SELECT
    e.user_id,
    u.name as user_name,
    u.email,
    e.course_id,
    c.title as course_title,
    e.progress_percentage,
    e.enrolled_at,
    DATEDIFF(NOW(), e.last_accessed_at) as days_since_last_access,
    e.last_accessed_at,
    (
        SELECT AVG(qa.score)
        FROM quiz_attempts qa
        INNER JOIN quizzes q ON qa.quiz_id = q.id
        WHERE qa.user_id = e.user_id
        AND q.module_id IN (SELECT id FROM modules WHERE course_id = e.course_id)
    ) as avg_quiz_score,
    (
        SELECT COUNT(*)
        FROM quiz_attempts qa
        INNER JOIN quizzes q ON qa.quiz_id = q.id
        WHERE qa.user_id = e.user_id
        AND q.module_id IN (SELECT id FROM modules WHERE course_id = e.course_id)
        AND qa.passed = 0
    ) as failed_quiz_count,
    CASE
        WHEN e.progress_percentage < 10 AND DATEDIFF(NOW(), e.enrolled_at) > 30 THEN 90
        WHEN e.progress_percentage < 25 AND DATEDIFF(NOW(), e.last_accessed_at) > 14 THEN 75
        WHEN e.progress_percentage < 50 AND DATEDIFF(NOW(), e.last_accessed_at) > 7 THEN 60
        WHEN DATEDIFF(NOW(), e.last_accessed_at) > 21 THEN 80
        WHEN (
            SELECT AVG(qa.score)
            FROM quiz_attempts qa
            INNER JOIN quizzes q ON qa.quiz_id = q.id
            WHERE qa.user_id = e.user_id
            AND q.module_id IN (SELECT id FROM modules WHERE course_id = e.course_id)
        ) < 50 THEN 70
        ELSE 30
    END as risk_score
FROM enrollments e
INNER JOIN users u ON e.user_id = u.id
INNER JOIN courses c ON e.course_id = c.id
WHERE e.status = 'active';

-- View: Lesson Completion Heatmap Data
CREATE OR REPLACE VIEW v_lesson_completion_heatmap AS
SELECT
    lp.user_id,
    lp.lesson_id,
    l.title as lesson_title,
    l.module_id,
    m.title as module_title,
    DATE(lp.completed_at) as completion_date,
    DAYOFWEEK(lp.completed_at) as day_of_week,
    HOUR(lp.completed_at) as hour_of_day,
    lp.time_spent_minutes,
    lp.status
FROM lesson_progress lp
INNER JOIN lessons l ON lp.lesson_id = l.id
INNER JOIN modules m ON l.module_id = m.id
WHERE lp.status = 'completed';

-- View: Course Popularity Rankings
CREATE OR REPLACE VIEW v_course_popularity AS
SELECT
    c.id as course_id,
    c.title as course_title,
    c.description,
    c.is_published,
    COUNT(e.id) as total_enrollments,
    SUM(CASE WHEN e.status = 'active' THEN 1 ELSE 0 END) as active_enrollments,
    SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) as completions,
    AVG(e.progress_percentage) as avg_progress_percentage,
    (SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) / NULLIF(COUNT(e.id), 0) * 100) as completion_rate,
    MAX(e.enrolled_at) as last_enrollment_date,
    (
        SELECT COUNT(DISTINCT qa.user_id)
        FROM quiz_attempts qa
        INNER JOIN quizzes q ON qa.quiz_id = q.id
        INNER JOIN modules m ON q.module_id = m.id
        WHERE m.course_id = c.id
    ) as active_quiz_takers
FROM courses c
LEFT JOIN enrollments e ON c.id = e.course_id
GROUP BY c.id, c.title, c.description, c.is_published;

-- =====================================================
-- SECTION 12: Default Data
-- =====================================================

-- Default admin user (password: Admin123!)
INSERT INTO users (email, password_hash, name, role, is_active, is_verified)
VALUES (
    'admin@sci-bono.org',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'System Administrator',
    'admin',
    TRUE,
    TRUE
) ON DUPLICATE KEY UPDATE email=email;

-- Achievement Categories
INSERT INTO achievement_categories (name, description, icon, color, display_order) VALUES
('Learning Progress', 'Achievements for completing courses and modules', 'fa-graduation-cap', '#4B6EFB', 1),
('Quiz Mastery', 'Achievements for quiz performance and consistency', 'fa-trophy', '#FFD700', 2),
('Engagement', 'Achievements for active participation and interaction', 'fa-fire', '#FB4B4B', 3),
('Speed Learning', 'Achievements for completing content quickly', 'fa-bolt', '#FFA500', 4),
('Consistency', 'Achievements for regular learning habits', 'fa-calendar-check', '#4BFB9D', 5),
('Special', 'Rare and unique achievements', 'fa-star', '#6E4BFB', 6)
ON DUPLICATE KEY UPDATE name=name;

-- Sample Achievements
INSERT INTO achievements (category_id, name, description, badge_icon, badge_color, tier, points, unlock_criteria, is_secret) VALUES
-- Learning Progress
(1, 'First Steps', 'Complete your first lesson', 'fa-shoe-prints', '#4B6EFB', 'bronze', 10,
 JSON_OBJECT('type', 'lesson_completion', 'count', 1), 0),
(1, 'Module Master', 'Complete an entire module', 'fa-book', '#4B6EFB', 'silver', 50,
 JSON_OBJECT('type', 'module_completion', 'count', 1), 0),
(1, 'AI Fluency Graduate', 'Complete all 6 modules', 'fa-graduation-cap', '#4B6EFB', 'platinum', 500,
 JSON_OBJECT('type', 'course_completion', 'min_completion', 100), 0),

-- Quiz Mastery
(2, 'Perfect Score', 'Score 100% on any quiz', 'fa-bullseye', '#FFD700', 'gold', 100,
 JSON_OBJECT('type', 'quiz_score', 'min_score', 100, 'count', 1), 0),
(2, 'Quiz Champion', 'Score 100% on all module quizzes', 'fa-crown', '#FFD700', 'platinum', 300,
 JSON_OBJECT('type', 'quiz_score', 'min_score', 100, 'all_quizzes', true), 0),
(2, 'Quick Learner', 'Pass a quiz on first attempt', 'fa-rocket', '#FFD700', 'silver', 30,
 JSON_OBJECT('type', 'quiz_first_attempt', 'min_score', 70), 0),
(2, 'Persistent Student', 'Complete 10 quiz attempts', 'fa-repeat', '#FFD700', 'bronze', 20,
 JSON_OBJECT('type', 'quiz_attempts', 'count', 10), 0),

-- Engagement
(3, 'Note Taker', 'Create 5 study notes', 'fa-sticky-note', '#FB4B4B', 'bronze', 15,
 JSON_OBJECT('type', 'notes_created', 'count', 5), 0),
(3, 'Bookmark Collector', 'Bookmark 10 lessons', 'fa-bookmark', '#FB4B4B', 'bronze', 15,
 JSON_OBJECT('type', 'bookmarks_created', 'count', 10), 0),
(3, 'Active Learner', 'Log in for 7 consecutive days', 'fa-calendar-week', '#FB4B4B', 'silver', 50,
 JSON_OBJECT('type', 'consecutive_login_days', 'count', 7), 0),

-- Speed Learning
(4, 'Speed Reader', 'Complete a module in under 2 hours', 'fa-bolt', '#FFA500', 'gold', 75,
 JSON_OBJECT('type', 'module_completion_time', 'max_hours', 2), 0),
(4, 'Flash Learner', 'Complete 5 lessons in one day', 'fa-zap', '#FFA500', 'silver', 40,
 JSON_OBJECT('type', 'lessons_per_day', 'count', 5), 0),

-- Consistency
(5, 'Weekly Warrior', 'Complete lessons every day for a week', 'fa-calendar-check', '#4BFB9D', 'silver', 60,
 JSON_OBJECT('type', 'weekly_consistency', 'days', 7), 0),
(5, 'Monthly Champion', 'Complete lessons every week for a month', 'fa-calendar-alt', '#4BFB9D', 'gold', 150,
 JSON_OBJECT('type', 'monthly_consistency', 'weeks', 4), 0),

-- Special
(6, 'Early Adopter', 'Be among the first 100 students', 'fa-medal', '#6E4BFB', 'platinum', 200,
 JSON_OBJECT('type', 'user_rank', 'max_rank', 100), 1),
(6, 'Overachiever', 'Earn 1000 achievement points', 'fa-star', '#6E4BFB', 'platinum', 100,
 JSON_OBJECT('type', 'total_points', 'min_points', 1000), 0)
ON DUPLICATE KEY UPDATE name=name;

-- Default Certificate Templates
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
),
(
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
)
ON DUPLICATE KEY UPDATE name=name;

-- Record migrations
INSERT INTO schema_migrations (version) VALUES
('001'), ('002'), ('003'), ('004'), ('005'), ('006'),
('009'), ('010'), ('011'), ('012'), ('013'), ('017'),
('019'), ('020'), ('021')
ON DUPLICATE KEY UPDATE version=version;

-- =====================================================
-- SECTION 13: Post-Installation Verification
-- =====================================================

-- Show all tables
SHOW TABLES;

-- Count records in key tables
SELECT 'Users' as table_name, COUNT(*) as record_count FROM users
UNION ALL
SELECT 'Courses', COUNT(*) FROM courses
UNION ALL
SELECT 'Achievement Categories', COUNT(*) FROM achievement_categories
UNION ALL
SELECT 'Achievements', COUNT(*) FROM achievements
UNION ALL
SELECT 'Certificate Templates', COUNT(*) FROM certificate_templates;

-- =====================================================
-- Installation Complete
-- =====================================================
SELECT '
=====================================================
AI FLUENCY LMS - Database Setup Complete
=====================================================

Database: ai_fluency_lms
Tables Created: 30+
Views Created: 10
Triggers Created: 1

Default Credentials:
  Email: admin@sci-bono.org
  Password: Admin123!

Next Steps:
1. Change the default admin password
2. Configure your .env file with database credentials
3. Run the application and verify connectivity
4. Populate courses, modules, and lessons
5. Test the system with a student account

For more information, see the README.md file.
=====================================================
' AS installation_summary;
