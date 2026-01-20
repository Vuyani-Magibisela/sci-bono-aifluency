<?php
/**
 * Migration Runner for 020_profile_enhancements.sql
 * Phase 8: Profile Building & Viewing
 *
 * Run: php run_migration_020.php
 */

require_once __DIR__ . '/api/config/database.php';

echo "=== Running Migration 020: Profile Enhancements ===\n\n";

// Read migration file
$migrationFile = __DIR__ . '/api/migrations/020_profile_enhancements.sql';

if (!file_exists($migrationFile)) {
    die("ERROR: Migration file not found: $migrationFile\n");
}

$sql = file_get_contents($migrationFile);

if (empty($sql)) {
    die("ERROR: Migration file is empty\n");
}

// Split SQL into individual statements
// Remove comments and empty lines
$lines = explode("\n", $sql);
$currentStatement = '';
$statements = [];

foreach ($lines as $line) {
    $line = trim($line);

    // Skip comments and empty lines
    if (empty($line) || strpos($line, '--') === 0) {
        continue;
    }

    $currentStatement .= ' ' . $line;

    // Check if statement is complete (ends with semicolon)
    if (substr($line, -1) === ';') {
        $statements[] = trim($currentStatement);
        $currentStatement = '';
    }
}

echo "Found " . count($statements) . " SQL statements to execute\n\n";

// Execute statements
$executed = 0;
$errors = 0;

try {
    $pdo->beginTransaction();

    foreach ($statements as $index => $statement) {
        $cleanStatement = trim($statement);

        if (empty($cleanStatement)) {
            continue;
        }

        try {
            // Check if it's a SELECT statement (informational query)
            if (stripos($cleanStatement, 'SELECT') === 0 ||
                stripos($cleanStatement, 'SHOW') === 0) {

                $stmt = $pdo->query($cleanStatement);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Display SELECT results
                if (!empty($results)) {
                    echo "Query Results:\n";
                    foreach ($results as $row) {
                        foreach ($row as $key => $value) {
                            echo "  $key: $value\n";
                        }
                        echo "\n";
                    }
                }
            } else {
                // Execute non-SELECT statements
                $pdo->exec($cleanStatement);
                $executed++;

                // Show progress for important operations
                if (stripos($cleanStatement, 'ALTER TABLE') === 0) {
                    echo "✓ Executed ALTER TABLE statement\n";
                } elseif (stripos($cleanStatement, 'CREATE TABLE') === 0) {
                    echo "✓ Created table\n";
                } elseif (stripos($cleanStatement, 'UPDATE') === 0) {
                    echo "✓ Updated existing records\n";
                }
            }

        } catch (PDOException $e) {
            $errorMsg = $e->getMessage();

            // Check if error is ignorable (duplicate column/table already exists)
            if (strpos($errorMsg, 'Duplicate column name') !== false ||
                strpos($errorMsg, 'Duplicate key name') !== false ||
                strpos($errorMsg, 'already exists') !== false ||
                strpos($errorMsg, 'Table') !== false && strpos($errorMsg, 'already exists') !== false) {

                echo "⚠ Notice: " . $errorMsg . " (continuing...)\n";
                continue;
            }

            // Critical error
            echo "✗ ERROR executing statement " . ($index + 1) . ":\n";
            echo "  " . substr($cleanStatement, 0, 100) . "...\n";
            echo "  Error: " . $errorMsg . "\n\n";
            $errors++;

            throw $e; // Rollback transaction
        }
    }

    $pdo->commit();

    echo "\n=== Migration Summary ===\n";
    echo "Statements executed: $executed\n";
    echo "Errors: $errors\n";

    if ($errors === 0) {
        echo "\n✓✓✓ Migration 020 completed successfully! ✓✓✓\n";
        echo "\nNext step: Run tests with: php test_profile_system.php\n";
        exit(0);
    } else {
        echo "\n✗ Migration completed with errors\n";
        exit(1);
    }

} catch (PDOException $e) {
    $pdo->rollBack();
    echo "\n✗✗✗ Migration FAILED ✗✗✗\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
