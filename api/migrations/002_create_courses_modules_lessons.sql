-- Migration 002: Create Course Structure Tables
-- Purpose: Courses, Modules, and Lessons
-- Run: mysql -u user -p database_name < 002_create_courses_modules_lessons.sql

-- ============================================================================
-- COURSES TABLE
-- ============================================================================

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

-- ============================================================================
-- MODULES TABLE
-- ============================================================================

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

-- ============================================================================
-- LESSONS TABLE
-- ============================================================================

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

-- Verify tables created
SELECT 'Course structure tables created successfully' AS status;
SHOW TABLES LIKE '%courses%';
SHOW TABLES LIKE '%modules%';
SHOW TABLES LIKE '%lessons%';
