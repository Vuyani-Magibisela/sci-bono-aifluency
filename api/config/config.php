<?php
/**
 * Application Configuration
 *
 * Central configuration file for the Sci-Bono AI Fluency LMS API
 */

// Load environment variables using PHPDotEnv
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Application Settings
define('APP_ENV', $_ENV['APP_ENV'] ?? 'production');
define('APP_DEBUG', filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN));
define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost');

// Database Settings
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_PORT', $_ENV['DB_PORT'] ?? 3306);
define('DB_NAME', $_ENV['DB_NAME'] ?? 'ai_fluency_lms');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? '');

// JWT Settings
define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? '');
define('JWT_EXPIRY', (int)($_ENV['JWT_EXPIRY'] ?? 3600)); // 1 hour
define('JWT_REFRESH_EXPIRY', (int)($_ENV['JWT_REFRESH_EXPIRY'] ?? 2592000)); // 30 days
define('JWT_ALGORITHM', 'HS256');

// API Settings
define('API_VERSION', 'v1');
define('API_PREFIX', '/api');

// CORS Settings
define('CORS_ALLOWED_ORIGINS', [
    'http://localhost',
    'http://localhost:8000',
    'http://localhost:3000',
    APP_URL
]);

// Security Settings
define('PASSWORD_MIN_LENGTH', 8);
define('RATE_LIMIT_REQUESTS', 100); // requests per window
define('RATE_LIMIT_WINDOW', 3600); // 1 hour in seconds

// File Upload Settings
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('UPLOAD_ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);
define('UPLOAD_PATH', __DIR__ . '/../../uploads/');

// Pagination
define('DEFAULT_PAGE_SIZE', 20);
define('MAX_PAGE_SIZE', 100);

// Email Settings (for future use)
define('MAIL_HOST', $_ENV['MAIL_HOST'] ?? '');
define('MAIL_PORT', $_ENV['MAIL_PORT'] ?? 587);
define('MAIL_USERNAME', $_ENV['MAIL_USERNAME'] ?? '');
define('MAIL_PASSWORD', $_ENV['MAIL_PASSWORD'] ?? '');

// Timezone
date_default_timezone_set('Africa/Johannesburg');

// Error Reporting
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
}

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}
