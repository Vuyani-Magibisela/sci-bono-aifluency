<?php
/**
 * Database Import Script: Import Validated JSON to MySQL
 *
 * Purpose: Import validated JSON data into MySQL database
 * Input: Validated JSON files in output/ directory
 * Output: Database records
 *
 * Prerequisites:
 * - Database created
 * - Tables created (run SQL migration scripts first)
 * - .env file configured
 *
 * Usage: php import-to-db.php
 */

// Configuration
$outputDir = __DIR__ . '/output';
$configFile = dirname(dirname(__DIR__)) . '/api/config/database.php';

echo "=== Database Import ===\n";
echo "Output directory: $outputDir\n";
echo "Config file: $configFile\n\n";

// Check prerequisites
if (!file_exists($configFile)) {
    die("ERROR: Database config file not found: $configFile\n" .
        "Please create config file first.\n");
}

// Load database configuration
require_once $configFile;

// Assume $pdo is created in database.php
// If not, create PDO connection here
if (!isset($pdo)) {
    // Fallback: Create PDO connection
    // You'll need to replace these with actual values or load from .env
    try {
        $host = getenv('DB_HOST') ?: 'localhost';
        $dbname = getenv('DB_NAME') ?: 'ai_fluency_lms';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASSWORD') ?: '';

        $pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );

        echo "✓ Connected to database: $dbname\n\n";
    } catch (PDOException $e) {
        die("ERROR: Database connection failed: " . $e->getMessage() . "\n");
    }
}

// === 1. Load JSON Files ===
echo "Loading JSON files...\n";

$lessonsFile = $outputDir . '/lessons.json';
$quizzesFile = $outputDir . '/quizzes.json';
$questionsFile = $outputDir . '/quiz_questions.json';

if (!file_exists($lessonsFile) || !file_exists($quizzesFile) || !file_exists($questionsFile)) {
    die("ERROR: One or more JSON files missing. Run extract scripts first.\n");
}

$lessons = json_decode(file_get_contents($lessonsFile), true);
$quizzes = json_decode(file_get_contents($quizzesFile), true);
$questions = json_decode(file_get_contents($questionsFile), true);

if (!$lessons || !$quizzes || !$questions) {
    die("ERROR: Failed to decode JSON files\n");
}

echo "  ✓ Loaded lessons: " . count($lessons) . "\n";
echo "  ✓ Loaded quizzes: " . count($quizzes) . "\n";
echo "  ✓ Loaded questions: " . count($questions) . "\n\n";

// === 2. Confirm Import ===
echo "This will import the following to the database:\n";
echo "  - " . count($lessons) . " lessons\n";
echo "  - " . count($quizzes) . " quizzes\n";
echo "  - " . count($questions) . " quiz questions\n\n";

echo "WARNING: This may overwrite existing data.\n";
echo "Continue? (yes/no): ";

$handle = fopen("php://stdin", "r");
$confirmation = trim(fgets($handle));
fclose($handle);

if (strtolower($confirmation) !== 'yes') {
    die("Import cancelled.\n");
}

echo "\n";

