<?php
/**
 * Run Migration 018: Populate Quiz Questions
 *
 * This script runs the quiz questions migration using the database
 * configuration from .env file
 */

// Get database connection
try {
    $pdo = require __DIR__ . '/../config/database.php';

    echo "=== Migration 018: Populate Quiz Questions ===\n\n";
    echo "Connected to database successfully\n\n";

    // Read the migration SQL file
    $sqlFile = __DIR__ . '/../migrations/018_populate_quiz_questions.sql';

    if (!file_exists($sqlFile)) {
        die("ERROR: Migration file not found: $sqlFile\n");
    }

    $sql = file_get_contents($sqlFile);

    // Remove comment lines but keep the actual SQL
    $lines = explode("\n", $sql);
    $cleanedLines = [];

    foreach ($lines as $line) {
        $trimmed = trim($line);
        // Skip comment-only lines and empty lines
        if (!empty($trimmed) && !preg_match('/^--/', $trimmed)) {
            $cleanedLines[] = $line;
        }
    }

    $cleanedSQL = implode("\n", $cleanedLines);

    echo "Executing migration SQL...\n";
    echo "SQL length: " . strlen($cleanedSQL) . " bytes\n\n";

    try {
        // Execute the entire SQL as one statement
        $result = $pdo->exec($cleanedSQL);

        if ($result === false) {
            echo "⚠️  WARNING: exec() returned false\n\n";
        } else {
            echo "✅ Migration executed successfully\n";
            echo "Rows affected: $result\n\n";
        }

    } catch (PDOException $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n\n";
        echo "Error Code: " . $e->getCode() . "\n\n";
    }

    // Verify results
    echo "=== Verification ===\n";
    $stmt = $pdo->query("
        SELECT
            m.id,
            m.title as module,
            q.title as quiz,
            COUNT(qq.id) as questions
        FROM modules m
        LEFT JOIN quizzes q ON q.module_id = m.id
        LEFT JOIN quiz_questions qq ON qq.quiz_id = q.id
        WHERE m.id BETWEEN 1 AND 6
        GROUP BY m.id, q.id
        ORDER BY m.id
    ");

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "\nModule | Quiz | Questions\n";
    echo str_repeat("-", 50) . "\n";

    $totalQuestions = 0;
    foreach ($results as $row) {
        $questions = $row['questions'] ?? 0;
        $totalQuestions += $questions;
        printf("%-6s | %-30s | %d\n",
            "Module " . $row['id'],
            substr($row['quiz'] ?? 'N/A', 0, 30),
            $questions
        );
    }

    echo str_repeat("-", 50) . "\n";
    echo "TOTAL: $totalQuestions questions\n\n";

    if ($totalQuestions == 61) {
        echo "✅ SUCCESS: All 61 quiz questions migrated (10+10+10+10+11+10)\n";
    } else {
        echo "⚠️  WARNING: Expected 61 questions, found $totalQuestions\n";
    }

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}
