<?php
/**
 * Migration Runner for 031_add_admin_response_to_feedback.sql
 *
 * Adds admin_response column to user_feedback table (public-facing reply to user).
 */

require_once __DIR__ . '/../config/database.php';

echo "==============================================\n";
echo "Running Migration 031: Add admin_response to user_feedback\n";
echo "==============================================\n\n";

$migrationFile = __DIR__ . '/031_add_admin_response_to_feedback.sql';

if (!file_exists($migrationFile)) {
    die("ERROR: Migration file not found: $migrationFile\n");
}

$sql = file_get_contents($migrationFile);

if (empty($sql)) {
    die("ERROR: Migration file is empty\n");
}

try {
    $pdo->exec($sql);
    echo "✓ Migration 031 applied successfully.\n\n";

    // Verify
    $stmt = $pdo->query("SHOW COLUMNS FROM user_feedback LIKE 'admin_response'");
    if ($stmt->rowCount() > 0) {
        echo "✓ admin_response column: exists\n";
    } else {
        echo "✗ admin_response column: NOT FOUND\n";
    }

    echo "\nMigration 031 completed!\n";
    echo "==============================================\n";

} catch (PDOException $e) {
    $msg = $e->getMessage();
    if (strpos($msg, 'Duplicate column name') !== false) {
        echo "→ admin_response column already exists, skipped.\n";
    } else {
        echo "\nERROR: Migration failed!\n";
        echo "Error: " . $msg . "\n";
        exit(1);
    }
}
