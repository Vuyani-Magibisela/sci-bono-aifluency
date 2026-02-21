-- =====================================================
-- AI FLUENCY LMS - Super Admin User
-- =====================================================
-- Creates the initial superadmin account
-- =====================================================

SET NAMES utf8mb4;

-- Insert superadmin user
INSERT INTO users (email, password_hash, name, role, is_active, is_verified, created_at)
VALUES (
    'admin@vuyanimagibisela.co.za',
    '$2y$10$cMFeuSg3WRrep2ROqAiEd.gPwdUbrGHLh0YWowpOWuxaBvc53gOMO',
    'Vuyani Magibisela',
    'superadmin',
    1,
    1,
    NOW()
) ON DUPLICATE KEY UPDATE email = email;

-- Assign to default organization
INSERT INTO user_organizations (user_id, organization_id, is_primary, role_in_org)
SELECT
    u.id,
    o.id,
    TRUE,
    'superadmin'
FROM users u
CROSS JOIN organizations o
WHERE u.email = 'admin@vuyanimagibisela.co.za'
AND o.slug = 'sci-bono'
ON DUPLICATE KEY UPDATE role_in_org = 'superadmin';

-- Verify
SELECT id, email, name, role, is_active FROM users WHERE email = 'admin@vuyanimagibisela.co.za';
