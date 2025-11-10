<?php
/**
 * Database Configuration
 *
 * PDO Database connection for Sci-Bono AI Fluency LMS
 */

// Load environment variables from .env file
$envFile = __DIR__ . '/../.env';

if (!file_exists($envFile)) {
    die("ERROR: .env file not found at: $envFile\n");
}

// Parse .env file (custom parser to handle special characters)
$env = [];
$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($lines as $line) {
    // Skip comments and empty lines
    if (empty($line) || strpos(trim($line), '#') === 0) {
        continue;
    }

    // Parse KEY=VALUE
    if (strpos($line, '=') !== false) {
        list($key, $value) = explode('=', $line, 2);
        $env[trim($key)] = trim($value);
    }
}

if (empty($env)) {
    die("ERROR: Failed to parse .env file\n");
}

// Database configuration from .env
$host = $env['DB_HOST'] ?? 'localhost';
$port = $env['DB_PORT'] ?? 3306;
$dbname = $env['DB_NAME'] ?? 'ai_fluency_lms';
$username = $env['DB_USER'] ?? 'root';
$password = $env['DB_PASSWORD'] ?? '';

// DSN (Data Source Name)
$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

// PDO options
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    // Create PDO instance
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    // In production, log the error instead of displaying it
    if ($env['APP_DEBUG'] === 'true') {
        die("Database connection failed: " . $e->getMessage() . "\n");
    } else {
        die("Database connection failed. Please contact support.\n");
    }
}

// Return PDO instance for use in other scripts
return $pdo;