// === 3. Begin Transaction ===
try {
    $pdo->beginTransaction();
    echo "Transaction started...\n\n";

    // === 4. Import Course (Single Record) ===
    echo "Importing course...\n";

    $courseStmt = $pdo->prepare("
        INSERT INTO courses (id, title, description, difficulty_level, duration_hours, is_published)
        VALUES (1, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            description = VALUES(description),
            difficulty_level = VALUES(difficulty_level),
            duration_hours = VALUES(duration_hours),
            is_published = VALUES(is_published)
    ");

    $courseStmt->execute([
        'AI Fluency',
        'Master artificial intelligence concepts from foundations to advanced applications. Learn about AI history, generative AI, responsible AI practices, and AI\'s impact on society.',
        'intermediate',
        40,
        1
    ]);

    echo "  ✓ Course record created/updated\n\n";

    // === 5. Import Modules ===
    echo "Importing modules...\n";

    $moduleData = [
        [1, 1, 'AI Foundations', 'Explore the history and fundamental concepts of AI', 1],
        [2, 1, 'Generative AI', 'Understanding generative AI models and applications', 2],
        [3, 1, 'Advanced Search', 'Master advanced search techniques with AI', 3],
        [4, 1, 'Responsible AI', 'Learn ethical AI practices and responsible development', 4],
        [5, 1, 'Microsoft Copilot', 'Leverage Microsoft Copilot for productivity', 5],
        [6, 1, 'AI Impact', 'Understand AI\'s impact on society and industries', 6]
    ];

    $moduleStmt = $pdo->prepare("
        INSERT INTO modules (id, course_id, title, description, order_index)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            description = VALUES(description),
            order_index = VALUES(order_index)
    ");

    foreach ($moduleData as $module) {
        $moduleStmt->execute($module);
    }

    echo "  ✓ Imported 6 modules\n\n";

    // === 6. Import Lessons ===
    echo "Importing lessons...\n";

    $lessonStmt = $pdo->prepare("
        INSERT INTO lessons (module_id, title, subtitle, slug, content, order_index, duration_minutes, is_published)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            module_id = VALUES(module_id),
            title = VALUES(title),
            subtitle = VALUES(subtitle),
            content = VALUES(content),
            order_index = VALUES(order_index),
            duration_minutes = VALUES(duration_minutes),
            is_published = VALUES(is_published)
    ");

    $lessonCount = 0;
    foreach ($lessons as $lesson) {
        try {
            $lessonStmt->execute([
                $lesson['module_id'],
                $lesson['title'],
                $lesson['subtitle'] ?? '',
                $lesson['slug'],
                $lesson['content'],
                $lesson['order_index'],
                $lesson['duration_minutes'] ?? 15,
                $lesson['is_published'] ?? true
            ]);
            $lessonCount++;

            if ($lessonCount % 10 === 0) {
                echo "  ... imported $lessonCount lessons\n";
            }
        } catch (PDOException $e) {
            throw new Exception("Failed to import lesson '{$lesson['slug']}': " . $e->getMessage());
        }
    }

    echo "  ✓ Imported $lessonCount lessons\n\n";

    // === 7. Import Quizzes ===
    echo "Importing quizzes...\n";

    $quizStmt = $pdo->prepare("
        INSERT INTO quizzes (id, module_id, title, description, passing_score, time_limit_minutes)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            module_id = VALUES(module_id),
            title = VALUES(title),
            description = VALUES(description),
            passing_score = VALUES(passing_score),
            time_limit_minutes = VALUES(time_limit_minutes)
    ");

    foreach ($quizzes as $quiz) {
        $quizStmt->execute([
            $quiz['id'],
            $quiz['module_id'],
            $quiz['title'],
            $quiz['description'] ?? '',
            $quiz['passing_score'] ?? 70,
            $quiz['time_limit_minutes'] ?? 30
        ]);
    }

    echo "  ✓ Imported " . count($quizzes) . " quizzes\n\n";

    // === 8. Import Quiz Questions ===
    echo "Importing quiz questions...\n";

    $questionStmt = $pdo->prepare("
        INSERT INTO quiz_questions (quiz_id, question_text, options, correct_option, explanation, points, order_index)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            question_text = VALUES(question_text),
            options = VALUES(options),
            correct_option = VALUES(correct_option),
            explanation = VALUES(explanation),
            points = VALUES(points),
            order_index = VALUES(order_index)
    ");

    $questionCount = 0;
    foreach ($questions as $question) {
        try {
            $questionStmt->execute([
                $question['quiz_id'],
                $question['question_text'],
                $question['options'], // Already JSON string
                $question['correct_option'],
                $question['explanation'] ?? '',
                $question['points'] ?? 1,
                $question['order_index']
            ]);
            $questionCount++;

            if ($questionCount % 20 === 0) {
                echo "  ... imported $questionCount questions\n";
            }
        } catch (PDOException $e) {
            throw new Exception("Failed to import question #{$question['order_index']} for quiz {$question['quiz_id']}: " . $e->getMessage());
        }
    }

    echo "  ✓ Imported $questionCount questions\n\n";

    // === 9. Commit Transaction ===
    $pdo->commit();

    echo "✓ Transaction committed successfully!\n\n";

} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        echo "\n✗ Transaction rolled back\n";
    }

    die("ERROR: Import failed: " . $e->getMessage() . "\n");
}

// === 10. Verify Import ===
echo "=== Verifying Import ===\n";

$coursesCount = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$modulesCount = $pdo->query("SELECT COUNT(*) FROM modules")->fetchColumn();
$lessonsCount = $pdo->query("SELECT COUNT(*) FROM lessons")->fetchColumn();
$quizzesCount = $pdo->query("SELECT COUNT(*) FROM quizzes")->fetchColumn();
$questionsCount = $pdo->query("SELECT COUNT(*) FROM quiz_questions")->fetchColumn();

echo "Database record counts:\n";
echo "  - Courses: $coursesCount\n";
echo "  - Modules: $modulesCount\n";
echo "  - Lessons: $lessonsCount\n";
echo "  - Quizzes: $quizzesCount\n";
echo "  - Quiz Questions: $questionsCount\n\n";

// Verify counts match
$allMatch = (
    $coursesCount === 1 &&
    $modulesCount === 6 &&
    $lessonsCount === count($lessons) &&
    $quizzesCount === count($quizzes) &&
    $questionsCount === count($questions)
);

if ($allMatch) {
    echo "✓ All record counts match expected values\n";
} else {
    echo "⚠ WARNING: Record count mismatch - please verify data\n";
}

// Sample queries to verify data
echo "\nSample verification queries:\n";

$sampleLesson = $pdo->query("SELECT title, module_id FROM lessons ORDER BY order_index LIMIT 1")->fetch();
if ($sampleLesson) {
    echo "  First lesson: \"{$sampleLesson['title']}\" (Module {$sampleLesson['module_id']})\n";
}

$sampleQuiz = $pdo->query("SELECT title, module_id FROM quizzes LIMIT 1")->fetch();
if ($sampleQuiz) {
    echo "  First quiz: \"{$sampleQuiz['title']}\" (Module {$sampleQuiz['module_id']})\n";
}

$sampleQuestion = $pdo->query("SELECT question_text FROM quiz_questions LIMIT 1")->fetch();
if ($sampleQuestion) {
    $questionPreview = substr($sampleQuestion['question_text'], 0, 60) . '...';
    echo "  First question: \"$questionPreview\"\n";
}

echo "\n=== Import Summary ===\n";
echo "✓ Successfully imported:\n";
echo "  - 1 course\n";
echo "  - 6 modules\n";
echo "  - $lessonCount lessons\n";
echo "  - " . count($quizzes) . " quizzes\n";
echo "  - $questionCount quiz questions\n\n";

echo "✓ Content migration complete!\n";
echo "Next step: Test content rendering from database\n";
