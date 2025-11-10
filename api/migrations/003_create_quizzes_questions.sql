-- Migration 003: Create Quiz Tables
-- Purpose: Quizzes and Quiz Questions
-- Run: mysql -u user -p database_name < 003_create_quizzes_questions.sql

-- ============================================================================
-- QUIZZES TABLE
-- ============================================================================

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

-- ============================================================================
-- QUIZ_QUESTIONS TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS quiz_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Foreign key
    quiz_id INT NOT NULL,

    -- Question content
    question_text TEXT NOT NULL,

    -- Options stored as JSON array
    -- Example: ["Option A", "Option B", "Option C", "Option D"]
    options JSON NOT NULL,

    -- Correct answer (0-based index into options array)
    correct_option INT NOT NULL,

    -- Explanation shown after submission
    explanation TEXT,

    -- Points awarded for correct answer
    points INT DEFAULT 1,

    -- Order within quiz
    order_index INT NOT NULL,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign key constraint
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,

    -- Indexes
    INDEX idx_quiz_id (quiz_id),
    INDEX idx_order (quiz_id, order_index),

    -- Constraints
    CONSTRAINT chk_correct_option CHECK (correct_option >= 0 AND correct_option <= 10)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verify tables created
SELECT 'Quiz tables created successfully' AS status;
SHOW TABLES LIKE '%quizzes%';
SHOW TABLES LIKE '%quiz_questions%';
