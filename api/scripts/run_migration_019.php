<?php
/**
 * Migration Runner for 019_fix_projects_schema.sql
 * Phase 7: Project System Schema Alignment
 */

require_once 'api/config/database.php';

$pdo = require 'api/config/database.php';

echo "===========================================\n";
echo "Running Migration 019: Fix Projects Schema\n";
echo "===========================================\n\n";

// Read migration file
$migrationFile = 'api/migrations/019_fix_projects_schema.sql';
if (!file_exists($migrationFile)) {
    die("ERROR: Migration file not found: $migrationFile\n");
}

$sql = file_get_contents($migrationFile);

// Split into statements
$statements = explode(';', $sql);

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$executed = 0;
$errors = 0;

foreach ($statements as $statement) {
    $statement = trim($statement);

    // Skip empty statements and comment-only statements
    if (empty($statement)) continue;

    // Remove leading comment lines
    $lines = explode("\n", $statement);
    $cleanLines = [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (!empty($trimmed) && strpos($trimmed, '--') !== 0) {
            $cleanLines[] = $line;
        }
    }

    $cleanStatement = trim(implode("\n", $cleanLines));
    if (empty($cleanStatement)) continue;

    try {
        // Execute statement
        if (stripos($cleanStatement, 'SELECT') === 0 ||
            stripos($cleanStatement, 'DESCRIBE') === 0 ||
            stripos($cleanStatement, 'SHOW') === 0) {
            // Query statement - fetch and display results
            $stmt = $pdo->query($cleanStatement);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($results)) {
                foreach ($results as $row) {
                    foreach ($row as $key => $value) {
                        echo "$key: $value\n";
                    }
                    if (count($results) > 1) echo "---\n";
                }
                echo "\n";
            }
        } else {
            // DML/DDL statement - execute
            $pdo->exec($cleanStatement);
        }

        $executed++;

    } catch (PDOException $e) {
        // Check if error is critical
        $errorMsg = $e->getMessage();

        // Ignore already-exists errors for columns/indexes (makes migration idempotent)
        if (strpos($errorMsg, 'Duplicate column name') !== false ||
            strpos($errorMsg, 'Duplicate key name') !== false ||
            strpos($errorMsg, 'already exists') !== false) {
            echo "⚠️  SKIP: " . $errorMsg . "\n";
            continue;
        }

        echo "❌ ERROR: " . $errorMsg . "\n";
        echo "Statement: " . substr($cleanStatement, 0, 100) . "...\n\n";
        $errors++;

        // Don't stop on SELECT errors (informational queries)
        if (stripos($cleanStatement, 'SELECT') !== false) {
            continue;
        }

        // Stop on critical errors
        echo "Migration aborted due to error.\n";
        exit(1);
    }
}

echo "\n===========================================\n";
echo "Migration Summary:\n";
echo "Statements executed: $executed\n";
echo "Errors: $errors\n";
if ($errors === 0) {
    echo "Status: ✅ SUCCESS\n";
} else {
    echo "Status: ⚠️  COMPLETED WITH WARNINGS\n";
}
echo "===========================================\n";
