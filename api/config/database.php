<?php
/**
 * Database Configuration
 *
 * PDO Database connection for Sci-Bono AI Fluency LMS
 */

// Use constants defined by config.php (loaded via Dotenv, which strips quotes properly)
$host = defined('DB_HOST') ? DB_HOST : ($_ENV['DB_HOST'] ?? 'localhost');
$port = defined('DB_PORT') ? DB_PORT : ($_ENV['DB_PORT'] ?? 3306);
$dbname = defined('DB_NAME') ? DB_NAME : ($_ENV['DB_NAME'] ?? 'ai_fluency_lms');
$username = defined('DB_USER') ? DB_USER : ($_ENV['DB_USER'] ?? 'root');
$password = defined('DB_PASSWORD') ? DB_PASSWORD : ($_ENV['DB_PASSWORD'] ?? '');


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
    // Log the actual error for debugging
    error_log("Database connection failed: " . $e->getMessage());

    // Throw exception so index.php's catch block returns proper JSON error
    if (defined('APP_DEBUG') && APP_DEBUG) {
        throw new \RuntimeException("Database connection failed: " . $e->getMessage(), 500, $e);
    } else {
        throw new \RuntimeException("Database connection failed. Please contact support.", 500, $e);
    }
}


// Return PDO instance for use in other scripts
return $pdo;
