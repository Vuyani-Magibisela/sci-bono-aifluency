<?php
/**
 * Content Extraction Script 2: Extract Quizzes to JSON
 *
 * Purpose: Extract quiz data from JavaScript arrays in quiz HTML files
 * Input: Quiz HTML files (module*Quiz.html)
 * Output: quizzes.json and quiz_questions.json
 *
 * Usage: php extract-quizzes.php
 */

// Configuration
$baseDir = dirname(dirname(__DIR__)); // Project root
$outputDir = __DIR__ . '/output';
$quizzesFile = $outputDir . '/quizzes.json';
$questionsFile = $outputDir . '/quiz_questions.json';

// Ensure output directory exists
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

echo "=== Quiz Content Extraction ===\n";
echo "Base directory: $baseDir\n";
echo "Output files:\n";
echo "  - $quizzesFile\n";
echo "  - $questionsFile\n\n";

// Find all quiz HTML files
$quizFiles = glob($baseDir . '/module*Quiz.html');

if (empty($quizFiles)) {
    die("ERROR: No quiz files found in $baseDir\n");
}

echo "Found " . count($quizFiles) . " quiz files\n\n";

$quizzes = [];
$questions = [];
$errors = [];

foreach ($quizFiles as $file) {
    $filename = basename($file);
    echo "Processing: $filename... ";

    try {
        $html = file_get_contents($file);

        if ($html === false) {
            throw new Exception("Failed to read file");
        }

        // Extract module number from filename
        if (!preg_match('/module(\d+)Quiz/', $filename, $matches)) {
            throw new Exception("Cannot extract module ID from filename");
        }

        $moduleId = (int)$matches[1];

        // Parse HTML for quiz title
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $titleNodes = $xpath->query("//div[@class='quiz-header']/h1");

        $quizTitle = "Module $moduleId Quiz";
        if ($titleNodes->length > 0) {
            $quizTitle = trim($titleNodes->item(0)->textContent);
        }

        // Extract JavaScript quizData array
        if (!preg_match('/const\s+quizData\s*=\s*(\[[\s\S]*?\]);/m', $html, $jsMatches)) {
            throw new Exception("Cannot find quizData array in JavaScript");
        }

        $jsArray = $jsMatches[1];

        // Convert JavaScript to valid JSON
        // Step 1: Quote unquoted keys
        $jsonString = preg_replace('/(\w+):/','"\1":', $jsArray);

        // Step 2: Remove trailing commas before closing brackets/braces
        $jsonString = preg_replace('/,(\s*[\]}])/','$1', $jsonString);

        // Step 3: Handle JavaScript comments (if any)
        $jsonString = preg_replace('/\/\/.*$/m', '', $jsonString);

        // Decode JSON
        $quizData = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON decode error: " . json_last_error_msg());
        }

        if (!is_array($quizData) || empty($quizData)) {
            throw new Exception("Invalid or empty quiz data");
        }

        // Create quiz record
        $quizId = $moduleId; // Use module ID as quiz ID
        $quizzes[] = [
            'id' => $quizId,
            'module_id' => $moduleId,
            'title' => $quizTitle,
            'description' => "Test your knowledge of module $moduleId concepts",
            'passing_score' => 70,
            'time_limit_minutes' => 30,
            'question_count' => count($quizData),
            'source_file' => $filename
        ];

        // Create question records
        foreach ($quizData as $index => $q) {
            // Validate question structure
            if (!isset($q['question']) || !isset($q['options']) || !isset($q['correctAnswer'])) {
                throw new Exception("Question " . ($index + 1) . " missing required fields");
            }

            // Validate correctAnswer is within options range
            $optionCount = count($q['options']);
            if ($q['correctAnswer'] < 0 || $q['correctAnswer'] >= $optionCount) {
                throw new Exception("Question " . ($index + 1) . " has invalid correctAnswer index: " . $q['correctAnswer']);
            }

            $questions[] = [
                'quiz_id' => $quizId,
                'question_text' => $q['question'],
                'options' => $q['options'], // Will be JSON-encoded when saved
                'correct_option' => (int)$q['correctAnswer'],
                'explanation' => $q['explanation'] ?? '',
                'points' => 1,
                'order_index' => $index + 1,
                'source_id' => $q['id'] ?? null
            ];
        }

        echo "✓ OK (" . count($quizData) . " questions)\n";

    } catch (Exception $e) {
        echo "✗ ERROR: " . $e->getMessage() . "\n";
        $errors[] = [
            'file' => $filename,
            'error' => $e->getMessage()
        ];
    }
}

// Save quizzes to JSON
$quizzesJson = json_encode($quizzes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if (file_put_contents($quizzesFile, $quizzesJson) === false) {
    die("\nERROR: Failed to write quizzes file\n");
}

// For questions, encode options as JSON strings
$questionsFormatted = array_map(function($q) {
    $q['options'] = json_encode($q['options'], JSON_UNESCAPED_UNICODE);
    return $q;
}, $questions);

$questionsJson = json_encode($questionsFormatted, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (file_put_contents($questionsFile, $questionsJson) === false) {
    die("\nERROR: Failed to write questions file\n");
}

// Print summary
echo "\n=== Extraction Summary ===\n";
echo "Total quizzes extracted: " . count($quizzes) . "\n";
echo "Total questions extracted: " . count($questions) . "\n";
echo "\nOutput files:\n";
echo "  - $quizzesFile\n";
echo "  - $questionsFile\n";

if (!empty($errors)) {
    echo "\nERRORS ENCOUNTERED (" . count($errors) . "):\n";
    foreach ($errors as $error) {
        echo "  - {$error['file']}: {$error['error']}\n";
    }
}

// Print breakdown
echo "\nQuestions per Quiz:\n";
foreach ($quizzes as $quiz) {
    echo "  Module {$quiz['module_id']}: {$quiz['question_count']} questions\n";
}

// Validation checks
echo "\nValidation Checks:\n";

$totalExpected = array_sum(array_column($quizzes, 'question_count'));
if (count($questions) === $totalExpected) {
    echo "  ✓ Question count matches: " . count($questions) . " questions\n";
} else {
    echo "  ⚠ WARNING: Question count mismatch (expected $totalExpected, got " . count($questions) . ")\n";
}

// Check for questions with no explanation
$noExplanation = array_filter($questions, fn($q) => empty($q['explanation']));
if (!empty($noExplanation)) {
    echo "  ⚠ INFO: " . count($noExplanation) . " questions have no explanation\n";
}

// Check answer indices
$invalidAnswers = array_filter($questions, function($q) {
    $options = json_decode($q['options'], true);
    return $q['correct_option'] < 0 || $q['correct_option'] >= count($options);
});

if (empty($invalidAnswers)) {
    echo "  ✓ All answer indices valid\n";
} else {
    echo "  ✗ ERROR: " . count($invalidAnswers) . " questions have invalid answer indices\n";
}

echo "\n✓ Extraction complete!\n";
echo "Next step: Run validate-content.php to verify data integrity\n";
