<?php
// Test refresh token endpoint
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// Setup environment
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/auth/refresh';
$_SERVER['HTTP_ORIGIN'] = 'http://localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'TestScript';

// Use the refresh token from login response
$_POST = [
    'refreshToken' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vbG9jYWxob3N0L3NjaS1ib25vLWFpZmx1ZW5jeSIsImF1ZCI6Imh0dHA6Ly9sb2NhbGhvc3Qvc2NpLWJvbm8tYWlmbHVlbmN5IiwiaWF0IjoxNzYyNzc1MzU3LCJleHAiOjE3NjUzNjczNTcsInN1YiI6MywidHlwZSI6InJlZnJlc2gifQ.c2o29VKAUChffkyY8VmjweYnH3T813nCphwwqsUCKKo'
];

// Create controller and test
use App\Controllers\AuthController;

try {
    $controller = new AuthController();
    $controller->refresh([]);
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
