<?php
/**
 * Content Validation Script: Validate Extracted JSON Data
 *
 * Purpose: Validate extracted JSON data before database import
 * Input: All JSON files in output/ directory
 * Output: Validation report (console + validation-report.txt)
 *
 * Usage: php validate-content.php
 */

// Configuration
$outputDir = __DIR__ . '/output';
$reportFile = $outputDir . '/validation-report.txt';

echo "=== Content Validation ===\n";
echo "Validating files in: $outputDir\n\n";

$errors = [];
$warnings = [];
$report = [];

// Helper function to add error
function addError($category, $message) {
    global $errors, $report;
    $errors[] = ['category' => $category, 'message' => $message];
    $report[] = "✗ ERROR [$category]: $message";
}

// Helper function to add warning
function addWarning($category, $message) {
    global $warnings, $report;
    $warnings[] = ['category' => $category, 'message' => $message];
    $report[] = "⚠ WARNING [$category]: $message";
}

// Helper function to add success
function addSuccess($category, $message) {
    global $report;
    $report[] = "✓ [$category]: $message";
}

// === 1. Load JSON Files ===
echo "Loading JSON files...\n";

$lessons = [];
$quizzes = [];
$questions = [];

// Load lessons.json
$lessonsFile = $outputDir . '/lessons.json';
if (!file_exists($lessonsFile)) {
    addError('FILES', 'lessons.json not found');
} else {
    $lessonsJson = file_get_contents($lessonsFile);
    $lessons = json_decode($lessonsJson, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        addError('LESSONS', 'Invalid JSON: ' . json_last_error_msg());
        $lessons = [];
    } else {
        echo "  ✓ Loaded lessons.json (" . count($lessons) . " lessons)\n";
    }
}

// Load quizzes.json
$quizzesFile = $outputDir . '/quizzes.json';
if (!file_exists($quizzesFile)) {
    addError('FILES', 'quizzes.json not found');
} else {
    $quizzesJson = file_get_contents($quizzesFile);
    $quizzes = json_decode($quizzesJson, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        addError('QUIZZES', 'Invalid JSON: ' . json_last_error_msg());
        $quizzes = [];
    } else {
        echo "  ✓ Loaded quizzes.json (" . count($quizzes) . " quizzes)\n";
    }
}

// Load quiz_questions.json
$questionsFile = $outputDir . '/quiz_questions.json';
if (!file_exists($questionsFile)) {
    addError('FILES', 'quiz_questions.json not found');
} else {
    $questionsJson = file_get_contents($questionsFile);
    $questions = json_decode($questionsJson, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        addError('QUESTIONS', 'Invalid JSON: ' . json_last_error_msg());
        $questions = [];
    } else {
        echo "  ✓ Loaded quiz_questions.json (" . count($questions) . " questions)\n";
    }
}

echo "\n";

// === 2. Validate Lessons ===
echo "Validating lessons...\n";

$requiredLessonFields = ['module_id', 'title', 'slug', 'content', 'order_index'];
$lessonIds = [];
$lessonSlugs = [];

foreach ($lessons as $index => $lesson) {
    $lessonNum = $index + 1;

    // Check required fields
    foreach ($requiredLessonFields as $field) {
        if (!isset($lesson[$field]) || $lesson[$field] === null || $lesson[$field] === '') {
            addError('LESSONS', "Lesson #$lessonNum missing required field: $field");
        }
    }

    // Validate module_id
    if (isset($lesson['module_id'])) {
        if (!is_int($lesson['module_id']) || $lesson['module_id'] < 1 || $lesson['module_id'] > 6) {
            addError('LESSONS', "Lesson #$lessonNum has invalid module_id: {$lesson['module_id']}");
        }
    }

    // Validate order_index
    if (isset($lesson['order_index'])) {
        if (!is_int($lesson['order_index']) || $lesson['order_index'] < 0) {
            addError('LESSONS', "Lesson #$lessonNum has invalid order_index: {$lesson['order_index']}");
        }
    }

    // Check slug uniqueness
    if (isset($lesson['slug'])) {
        if (in_array($lesson['slug'], $lessonSlugs)) {
            addError('LESSONS', "Duplicate slug found: {$lesson['slug']}");
        }
        $lessonSlugs[] = $lesson['slug'];
    }

    // Check content length
    if (isset($lesson['content'])) {
        $contentLength = strlen($lesson['content']);
        if ($contentLength > 500000) { // 500KB warning
            addWarning('LESSONS', "Lesson #$lessonNum has very large content: " . number_format($contentLength) . " bytes");
        }
        if ($contentLength < 100) { // Suspiciously short
            addWarning('LESSONS', "Lesson #$lessonNum has very short content: $contentLength bytes");
        }
    }

    // Validate HTML content well-formed
    if (isset($lesson['content']) && !empty($lesson['content'])) {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($lesson['content']);
        $htmlErrors = libxml_get_errors();
        libxml_clear_errors();

        // Critical HTML errors only
        foreach ($htmlErrors as $error) {
            if ($error->level === LIBXML_ERR_ERROR || $error->level === LIBXML_ERR_FATAL) {
                addWarning('LESSONS', "Lesson #$lessonNum has malformed HTML: " . trim($error->message));
                break;
            }
        }
    }
}

