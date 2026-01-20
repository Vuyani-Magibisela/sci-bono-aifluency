-- =====================================================
-- Migration 020: Profile Enhancements
-- Phase 8: Profile Building & Viewing
--
-- Purpose: Extend users table for comprehensive profile features
-- Dependencies: Migration 001 (users table)
-- Author: AI Fluency LMS Team
-- Date: 2026-01-19
-- =====================================================

-- Section 1: Add Profile Fields to users table
-- =====================================================
ALTER TABLE users
ADD COLUMN bio TEXT DEFAULT NULL COMMENT 'User biography/introduction (max 5000 chars)',
ADD COLUMN headline VARCHAR(255) DEFAULT NULL COMMENT 'Professional headline (max 255 chars)',
ADD COLUMN location VARCHAR(255) DEFAULT NULL COMMENT 'City, Country',
ADD COLUMN website_url VARCHAR(255) DEFAULT NULL COMMENT 'Personal website URL',
ADD COLUMN github_url VARCHAR(255) DEFAULT NULL COMMENT 'GitHub profile URL',
ADD COLUMN linkedin_url VARCHAR(255) DEFAULT NULL COMMENT 'LinkedIn profile URL',
ADD COLUMN twitter_url VARCHAR(255) DEFAULT NULL COMMENT 'Twitter/X profile URL',
ADD COLUMN is_public_profile BOOLEAN DEFAULT TRUE COMMENT 'Profile visibility toggle',
ADD COLUMN show_email BOOLEAN DEFAULT FALSE COMMENT 'Email visibility in public profile',
ADD COLUMN show_achievements BOOLEAN DEFAULT TRUE COMMENT 'Achievements visibility toggle',
ADD COLUMN show_certificates BOOLEAN DEFAULT TRUE COMMENT 'Certificates visibility toggle',
ADD COLUMN profile_views_count INT DEFAULT 0 COMMENT 'Total profile view count',
ADD COLUMN last_profile_updated TIMESTAMP NULL DEFAULT NULL COMMENT 'Last profile edit timestamp';

-- Section 2: Populate Defaults for Existing Users
-- =====================================================
-- Set default values for all existing users to ensure consistency
UPDATE users
SET
    bio = NULL,
    headline = NULL,
    location = NULL,
    website_url = NULL,
    github_url = NULL,
    linkedin_url = NULL,
    twitter_url = NULL,
    is_public_profile = TRUE,
    show_email = FALSE,
    show_achievements = TRUE,
    show_certificates = TRUE,
    profile_views_count = 0,
    last_profile_updated = NULL
WHERE bio IS NULL; -- Only update rows that haven't been migrated yet

-- Section 3: Create Profile Views Tracking Table
-- =====================================================
CREATE TABLE IF NOT EXISTS profile_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    viewer_user_id INT NOT NULL COMMENT 'User who viewed the profile',
    viewed_user_id INT NOT NULL COMMENT 'User whose profile was viewed',
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IP address for analytics (supports IPv6)',
    user_agent TEXT DEFAULT NULL COMMENT 'Browser user agent string',

    -- Foreign keys with CASCADE delete
    CONSTRAINT fk_profile_views_viewer
        FOREIGN KEY (viewer_user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_profile_views_viewed
        FOREIGN KEY (viewed_user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    -- Indexes for performance
    INDEX idx_viewed_user (viewed_user_id, viewed_at DESC),
    INDEX idx_viewer_user (viewer_user_id, viewed_at DESC),
    INDEX idx_viewed_at (viewed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Track profile views for analytics';

-- Section 4: Add Indexes for Performance
-- =====================================================
ALTER TABLE users
ADD INDEX idx_is_public_profile (is_public_profile),
ADD INDEX idx_last_profile_updated (last_profile_updated DESC),
ADD INDEX idx_profile_views_count (profile_views_count DESC);

-- Section 5: Validation Checks
-- =====================================================
-- Verify migration success by counting users with new fields
SELECT
    COUNT(*) as total_users,
    SUM(CASE WHEN is_public_profile = TRUE THEN 1 ELSE 0 END) as public_profiles,
    SUM(CASE WHEN bio IS NOT NULL AND bio != '' THEN 1 ELSE 0 END) as users_with_bio,
    SUM(CASE WHEN headline IS NOT NULL AND headline != '' THEN 1 ELSE 0 END) as users_with_headline,
    SUM(CASE WHEN location IS NOT NULL AND location != '' THEN 1 ELSE 0 END) as users_with_location,
    SUM(CASE WHEN website_url IS NOT NULL THEN 1 ELSE 0 END) as users_with_website,
    SUM(CASE WHEN github_url IS NOT NULL THEN 1 ELSE 0 END) as users_with_github,
    SUM(CASE WHEN linkedin_url IS NOT NULL THEN 1 ELSE 0 END) as users_with_linkedin,
    SUM(CASE WHEN twitter_url IS NOT NULL THEN 1 ELSE 0 END) as users_with_twitter
FROM users;

-- Verify profile_views table was created
SHOW TABLES LIKE 'profile_views';

-- Display new users table structure
SHOW FULL COLUMNS FROM users WHERE Field IN (
    'bio', 'headline', 'location', 'website_url', 'github_url',
    'linkedin_url', 'twitter_url', 'is_public_profile', 'show_email',
    'show_achievements', 'show_certificates', 'profile_views_count',
    'last_profile_updated'
);

-- Section 6: Migration Summary
-- =====================================================
SELECT
    'Migration 020: Profile Enhancements' AS migration_name,
    '13 columns added to users table' AS users_table_changes,
    '1 new table created (profile_views)' AS new_tables,
    '3 indexes added to users table' AS performance_indexes,
    'COMPLETE' AS status;

-- =====================================================
-- ROLLBACK INSTRUCTIONS (if needed)
-- =====================================================
-- To rollback this migration, run:
--
-- DROP TABLE IF EXISTS profile_views;
--
-- ALTER TABLE users
-- DROP INDEX idx_is_public_profile,
-- DROP INDEX idx_last_profile_updated,
-- DROP INDEX idx_profile_views_count,
-- DROP COLUMN bio,
-- DROP COLUMN headline,
-- DROP COLUMN location,
-- DROP COLUMN website_url,
-- DROP COLUMN github_url,
-- DROP COLUMN linkedin_url,
-- DROP COLUMN twitter_url,
-- DROP COLUMN is_public_profile,
-- DROP COLUMN show_email,
-- DROP COLUMN show_achievements,
-- DROP COLUMN show_certificates,
-- DROP COLUMN profile_views_count,
-- DROP COLUMN last_profile_updated;
--
-- =====================================================
