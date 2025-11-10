-- Migration 001: Create Users Table
-- Purpose: User authentication and profile management
-- Run: mysql -u user -p database_name < 001_create_users_table.sql

-- Drop table if exists (for clean re-run)
-- DROP TABLE IF EXISTS users;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Authentication
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,

    -- Profile
    name VARCHAR(255) NOT NULL,
    profile_picture_url VARCHAR(255) DEFAULT NULL,

    -- Role-based access control
    role ENUM('student', 'instructor', 'admin') DEFAULT 'student' NOT NULL,

    -- Account status
    is_active BOOLEAN DEFAULT TRUE,
    is_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(255) DEFAULT NULL,

    -- Password reset
    reset_token VARCHAR(255) DEFAULT NULL,
    reset_token_expires TIMESTAMP NULL DEFAULT NULL,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login_at TIMESTAMP NULL DEFAULT NULL,

    -- Indexes for performance
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_is_active (is_active)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user (password: Admin123!)
-- Password hash generated with: password_hash('Admin123!', PASSWORD_BCRYPT)
INSERT INTO users (email, password_hash, name, role, is_active, is_verified)
VALUES (
    'admin@sci-bono.org',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'System Administrator',
    'admin',
    TRUE,
    TRUE
) ON DUPLICATE KEY UPDATE email=email;

-- Verify table created
SELECT 'Users table created successfully' AS status;
SELECT COUNT(*) AS user_count FROM users;
