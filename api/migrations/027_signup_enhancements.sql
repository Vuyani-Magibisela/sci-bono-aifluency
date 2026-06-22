-- Migration 027: Signup Enhancements
-- Created: 2026-02-15
-- Purpose: Add demographic columns to users, EMIS/district to schools, and create GDE organization

-- ============================================================================
-- PHASE 1: Add demographic columns to users table (one per statement)
-- ============================================================================

ALTER TABLE users ADD COLUMN contact_number VARCHAR(20) NULL AFTER name;

ALTER TABLE users ADD COLUMN gender ENUM('male','female','prefer_not_to_say') NULL AFTER contact_number;

ALTER TABLE users ADD COLUMN grade VARCHAR(5) NULL AFTER gender;

ALTER TABLE users ADD COLUMN date_of_birth DATE NULL AFTER grade;

-- ============================================================================
-- PHASE 2: Add EMIS number and district to schools table
-- ============================================================================

ALTER TABLE schools ADD COLUMN emis_number VARCHAR(20) NULL AFTER name;

ALTER TABLE schools ADD COLUMN district VARCHAR(100) NULL AFTER emis_number;

ALTER TABLE schools ADD INDEX idx_emis_number (emis_number);

-- ============================================================================
-- PHASE 3: Create GDE organization
-- ============================================================================

INSERT INTO organizations (name, slug, description, province, country, is_active)
VALUES ('Gauteng Department of Education', 'gde', 'GDE school system', 'Gauteng', 'South Africa', TRUE)
ON DUPLICATE KEY UPDATE name = name;

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================

SELECT 'Migration 027 Complete' as Status,
       (SELECT COUNT(*) FROM organizations WHERE slug = 'gde') as GDE_Org_Created;
