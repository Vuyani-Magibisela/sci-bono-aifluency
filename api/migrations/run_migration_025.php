<?php
/**
 * Migration Runner for 025_fix_lessons_schema.sql
 *
 * This script executes the migration using PHP PDO since we have working
 * database credentials in the application configuration.
 */

// Load database configuration
require_once __DIR__ . '/../config/database.php';

echo "==============================================\n";
echo "Running Migration 025: Fix Lesson Slug Uniqueness\n";
echo "==============================================\n\n";

// Read migration SQL file
$migrationFile = __DIR__ . '/025_fix_lessons_schema.sql';

if (!file_exists($migrationFile)) {
    die("ERROR: Migration file not found: $migrationFile\n");
}

$sql = file_get_contents($migrationFile);

if (empty($sql)) {
    die("ERROR: Migration file is empty\n");
}

// Remove comments and split into individual statements
$statements = [];
$lines = explode("\n", $sql);
$currentStatement = '';

foreach ($lines as $line) {
    $trimmed = trim($line);

    // Skip empty lines and comments
    if (empty($trimmed) || strpos($trimmed, '--') === 0) {
        continue;
    }

    $currentStatement .= $line . "\n";

    // Check if statement ends with semicolon
    if (substr(rtrim($trimmed), -1) === ';') {
        $statements[] = trim($currentStatement);
        $currentStatement = '';
    }
}

// Add last statement if not empty
if (!empty(trim($currentStatement))) {
    $statements[] = trim($currentStatement);
}

echo "Found " . count($statements) . " SQL statements to execute.\n\n";

// Execute each statement
$successCount = 0;
$errorCount = 0;

try {
    $pdo->beginTransaction();

    foreach ($statements as $index => $statement) {
        // Skip USE statements (we're already connected to the correct DB)
        if (stripos($statement, 'USE ') === 0) {
            echo "Skipping USE statement...\n";
            continue;
        }

        // Skip SELECT statements for display (they're just for verification)
        if (stripos($statement, 'SELECT ') === 0 && stripos($statement, 'INSERT') === false) {
            echo "Executing verification query " . ($index + 1) . "...\n";
            try {
                $result = $pdo->query($statement);
                if ($result) {
                    $rows = $result->fetchAll(PDO::FETCH_ASSOC);
                    if (!empty($rows)) {
                        foreach ($rows as $row) {
                            if (isset($row[''])) {
                                echo "  " . $row[''] . "\n";
                            } else {
                                print_r($row);
                            }
                        }
                    }
                }
            } catch (PDOException $e) {
                echo "  Note: " . $e->getMessage() . "\n";
            }
            continue;
        }

        // Skip SHOW statements
        if (stripos($statement, 'SHOW ') === 0) {
            echo "Executing SHOW statement " . ($index + 1) . "...\n";
            try {
                $result = $pdo->query($statement);
                if ($result) {
                    $rows = $result->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($rows as $row) {
                        echo "  ";
                        print_r($row);
                    }
                }
            } catch (PDOException $e) {
                echo "  Note: " . $e->getMessage() . "\n";
            }
            continue;
        }

        try {
            echo "Executing statement " . ($index + 1) . "...\n";
            $pdo->exec($statement);
            $successCount++;
            echo "  ✓ Success\n";
        } catch (PDOException $e) {
            echo "  ✗ Error: " . $e->getMessage() . "\n";
            $errorCount++;

            // Handle specific errors gracefully
            if (strpos($e->getMessage(), "Duplicate key name") !== false) {
                echo "  → Index already exists, continuing...\n";
            } elseif (strpos($e->getMessage(), "Can't DROP") !== false) {
                echo "  → Index doesn't exist, continuing...\n";
            } else {
                throw $e; // Re-throw if it's a critical error
            }
        }
    }

    $pdo->commit();

    echo "\n==============================================\n";
    echo "Migration Summary:\n";
    echo "==============================================\n";
    echo "Total statements: " . count($statements) . "\n";
    echo "Successful: $successCount\n";
    echo "Errors: $errorCount\n";
    echo "\nMigration completed successfully!\n";
    echo "==============================================\n\n";

    // Verify the changes
    echo "Verifying changes...\n\n";

    // Check lessons table indexes
    $stmt = $pdo->query("SHOW INDEX FROM lessons WHERE Key_name LIKE '%slug%'");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Lesson slug indexes:\n";
    foreach ($indexes as $index) {
        echo "  - {$index['Key_name']}: Column={$index['Column_name']}, Unique=" . ($index['Non_unique'] == 0 ? 'Yes' : 'No') . "\n";
    }

    // Test: Try to get lessons with same slug in different modules
    $stmt = $pdo->query("
        SELECT slug, COUNT(*) as count, GROUP_CONCAT(module_id) as modules
        FROM lessons
        GROUP BY slug
        HAVING count > 1
        LIMIT 5
    ");
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($duplicates)) {
        echo "\nLessons with same slug in different modules:\n";
        foreach ($duplicates as $dup) {
            echo "  - Slug '{$dup['slug']}' exists in modules: {$dup['modules']}\n";
        }
    } else {
        echo "\nNo duplicate slugs found across modules (this is expected if you haven't added test data yet).\n";
    }

    echo "\n==============================================\n";
    echo "Next steps:\n";
    echo "1. Test creating lessons with same slug in different modules\n";
    echo "2. Run Migration 026 to add course columns: php api/migrations/run_migration_026.php\n";
    echo "==============================================\n";

} catch (PDOException $e) {
    $pdo->rollBack();
    echo "\n==============================================\n";
    echo "ERROR: Migration failed!\n";
    echo "==============================================\n";
    echo "Error message: " . $e->getMessage() . "\n";
    echo "\nTransaction has been rolled back.\n";
    echo "Database state is unchanged.\n";
    echo "\nTo rollback manually: php run_rollback_025.php\n";
    exit(1);
}
