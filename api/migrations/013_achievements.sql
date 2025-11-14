-- Migration 013: Achievement Badges System
-- Phase 6: Quiz Tracking & Grading System
-- Created: 2025-11-14

-- This migration creates the achievement/badges system including:
-- 1. Achievement definitions with unlock criteria
-- 2. User achievement tracking
-- 3. Achievement categories and tiers

-- Create achievement_categories table
CREATE TABLE IF NOT EXISTS achievement_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    icon VARCHAR(50) NULL COMMENT 'Font Awesome icon class',
    color VARCHAR(7) NULL COMMENT 'Hex color code',
    display_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create achievements table
CREATE TABLE IF NOT EXISTS achievements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    badge_icon VARCHAR(50) NOT NULL COMMENT 'Font Awesome icon or image filename',
    badge_color VARCHAR(7) NOT NULL DEFAULT '#4B6EFB' COMMENT 'Hex color code',
    tier ENUM('bronze', 'silver', 'gold', 'platinum') NOT NULL DEFAULT 'bronze',
    points INT NOT NULL DEFAULT 10 COMMENT 'Points awarded for earning this achievement',
    unlock_criteria JSON NOT NULL COMMENT 'Criteria for unlocking (quiz scores, completion, etc)',
    is_secret BOOLEAN NOT NULL DEFAULT 0 COMMENT 'Hidden until unlocked',
    is_active BOOLEAN NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES achievement_categories(id) ON DELETE CASCADE,
    INDEX idx_tier (tier, is_active),
    INDEX idx_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create user_achievements table
