<?php
/**
 * Migration Runner for 026_add_course_columns.sql
 *
 * This script executes the migration using PHP PDO since we have working
 * database credentials in the application configuration.
 */

// Load database configuration
require_once __DIR__ . '/../config/database.php';

echo "==============================================\n";
echo "Running Migration 026: Add Course Columns\n";
echo "==============================================\n\n";

// Read migration SQL file
$migrationFile = __DIR__ . '/026_add_course_columns.sql';

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

        // Handle SELECT/DESCRIBE/SHOW statements
        if (stripos($statement, 'SELECT ') === 0 || stripos($statement, 'DESCRIBE ') === 0 || stripos($statement, 'SHOW ') === 0) {
            // Skip informational queries
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
            if (strpos($e->getMessage(), "Duplicate column name") !== false) {
                echo "  → Column already exists, continuing...\n";
            } elseif (strpos($e->getMessage(), "Duplicate key name") !== false) {
                echo "  → Index already exists, continuing...\n";
            } elseif (strpos($e->getMessage(), "Can't DROP") !== false) {
                echo "  → Column/Index doesn't exist, continuing...\n";
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

    // Check courses table structure
    $stmt = $pdo->query("DESCRIBE courses");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Courses table columns:\n";
    foreach ($columns as $column) {
        echo "  - {$column['Field']} ({$column['Type']})\n";
    }

    // Check if new columns exist
    $requiredColumns = ['slug', 'instructor_id'];
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

    // Show courses with new columns
    echo "\nCourses with new columns:\n";
    $stmt = $pdo->query("SELECT id, title, slug, instructor_id FROM courses");
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($courses as $course) {
        $instructor = $course['instructor_id'] ?? 'Not assigned';
        echo "  - ID: {$course['id']}, Title: {$course['title']}, Slug: {$course['slug']}, Instructor: $instructor\n";
    }

    echo "\n==============================================\n";
    echo "Next steps:\n";
    echo "1. Assign instructors to courses if needed\n";
    echo "2. Update backend controllers with enrollment verification\n";
    echo "3. Update admin UI with course filtering\n";
    echo "==============================================\n";

} catch (PDOException $e) {
    $pdo->rollBack();
    echo "\n==============================================\n";
    echo "ERROR: Migration failed!\n";
    echo "==============================================\n";
    echo "Error message: " . $e->getMessage() . "\n";
    echo "\nTransaction has been rolled back.\n";
    echo "Database state is unchanged.\n";
    echo "\nTo rollback manually: mysql -u ai_fluency_user -p ai_fluency_lms < 026_rollback.sql\n";
    exit(1);
}
