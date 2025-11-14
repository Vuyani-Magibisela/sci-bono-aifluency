-- Migration 010: Bookmarks Table
-- Phase 5D Priority 5
-- Enables students to bookmark lessons for quick access

CREATE TABLE IF NOT EXISTS bookmarks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    lesson_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_lesson (user_id, lesson_id),
    INDEX idx_user_bookmarks (user_id, created_at DESC),
    INDEX idx_lesson_bookmarks (lesson_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add index for efficient bookmark existence checks
CREATE INDEX idx_bookmark_lookup ON bookmarks(user_id, lesson_id);