if (empty($errors) && empty($warnings)) {
    addSuccess('LESSONS', 'All ' . count($lessons) . ' lessons validated successfully');
}

echo "  Validated " . count($lessons) . " lessons\n\n";

// === 3. Validate Quizzes ===
echo "Validating quizzes...\n";

$requiredQuizFields = ['id', 'module_id', 'title'];
$quizIds = [];

foreach ($quizzes as $index => $quiz) {
    $quizNum = $index + 1;

    // Check required fields
    foreach ($requiredQuizFields as $field) {
        if (!isset($quiz[$field]) || $quiz[$field] === null || $quiz[$field] === '') {
            addError('QUIZZES', "Quiz #$quizNum missing required field: $field");
        }
    }

    // Validate module_id
    if (isset($quiz['module_id'])) {
        if (!is_int($quiz['module_id']) || $quiz['module_id'] < 1 || $quiz['module_id'] > 6) {
            addError('QUIZZES', "Quiz #$quizNum has invalid module_id: {$quiz['module_id']}");
        }
    }

    // Check ID uniqueness
    if (isset($quiz['id'])) {
        if (in_array($quiz['id'], $quizIds)) {
            addError('QUIZZES', "Duplicate quiz ID found: {$quiz['id']}");
        }
        $quizIds[] = $quiz['id'];
    }

    // Validate passing_score
    if (isset($quiz['passing_score'])) {
        if (!is_int($quiz['passing_score']) || $quiz['passing_score'] < 0 || $quiz['passing_score'] > 100) {
            addError('QUIZZES', "Quiz #$quizNum has invalid passing_score: {$quiz['passing_score']}");
        }
    }
}

if (empty($errors) && count($quizzes) > 0) {
    addSuccess('QUIZZES', 'All ' . count($quizzes) . ' quizzes validated successfully');
}

echo "  Validated " . count($quizzes) . " quizzes\n\n";

// === 4. Validate Quiz Questions ===
echo "Validating quiz questions...\n";

$requiredQuestionFields = ['quiz_id', 'question_text', 'options', 'correct_option', 'order_index'];

foreach ($questions as $index => $question) {
    $questionNum = $index + 1;

    // Check required fields
    foreach ($requiredQuestionFields as $field) {
        if (!isset($question[$field]) || $question[$field] === null || $question[$field] === '') {
            addError('QUESTIONS', "Question #$questionNum missing required field: $field");
        }
    }

    // Validate quiz_id exists
    if (isset($question['quiz_id'])) {
        if (!in_array($question['quiz_id'], $quizIds)) {
            addError('QUESTIONS', "Question #$questionNum references non-existent quiz_id: {$question['quiz_id']}");
        }
    }

    // Validate options JSON
    if (isset($question['options'])) {
        $options = json_decode($question['options'], true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            addError('QUESTIONS', "Question #$questionNum has invalid options JSON: " . json_last_error_msg());
        } elseif (!is_array($options) || count($options) < 2) {
            addError('QUESTIONS', "Question #$questionNum must have at least 2 options");
        }

        // Validate correct_option index
        if (isset($question['correct_option']) && is_array($options)) {
            $correctOption = (int)$question['correct_option'];
            if ($correctOption < 0 || $correctOption >= count($options)) {
                addError('QUESTIONS', "Question #$questionNum has invalid correct_option: $correctOption (options count: " . count($options) . ")");
            }
        }
    }

    // Check for explanation
    if (empty($question['explanation'])) {
        addWarning('QUESTIONS', "Question #$questionNum has no explanation");
    }
}

