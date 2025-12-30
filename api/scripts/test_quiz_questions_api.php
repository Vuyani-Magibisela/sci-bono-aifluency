#!/usr/bin/env php
<?php
/**
 * Quiz Question API Endpoint Testing Script
 * Tests POST, PUT, DELETE /api/quiz-questions endpoints
 *
 * Usage: php test_quiz_questions_api.php
 */

// Configuration
$baseUrl = 'http://localhost/sci-bono-aifluency/api';
$testEmail = 'test.admin@scibono.test';  // Test admin user
$testPassword = 'TestAdmin123!';

// ANSI color codes for terminal output
$colors = [
    'green' => "\033[32m",
    'red' => "\033[31m",
    'yellow' => "\033[33m",
    'blue' => "\033[34m",
    'reset' => "\033[0m"
];

function colorize($text, $color, $colors) {
    return $colors[$color] . $text . $colors['reset'];
}

function logTest($message, $colors) {
    echo colorize("\n[TEST] ", 'blue', $colors) . $message . "\n";
}

function logSuccess($message, $colors) {
    echo colorize("  ✓ ", 'green', $colors) . $message . "\n";
}

function logError($message, $colors) {
    echo colorize("  ✗ ", 'red', $colors) . $message . "\n";
}

function logWarning($message, $colors) {
    echo colorize("  ⚠ ", 'yellow', $colors) . $message . "\n";
}

function apiRequest($method, $url, $data = null, $token = null) {
    $ch = curl_init($url);

    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'status' => $httpCode,
        'body' => json_decode($response, true),
        'raw' => $response
    ];
}

echo colorize("\n╔════════════════════════════════════════════════════════════╗\n", 'blue', $colors);
echo colorize("║  Quiz Question API Endpoint Testing Suite                 ║\n", 'blue', $colors);
echo colorize("╚════════════════════════════════════════════════════════════╝\n", 'blue', $colors);

// Initialize database connection
$pdo = require __DIR__ . '/../config/database.php';

// Step 1: Authentication
logTest("Authenticating as admin user", $colors);
$authResponse = apiRequest('POST', "$baseUrl/auth/login", [
    'email' => $testEmail,
    'password' => $testPassword
]);

if ($authResponse['status'] !== 200 || !isset($authResponse['body']['data']['tokens']['accessToken'])) {
    logError("Authentication failed", $colors);
    echo "Response: " . print_r($authResponse, true) . "\n";
    exit(1);
}

$token = $authResponse['body']['data']['tokens']['accessToken'];
logSuccess("Authenticated successfully", $colors);
echo "  Token: " . substr($token, 0, 20) . "...\n";

// Step 2: Test POST /api/quiz-questions (Create)
logTest("POST /api/quiz-questions - Create new question", $colors);

$newQuestion = [
    'quiz_id' => 2,  // Module 2 quiz
    'question' => 'What is the primary purpose of this test question?',
    'options' => json_encode([
        'To verify API functionality',
        'To test database insertion',
        'To validate authentication',
        'All of the above'
    ]),
    'correct_answer' => 3,  // Index 3 = "All of the above"
    'explanation' => 'This test question verifies that the API can create new quiz questions with proper validation and storage.',
    'points' => 1
];

$createResponse = apiRequest('POST', "$baseUrl/quiz-questions", $newQuestion, $token);

if ($createResponse['status'] === 201) {
    logSuccess("Question created successfully (HTTP 201)", $colors);
    $createdId = $createResponse['body']['data']['id'] ?? null;
    if ($createdId) {
        echo "  Created Question ID: $createdId\n";
    }
} else {
    logError("Failed to create question (HTTP {$createResponse['status']})", $colors);
    echo "  Response: " . print_r($createResponse['body'], true) . "\n";
}

// Step 3: Verify question exists in database
if (isset($createdId)) {
    logTest("Verifying question in database", $colors);

    try {
        $stmt = $pdo->prepare("SELECT * FROM quiz_questions WHERE id = ?");
        $stmt->execute([$createdId]);
        $dbQuestion = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($dbQuestion) {
            logSuccess("Question found in database", $colors);
            echo "  Question Text: " . substr($dbQuestion['question_text'], 0, 50) . "...\n";
            echo "  Correct Option: {$dbQuestion['correct_option']}\n";
            echo "  Points: {$dbQuestion['points']}\n";

            // Verify field mapping (correct_answer → correct_option)
            if ((int)$dbQuestion['correct_option'] === (int)$newQuestion['correct_answer']) {
                logSuccess("Field mapping correct (correct_answer → correct_option)", $colors);
            } else {
                logError("Field mapping incorrect! Expected {$newQuestion['correct_answer']}, got {$dbQuestion['correct_option']}", $colors);
            }
        } else {
            logError("Question not found in database!", $colors);
        }
    } catch (Exception $e) {
        logError("Database verification failed: " . $e->getMessage(), $colors);
    }
}

