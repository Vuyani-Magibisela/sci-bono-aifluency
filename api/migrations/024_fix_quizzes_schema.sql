-- =====================================================
-- Migration 024: Fix Quizzes Table Schema
-- =====================================================
-- Purpose: Add missing columns (slug, is_published, lesson_id, max_attempts, order) to quizzes table
-- Dependencies: Migrations 001-023
-- Issue: Quizzes not displaying because table lacks columns that application code expects
-- Date: 2026-02-06
-- =====================================================

USE ai_fluency_lms;

-- =====================================================
-- SECTION 1: Add Missing Columns
-- =====================================================

-- Add columns one at a time to avoid MySQL syntax issues
ALTER TABLE quizzes ADD COLUMN slug VARCHAR(255) NOT NULL DEFAULT '' AFTER title;
ALTER TABLE quizzes ADD COLUMN lesson_id INT DEFAULT NULL AFTER module_id;
ALTER TABLE quizzes ADD COLUMN max_attempts INT DEFAULT 3 AFTER time_limit_minutes;
ALTER TABLE quizzes ADD COLUMN is_published BOOLEAN DEFAULT FALSE AFTER max_attempts;
ALTER TABLE quizzes ADD COLUMN `order` INT DEFAULT 0 AFTER is_published;

-- =====================================================
-- SECTION 2: Populate Slug Values for Existing Records
-- =====================================================
-- Generate slugs from quiz titles with ID suffix to ensure uniqueness
-- Example: "Module 1 Quiz" becomes "module-1-quiz-quiz-001"

UPDATE quizzes
SET slug = CONCAT(
    LOWER(REPLACE(REPLACE(REPLACE(TRIM(title), ' ', '-'), '--', '-'), '---', '-')),
    '-quiz-',
    LPAD(id, 3, '0')
);

-- =====================================================
-- SECTION 3: Add Foreign Key and Indexes
-- =====================================================

-- Add foreign key for lesson_id (quizzes can be module-level or lesson-level)
ALTER TABLE quizzes ADD CONSTRAINT fk_quizzes_lesson_id
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE SET NULL;

-- Add indexes for performance
ALTER TABLE quizzes ADD UNIQUE INDEX idx_quiz_slug (module_id, slug);
ALTER TABLE quizzes ADD INDEX idx_quiz_published (is_published);
ALTER TABLE quizzes ADD INDEX idx_quiz_lesson (lesson_id);
ALTER TABLE quizzes ADD INDEX idx_quiz_order (module_id, `order`);

-- =====================================================
-- SECTION 4: Verification Queries
-- =====================================================

-- Display updated table structure
SELECT '=== QUIZZES TABLE STRUCTURE ===' AS '';
DESCRIBE quizzes;

-- Count total quizzes
SELECT '=== TOTAL QUIZZES ===' AS '';
SELECT COUNT(*) as total_quizzes FROM quizzes;

-- Display sample records with new columns
SELECT '=== SAMPLE QUIZZES (with new columns) ===' AS '';
SELECT id, title, slug, is_published, max_attempts, `order`, lesson_id
FROM quizzes
LIMIT 5;

-- Check for any null slugs (should be none)
SELECT '=== SLUG VALIDATION ===' AS '';
SELECT
    COUNT(*) as quizzes_with_slug,
    COUNT(CASE WHEN slug IS NULL OR slug = '' THEN 1 END) as null_or_empty_slugs
FROM quizzes;

-- Check for duplicate slugs within modules (should be none)
SELECT '=== DUPLICATE SLUG CHECK ===' AS '';
SELECT module_id, slug, COUNT(*) as count
FROM quizzes
GROUP BY module_id, slug
HAVING COUNT(*) > 1;

-- Summary
SELECT '
=====================================================
MIGRATION 024 COMPLETE
=====================================================

Changes Applied:
- Added slug column (VARCHAR 255, NOT NULL)
- Added lesson_id column (INT, DEFAULT NULL, FK to lessons)
- Added max_attempts column (INT, DEFAULT 3)
- Added is_published column (BOOLEAN, DEFAULT FALSE)
- Added order column (INT, DEFAULT 0)
- Created 4 indexes for query optimization
- Generated slugs for all existing quizzes

Next Steps:
1. Verify the output above shows correct structure
2. Check that all quizzes have valid slugs
3. Publish quizzes: UPDATE quizzes SET is_published = 1;
4. Test the admin quizzes page: http://aifluency.local/admin/quizzes.html
5. Test the API endpoint: GET /api/quizzes

If any issues occur, run the rollback commands from the plan.
=====================================================
' AS migration_summary;
