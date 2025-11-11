<?php
/**
 * Simple Endpoint Testing - Tests routing and basic responses
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

global $pdo;

// Test counters
$total = 0;
$passed = 0;
$failed = 0;

echo "================================================================================\n";
echo " BACKEND API ENDPOINT TEST\n";
echo "================================================================================\n\n";

// Test data
$endpoints = [
    // Course endpoints
    ['GET', '/api/courses', 'List all courses'],
    ['GET', '/api/courses?published=true', 'List published courses'],
    ['GET', '/api/courses?page=1&pageSize=5', 'List courses with pagination'],

    // Module endpoints
    ['GET', '/api/modules', 'List all modules'],
    ['GET', '/api/modules?course_id=1', 'List modules by course'],
    ['GET', '/api/modules?published=true', 'List published modules'],

    // Lesson endpoints
    ['GET', '/api/lessons', 'List all lessons'],
    ['GET', '/api/lessons?module_id=1', 'List lessons by module'],
    ['GET', '/api/lessons?published=true', 'List published lessons'],

    // Quiz endpoints
    ['GET', '/api/quizzes', 'List all quizzes'],
    ['GET', '/api/quizzes?module_id=1', 'List quizzes by module'],
    ['GET', '/api/quizzes?published=true', 'List published quizzes'],

    // Project endpoints
    ['GET', '/api/projects', 'List all projects'],
    ['GET', '/api/projects?module_id=1', 'List projects by module'],
    ['GET', '/api/projects?course_id=1', 'List projects by course'],

    // Enrollment endpoints (requires auth)
    ['GET', '/api/enrollments', 'List enrollments (expect 401)'],

    // Certificate endpoints (requires auth)
    ['GET', '/api/certificates', 'List certificates (expect 401)'],

    // User endpoints (requires auth)
    ['GET', '/api/users', 'List users (expect 401)'],
];

// Get sample IDs from database
$courseId = $pdo->query("SELECT id FROM courses LIMIT 1")->fetchColumn();
$moduleId = $pdo->query("SELECT id FROM modules LIMIT 1")->fetchColumn();
$lessonId = $pdo->query("SELECT id FROM lessons LIMIT 1")->fetchColumn();
$quizId = $pdo->query("SELECT id FROM quizzes LIMIT 1")->fetchColumn();
$projectId = $pdo->query("SELECT id FROM projects LIMIT 1")->fetchColumn();

// Add specific ID tests if data exists
if ($courseId) {
    $endpoints[] = ['GET', "/api/courses/{$courseId}", 'Get course by ID'];
}

if ($moduleId) {
    $endpoints[] = ['GET', "/api/modules/{$moduleId}", 'Get module by ID'];
}

if ($lessonId) {
    $endpoints[] = ['GET', "/api/lessons/{$lessonId}", 'Get lesson by ID'];
}

if ($quizId) {
    $endpoints[] = ['GET', "/api/quizzes/{$quizId}", 'Get quiz by ID'];
}

if ($projectId) {
    $endpoints[] = ['GET', "/api/projects/{$projectId}", 'Get project by ID'];
}

// Test each endpoint
foreach ($endpoints as $endpoint) {
    list($method, $uri, $description) = $endpoint;
    $total++;

    // Parse URI
    $parsedUrl = parse_url($uri);
    $path = $parsedUrl['path'];
    $query = isset($parsedUrl['query']) ? $parsedUrl['query'] : '';

    // Set up environment
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $uri;
    $_SERVER['HTTP_ORIGIN'] = 'http://localhost';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['HTTP_USER_AGENT'] = 'TestScript';
    $_GET = [];
    $_POST = [];

    // Parse query string
    if ($query) {
        parse_str($query, $_GET);
    }

    // Capture output
    ob_start();
    http_response_code(200); // Reset

    try {
        include __DIR__ . '/routes/api.php';
        $output = ob_get_clean();

        // Parse JSON response
        $jsonStart = strpos($output, '{');
        if ($jsonStart !== false) {
            $json = substr($output, $jsonStart);
            $response = json_decode($json, true);

            // Check if response is valid
            if (isset($response['success'])) {
                $passed++;
                echo "\033[32m✓\033[0m [{$method}] {$uri} - {$description}\n";
            } else {
                $failed++;
                echo "\033[31m✗\033[0m [{$method}] {$uri} - {$description}\n";
                echo "  Response: " . substr($json, 0, 100) . "...\n";
            }
        } else {
            $failed++;
            echo "\033[31m✗\033[0m [{$method}] {$uri} - {$description} (No JSON response)\n";
        }

    } catch (Exception $e) {
        ob_end_clean();
        $failed++;
        echo "\033[31m✗\033[0m [{$method}] {$uri} - {$description}\n";
        echo "  Error: {$e->getMessage()}\n";
    }

    // Reset for next iteration
    unset($_SERVER['REQUEST_METHOD']);
    unset($_SERVER['REQUEST_URI']);
}

echo "\n================================================================================\n";
echo " TEST SUMMARY\n";
echo "================================================================================\n";
echo "Total Tests:  {$total}\n";
echo "Passed:       \033[32m{$passed}\033[0m\n";
echo "Failed:       \033[31m{$failed}\033[0m\n";
$successRate = $total > 0 ? round(($passed / $total) * 100, 2) : 0;
echo "Success Rate: {$successRate}%\n";
echo "================================================================================\n";
