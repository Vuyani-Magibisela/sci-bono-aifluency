<?php
// Simulate POST request to registration endpoint
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/auth/register';
$_SERVER['HTTP_ORIGIN'] = 'http://localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'TestScript';
$_SERVER['CONTENT_TYPE'] = 'application/json';

// Simulate JSON input
$jsonData = json_encode([
    'name' => 'Test Student',
    'email' => 'teststudent@test.com',
    'password' => 'SecurePass123!',
    'password_confirmation' => 'SecurePass123!',
    'role' => 'student'
]);

// Parse JSON into $_POST
$_POST = json_decode($jsonData, true);

// Include the API entry point
require_once __DIR__ . '/index.php';
