-- =====================================================
-- Migration 023: Fix Modules Table Schema
-- =====================================================
-- Purpose: Add missing columns (slug, is_published, objectives, duration_hours) to modules table
-- Dependencies: Migrations 001-022
-- Issue: Modules not displaying because table lacks columns that application code expects
-- Date: 2026-02-06
-- =====================================================

USE ai_fluency_lms;

-- =====================================================
-- SECTION 1: Add Missing Columns
-- =====================================================

-- Add columns one at a time to avoid MySQL syntax issues with COMMENT and AFTER clauses
ALTER TABLE modules ADD COLUMN slug VARCHAR(255) NOT NULL DEFAULT '' AFTER title;
ALTER TABLE modules ADD COLUMN objectives TEXT DEFAULT NULL AFTER description;
ALTER TABLE modules ADD COLUMN duration_hours INT DEFAULT 0 AFTER objectives;
ALTER TABLE modules ADD COLUMN is_published BOOLEAN DEFAULT FALSE AFTER duration_hours;

-- =====================================================
-- SECTION 2: Populate Slug Values for Existing Records
-- =====================================================
-- Generate slugs from module titles with ID suffix to ensure uniqueness
-- Example: "Module 1" becomes "module-1-001"

UPDATE modules
SET slug = CONCAT(
    LOWER(REPLACE(REPLACE(REPLACE(TRIM(title), ' ', '-'), '--', '-'), '---', '-')),
    '-',
    LPAD(id, 3, '0')
);

-- =====================================================
-- SECTION 3: Add Indexes for Performance
-- =====================================================

-- Add indexes one at a time
ALTER TABLE modules ADD UNIQUE INDEX idx_slug_course (course_id, slug);
ALTER TABLE modules ADD INDEX idx_is_published (is_published);
ALTER TABLE modules ADD INDEX idx_duration (duration_hours);

-- =====================================================
-- SECTION 4: Record Migration Metadata
-- =====================================================

INSERT INTO schema_migrations (version)
VALUES ('023')
ON DUPLICATE KEY UPDATE version=version;

-- =====================================================
-- SECTION 5: Verification Queries
-- =====================================================

-- Display updated table structure
SELECT '=== MODULES TABLE STRUCTURE ===' AS '';
DESCRIBE modules;

-- Count total modules
SELECT '=== TOTAL MODULES ===' AS '';
SELECT COUNT(*) as total_modules FROM modules;

-- Display sample records with new columns
SELECT '=== SAMPLE MODULES (with new columns) ===' AS '';
SELECT id, title, slug, is_published, duration_hours, objectives
FROM modules
LIMIT 5;

-- Check for any null slugs (should be none)
SELECT '=== SLUG VALIDATION ===' AS '';
SELECT
    COUNT(*) as modules_with_slug,
    COUNT(CASE WHEN slug IS NULL OR slug = '' THEN 1 END) as null_or_empty_slugs
FROM modules;

-- Check for duplicate slugs within courses (should be none)
SELECT '=== DUPLICATE SLUG CHECK ===' AS '';
SELECT course_id, slug, COUNT(*) as count
FROM modules
GROUP BY course_id, slug
HAVING COUNT(*) > 1;

-- Summary
SELECT '
=====================================================
MIGRATION 023 COMPLETE
=====================================================

Changes Applied:
- Added slug column (VARCHAR 255, NOT NULL)
- Added is_published column (BOOLEAN, DEFAULT FALSE)
- Added objectives column (TEXT, DEFAULT NULL)
- Added duration_hours column (INT, DEFAULT 0)
- Created 3 indexes for query optimization
- Generated slugs for all existing modules

Next Steps:
1. Verify the output above shows correct structure
2. Check that all modules have valid slugs
3. Test the admin modules page: http://aifluency.local/admin/modules.html
4. Test the API endpoint: GET /api/modules

If any issues occur, run the rollback script:
api/migrations/023_rollback.sql
=====================================================
' AS migration_summary;
