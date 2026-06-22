<?php
/**
 * Migration Runner for 027_signup_enhancements.sql
 *
 * Adds demographic columns to users, EMIS/district to schools, and creates GDE organization.
 */

require_once __DIR__ . '/../config/database.php';

echo "==============================================\n";
echo "Running Migration 027: Signup Enhancements\n";
echo "==============================================\n\n";

$migrationFile = __DIR__ . '/027_signup_enhancements.sql';

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
    if (empty($trimmed) || strpos($trimmed, '--') === 0) {
        continue;
    }
    $currentStatement .= $line . "\n";
    if (substr(rtrim($trimmed), -1) === ';') {
        $statements[] = trim($currentStatement);
        $currentStatement = '';
    }
}

if (!empty(trim($currentStatement))) {
    $statements[] = trim($currentStatement);
}

echo "Found " . count($statements) . " SQL statements to execute.\n\n";

$successCount = 0;
$errorCount = 0;

try {
    foreach ($statements as $index => $statement) {
        if (stripos($statement, 'USE ') === 0) {
            echo "Skipping USE statement...\n";
            continue;
        }
        if (stripos($statement, 'SELECT ') === 0) {
            // Run SELECT for verification output
            try {
                $stmt = $pdo->query($statement);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    echo "  Verification: " . implode(', ', array_map(fn($k,$v) => "$k=$v", array_keys($row), $row)) . "\n";
                }
            } catch (PDOException $e) {
                echo "  Verification query failed: " . $e->getMessage() . "\n";
            }
            continue;
        }

        try {
            echo "Executing statement " . ($index + 1) . "...\n";
            $pdo->exec($statement);
            $successCount++;
            echo "  ✓ Success\n";
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            echo "  ✗ Error: $msg\n";
            $errorCount++;

            if (strpos($msg, 'Duplicate column name') !== false) {
                echo "  → Column already exists, continuing...\n";
            } elseif (strpos($msg, 'Duplicate key name') !== false) {
                echo "  → Index already exists, continuing...\n";
            } elseif (strpos($msg, 'Duplicate entry') !== false) {
                echo "  → Record already exists, continuing...\n";
            } elseif (strpos($msg, "check that column/key exists") !== false) {
                echo "  → Column/key check skipped, continuing...\n";
            } else {
                throw $e;
            }
        }
    }

    echo "\n==============================================\n";
    echo "Migration 027 Summary:\n";
    echo "==============================================\n";
    echo "Successful: $successCount\n";
    echo "Skipped/Errors: $errorCount\n";

    // Verify new columns on users
    echo "\nVerifying users table columns:\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'contact_number'");
    echo "  contact_number: " . ($stmt->rowCount() > 0 ? "✓ exists" : "✗ missing") . "\n";

    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'gender'");
    echo "  gender:         " . ($stmt->rowCount() > 0 ? "✓ exists" : "✗ missing") . "\n";

    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'grade'");
    echo "  grade:          " . ($stmt->rowCount() > 0 ? "✓ exists" : "✗ missing") . "\n";

    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'date_of_birth'");
    echo "  date_of_birth:  " . ($stmt->rowCount() > 0 ? "✓ exists" : "✗ missing") . "\n";

    echo "\nVerifying schools table columns:\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM schools LIKE 'emis_number'");
    echo "  emis_number:    " . ($stmt->rowCount() > 0 ? "✓ exists" : "✗ missing") . "\n";

    $stmt = $pdo->query("SHOW COLUMNS FROM schools LIKE 'district'");
    echo "  district:       " . ($stmt->rowCount() > 0 ? "✓ exists" : "✗ missing") . "\n";

    echo "\nVerifying GDE organization:\n";
    $stmt = $pdo->query("SELECT id, name FROM organizations WHERE slug = 'gde'");
    $org = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($org) {
        echo "  GDE org: ✓ ID={$org['id']}, Name={$org['name']}\n";
    } else {
        echo "  GDE org: ✗ not found\n";
    }

    echo "\nMigration 027 completed successfully!\n";
    echo "==============================================\n";

} catch (PDOException $e) {
    echo "\n==============================================\n";
    echo "ERROR: Migration failed!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "==============================================\n";
    exit(1);
}
