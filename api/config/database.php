<?php
/**
 * Database Configuration
 *
 * PDO Database connection for Sci-Bono AI Fluency LMS
 */

// Database configuration from .env, loaded via $_ENV from config.php
$host = $_ENV['DB_HOST'] ?? 'localhost';
$port = $_ENV['DB_PORT'] ?? 3306;
$dbname = $_ENV['DB_NAME'] ?? 'ai_fluency_lms';
$username = $_ENV['DB_USER'] ?? 'root';
$password = $_ENV['DB_PASSWORD'] ?? '';


// DSN (Data Source Name)
$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

// PDO options
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

// error_log("DB_HOST: " . $host);
// error_log("DB_PORT: " . $port);
// error_log("DB_NAME: " . $dbname);
// error_log("DB_USER: " . $username);
// error_log("DB_PASSWORD_LENGTH: " . strlen($password)); // Log length, not value for security

try {
    // Create PDO instance
    $pdo = new PDO($dsn, $username, $password, $options);
    // error_log("Database connection successful.");
} catch (PDOException $e) {
    // In production, log the error instead of displaying it, REVERTED TO DISPLAY FOR DEBUGGING
    if ($_ENV['APP_DEBUG'] === 'true') {
        die("Database connection failed: " . $e->getMessage() . "\n"); 
        // error_log("Database connection failed: " . $e->getMessage());
        // throw new PDOException("Database connection failed: " . $e->getMessage());
    } else {
        die("Database connection failed. Please contact support.\n"); 
        // error_log("Database connection failed: " . $e->getMessage());
        // throw new PDOException("Database connection failed. Please contact support.");
    }
}


// Return PDO instance for use in other scripts
return $pdo;
