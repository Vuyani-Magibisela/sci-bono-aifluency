<?php
/**
 * Migration Runner for 024_fix_quizzes_schema.sql
 */

require_once __DIR__ . '/../config/database.php';

echo "==============================================\n";
echo "Running Migration 024: Fix Quizzes Schema\n";
echo "==============================================\n\n";

// Read migration SQL file
$migrationFile = __DIR__ . '/024_fix_quizzes_schema.sql';

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
        // Skip USE statements
        if (stripos($statement, 'USE ') === 0) {
            echo "Skipping USE statement...\n";
            continue;
        }

        // Skip SELECT statements for display
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

            // If column already exists, continue
            if (strpos($e->getMessage(), "Duplicate column name") !== false) {
                echo "  → Column already exists, continuing...\n";
            } else {
                throw $e;
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

    // Check quizzes table structure
    $stmt = $pdo->query("DESCRIBE quizzes");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Quizzes table columns:\n";
    foreach ($columns as $column) {
        echo "  - {$column['Field']} ({$column['Type']})\n";
    }

    // Check if new columns exist
    $requiredColumns = ['slug', 'is_published', 'lesson_id', 'max_attempts', 'order'];
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

    // Count quizzes
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM quizzes");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "\nTotal quizzes in database: {$count['count']}\n";

    // Show sample quizzes with new columns
    echo "\nSample quizzes:\n";
    $stmt = $pdo->query("SELECT id, title, slug, is_published, max_attempts FROM quizzes LIMIT 3");
    $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($samples as $quiz) {
        echo "  - ID: {$quiz['id']}, Title: {$quiz['title']}, Slug: {$quiz['slug']}, Published: " . ($quiz['is_published'] ? 'Yes' : 'No') . "\n";
    }

    echo "\n==============================================\n";
    echo "Next steps:\n";
    echo "1. Publish quizzes: UPDATE quizzes SET is_published = 1;\n";
    echo "2. Test the API: curl http://aifluency.local/api/quizzes\n";
    echo "3. Test admin page: http://aifluency.local/admin/quizzes.html\n";
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
