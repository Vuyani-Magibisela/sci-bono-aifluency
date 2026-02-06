-- Migration 022: Hierarchical Role-Based User Management System
-- Created: 2025-02-04
-- Purpose: Transform 3-role system (admin, instructor, student) into 5-role hierarchical system
--          (superadmin, orgadmin, schooladmin, teacher, student) with organizational structure

-- ============================================================================
-- PHASE 1: Create Organizations Table
-- ============================================================================

CREATE TABLE IF NOT EXISTS organizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    logo_url VARCHAR(500),
    website VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(50),
    address TEXT,
    city VARCHAR(100),
    province VARCHAR(100),
    country VARCHAR(100) DEFAULT 'South Africa',
    postal_code VARCHAR(20),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PHASE 2: Create Schools Table
-- ============================================================================

CREATE TABLE IF NOT EXISTS schools (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organization_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT,
    school_type ENUM('primary', 'secondary', 'combined', 'other') DEFAULT 'combined',
    logo_url VARCHAR(500),
    email VARCHAR(255),
    phone VARCHAR(50),
    address TEXT,
    city VARCHAR(100),
    province VARCHAR(100),
    country VARCHAR(100) DEFAULT 'South Africa',
    postal_code VARCHAR(20),
    principal_name VARCHAR(255),
    principal_email VARCHAR(255),
    principal_phone VARCHAR(50),
    total_students INT DEFAULT 0,
    total_teachers INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    UNIQUE KEY unique_org_slug (organization_id, slug),
    INDEX idx_organization (organization_id),
    INDEX idx_slug (slug),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PHASE 3: Modify Users Table - Add Organizational Fields
-- ============================================================================

-- Add organizational relationship columns
ALTER TABLE users
ADD COLUMN primary_organization_id INT NULL AFTER role,
ADD COLUMN primary_school_id INT NULL AFTER primary_organization_id,
ADD COLUMN organizational_title VARCHAR(255) NULL AFTER primary_school_id;

-- Add foreign key constraints
ALTER TABLE users
ADD CONSTRAINT fk_users_organization
    FOREIGN KEY (primary_organization_id) REFERENCES organizations(id) ON DELETE SET NULL,
ADD CONSTRAINT fk_users_school
    FOREIGN KEY (primary_school_id) REFERENCES schools(id) ON DELETE SET NULL;

-- Add indexes for performance
ALTER TABLE users
ADD INDEX idx_primary_organization (primary_organization_id),
ADD INDEX idx_primary_school (primary_school_id);

-- ============================================================================
-- PHASE 4: Migrate Role ENUM (3 roles → 5 roles)
-- ============================================================================

-- Strategy: Use temporary column to avoid ENUM modification issues
-- Step 1: Add temporary role column with new ENUM values
ALTER TABLE users
ADD COLUMN role_new ENUM('student', 'teacher', 'schooladmin', 'orgadmin', 'superadmin')
DEFAULT 'student' AFTER role;

-- Step 2: Migrate existing role data
UPDATE users SET role_new = 'superadmin' WHERE role = 'admin';
UPDATE users SET role_new = 'teacher' WHERE role = 'instructor';
UPDATE users SET role_new = 'student' WHERE role = 'student';

-- Step 3: Drop old role column
ALTER TABLE users DROP COLUMN role;

-- Step 4: Rename new role column to 'role'
ALTER TABLE users
CHANGE COLUMN role_new role
ENUM('student', 'teacher', 'schooladmin', 'orgadmin', 'superadmin')
DEFAULT 'student' NOT NULL;

-- Step 5: Re-add index on role column
ALTER TABLE users ADD INDEX idx_role (role);

-- ============================================================================
-- PHASE 5: Create User-Organization Pivot Table (Multi-Organization Support)
-- ============================================================================

CREATE TABLE IF NOT EXISTS user_organizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    organization_id INT NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    role_in_org ENUM('student', 'teacher', 'schooladmin', 'orgadmin', 'superadmin') DEFAULT 'student',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_org (user_id, organization_id),
    INDEX idx_user (user_id),
    INDEX idx_organization (organization_id),
    INDEX idx_is_primary (is_primary)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PHASE 6: Create User-School Pivot Table (Multi-School Support)
-- ============================================================================

CREATE TABLE IF NOT EXISTS user_schools (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    school_id INT NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    role_in_school ENUM('student', 'teacher', 'schooladmin') DEFAULT 'student',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_school (user_id, school_id),
    INDEX idx_user (user_id),
    INDEX idx_school (school_id),
    INDEX idx_is_primary (is_primary)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PHASE 7: Create Default Organization and School
-- ============================================================================

-- Insert default organization
INSERT INTO organizations (name, slug, description, is_active)
VALUES (
    'Sci-Bono Discovery Centre',
    'sci-bono',
    'Default organization for Sci-Bono Discovery Centre',
    TRUE
) ON DUPLICATE KEY UPDATE name = name;

-- Get the organization ID
SET @default_org_id = (SELECT id FROM organizations WHERE slug = 'sci-bono' LIMIT 1);

-- Insert default school
INSERT INTO schools (organization_id, name, slug, description, school_type, is_active)
VALUES (
    @default_org_id,
    'Main Campus',
    'main-campus',
    'Default school for existing users',
    'combined',
    TRUE
) ON DUPLICATE KEY UPDATE name = name;

-- Get the school ID
SET @default_school_id = (SELECT id FROM schools WHERE slug = 'main-campus' AND organization_id = @default_org_id LIMIT 1);

-- ============================================================================
-- PHASE 8: Migrate Existing Users to Default Organization/School
-- ============================================================================

-- Assign all existing users to default organization and school
UPDATE users
SET
    primary_organization_id = @default_org_id,
    primary_school_id = @default_school_id,
    is_active = TRUE
WHERE primary_organization_id IS NULL;

-- Populate user_organizations pivot table
INSERT INTO user_organizations (user_id, organization_id, is_primary, role_in_org)
SELECT
    id as user_id,
    @default_org_id as organization_id,
    TRUE as is_primary,
    role as role_in_org
FROM users
WHERE id NOT IN (SELECT user_id FROM user_organizations WHERE organization_id = @default_org_id);

-- Populate user_schools pivot table
INSERT INTO user_schools (user_id, school_id, is_primary, role_in_school)
SELECT
    id as user_id,
    @default_school_id as school_id,
    TRUE as is_primary,
    role as role_in_school
FROM users
WHERE role IN ('student', 'teacher', 'schooladmin')
AND id NOT IN (SELECT user_id FROM user_schools WHERE school_id = @default_school_id);

-- ============================================================================
-- PHASE 9: Update School Statistics
-- ============================================================================

-- Update total_students and total_teachers counts
UPDATE schools s
SET
    total_students = (
        SELECT COUNT(*)
        FROM users
        WHERE primary_school_id = s.id
        AND role = 'student'
        AND is_active = TRUE
    ),
    total_teachers = (
        SELECT COUNT(*)
        FROM users
        WHERE primary_school_id = s.id
        AND role IN ('teacher', 'schooladmin')
        AND is_active = TRUE
    );

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================

-- Verify migration success
SELECT
    'Migration 022 Complete' as Status,
    (SELECT COUNT(*) FROM organizations) as Organizations,
    (SELECT COUNT(*) FROM schools) as Schools,
    (SELECT COUNT(*) FROM users WHERE role = 'superadmin') as SuperAdmins,
    (SELECT COUNT(*) FROM users WHERE role = 'orgadmin') as OrgAdmins,
    (SELECT COUNT(*) FROM users WHERE role = 'schooladmin') as SchoolAdmins,
    (SELECT COUNT(*) FROM users WHERE role = 'teacher') as Teachers,
    (SELECT COUNT(*) FROM users WHERE role = 'student') as Students,
    (SELECT COUNT(*) FROM user_organizations) as UserOrgLinks,
    (SELECT COUNT(*) FROM user_schools) as UserSchoolLinks;