if (empty($errors) && count($questions) > 0) {
    addSuccess('QUESTIONS', 'All ' . count($questions) . ' questions validated successfully');
}

echo "  Validated " . count($questions) . " questions\n\n";

// === 5. Foreign Key Integrity ===
echo "Checking foreign key integrity...\n";

// Check lessons reference valid modules (1-6)
$validModules = [1, 2, 3, 4, 5, 6];
foreach ($lessons as $lesson) {
    if (isset($lesson['module_id']) && !in_array($lesson['module_id'], $validModules)) {
        addError('INTEGRITY', "Lesson '{$lesson['slug']}' references invalid module_id: {$lesson['module_id']}");
    }
}

// Check quizzes reference valid modules
foreach ($quizzes as $quiz) {
    if (isset($quiz['module_id']) && !in_array($quiz['module_id'], $validModules)) {
        addError('INTEGRITY', "Quiz '{$quiz['title']}' references invalid module_id: {$quiz['module_id']}");
    }
}

// Check questions reference valid quizzes
foreach ($questions as $question) {
    if (isset($question['quiz_id']) && !in_array($question['quiz_id'], $quizIds)) {
        addError('INTEGRITY', "Question references non-existent quiz_id: {$question['quiz_id']}");
    }
}

if (empty($errors)) {
    addSuccess('INTEGRITY', 'All foreign key relationships valid');
}

echo "  Foreign key checks complete\n\n";

// === 6. Generate Report ===
$reportContent = "VALIDATION REPORT\n";
$reportContent .= "=================\n";
$reportContent .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";

$reportContent .= "FILES VALIDATED\n";
$reportContent .= "---------------\n";
$reportContent .= "Lessons: " . count($lessons) . " records\n";
$reportContent .= "Quizzes: " . count($quizzes) . " records\n";
$reportContent .= "Quiz Questions: " . count($questions) . " records\n";
$reportContent .= "Total Records: " . (count($lessons) + count($quizzes) + count($questions)) . "\n\n";

$reportContent .= "VALIDATION RESULTS\n";
$reportContent .= "------------------\n";
foreach ($report as $line) {
    $reportContent .= $line . "\n";
}

$reportContent .= "\nSUMMARY\n";
$reportContent .= "-------\n";
$reportContent .= "Errors: " . count($errors) . "\n";
$reportContent .= "Warnings: " . count($warnings) . "\n";

if (count($errors) === 0) {
    $reportContent .= "\n✓ VALIDATION PASSED - Safe to import to database\n";
} else {
    $reportContent .= "\n✗ VALIDATION FAILED - Fix errors before importing\n";
}

// Save report
file_put_contents($reportFile, $reportContent);

// Print summary
echo "=== Validation Summary ===\n";
echo "Total Records: " . (count($lessons) + count($quizzes) + count($questions)) . "\n";
echo "Errors: " . count($errors) . "\n";
echo "Warnings: " . count($warnings) . "\n\n";

if (!empty($errors)) {
    echo "ERRORS:\n";
    foreach ($errors as $error) {
        echo "  ✗ [{$error['category']}] {$error['message']}\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "WARNINGS:\n";
    $warningLimit = 10;
    $shownWarnings = array_slice($warnings, 0, $warningLimit);
    foreach ($shownWarnings as $warning) {
        echo "  ⚠ [{$warning['category']}] {$warning['message']}\n";
    }
    if (count($warnings) > $warningLimit) {
        echo "  ... and " . (count($warnings) - $warningLimit) . " more warnings\n";
    }
    echo "\n";
}

echo "Full report saved to: $reportFile\n\n";

if (count($errors) === 0) {
    echo "✓ VALIDATION PASSED\n";
    echo "Next step: Run import-to-db.php to import data to database\n";
    exit(0);
} else {
    echo "✗ VALIDATION FAILED\n";
    echo "Fix errors above before importing to database\n";
    exit(1);
}
