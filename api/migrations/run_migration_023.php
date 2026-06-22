<?php
/**
 * Migration Runner for 023_fix_modules_schema.sql
 *
 * This script executes the migration using PHP PDO since we have working
 * database credentials in the application configuration.
 */

// Load database configuration
require_once __DIR__ . '/../config/database.php';

echo "==============================================\n";
echo "Running Migration 023: Fix Modules Schema\n";
echo "==============================================\n\n";

// Read migration SQL file
$migrationFile = __DIR__ . '/023_fix_modules_schema.sql';

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
            echo "Skipping SELECT verification statement...\n";
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

            // If critical error (like column already exists), we might want to continue
            // but for ALTER TABLE errors related to existing columns, we'll continue
            if (strpos($e->getMessage(), "Duplicate column name") !== false) {
                echo "  → Column already exists, continuing...\n";
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

    // Check modules table structure
    $stmt = $pdo->query("DESCRIBE modules");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Modules table columns:\n";
    foreach ($columns as $column) {
        echo "  - {$column['Field']} ({$column['Type']})\n";
    }

    // Check if new columns exist
    $requiredColumns = ['slug', 'is_published', 'objectives', 'duration_hours'];
    $missingColumns = [];
    $existingColumnNames = array_column($columns, 'Field');

    foreach ($requiredColumns as $col) {
        if (!in_array($col, $existingColumnNames)) {
            $missingColumns[] = $col;
        }
    }

    if (empty($missingColumns)) {
        echo "\n✓ All required columns are present!\n";
    } else {
        echo "\n✗ Missing columns: " . implode(', ', $missingColumns) . "\n";
    }

    // Count modules
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM modules");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "\nTotal modules in database: {$count['count']}\n";

    // Show sample modules with new columns
    echo "\nSample modules:\n";
    $stmt = $pdo->query("SELECT id, title, slug, is_published, duration_hours FROM modules LIMIT 3");
    $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($samples as $module) {
        echo "  - ID: {$module['id']}, Title: {$module['title']}, Slug: {$module['slug']}, Published: " . ($module['is_published'] ? 'Yes' : 'No') . "\n";
    }

    echo "\n==============================================\n";
    echo "Next steps:\n";
    echo "1. Test the API: curl http://aifluency.local/api/modules\n";
    echo "2. Test admin page: http://aifluency.local/admin/modules.html\n";
    echo "==============================================\n";

} catch (PDOException $e) {
    $pdo->rollBack();
    echo "\n==============================================\n";
    echo "ERROR: Migration failed!\n";
    echo "==============================================\n";
    echo "Error message: " . $e->getMessage() . "\n";
    echo "\nTransaction has been rolled back.\n";
    echo "Database state is unchanged.\n";
    exit(1);
}
