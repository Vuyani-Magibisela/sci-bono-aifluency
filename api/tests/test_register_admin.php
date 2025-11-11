<?php
// Test registration with admin role
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// Setup environment
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/auth/register';
$_SERVER['HTTP_ORIGIN'] = 'http://localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'TestScript';

// Simulate POST data
$_POST = [
    'name' => 'Admin User',
    'email' => 'admin@test.com',
    'password' => 'AdminPass123!',
    'password_confirmation' => 'AdminPass123!',
    'role' => 'admin'
];

// Create controller and test
use App\Controllers\AuthController;

try {
    $controller = new AuthController();
    $controller->register([]);
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
