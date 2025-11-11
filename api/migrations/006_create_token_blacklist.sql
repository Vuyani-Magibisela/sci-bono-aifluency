-- Migration: 006_create_token_blacklist.sql
-- Description: Create token blacklist table for JWT logout functionality
-- Author: Dev Team
-- Date: 2025-11-10

-- Create token_blacklist table
CREATE TABLE IF NOT EXISTS token_blacklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(255) NOT NULL COMMENT 'SHA-256 hash of JWT token',
    user_id INT NOT NULL COMMENT 'User who owns this token',
    expires_at TIMESTAMP NOT NULL COMMENT 'When token expires (no need to check after this)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When token was blacklisted',

    -- Indexes for performance
    INDEX idx_token (token),
    INDEX idx_expires (expires_at),
    INDEX idx_user (user_id),

    -- Foreign key constraint
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stores blacklisted JWT tokens for logout functionality';

-- Note: Tokens are stored as SHA-256 hashes for security
-- Note: Cleanup job should periodically delete expired tokens: DELETE FROM token_blacklist WHERE expires_at < NOW()
