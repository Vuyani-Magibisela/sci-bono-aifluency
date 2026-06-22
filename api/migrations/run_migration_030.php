<?php
/**
 * Migration Runner for 030_create_notifications_table.sql
 *
 * Creates the notifications table for in-app user notifications.
 */

require_once __DIR__ . '/../config/database.php';

echo "==============================================\n";
echo "Running Migration 030: Create Notifications Table\n";
echo "==============================================\n\n";

$migrationFile = __DIR__ . '/030_create_notifications_table.sql';

if (!file_exists($migrationFile)) {
    die("ERROR: Migration file not found: $migrationFile\n");
}

$sql = file_get_contents($migrationFile);

if (empty($sql)) {
    die("ERROR: Migration file is empty\n");
}

try {
    $pdo->exec($sql);
    echo "✓ Migration 030 applied successfully.\n\n";

    // Verify
    $stmt = $pdo->query("SHOW TABLES LIKE 'notifications'");
    if ($stmt->rowCount() > 0) {
        echo "✓ notifications table: exists\n";
    } else {
        echo "✗ notifications table: NOT FOUND\n";
    }

    echo "\nMigration 030 completed!\n";
    echo "==============================================\n";

} catch (PDOException $e) {
    $msg = $e->getMessage();
    if (strpos($msg, 'already exists') !== false) {
        echo "→ notifications table already exists, skipped.\n";
    } else {
        echo "\nERROR: Migration failed!\n";
        echo "Error: " . $msg . "\n";
        exit(1);
    }
}
