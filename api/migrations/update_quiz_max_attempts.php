<?php
/**
 * Migration: Update all quizzes to max_attempts = 4
 *
 * Run this on the production server:
 *   cd /var/www/html/sci-bono-aifluency && php api/migrations/update_quiz_max_attempts.php
 *
 * Safe to run multiple times (idempotent).
 */

require_once __DIR__ . '/../config/config.php';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASSWORD,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Show current state
    $stmt = $pdo->query('SELECT id, title, max_attempts FROM quizzes');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Current quizzes:\n";
    foreach ($rows as $r) {
        echo "  ID: {$r['id']}, Title: {$r['title']}, Max Attempts: " . ($r['max_attempts'] ?? 'NULL') . "\n";
    }

    // Update all quizzes to max_attempts = 4
    $updated = $pdo->exec('UPDATE quizzes SET max_attempts = 4 WHERE max_attempts IS NULL OR max_attempts != 4');
    echo "\nUpdated $updated quiz(es) to max_attempts = 4\n";

    // Verify
    $stmt = $pdo->query('SELECT id, title, max_attempts FROM quizzes');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nAfter update:\n";
    foreach ($rows as $r) {
        echo "  ID: {$r['id']}, Title: {$r['title']}, Max Attempts: {$r['max_attempts']}\n";
    }

    echo "\nDone!\n";

} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
    exit(1);
}
