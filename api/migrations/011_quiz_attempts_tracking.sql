-- Migration 011: Enhanced Quiz Attempts Tracking
-- Phase 6: Quiz Tracking & Grading System
-- Created: 2025-11-14

-- This migration enhances the existing quiz_attempts table to support:
-- 1. Detailed question-by-question tracking
-- 2. Instructor grading and override capabilities
-- 3. Time tracking per attempt
-- 4. Attempt metadata (IP, user agent for academic integrity)

-- Add new columns to quiz_attempts table (with existence checks)
SET @ALTER_SQL = '';

-- Check and add attempt_number column
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'ai_fluency_lms' AND TABLE_NAME = 'quiz_attempts' AND COLUMN_NAME = 'attempt_number';
SET @ALTER_SQL = IF(@col_exists = 0, 'ALTER TABLE quiz_attempts ADD COLUMN attempt_number INT NOT NULL DEFAULT 1 COMMENT ''Sequential attempt number for this user/quiz'';', 'SELECT ''Column attempt_number already exists'';');
PREPARE stmt FROM @ALTER_SQL; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Check and add time_started column
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'ai_fluency_lms' AND TABLE_NAME = 'quiz_attempts' AND COLUMN_NAME = 'time_started';
SET @ALTER_SQL = IF(@col_exists = 0, 'ALTER TABLE quiz_attempts ADD COLUMN time_started TIMESTAMP NULL COMMENT ''When the attempt began'';', 'SELECT ''Column time_started already exists'';');
PREPARE stmt FROM @ALTER_SQL; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Check and add time_completed column
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'ai_fluency_lms' AND TABLE_NAME = 'quiz_attempts' AND COLUMN_NAME = 'time_completed';
SET @ALTER_SQL = IF(@col_exists = 0, 'ALTER TABLE quiz_attempts ADD COLUMN time_completed TIMESTAMP NULL COMMENT ''When the attempt was submitted'';', 'SELECT ''Column time_completed already exists'';');
PREPARE stmt FROM @ALTER_SQL; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Check and add time_spent_seconds column
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'ai_fluency_lms' AND TABLE_NAME = 'quiz_attempts' AND COLUMN_NAME = 'time_spent_seconds';
SET @ALTER_SQL = IF(@col_exists = 0, 'ALTER TABLE quiz_attempts ADD COLUMN time_spent_seconds INT NULL COMMENT ''Total time spent in seconds'';', 'SELECT ''Column time_spent_seconds already exists'';');
PREPARE stmt FROM @ALTER_SQL; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Check and add ip_address column
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'ai_fluency_lms' AND TABLE_NAME = 'quiz_attempts' AND COLUMN_NAME = 'ip_address';
SET @ALTER_SQL = IF(@col_exists = 0, 'ALTER TABLE quiz_attempts ADD COLUMN ip_address VARCHAR(45) NULL COMMENT ''IP address for academic integrity'';', 'SELECT ''Column ip_address already exists'';');
PREPARE stmt FROM @ALTER_SQL; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Check and add user_agent column
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'ai_fluency_lms' AND TABLE_NAME = 'quiz_attempts' AND COLUMN_NAME = 'user_agent';
SET @ALTER_SQL = IF(@col_exists = 0, 'ALTER TABLE quiz_attempts ADD COLUMN user_agent TEXT NULL COMMENT ''Browser user agent'';', 'SELECT ''Column user_agent already exists'';');
PREPARE stmt FROM @ALTER_SQL; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Check and add instructor_score column
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'ai_fluency_lms' AND TABLE_NAME = 'quiz_attempts' AND COLUMN_NAME = 'instructor_score';
SET @ALTER_SQL = IF(@col_exists = 0, 'ALTER TABLE quiz_attempts ADD COLUMN instructor_score DECIMAL(5,2) NULL COMMENT ''Override score set by instructor'';', 'SELECT ''Column instructor_score already exists'';');
PREPARE stmt FROM @ALTER_SQL; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Check and add instructor_feedback column
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'ai_fluency_lms' AND TABLE_NAME = 'quiz_attempts' AND COLUMN_NAME = 'instructor_feedback';
SET @ALTER_SQL = IF(@col_exists = 0, 'ALTER TABLE quiz_attempts ADD COLUMN instructor_feedback TEXT NULL COMMENT ''Instructor feedback on attempt'';', 'SELECT ''Column instructor_feedback already exists'';');
PREPARE stmt FROM @ALTER_SQL; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Check and add graded_by column
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'ai_fluency_lms' AND TABLE_NAME = 'quiz_attempts' AND COLUMN_NAME = 'graded_by';
SET @ALTER_SQL = IF(@col_exists = 0, 'ALTER TABLE quiz_attempts ADD COLUMN graded_by INT NULL COMMENT ''Instructor who graded/reviewed'';', 'SELECT ''Column graded_by already exists'';');
PREPARE stmt FROM @ALTER_SQL; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Check and add graded_at column
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'ai_fluency_lms' AND TABLE_NAME = 'quiz_attempts' AND COLUMN_NAME = 'graded_at';
SET @ALTER_SQL = IF(@col_exists = 0, 'ALTER TABLE quiz_attempts ADD COLUMN graded_at TIMESTAMP NULL COMMENT ''When instructor grading occurred'';', 'SELECT ''Column graded_at already exists'';');
PREPARE stmt FROM @ALTER_SQL; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Check and add status column
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'ai_fluency_lms' AND TABLE_NAME = 'quiz_attempts' AND COLUMN_NAME = 'status';
SET @ALTER_SQL = IF(@col_exists = 0, 'ALTER TABLE quiz_attempts ADD COLUMN status ENUM(''in_progress'', ''submitted'', ''graded'', ''reviewed'') DEFAULT ''submitted'' COMMENT ''Attempt status'';', 'SELECT ''Column status already exists'';');
PREPARE stmt FROM @ALTER_SQL; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add indexes (check existence first)
SELECT COUNT(*) INTO @idx_exists FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = 'ai_fluency_lms' AND TABLE_NAME = 'quiz_attempts' AND INDEX_NAME = 'idx_attempt_status';
SET @ALTER_SQL = IF(@idx_exists = 0, 'ALTER TABLE quiz_attempts ADD INDEX idx_attempt_status (status);', 'SELECT ''Index idx_attempt_status already exists'';');
PREPARE stmt FROM @ALTER_SQL; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @idx_exists FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = 'ai_fluency_lms' AND TABLE_NAME = 'quiz_attempts' AND INDEX_NAME = 'idx_graded_by';
SET @ALTER_SQL = IF(@idx_exists = 0, 'ALTER TABLE quiz_attempts ADD INDEX idx_graded_by (graded_by);', 'SELECT ''Index idx_graded_by already exists'';');
PREPARE stmt FROM @ALTER_SQL; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add foreign key constraint (check existence first)
SELECT COUNT(*) INTO @fk_exists FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA = 'ai_fluency_lms' AND TABLE_NAME = 'quiz_attempts' AND CONSTRAINT_NAME = 'fk_graded_by';
SET @ALTER_SQL = IF(@fk_exists = 0, 'ALTER TABLE quiz_attempts ADD CONSTRAINT fk_graded_by FOREIGN KEY (graded_by) REFERENCES users(id) ON DELETE SET NULL;', 'SELECT ''Foreign key fk_graded_by already exists'';');
PREPARE stmt FROM @ALTER_SQL; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Create quiz_attempt_answers table for question-by-question tracking
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

