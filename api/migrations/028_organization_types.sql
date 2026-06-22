-- Migration 028: Organization Types & Education Context
-- Created: 2026-02-28
-- Purpose: Support private schools, homeschool learners, and independent learners
--          by adding organization_type, parent_organization_id, and user education_context

-- ============================================================================
-- STEP 1: Add organization_type to organizations
-- ============================================================================

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'organizations' AND COLUMN_NAME = 'organization_type');
SET @sql = IF(@col_exists = 0,
    "ALTER TABLE organizations ADD COLUMN organization_type ENUM('education_department','school_public','school_private','homeschool_provider','individual_home_educator','afterschool_program','training_provider') DEFAULT 'school_public' AFTER slug",
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================================
-- STEP 2: Add parent_organization_id for hierarchy
-- ============================================================================

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'organizations' AND COLUMN_NAME = 'parent_organization_id');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE organizations ADD COLUMN parent_organization_id INT NULL AFTER organization_type',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================================
-- STEP 3: Update existing organizations to correct types
-- ============================================================================

-- GDE organization -> education_department
UPDATE organizations SET organization_type = 'education_department'
WHERE slug = 'gde' OR name LIKE '%Gauteng Department of Education%' OR name LIKE '%GDE%';

-- Sci-Bono -> training_provider
UPDATE organizations SET organization_type = 'training_provider'
WHERE slug = 'sci-bono' OR name LIKE '%Sci-Bono%' OR name LIKE '%SciBono%';

-- All remaining orgs default to school_public (already the column default)

-- ============================================================================
-- STEP 4: Add education_context to users
-- ============================================================================

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'education_context');
SET @sql = IF(@col_exists = 0,
    "ALTER TABLE users ADD COLUMN education_context ENUM('public_school','private_school','homeschool','independent_learner','afterschool_program') DEFAULT 'public_school' AFTER role",
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================================
-- STEP 5: Expand schools.school_type to include 'private'
-- ============================================================================

ALTER TABLE schools MODIFY COLUMN school_type
    ENUM('primary','secondary','combined','private','other') DEFAULT 'other';
