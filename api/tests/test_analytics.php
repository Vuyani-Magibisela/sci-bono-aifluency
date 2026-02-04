<?php
/**
 * Analytics Endpoints Test Script
 * Phase 6 - Task 9: Test all analytics endpoints
 */

$baseUrl = 'http://localhost/sci-bono-aifluency/api';

echo "=== Phase 6 Analytics Endpoints Test ===\n\n";

// Test credentials (admin user for full access)
$email = 'admin@test.com';
$password = 'password123';

echo "Step 1: Logging in as admin...\n";

$ch = curl_init("$baseUrl/auth/login");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'email' => $email,
    'password' => $password
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$loginData = json_decode($response, true);

if ($httpCode !== 200 || !isset($loginData['data']['token'])) {
    echo "❌ Login failed: " . ($loginData['message'] ?? 'Unknown error') . "\n";
    echo "Response: " . print_r($loginData, true) . "\n";
    exit(1);
}

$token = $loginData['data']['token'];
$userId = $loginData['data']['user']['id'];
echo "✅ Login successful (User ID: $userId)\n\n";

// Test endpoints
$tests = [
    [
        'name' => 'Question Difficulty Stats',
        'url' => "$baseUrl/analytics/questions/1",
        'method' => 'GET',
        'expected_fields' => ['total_attempts', 'success_rate', 'difficulty_score']
    ],
    [
        'name' => 'Quiz Question Difficulty Ranking',
        'url' => "$baseUrl/analytics/quiz/1/questions",
        'method' => 'GET',
        'expected_array' => true
    ],
    [
        'name' => 'Performance Trends (User 3, Quiz 1)',
        'url' => "$baseUrl/analytics/trends/3/1",
        'method' => 'GET',
        'expected_fields' => ['attempts', 'trend', 'best_score']
    ],
    [
        'name' => 'User Learning Curve (User 3)',
        'url' => "$baseUrl/analytics/learning-curve/3",
        'method' => 'GET',
        'expected_array' => true
    ],
    [
        'name' => 'Class Comparison (Quiz 1, User 3)',
        'url' => "$baseUrl/analytics/comparison/1/3",
        'method' => 'GET',
        'expected_fields' => ['user_best_score', 'class_average', 'percentile', 'rank']
    ],
    [
        'name' => 'Quiz Leaderboard (Quiz 1)',
        'url' => "$baseUrl/analytics/leaderboard/1",
        'method' => 'GET',
        'expected_array' => true
    ]
];

$passed = 0;
$failed = 0;

foreach ($tests as $test) {
    echo "Testing: {$test['name']}\n";
    echo "URL: {$test['url']}\n";

    $ch = curl_init($test['url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);

    if ($httpCode !== 200) {
        echo "❌ FAILED - HTTP $httpCode\n";
        echo "Response: " . substr($response, 0, 200) . "\n";
        $failed++;
    } else {
        // Verify expected fields/structure
        $valid = true;

        if (isset($test['expected_array']) && $test['expected_array']) {
            if (!isset($data['data']) || !is_array($data['data'])) {
                $valid = false;
                echo "❌ FAILED - Expected array in data field\n";
            }
        }

        if (isset($test['expected_fields'])) {
            foreach ($test['expected_fields'] as $field) {
                if (!isset($data['data'][$field])) {
                    $valid = false;
                    echo "❌ FAILED - Missing field: $field\n";
                    break;
                }
            }
        }

        if ($valid) {
            echo "✅ PASSED\n";
            echo "Data: " . json_encode($data['data'], JSON_PRETTY_PRINT) . "\n";
            $passed++;
        } else {
            echo "Full response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
            $failed++;
        }
    }

    echo str_repeat("-", 80) . "\n\n";
}

// Summary
echo "\n=== Test Summary ===\n";
echo "Passed: $passed / " . count($tests) . "\n";
echo "Failed: $failed / " . count($tests) . "\n";

if ($failed === 0) {
    echo "\n✅ All analytics endpoints working correctly!\n";
    exit(0);
} else {
    echo "\n❌ Some tests failed. Please review the errors above.\n";
    exit(1);
}
