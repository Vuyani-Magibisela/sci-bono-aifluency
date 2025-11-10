-- Migration 004: Create Enrollment and Progress Tables
-- Purpose: Track student enrollments and lesson progress
-- Run: mysql -u user -p database_name < 004_create_enrollments_progress.sql

-- ============================================================================
-- ENROLLMENTS TABLE
-- ============================================================================

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
    INDEX idx_status (status)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- LESSON_PROGRESS TABLE
-- ============================================================================

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
    INDEX idx_status (status)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- QUIZ_ATTEMPTS TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS quiz_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Foreign keys
    user_id INT NOT NULL,
    quiz_id INT NOT NULL,

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

    -- Timestamps
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    submitted_at TIMESTAMP NULL DEFAULT NULL,

    -- Foreign key constraints
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,

    -- Indexes
    INDEX idx_user_id (user_id),
    INDEX idx_quiz_id (quiz_id),
    INDEX idx_passed (passed)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verify tables created
SELECT 'Enrollment and progress tables created successfully' AS status;
SHOW TABLES LIKE '%enrollments%';
SHOW TABLES LIKE '%progress%';
SHOW TABLES LIKE '%attempts%';
