<?php
/**
 * Local development router for the PHP built-in server.
 *
 * Apache/mod_rewrite is not available under `php -S`, so the pretty `/api/*` URLs
 * that `api/.htaccess` normally rewrites would 404. This script reproduces that
 * rewrite rule plus the .htaccess deny rules, and lets everything else fall through
 * to the built-in static handler.
 *
 * Usage (from the repo root):
 *     php -S localhost:8000 dev-router.php
 *
 * Development only - excluded from production uploads via .deployignore.
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (strpos($uri, '/api/') === 0 || $uri === '/api') {

    // Mirror the RedirectMatch 403 rules in api/.htaccess. mod_rewrite is not doing it
    // for us here, and without this the built-in server would happily serve api/.env.
    $denied = [
        '#^/api/\.env#',
        '#^/api/config/#',
        '#^/api/vendor/#',
        '#^/api/composer\.(json|lock)$#',
        '#^/api/\.git#',
        '#^/api/logs/#',
    ];
    foreach ($denied as $pattern) {
        if (preg_match($pattern, $uri)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => false, 'message' => 'Forbidden']);
            return true;
        }
    }

    // Static assets that physically exist under /api/ are served as-is, mirroring the
    // RewriteCond !-f / !-d guard in api/.htaccess. PHP files never are - they must go
    // through the front controller.
    $target = __DIR__ . $uri;
    if (is_file($target) && substr($target, -4) !== '.php') {
        return false;
    }

    // REQUEST_URI is passed through untouched - api/routes/api.php strips the
    // /api prefix itself during its own $uri normalisation.
    require __DIR__ . '/api/index.php';
    return true;
}

// Everything else: let the built-in server serve it from disk.
return false;