CREATE TABLE IF NOT EXISTS user_achievements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    achievement_id INT NOT NULL,
    unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    progress_data JSON NULL COMMENT 'Progress towards achievement if partially complete',
    notification_sent BOOLEAN NOT NULL DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_achievement (user_id, achievement_id),
    INDEX idx_user_achievements (user_id, unlocked_at DESC),
    INDEX idx_recent_achievements (unlocked_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create user_achievement_points table for leaderboard
CREATE TABLE IF NOT EXISTS user_achievement_points (
    user_id INT PRIMARY KEY,
    total_points INT NOT NULL DEFAULT 0,
    achievements_count INT NOT NULL DEFAULT 0,
    bronze_count INT NOT NULL DEFAULT 0,
    silver_count INT NOT NULL DEFAULT 0,
    gold_count INT NOT NULL DEFAULT 0,
    platinum_count INT NOT NULL DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_leaderboard (total_points DESC, achievements_count DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert achievement categories
INSERT INTO achievement_categories (name, description, icon, color, display_order) VALUES
('Learning Progress', 'Achievements for completing courses and modules', 'fa-graduation-cap', '#4B6EFB', 1),
('Quiz Mastery', 'Achievements for quiz performance and consistency', 'fa-trophy', '#FFD700', 2),
('Engagement', 'Achievements for active participation and interaction', 'fa-fire', '#FB4B4B', 3),
('Speed Learning', 'Achievements for completing content quickly', 'fa-bolt', '#FFA500', 4),
('Consistency', 'Achievements for regular learning habits', 'fa-calendar-check', '#4BFB9D', 5),
('Special', 'Rare and unique achievements', 'fa-star', '#6E4BFB', 6);

-- Insert sample achievements
INSERT INTO achievements (category_id, name, description, badge_icon, badge_color, tier, points, unlock_criteria, is_secret) VALUES
-- Learning Progress
(1, 'First Steps', 'Complete your first lesson', 'fa-shoe-prints', '#4B6EFB', 'bronze', 10,
 JSON_OBJECT('type', 'lesson_completion', 'count', 1), 0),
(1, 'Module Master', 'Complete an entire module', 'fa-book', '#4B6EFB', 'silver', 50,
 JSON_OBJECT('type', 'module_completion', 'count', 1), 0),
(1, 'AI Fluency Graduate', 'Complete all 6 modules', 'fa-graduation-cap', '#4B6EFB', 'platinum', 500,
 JSON_OBJECT('type', 'course_completion', 'min_completion', 100), 0),

-- Quiz Mastery
(2, 'Perfect Score', 'Score 100% on any quiz', 'fa-bullseye', '#FFD700', 'gold', 100,
 JSON_OBJECT('type', 'quiz_score', 'min_score', 100, 'count', 1), 0),
(2, 'Quiz Champion', 'Score 100% on all module quizzes', 'fa-crown', '#FFD700', 'platinum', 300,
 JSON_OBJECT('type', 'quiz_score', 'min_score', 100, 'all_quizzes', true), 0),
(2, 'Quick Learner', 'Pass a quiz on first attempt', 'fa-rocket', '#FFD700', 'silver', 30,
 JSON_OBJECT('type', 'quiz_first_attempt', 'min_score', 70), 0),
(2, 'Persistent Student', 'Complete 10 quiz attempts', 'fa-repeat', '#FFD700', 'bronze', 20,
 JSON_OBJECT('type', 'quiz_attempts', 'count', 10), 0),

-- Engagement
(3, 'Note Taker', 'Create 5 study notes', 'fa-sticky-note', '#FB4B4B', 'bronze', 15,
 JSON_OBJECT('type', 'notes_created', 'count', 5), 0),
(3, 'Bookmark Collector', 'Bookmark 10 lessons', 'fa-bookmark', '#FB4B4B', 'bronze', 15,
 JSON_OBJECT('type', 'bookmarks_created', 'count', 10), 0),
(3, 'Active Learner', 'Log in for 7 consecutive days', 'fa-calendar-week', '#FB4B4B', 'silver', 50,
 JSON_OBJECT('type', 'consecutive_login_days', 'count', 7), 0),

-- Speed Learning
(4, 'Speed Reader', 'Complete a module in under 2 hours', 'fa-bolt', '#FFA500', 'gold', 75,
 JSON_OBJECT('type', 'module_completion_time', 'max_hours', 2), 0),
(4, 'Flash Learner', 'Complete 5 lessons in one day', 'fa-zap', '#FFA500', 'silver', 40,
 JSON_OBJECT('type', 'lessons_per_day', 'count', 5), 0),

-- Consistency
(5, 'Weekly Warrior', 'Complete lessons every day for a week', 'fa-calendar-check', '#4BFB9D', 'silver', 60,
 JSON_OBJECT('type', 'weekly_consistency', 'days', 7), 0),
(5, 'Monthly Champion', 'Complete lessons every week for a month', 'fa-calendar-alt', '#4BFB9D', 'gold', 150,
 JSON_OBJECT('type', 'monthly_consistency', 'weeks', 4), 0),

-- Special
(6, 'Early Adopter', 'Be among the first 100 students', 'fa-medal', '#6E4BFB', 'platinum', 200,
 JSON_OBJECT('type', 'user_rank', 'max_rank', 100), 1),
(6, 'Overachiever', 'Earn 1000 achievement points', 'fa-star', '#6E4BFB', 'platinum', 100,
 JSON_OBJECT('type', 'total_points', 'min_points', 1000), 0);

-- Create trigger to update user_achievement_points
DELIMITER //

CREATE TRIGGER IF NOT EXISTS after_user_achievement_insert
AFTER INSERT ON user_achievements
FOR EACH ROW
BEGIN
    DECLARE achievement_points INT;
    DECLARE achievement_tier VARCHAR(20);

    -- Get achievement details
    SELECT points, tier INTO achievement_points, achievement_tier
    FROM achievements
    WHERE id = NEW.achievement_id;

    -- Insert or update user points
    INSERT INTO user_achievement_points (user_id, total_points, achievements_count, bronze_count, silver_count, gold_count, platinum_count)
    VALUES (
        NEW.user_id,
        achievement_points,
        1,
        CASE WHEN achievement_tier = 'bronze' THEN 1 ELSE 0 END,
        CASE WHEN achievement_tier = 'silver' THEN 1 ELSE 0 END,
        CASE WHEN achievement_tier = 'gold' THEN 1 ELSE 0 END,
        CASE WHEN achievement_tier = 'platinum' THEN 1 ELSE 0 END
    )
    ON DUPLICATE KEY UPDATE
        total_points = total_points + achievement_points,
        achievements_count = achievements_count + 1,
        bronze_count = bronze_count + CASE WHEN achievement_tier = 'bronze' THEN 1 ELSE 0 END,
        silver_count = silver_count + CASE WHEN achievement_tier = 'silver' THEN 1 ELSE 0 END,
        gold_count = gold_count + CASE WHEN achievement_tier = 'gold' THEN 1 ELSE 0 END,
        platinum_count = platinum_count + CASE WHEN achievement_tier = 'platinum' THEN 1 ELSE 0 END;
END//

DELIMITER ;

-- Migration verification
SELECT 'Migration 013 completed successfully' AS status;
