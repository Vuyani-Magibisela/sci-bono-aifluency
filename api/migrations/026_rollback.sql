-- =====================================================
-- Migration 026 Rollback: Remove Course Columns
-- =====================================================
-- Purpose: Rollback course column additions
-- WARNING: This will delete slug and instructor_id data
-- =====================================================

USE ai_fluency_lms;

-- SECTION 1: Drop Foreign Key
ALTER TABLE courses DROP FOREIGN KEY fk_courses_instructor;

-- SECTION 2: Drop Indexes
ALTER TABLE courses DROP INDEX idx_courses_instructor;
ALTER TABLE courses DROP INDEX idx_courses_slug;

-- SECTION 3: Drop Columns
ALTER TABLE courses DROP COLUMN instructor_id;
ALTER TABLE courses DROP COLUMN slug;

-- SECTION 4: Remove migration record
DELETE FROM schema_migrations WHERE version = '026';

SELECT '
=====================================================
MIGRATION 026 ROLLBACK COMPLETE
=====================================================

Changes Reverted:
- Dropped instructor_id column
- Dropped slug column
- Removed foreign key and indexes

WARNING:
- All slug and instructor assignment data has been lost
- Course model may throw errors if trying to access these fields

=====================================================
' AS rollback_summary;
