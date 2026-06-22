-- =====================================================
-- Migration 025 Rollback: Restore Global Slug Uniqueness
-- =====================================================
-- Purpose: Rollback lesson slug uniqueness from module-scoped to global
-- WARNING: This will fail if you have lessons with duplicate slugs across modules
-- =====================================================

USE ai_fluency_lms;

-- SECTION 1: Check for conflicts
SELECT '=== CHECKING FOR SLUG CONFLICTS ===' AS '';
SELECT slug, COUNT(*) as count, GROUP_CONCAT(module_id) as modules
FROM lessons
GROUP BY slug
HAVING count > 1;
-- If this returns rows, rollback will fail!

-- SECTION 2: Drop Module-Scoped Unique Constraint
ALTER TABLE lessons DROP INDEX idx_lesson_slug_module;

-- SECTION 3: Restore Global Unique Constraint
ALTER TABLE lessons ADD UNIQUE INDEX slug (slug);

-- SECTION 4: Remove migration record
DELETE FROM schema_migrations WHERE version = '025';

SELECT '
=====================================================
MIGRATION 025 ROLLBACK COMPLETE
=====================================================

Changes Reverted:
- Dropped module-scoped UNIQUE constraint (module_id, slug)
- Restored global UNIQUE constraint on lessons.slug

WARNING:
- Lesson slugs are now globally unique again
- You cannot have lessons with same slug in different modules

=====================================================
' AS rollback_summary;
