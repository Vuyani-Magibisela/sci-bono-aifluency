<?php
// Test login endpoint
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// Setup environment
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/auth/login';
$_SERVER['HTTP_ORIGIN'] = 'http://localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'TestScript';

// Simulate POST data
$_POST = [
    'email' => 'testfixed@test.com',
    'password' => 'SecurePass123!'
];

// Create controller and test
use App\Controllers\AuthController;

try {
    $controller = new AuthController();
    $controller->login([]);
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
