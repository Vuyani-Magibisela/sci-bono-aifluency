<?php
// Test database connection
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing database connection...\n\n";

$host = 'localhost';
$dbname = 'ai_fluency_lms';
$username = 'ai_fluency_user';
$password = 'AiFluency2024!@SM0yi5NiKo';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "✓ Database connection successful!\n";

    // Check if users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Users table exists\n";

        // Check if admin user exists
        $stmt = $pdo->query("SELECT email, name, role FROM users WHERE role='admin' LIMIT 1");
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin) {
            echo "✓ Admin user found: " . $admin['email'] . " (" . $admin['name'] . ")\n";
        } else {
            echo "✗ No admin user found\n";
        }
    } else {
        echo "✗ Users table does not exist\n";
        echo "  → Run migrations to create tables\n";
    }

} catch (PDOException $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";

    if (strpos($e->getMessage(), 'Access denied') !== false) {
        echo "  → Check database username and password\n";
    } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
        echo "  → Database 'ai_fluency_lms' does not exist\n";
        echo "  → Create it with: CREATE DATABASE ai_fluency_lms;\n";
    }
}
