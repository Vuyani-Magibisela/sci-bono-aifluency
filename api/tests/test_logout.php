<?php
// Test logout endpoint
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// Setup environment
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/auth/logout';
$_SERVER['HTTP_ORIGIN'] = 'http://localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'TestScript';

// Use the access token from refresh response
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vbG9jYWxob3N0L3NjaS1ib25vLWFpZmx1ZW5jeSIsImF1ZCI6Imh0dHA6Ly9sb2NhbGhvc3Qvc2NpLWJvbm8tYWlmbHVlbmN5IiwiaWF0IjoxNzYyNzc1MzcwLCJleHAiOjE3NjI3Nzg5NzAsInN1YiI6MywiZW1haWwiOiJ0ZXN0Zml4ZWRAdGVzdC5jb20iLCJyb2xlIjoic3R1ZGVudCIsInR5cGUiOiJhY2Nlc3MifQ.W_Sn5cWVuGcTZVum-YUqjmPeub8L0SOewfaTKwxexd0';

// Create controller and test
use App\Controllers\AuthController;

try {
    $controller = new AuthController();
    $controller->logout([]);
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
