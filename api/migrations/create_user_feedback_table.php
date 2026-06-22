<?php
/**
 * Migration Runner: Create user_feedback table
 * Run: php api/migrations/create_user_feedback_table.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

try {
    $sql = file_get_contents(__DIR__ . '/029_create_user_feedback_table.sql');
    $pdo->exec($sql);
    echo "✓ user_feedback table created successfully.\n";
} catch (PDOException $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
