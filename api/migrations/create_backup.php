<?php
/**
 * Database Backup Script
 *
 * Creates a backup of the database before running migrations 025 and 026
 */

// Load database configuration
require_once __DIR__ . '/../config/database.php';

echo "==============================================\n";
echo "Creating Database Backup\n";
echo "==============================================\n\n";

$backupFile = __DIR__ . '/backup_025_026.sql';

echo "Backup file: $backupFile\n\n";

try {
    // Get all tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "Found " . count($tables) . " tables to backup\n\n";

    $backup = "-- AI Fluency LMS Database Backup\n";
    $backup .= "-- Created: " . date('Y-m-d H:i:s') . "\n";
    $backup .= "-- Before migrations 025 and 026\n\n";
    $backup .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        echo "Backing up table: $table\n";

        // Get CREATE TABLE statement
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $createTable = $stmt->fetch(PDO::FETCH_ASSOC);

        $backup .= "-- Table: $table\n";
        $backup .= "DROP TABLE IF EXISTS `$table`;\n";
        $backup .= $createTable['Create Table'] . ";\n\n";

        // Get all rows
        $stmt = $pdo->query("SELECT * FROM `$table`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) > 0) {
            $backup .= "-- Data for table: $table\n";

            foreach ($rows as $row) {
                $values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = 'NULL';
                    } else {
                        $values[] = "'" . addslashes($value) . "'";
                    }
                }

                $columns = array_keys($row);
                $columnList = '`' . implode('`, `', $columns) . '`';
                $valueList = implode(', ', $values);

                $backup .= "INSERT INTO `$table` ($columnList) VALUES ($valueList);\n";
            }

            $backup .= "\n";
        }
    }

    $backup .= "SET FOREIGN_KEY_CHECKS=1;\n";

    // Write backup to file
    if (file_put_contents($backupFile, $backup)) {
        $size = filesize($backupFile);
        $sizeKB = round($size / 1024, 2);

        echo "\n==============================================\n";
        echo "Backup completed successfully!\n";
        echo "==============================================\n";
        echo "File: $backupFile\n";
        echo "Size: $sizeKB KB\n";
        echo "\nYou can restore this backup by running:\n";
        echo "php restore_backup.php\n";
        echo "==============================================\n";
    } else {
        throw new Exception("Failed to write backup file");
    }

} catch (Exception $e) {
    echo "\n==============================================\n";
    echo "ERROR: Backup failed!\n";
    echo "==============================================\n";
    echo "Error message: " . $e->getMessage() . "\n";
    exit(1);
}
