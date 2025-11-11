<?php
// Test list users endpoint (GET /users)
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// Setup environment
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/users';
$_SERVER['HTTP_ORIGIN'] = 'http://localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'TestScript';

// Use admin access token
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vbG9jYWxob3N0L3NjaS1ib25vLWFpZmx1ZW5jeSIsImF1ZCI6Imh0dHA6Ly9sb2NhbGhvc3Qvc2NpLWJvbm8tYWlmbHVlbmN5IiwiaWF0IjoxNzYyNzc1NjI1LCJleHAiOjE3NjI3NzkyMjUsInN1YiI6NCwiZW1haWwiOiJhZG1pbkB0ZXN0LmNvbSIsInJvbGUiOiJhZG1pbiIsInR5cGUiOiJhY2Nlc3MifQ.U-RKf3PolMyZ1uZFHINfY8zc3CxpacvKAWFwoiJvXXo';

// Simulate query parameters
$_GET['page'] = 1;
$_GET['pageSize'] = 10;

// Create controller and test
use App\Controllers\UserController;

try {
    $controller = new UserController();
    $controller->index([]);
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
