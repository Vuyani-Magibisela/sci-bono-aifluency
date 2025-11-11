<?php
// Test update user endpoint (PUT /users/:id)
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// Setup environment
$_SERVER['REQUEST_METHOD'] = 'PUT';
$_SERVER['REQUEST_URI'] = '/api/users/3';
$_SERVER['HTTP_ORIGIN'] = 'http://localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'TestScript';

// Use fresh access token
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vbG9jYWxob3N0L3NjaS1ib25vLWFpZmx1ZW5jeSIsImF1ZCI6Imh0dHA6Ly9sb2NhbGhvc3Qvc2NpLWJvbm8tYWlmbHVlbmN5IiwiaWF0IjoxNzYyNzc1NTM1LCJleHAiOjE3NjI3NzkxMzUsInN1YiI6MywiZW1haWwiOiJ0ZXN0Zml4ZWRAdGVzdC5jb20iLCJyb2xlIjoic3R1ZGVudCIsInR5cGUiOiJhY2Nlc3MifQ.QRL9NTylIL0mKHjf3sxuh6of59ThlvKkfM-d3-wVmRI';

// Simulate POST data for update (only name field exists in current schema)
$_POST = [
    'name' => 'Test Student Updated'
];

// Create controller and test
use App\Controllers\UserController;

try {
    $controller = new UserController();
    $controller->update(['id' => 3]);
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
