<?php
// Simple test for organizations endpoint
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config/database.php';

use App\Controllers\OrganizationController;

try {
    echo "Creating OrganizationController...\n";
    $controller = new OrganizationController($pdo);
    echo "Controller created successfully\n";

    echo "\nCalling index method...\n";
    $_GET = [];
    ob_start();
    $controller->index([]);
    $output = ob_get_clean();
    echo "Output: $output\n";

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
