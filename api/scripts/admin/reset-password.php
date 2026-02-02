<?php
/**
 * Admin Password Reset Utility
 * Resets the password for admin@sci-bono.org
 */

// Load database connection
$pdo = require_once __DIR__ . '/api/config/database.php';

echo "=== Admin Password Reset Utility ===\n\n";

// Check if admin user exists
$stmt = $pdo->prepare("SELECT id, email, role, name, is_active FROM users WHERE email = ?");
$stmt->execute(['admin@sci-bono.org']);
$admin = $stmt->fetch();

if (!$admin) {
    echo "❌ Admin user not found with email: admin@sci-bono.org\n";
    echo "\nChecking all admin users in database:\n";

    $stmt = $pdo->query("SELECT id, email, role, name, is_active FROM users WHERE role = 'admin'");
    $admins = $stmt->fetchAll();

    if (empty($admins)) {
        echo "❌ No admin users found in database!\n";
        echo "\nWould you like to create an admin user? (This requires manual database insertion)\n";
    } else {
        echo "Found " . count($admins) . " admin user(s):\n";
        foreach ($admins as $user) {
            echo "  - ID: {$user['id']}, Email: {$user['email']}, Name: {$user['name']}, Active: " . ($user['is_active'] ? 'Yes' : 'No') . "\n";
        }
    }
    exit(1);
}

echo "✅ Found admin user:\n";
echo "  - ID: {$admin['id']}\n";
echo "  - Email: {$admin['email']}\n";
echo "  - Name: {$admin['name']}\n";
echo "  - Role: {$admin['role']}\n";
echo "  - Active: " . ($admin['is_active'] ? 'Yes' : 'No') . "\n\n";

// Set new password
$newPassword = 'admin123';
$hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

echo "Resetting password to: $newPassword\n";
echo "Hashed password: $hashedPassword\n\n";

// Update password
$updateStmt = $pdo->prepare("UPDATE users SET password_hash = ?, is_active = 1 WHERE id = ?");
$result = $updateStmt->execute([$hashedPassword, $admin['id']]);

if ($result) {
    echo "✅ Password reset successful!\n\n";
    echo "=== LOGIN CREDENTIALS ===\n";
    echo "Email: admin@sci-bono.org\n";
    echo "Password: admin123\n";
    echo "========================\n\n";
    echo "⚠️  IMPORTANT: Please change this password after logging in!\n";
} else {
    echo "❌ Password reset failed!\n";
    exit(1);
}
