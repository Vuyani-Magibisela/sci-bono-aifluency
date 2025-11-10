<?php
/**
 * API Entry Point
 *
 * Main entry point for all API requests
 */

// Enable error reporting based on environment
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Start output buffering
ob_start();

try {
    // Load Composer autoloader
    require_once __DIR__ . '/vendor/autoload.php';

    // Load configuration
    require_once __DIR__ . '/config/config.php';

    // Load database connection
    require_once __DIR__ . '/config/database.php';

    // Set response headers
    header('Content-Type: application/json; charset=UTF-8');

    // Handle CORS
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (in_array($origin, CORS_ALLOWED_ORIGINS)) {
        header("Access-Control-Allow-Origin: {$origin}");
        header('Access-Control-Allow-Credentials: true');
    } else {
        // Allow localhost origins in development
        if (APP_DEBUG && (strpos($origin, 'localhost') !== false || strpos($origin, '127.0.0.1') !== false)) {
            header("Access-Control-Allow-Origin: {$origin}");
            header('Access-Control-Allow-Credentials: true');
        }
    }

    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 3600');

    // Handle OPTIONS preflight request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    // Parse JSON request body for POST, PUT, DELETE
    if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE', 'PATCH'])) {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (strpos($contentType, 'application/json') !== false) {
            $rawInput = file_get_contents('php://input');
            $_POST = json_decode($rawInput, true) ?? [];

            // Handle JSON decode errors
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid JSON in request body: ' . json_last_error_msg()
                ]);
                exit;
            }
        }
    }

    // Log request in debug mode
    if (APP_DEBUG) {
        $logEntry = sprintf(
            "[%s] %s %s - IP: %s - User-Agent: %s\n",
            date('Y-m-d H:i:s'),
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['REQUEST_URI'],
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        );

        $logFile = __DIR__ . '/logs/access.log';
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }

    // Rate limiting (simple implementation)
    if (defined('RATE_LIMIT_REQUESTS') && RATE_LIMIT_REQUESTS > 0) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rateLimit = __DIR__ . '/logs/rate_limit_' . md5($ip) . '.txt';

        if (file_exists($rateLimit)) {
            $requests = (int) file_get_contents($rateLimit);
            $fileTime = filemtime($rateLimit);

            // Reset counter if window expired
            if (time() - $fileTime > RATE_LIMIT_WINDOW) {
                $requests = 0;
            }

            if ($requests >= RATE_LIMIT_REQUESTS) {
                http_response_code(429);
                echo json_encode([
                    'success' => false,
                    'message' => 'Too many requests. Please try again later.'
                ]);
                exit;
            }

            $requests++;
            file_put_contents($rateLimit, $requests);
        } else {
            file_put_contents($rateLimit, 1);
        }
    }

    // Load routes
    require_once __DIR__ . '/routes/api.php';

} catch (\Throwable $e) {
    // Clear any output
    ob_end_clean();

    // Log error
    $errorMessage = sprintf(
        "[%s] ERROR: %s in %s:%d\nTrace: %s\n",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );

    $errorLog = __DIR__ . '/logs/error.log';
    $logDir = dirname($errorLog);

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    file_put_contents($errorLog, $errorMessage, FILE_APPEND);

    // Send error response
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');

    $response = [
        'success' => false,
        'message' => APP_DEBUG ? $e->getMessage() : 'Internal server error'
    ];

    if (APP_DEBUG) {
        $response['debug'] = [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString())
        ];
    }

    echo json_encode($response);
}

// Flush output buffer
ob_end_flush();
