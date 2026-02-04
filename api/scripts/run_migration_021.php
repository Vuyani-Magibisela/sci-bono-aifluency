<?php
// Run Migration 021 - Analytics Optimizations
require_once __DIR__ . '/api/config/database.php';

echo "Running Migration 021: Analytics Optimizations...\n\n";

// Read the migration file
$migrationSQL = file_get_contents(__dir__ . '/api/migrations/021_analytics_optimizations.sql');

// Split by semicolons to execute statements individually
$statements = array_filter(array_map('trim', explode(';', $migrationSQL)));

$successCount = 0;
$errorCount = 0;

foreach ($statements as $statement) {
    // Skip empty statements and comments
    if (empty($statement) || strpos($statement, '--') === 0) {
        continue;
    }

    try {
        $pdo->exec($statement);
        $successCount++;

        // Echo progress for CREATE VIEW and ALTER TABLE statements
        if (stripos($statement, 'CREATE') !== false || stripos($statement, 'ALTER TABLE') !== false) {
            $firstLine = strtok($statement, "\n");
            echo "✓ Executed: " . substr($firstLine, 0, 60) . "...\n";
        }
    } catch (PDOException $e) {
        $errorCount++;
        echo "✗ Error: " . $e->getMessage() . "\n";
        echo "Statement: " . substr($statement, 0, 100) . "...\n\n";
    }
}

echo "\n";
echo "========================================\n";
echo "Migration 021 Complete\n";
echo "========================================\n";
echo "Successful statements: $successCount\n";
echo "Errors: $errorCount\n";
echo "\n";

if ($errorCount === 0) {
    echo "✓ Migration completed successfully!\n";
} else {
    echo "⚠ Migration completed with $errorCount errors. Check output above.\n";
}
