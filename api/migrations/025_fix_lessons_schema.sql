-- =====================================================
-- Migration 025: Fix Lesson Slug Uniqueness
-- =====================================================
-- Purpose: Change lesson slug from globally unique to module-scoped
-- Issue: Cannot reuse common lesson names across different modules
-- Example: Two modules can't both have "introduction" lesson
-- =====================================================

USE ai_fluency_lms;

-- SECTION 1: Verify Current State
SELECT '=== CURRENT LESSON SLUGS ===' AS '';
SELECT module_id, slug, COUNT(*) as count
FROM lessons
GROUP BY module_id, slug
HAVING count > 1;
-- Expected: 0 rows (no duplicates within modules currently)

-- SECTION 2: Drop Global Unique Constraint
ALTER TABLE lessons DROP INDEX slug;

-- SECTION 3: Add Module-Scoped Unique Constraint
ALTER TABLE lessons ADD UNIQUE INDEX idx_lesson_slug_module (module_id, slug);

-- SECTION 4: Verification
SELECT '=== UPDATED CONSTRAINTS ===' AS '';
SHOW INDEX FROM lessons WHERE Key_name LIKE '%slug%';

-- SECTION 5: Record migration
-- Note: schema_migrations table may not exist yet
CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(50) PRIMARY KEY,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (version) VALUES ('025')
ON DUPLICATE KEY UPDATE version=version;

SELECT '
=====================================================
MIGRATION 025 COMPLETE
=====================================================

Changes Applied:
- Dropped global UNIQUE constraint on lessons.slug
- Added module-scoped UNIQUE constraint (module_id, slug)

Impact:
- Different modules can now have lessons with same slug
- Within same module, slugs must still be unique

Testing:
Run this query to test:
  INSERT INTO lessons (module_id, title, slug, order_index, content)
  VALUES (1, "Test Lesson A", "test", 1, "Test content");
  INSERT INTO lessons (module_id, title, slug, order_index, content)
  VALUES (2, "Test Lesson B", "test", 1, "Test content");
  -- Should SUCCEED (different modules)

  INSERT INTO lessons (module_id, title, slug, order_index, content)
  VALUES (1, "Test Lesson C", "test", 2, "Test content");
  -- Should FAIL (duplicate in module 1)

Rollback:
  Run api/migrations/025_rollback.sql
=====================================================
' AS migration_summary;
