-- Rollback Migration 022: Hierarchical Role-Based User Management System
-- Created: 2025-02-04
-- Purpose: Revert hierarchical role system back to original 3-role system

-- WARNING: This rollback will:
-- 1. Delete all organizations and schools data
-- 2. Revert roles back to admin/instructor/student
-- 3. Remove organizational relationships from users
-- 4. This is a DESTRUCTIVE operation - backup your database first!

-- ============================================================================
-- PHASE 1: Revert User Roles (5 roles → 3 roles)
-- ============================================================================

-- Add temporary role column with old ENUM values
ALTER TABLE users
ADD COLUMN role_old ENUM('student', 'instructor', 'admin')
DEFAULT 'student' AFTER role;

-- Migrate role data back to old system
-- Note: Multiple new roles map to old roles (data loss warning!)
UPDATE users SET role_old = 'admin' WHERE role IN ('superadmin', 'orgadmin', 'schooladmin');
UPDATE users SET role_old = 'instructor' WHERE role = 'teacher';
UPDATE users SET role_old = 'student' WHERE role = 'student';

-- Drop new role column
ALTER TABLE users DROP INDEX idx_role;
ALTER TABLE users DROP COLUMN role;

-- Rename old role column back to 'role'
ALTER TABLE users
CHANGE COLUMN role_old role
ENUM('student', 'instructor', 'admin')
DEFAULT 'student' NOT NULL;

-- Re-add index
ALTER TABLE users ADD INDEX idx_role (role);

-- ============================================================================
-- PHASE 2: Remove Organizational Fields from Users Table
-- ============================================================================

-- Drop foreign key constraints first
ALTER TABLE users
DROP FOREIGN KEY fk_users_organization,
DROP FOREIGN KEY fk_users_school;

-- Drop indexes
ALTER TABLE users
DROP INDEX idx_primary_organization,
DROP INDEX idx_primary_school,
DROP INDEX idx_is_active;

-- Drop columns
ALTER TABLE users
DROP COLUMN primary_organization_id,
DROP COLUMN primary_school_id,
DROP COLUMN organizational_title,
DROP COLUMN is_active;

-- ============================================================================
-- PHASE 3: Drop Pivot Tables
-- ============================================================================

DROP TABLE IF EXISTS user_schools;
DROP TABLE IF EXISTS user_organizations;

-- ============================================================================
-- PHASE 4: Drop Schools and Organizations Tables
-- ============================================================================

DROP TABLE IF EXISTS schools;
DROP TABLE IF EXISTS organizations;

-- ============================================================================
-- ROLLBACK COMPLETE
-- ============================================================================

-- Verify rollback success
SELECT
    'Rollback 022 Complete' as Status,
    (SELECT COUNT(*) FROM users WHERE role = 'admin') as Admins,
    (SELECT COUNT(*) FROM users WHERE role = 'instructor') as Instructors,
    (SELECT COUNT(*) FROM users WHERE role = 'student') as Students;
