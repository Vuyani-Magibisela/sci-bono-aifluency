-- Migration 019: Fix Projects Schema Mismatches
-- Phase 7: Project System Schema Alignment
-- Purpose: Add missing fields to projects table and enhance file tracking
-- Date: 2025-12-30
-- Run: mysql -u root -p sci_bono_aifluency < 019_fix_projects_schema.sql

-- ============================================================================
-- SECTION 1: ADD MISSING COLUMNS TO PROJECTS TABLE
-- ============================================================================

-- Add course_id (nullable initially for data migration)
ALTER TABLE projects
ADD COLUMN course_id INT NULL AFTER id,
ADD COLUMN slug VARCHAR(255) NULL AFTER title,
ADD COLUMN `order` INT DEFAULT 0 AFTER slug;

SELECT 'Section 1: Columns added to projects table' AS status;

-- ============================================================================
-- SECTION 2: MIGRATE EXISTING DATA
-- ============================================================================

-- Populate course_id from modules relationship
UPDATE projects p
JOIN modules m ON p.module_id = m.id
SET p.course_id = m.course_id
WHERE p.course_id IS NULL;

SELECT CONCAT('Populated course_id for ', ROW_COUNT(), ' projects') AS migration_step;

-- Generate slugs from titles (sanitized + ID for uniqueness)
UPDATE projects
SET slug = LOWER(CONCAT(
    REPLACE(
        REPLACE(
            REPLACE(
                REPLACE(
                    REPLACE(title, ' ', '-'),
                    '/', '-'
                ),
                '&', 'and'
            ),
            '''', ''
        ),
        '"', ''
    ),
    '-', id
))
WHERE slug IS NULL;

SELECT CONCAT('Generated slugs for ', ROW_COUNT(), ' projects') AS migration_step;

-- Set sequential order within each course
SET @row_number = 0;
SET @current_course = 0;

UPDATE projects p
JOIN (
    SELECT id, course_id,
           @row_number := IF(@current_course = course_id, @row_number + 1, 1) AS row_num,
           @current_course := course_id
    FROM projects
    ORDER BY course_id, id
) AS ranked ON p.id = ranked.id
SET p.`order` = ranked.row_num
WHERE p.`order` = 0;

SELECT 'Section 2: Data migration completed' AS status;

-- ============================================================================
-- SECTION 3: VALIDATION CHECKS
-- ============================================================================

-- Check for NULL course_id (should be 0)
SELECT
    CONCAT('Projects with missing course_id: ', COUNT(*)) AS validation_check
FROM projects
WHERE course_id IS NULL;

-- Check for NULL slugs (should be 0)
SELECT
    CONCAT('Projects with missing slug: ', COUNT(*)) AS validation_check
FROM projects
WHERE slug IS NULL;

-- Preview migrated data
SELECT
    'Sample migrated projects:' AS info;

SELECT
    id,
    course_id,
    title,
    slug,
    `order`,
    module_id
FROM projects
ORDER BY course_id, `order`
LIMIT 10;

SELECT 'Section 3: Validation checks completed' AS status;

-- ============================================================================
-- SECTION 4: ADD CONSTRAINTS AND INDEXES
-- ============================================================================

-- Make columns NOT NULL after data migration
ALTER TABLE projects
MODIFY COLUMN course_id INT NOT NULL,
MODIFY COLUMN slug VARCHAR(255) NOT NULL;

SELECT 'Made course_id and slug NOT NULL' AS constraint_step;

-- Add foreign key for course_id
ALTER TABLE projects
ADD CONSTRAINT fk_projects_course_id
FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE;

SELECT 'Added foreign key constraint for course_id' AS constraint_step;

-- Add unique constraint on (course_id, slug)
ALTER TABLE projects
ADD CONSTRAINT unique_project_slug_per_course
UNIQUE KEY (course_id, slug);

SELECT 'Added unique constraint on (course_id, slug)' AS constraint_step;

-- Add performance indexes
ALTER TABLE projects
ADD INDEX idx_course_id (course_id),
ADD INDEX idx_course_order (course_id, `order`);

SELECT 'Section 4: Constraints and indexes added' AS status;

-- ============================================================================
-- SECTION 5: ENHANCE PROJECT SUBMISSIONS FILE TRACKING
-- ============================================================================

-- Add uploaded_file_id for proper file tracking
ALTER TABLE project_submissions
ADD COLUMN uploaded_file_id INT NULL AFTER submission_file_url;

SELECT 'Added uploaded_file_id column to project_submissions' AS file_tracking_step;

-- Add foreign key to uploaded_files table
ALTER TABLE project_submissions
ADD CONSTRAINT fk_project_submissions_uploaded_file
FOREIGN KEY (uploaded_file_id) REFERENCES uploaded_files(id) ON DELETE SET NULL;

SELECT 'Added foreign key to uploaded_files table' AS file_tracking_step;

-- Add index for file queries
ALTER TABLE project_submissions
ADD INDEX idx_uploaded_file_id (uploaded_file_id);

SELECT 'Section 5: File tracking enhancements completed' AS status;

-- ============================================================================
-- SECTION 6: MODEL UPDATE DOCUMENTATION
-- ============================================================================

SELECT '
===========================================
POST-MIGRATION MODEL UPDATES REQUIRED
===========================================

File: /var/www/html/sci-bono-aifluency/api/models/Project.php
Change line 14-24 $fillable array to include "order":

protected array $fillable = [
    "course_id",
    "module_id",
    "title",
    "slug",
    "description",
    "requirements",
    "max_score",
    "due_date",
    "is_published",
    "order"  // ADD THIS LINE
];

-------------------------------------------

File: /var/www/html/sci-bono-aifluency/api/models/ProjectSubmission.php
Change line 14-26 $fillable array to include "uploaded_file_id":

protected array $fillable = [
    "project_id",
    "user_id",
    "submission_url",
    "submission_text",
    "status",
    "score",
    "feedback",
    "graded_by",
    "submitted_at",
    "graded_at",
    "uploaded_file_id"  // ADD THIS LINE
];

===========================================
' AS model_update_instructions;

-- ============================================================================
-- SECTION 7: FINAL VERIFICATION
-- ============================================================================

SELECT '
===========================================
FINAL VERIFICATION RESULTS
===========================================
' AS verification_header;

-- Show updated table structure for projects
SELECT 'projects table structure:' AS info;
DESCRIBE projects;

-- Show updated table structure for project_submissions
SELECT 'project_submissions table structure:' AS info;
DESCRIBE project_submissions;

-- Show constraints
SELECT
    'Table constraints:' AS info;

SELECT
    TABLE_NAME,
    CONSTRAINT_NAME,
    CONSTRAINT_TYPE
FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('projects', 'project_submissions')
ORDER BY TABLE_NAME, CONSTRAINT_TYPE;

-- Show indexes
SELECT 'Table indexes:' AS info;

SELECT
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME,
    SEQ_IN_INDEX
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('projects', 'project_submissions')
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

-- Show sample data with new fields
SELECT 'Sample projects with new fields:' AS info;

SELECT
    p.id,
    p.course_id,
    c.title AS course_title,
    p.title AS project_title,
    p.slug,
    p.`order`,
    p.is_published
FROM projects p
JOIN courses c ON p.course_id = c.id
ORDER BY p.course_id, p.`order`
LIMIT 20;

-- Final success message
SELECT '
===========================================
✅ Migration 019 completed successfully!
===========================================

Next steps:
1. Update Project.php model $fillable array
2. Update ProjectSubmission.php model $fillable array
3. Test API endpoints
4. Verify project creation/update operations

Run validation queries:
  SELECT COUNT(*) FROM projects WHERE course_id IS NULL;
  SELECT COUNT(*) FROM projects WHERE slug IS NULL;

===========================================
' AS completion_message;