// Step 4: Test PUT /api/quiz-questions/:id (Update)
if (isset($createdId)) {
    logTest("PUT /api/quiz-questions/$createdId - Update question", $colors);

    $updateData = [
        'question' => 'UPDATED: What is the primary purpose of this test question?',
        'correct_answer' => 2,  // Change correct answer
        'points' => 2  // Increase points
    ];

    $updateResponse = apiRequest('PUT', "$baseUrl/quiz-questions/$createdId", $updateData, $token);

    if ($updateResponse['status'] === 200) {
        logSuccess("Question updated successfully (HTTP 200)", $colors);

        // Verify update in database
        $stmt = $pdo->prepare("SELECT question_text, correct_option, points FROM quiz_questions WHERE id = ?");
        $stmt->execute([$createdId]);
        $updated = $stmt->fetch(PDO::FETCH_ASSOC);

        if (strpos($updated['question_text'], 'UPDATED:') !== false) {
            logSuccess("Question text updated correctly", $colors);
        } else {
            logError("Question text not updated", $colors);
        }

        if ((int)$updated['correct_option'] === 2) {
            logSuccess("Correct option updated correctly", $colors);
        } else {
            logError("Correct option not updated (expected 2, got {$updated['correct_option']})", $colors);
        }

        if ((int)$updated['points'] === 2) {
            logSuccess("Points updated correctly", $colors);
        } else {
            logError("Points not updated (expected 2, got {$updated['points']})", $colors);
        }
    } else {
        logError("Failed to update question (HTTP {$updateResponse['status']})", $colors);
        echo "  Response: " . print_r($updateResponse['body'], true) . "\n";
    }
}

// Step 5: Test validation (missing required fields)
logTest("POST /api/quiz-questions - Test validation (missing fields)", $colors);

$invalidQuestion = [
    'quiz_id' => 2,
    // Missing 'question', 'options', 'correct_answer'
];

$validationResponse = apiRequest('POST', "$baseUrl/quiz-questions", $invalidQuestion, $token);

if ($validationResponse['status'] === 400) {
    logSuccess("Validation working - rejected invalid data (HTTP 400)", $colors);
} else {
    logWarning("Expected HTTP 400, got {$validationResponse['status']}", $colors);
}

// Step 6: Test DELETE /api/quiz-questions/:id
if (isset($createdId)) {
    logTest("DELETE /api/quiz-questions/$createdId - Delete question", $colors);

    $deleteResponse = apiRequest('DELETE', "$baseUrl/quiz-questions/$createdId", null, $token);

    if ($deleteResponse['status'] === 200) {
        logSuccess("Question deleted successfully (HTTP 200)", $colors);

        // Verify deletion in database
        $stmt = $pdo->prepare("SELECT id FROM quiz_questions WHERE id = ?");
        $stmt->execute([$createdId]);
        $deleted = $stmt->fetch();

        if (!$deleted) {
            logSuccess("Question removed from database", $colors);
        } else {
            logError("Question still exists in database after deletion!", $colors);
        }
    } else {
        logError("Failed to delete question (HTTP {$deleteResponse['status']})", $colors);
        echo "  Response: " . print_r($deleteResponse['body'], true) . "\n";
    }
}

// Step 7: Test DELETE on non-existent question
logTest("DELETE /api/quiz-questions/99999 - Test 404 handling", $colors);
$notFoundResponse = apiRequest('DELETE', "$baseUrl/quiz-questions/99999", null, $token);

if ($notFoundResponse['status'] === 404) {
    logSuccess("404 handling working correctly", $colors);
} else {
    logWarning("Expected HTTP 404, got {$notFoundResponse['status']}", $colors);
}

// Step 8: Test unauthorized access (no token)
logTest("POST /api/quiz-questions - Test authentication (no token)", $colors);
$unauthResponse = apiRequest('POST', "$baseUrl/quiz-questions", $newQuestion, null);

if ($unauthResponse['status'] === 401) {
    logSuccess("Authentication required - rejected request without token (HTTP 401)", $colors);
} else {
    logWarning("Expected HTTP 401, got {$unauthResponse['status']}", $colors);
}

// Step 9: Verify total question count unchanged
logTest("Verifying database integrity (question count)", $colors);
$stmt = $pdo->query("SELECT COUNT(*) as total FROM quiz_questions");
$totalQuestions = $stmt->fetchColumn();

echo "  Total questions in database: $totalQuestions\n";
if ($totalQuestions == 71) {
    logSuccess("Database integrity maintained (71 questions)", $colors);
} else {
    logWarning("Expected 71 questions, found $totalQuestions", $colors);
}

// Summary
echo colorize("\n╔════════════════════════════════════════════════════════════╗\n", 'blue', $colors);
echo colorize("║  Test Suite Complete                                      ║\n", 'blue', $colors);
echo colorize("╚════════════════════════════════════════════════════════════╝\n", 'blue', $colors);

echo "\nTested Endpoints:\n";
echo "  ✓ POST   /api/quiz-questions (Create)\n";
echo "  ✓ PUT    /api/quiz-questions/:id (Update)\n";
echo "  ✓ DELETE /api/quiz-questions/:id (Delete)\n";
echo "\nValidation Tests:\n";
echo "  ✓ Required field validation\n";
echo "  ✓ Authentication requirement\n";
echo "  ✓ 404 handling for non-existent resources\n";
echo "  ✓ Field mapping (correct_answer → correct_option)\n";
echo "\n";
