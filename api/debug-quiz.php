<?php
/**
 * Quiz System Debug Script
 * DELETE THIS FILE AFTER DEBUGGING
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=UTF-8');
echo "=== QUIZ DEBUG ===\n";
echo "Time: " . date('Y-m-d H:i:s T') . "\n\n";

// ---- 1. FILE CHECKS ----
echo "--- 1. FILE VERSION CHECKS ---\n";

$checks = [
    'QuizAttempt.php' => [
        'path' => __DIR__ . '/models/QuizAttempt.php',
        'must_have' => [
            "data['time_completed']" => "uses time_completed (not completed_at)",
            "createAttempt FAILED" => "has debug logging",
        ],
        'must_not_have' => [
            "'completed_at'" => "completed_at removed from fillable",
        ],
    ],
    'Quiz.php' => [
        'path' => __DIR__ . '/models/Quiz.php',
        'must_have' => [],
        'must_not_have' => [],
    ],
    'QuizController.php' => [
        'path' => __DIR__ . '/controllers/QuizController.php',
        'must_have' => [
            "(string)(\$result['student_answer']" => "user_answer null fix (string cast)",
            "'passed' => \$passed ? 1 : 0" => "passed boolean cast to int",
        ],
        'must_not_have' => [],
    ],
    'QuizQuestion.php' => [
        'path' => __DIR__ . '/models/QuizQuestion.php',
        'must_have' => [
            "'question_text' => \$question->question_text" => "question_text in validateAnswers",
        ],
        'must_not_have' => [],
    ],
];

foreach ($checks as $name => $info) {
    $path = $info['path'];
    if (!file_exists($path)) {
        echo "[FAIL] $name NOT FOUND at $path\n";
        continue;
    }
    $content = file_get_contents($path);
    $modified = date('Y-m-d H:i:s', filemtime($path));
    echo "\n$name (modified: $modified):\n";

    // Check ORDER BY created_at in quiz-related files
    if (preg_match('/ORDER BY.*created_at/i', $content)) {
        echo "  [FAIL] Still has ORDER BY created_at — OLD FILE\n";
    } else {
        echo "  [OK]   No ORDER BY created_at references\n";
    }

    foreach ($info['must_have'] as $search => $desc) {
        if (strpos($content, $search) !== false) {
            echo "  [OK]   $desc\n";
        } else {
            echo "  [FAIL] MISSING: $desc — OLD FILE!\n";
        }
    }
    foreach ($info['must_not_have'] as $search => $desc) {
        if (strpos($content, $search) === false) {
            echo "  [OK]   $desc\n";
        } else {
            echo "  [FAIL] Still has '$search' — OLD FILE!\n";
        }
    }
}

// ---- 2. DATABASE ----
echo "\n\n--- 2. DATABASE CONNECTION & SCHEMA ---\n";

try {
    require_once __DIR__ . '/vendor/autoload.php';
    require_once __DIR__ . '/config/config.php';

    $host = defined('DB_HOST') ? DB_HOST : 'localhost';
    $port = defined('DB_PORT') ? DB_PORT : 3306;
    $dbname = defined('DB_NAME') ? DB_NAME : 'ai_fluency_lms';
    $username = defined('DB_USER') ? DB_USER : 'root';
    $password = defined('DB_PASSWORD') ? DB_PASSWORD : '';

    echo "DB: host=$host, port=$port, db=$dbname, user=$username\n";

    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username, $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "[OK] Connected to database\n\n";

    // quiz_attempts schema
    echo "quiz_attempts columns:\n";
    $cols = $pdo->query("DESCRIBE quiz_attempts")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = [];
    foreach ($cols as $col) {
        $colNames[] = $col['Field'];
        echo sprintf("  %-25s %-25s %s\n", $col['Field'], $col['Type'], $col['Null'] === 'NO' ? 'NOT NULL' : 'NULLABLE');
    }

    echo "\nCritical column checks:\n";
    echo (in_array('completed_at', $colNames) ? "  [WARN] completed_at EXISTS\n" : "  [OK]   completed_at does not exist\n");
    echo (in_array('time_completed', $colNames) ? "  [OK]   time_completed exists\n" : "  [FAIL] time_completed MISSING\n");
    echo (in_array('created_at', $colNames) ? "  [OK]   created_at exists\n" : "  [WARN] created_at does NOT exist — ORDER BY created_at will fail\n");
    echo (in_array('started_at', $colNames) ? "  [OK]   started_at exists\n" : "  [FAIL] started_at MISSING\n");

    // quiz_attempt_answers schema
    echo "\nquiz_attempt_answers columns:\n";
    try {
        $cols2 = $pdo->query("DESCRIBE quiz_attempt_answers")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols2 as $col) {
            echo sprintf("  %-25s %-25s %s\n", $col['Field'], $col['Type'], $col['Null'] === 'NO' ? 'NOT NULL' : 'NULLABLE');
        }
    } catch (Exception $e) {
        echo "  [FAIL] TABLE DOES NOT EXIST: " . $e->getMessage() . "\n";
    }

    // ---- 3. DATA ----
    echo "\n--- 3. EXISTING DATA ---\n";
    $cnt = $pdo->query("SELECT COUNT(*) as c FROM quiz_attempts")->fetch(PDO::FETCH_ASSOC);
    echo "Total quiz_attempts: {$cnt['c']}\n\n";

    echo "Recent attempts:\n";
    $stmt = $pdo->query("SELECT id, user_id, quiz_id, score, passed, status FROM quiz_attempts ORDER BY id DESC LIMIT 5");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        echo sprintf("  ID=%-4s user=%-4s quiz=%-4s score=%-6s passed=%-2s status=%s\n",
            $r['id'], $r['user_id'], $r['quiz_id'], $r['score'], $r['passed'], $r['status']);
    }
    if (empty($rows)) echo "  (none)\n";

    // ---- 4. TEST INSERT ----
    echo "\n--- 4. TEST INSERT ---\n";
    $quiz = $pdo->query("SELECT id FROM quizzes WHERE is_published = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $user = $pdo->query("SELECT id FROM users LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    if ($quiz && $user) {
        try {
            $pdo->beginTransaction();

            $sql = "INSERT INTO quiz_attempts
                (`quiz_id`, `user_id`, `score`, `total_questions`, `correct_answers`, `answers`,
                 `time_taken_minutes`, `passed`, `status`, `attempt_number`, `time_completed`, `time_started`)
                VALUES (:quiz_id, :user_id, :score, :total_questions, :correct_answers, :answers,
                        :time_taken_minutes, :passed, :status, :attempt_number, :time_completed, :time_started)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'quiz_id' => $quiz['id'],
                'user_id' => $user['id'],
                'score' => 99.99,
                'total_questions' => 1,
                'correct_answers' => 1,
                'answers' => '{"test":true}',
                'time_taken_minutes' => 0,
                'passed' => 1,
                'status' => 'submitted',
                'attempt_number' => 999,
                'time_completed' => date('Y-m-d H:i:s'),
                'time_started' => date('Y-m-d H:i:s'),
            ]);
            $insertId = $pdo->lastInsertId();
            echo "[OK] quiz_attempts INSERT succeeded (id=$insertId)\n";

            // Test quiz_attempt_answers
            $stmt2 = $pdo->prepare("INSERT INTO quiz_attempt_answers
                (attempt_id, question_id, question_text, user_answer, correct_answer, is_correct, points_awarded, points_possible, time_spent_seconds)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt2->execute([$insertId, 1, 'Test Q', '0', '0', 1, 10.00, 10.00, 5]);
            echo "[OK] quiz_attempt_answers INSERT succeeded\n";

            $pdo->rollback();
            echo "[OK] Rolled back (no permanent data)\n";
        } catch (PDOException $e) {
            $pdo->rollback();
            echo "[FAIL] INSERT ERROR: " . $e->getMessage() . "\n";
        }
    } else {
        echo "[FAIL] No quiz or user found for testing\n";
    }

    // ---- 5. OPCACHE ----
    echo "\n--- 5. OPCACHE ---\n";
    if (function_exists('opcache_get_status')) {
        $oc = @opcache_get_status(false);
        if ($oc && $oc['opcache_enabled']) {
            echo "[WARN] OPcache is ENABLED — uploaded files may be served from cache!\n";
            echo "  Cached scripts: {$oc['opcache_statistics']['num_cached_scripts']}\n";
            echo "  To fix: Restart PHP-FPM or add opcache_reset() call\n";

            // Try to invalidate our files
            $filesToInvalidate = [
                __DIR__ . '/models/QuizAttempt.php',
                __DIR__ . '/models/Quiz.php',
                __DIR__ . '/models/QuizQuestion.php',
                __DIR__ . '/models/BaseModel.php',
                __DIR__ . '/controllers/QuizController.php',
            ];
            echo "\n  Invalidating cached files:\n";
            foreach ($filesToInvalidate as $f) {
                if (file_exists($f)) {
                    $result = opcache_invalidate($f, true);
                    echo "    " . basename($f) . ": " . ($result ? "invalidated" : "failed/not cached") . "\n";
                }
            }
            echo "\n  [OK] Cache invalidated for quiz files. Try submitting a quiz again.\n";
        } else {
            echo "[OK] OPcache is not active\n";
        }
    } else {
        echo "[OK] OPcache extension not loaded\n";
    }

    // ---- 6. ERROR LOGS ----
    echo "\n--- 6. RECENT ERROR LOGS ---\n";
    $logFile = __DIR__ . '/logs/php_errors.log';
    if (file_exists($logFile)) {
        $lines = file($logFile);
        $recent = array_slice($lines, -10);
        echo "Last 10 lines of php_errors.log:\n";
        foreach ($recent as $line) {
            echo "  " . rtrim($line) . "\n";
        }
    } else {
        echo "php_errors.log not found\n";
    }

    $logFile2 = __DIR__ . '/logs/error.log';
    if (file_exists($logFile2)) {
        $lines = file($logFile2);
        $recent = array_slice($lines, -10);
        echo "\nLast 10 lines of error.log:\n";
        foreach ($recent as $line) {
            echo "  " . rtrim($line) . "\n";
        }
    }

} catch (Exception $e) {
    echo "[FAIL] " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n\n=== DELETE THIS FILE AFTER DEBUGGING ===\n";
