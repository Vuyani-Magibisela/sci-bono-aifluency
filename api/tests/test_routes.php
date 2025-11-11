<?php
// Test that routing system works
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// Simulate GET /api/courses request
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/courses';
$_SERVER['HTTP_ORIGIN'] = 'http://localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'TestScript';

echo "Testing route: GET /api/courses\n";
echo "==========================================\n\n";

// Include the routes file which will process the request
include __DIR__ . '/routes/api.php';
