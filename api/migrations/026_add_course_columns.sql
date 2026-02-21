-- =====================================================
-- Migration 026: Add Missing Course Columns
-- =====================================================
-- Purpose: Add slug and instructor_id columns to courses table
-- Issue: Course model expects these columns but schema doesn't have them
-- =====================================================

USE ai_fluency_lms;

-- SECTION 1: Add slug Column
ALTER TABLE courses ADD COLUMN slug VARCHAR(255) NULL AFTER title;

-- SECTION 2: Add instructor_id Column
ALTER TABLE courses ADD COLUMN instructor_id INT NULL AFTER slug;

-- SECTION 3: Add Foreign Key for instructor_id
ALTER TABLE courses ADD CONSTRAINT fk_courses_instructor
    FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE SET NULL;

-- SECTION 4: Add Indexes
ALTER TABLE courses ADD INDEX idx_courses_instructor (instructor_id);

-- SECTION 5: Generate Slugs for Existing Courses
UPDATE courses
SET slug = LOWER(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(title), ' ', '-'), '--', '-'), '---', '-'), '/', '-'))
WHERE slug IS NULL;

-- SECTION 6: Make Slug NOT NULL and UNIQUE
ALTER TABLE courses MODIFY COLUMN slug VARCHAR(255) NOT NULL;
ALTER TABLE courses ADD UNIQUE INDEX idx_courses_slug (slug);

-- SECTION 7: Verification
SELECT '=== UPDATED COURSES TABLE ===' AS '';
DESCRIBE courses;

SELECT '=== COURSE SLUGS ===' AS '';
SELECT id, title, slug, instructor_id FROM courses;

-- SECTION 8: Record migration
INSERT INTO schema_migrations (version) VALUES ('026')
ON DUPLICATE KEY UPDATE version=version;

SELECT '
=====================================================
MIGRATION 026 COMPLETE
=====================================================

Changes Applied:
- Added slug column (VARCHAR 255, NOT NULL, UNIQUE)
- Added instructor_id column (INT, NULL, FK to users)
- Generated slugs for existing courses
- Added indexes for performance

Next Steps:
1. Assign instructors: UPDATE courses SET instructor_id = X WHERE id = Y;
2. Verify slugs are SEO-friendly
3. Update Course model queries to use slug

Rollback:
  Run api/migrations/026_rollback.sql
=====================================================
' AS migration_summary;
