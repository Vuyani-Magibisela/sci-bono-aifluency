-- Migration 032: user_badges
--
-- Per-user, per-lesson gamified badges awarded on chapter completion in the v2 UI.
-- Distinct from the broader `achievements` table (which handles course-wide milestones).
--
-- Idempotency is enforced by UNIQUE(user_id, badge_slug): the runner can POST the same
-- badge multiple times (e.g. on chapter replay) without creating duplicates.

CREATE TABLE IF NOT EXISTS `user_badges` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `badge_slug` VARCHAR(64) NOT NULL,
    `badge_name` VARCHAR(120) NULL,
    `lesson_id` INT NULL,
    `module_id` INT NULL,
    `earned_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_user_badge` (`user_id`, `badge_slug`),
    KEY `idx_user` (`user_id`),
    KEY `idx_module` (`module_id`),
    CONSTRAINT `fk_user_badges_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_user_badges_lesson`
        FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_user_badges_module`
        FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