-- Create quiz_questions table to store question bank
CREATE TABLE IF NOT EXISTS quiz_questions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    quiz_id INT NOT NULL,
    question_type ENUM('multiple_choice', 'true_false', 'short_answer', 'essay') NOT NULL DEFAULT 'multiple_choice',
    question_text TEXT NOT NULL,
    question_data JSON NOT NULL COMMENT 'Options, correct answer, explanation in JSON format',
    points DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    order_index INT NOT NULL DEFAULT 0 COMMENT 'Display order',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    INDEX idx_quiz_questions (quiz_id, order_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add more indexes for performance
SELECT COUNT(*) INTO @idx_exists FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = 'ai_fluency_lms' AND TABLE_NAME = 'quiz_attempts' AND INDEX_NAME = 'idx_user_quiz_attempts';
SET @ALTER_SQL = IF(@idx_exists = 0, 'ALTER TABLE quiz_attempts ADD INDEX idx_user_quiz_attempts (user_id, quiz_id, attempt_number);', 'SELECT ''Index idx_user_quiz_attempts already exists'';');
PREPARE stmt FROM @ALTER_SQL; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @idx_exists FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = 'ai_fluency_lms' AND TABLE_NAME = 'quiz_attempts' AND INDEX_NAME = 'idx_completion_time';
SET @ALTER_SQL = IF(@idx_exists = 0, 'ALTER TABLE quiz_attempts ADD INDEX idx_completion_time (time_completed);', 'SELECT ''Index idx_completion_time already exists'';');
PREPARE stmt FROM @ALTER_SQL; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Update existing quiz_attempts to have attempt_number if not set
UPDATE quiz_attempts
SET attempt_number = (
    SELECT COUNT(*) + 1
    FROM (SELECT * FROM quiz_attempts) AS qa2
    WHERE qa2.user_id = quiz_attempts.user_id
    AND qa2.quiz_id = quiz_attempts.quiz_id
    AND qa2.id < quiz_attempts.id
)
WHERE attempt_number = 1;

-- Migration verification
SELECT 'Migration 011 completed successfully' AS status;
