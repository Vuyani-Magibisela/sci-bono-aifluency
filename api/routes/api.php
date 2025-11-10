<?php
/**
 * API Routes Definition
 *
 * Defines all API endpoints and their handlers
 */

use App\Utils\Response;

// Helper function to require authentication
function requireAuth(): ?object
{
    $user = \App\Utils\JWTHandler::getCurrentUser();

    if (!$user) {
        Response::unauthorized('Authentication required');
    }

    return $user;
}

// Helper function to require specific role
function requireRole($roles): ?object
{
    $user = requireAuth();

    if (!$user) {
        return null;
    }

    if (is_string($roles)) {
        $roles = [$roles];
    }

    if (!in_array($user->role, $roles, true)) {
        Response::forbidden('Insufficient permissions');
    }

    return $user;
}

// Parse the request method and URI
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = str_replace('/api', '', $uri);
$uri = rtrim($uri, '/');
$uri = $uri ?: '/';

// Parse URI segments
$segments = array_filter(explode('/', $uri));
$segments = array_values($segments);

/**
 * Route Definition Format:
 * [
 *     'method' => 'GET|POST|PUT|DELETE',
 *     'pattern' => '/path/to/endpoint',
 *     'handler' => 'ControllerClass@method',
 *     'auth' => true|false,
 *     'roles' => ['admin', 'instructor'] // optional
 * ]
 */

$routes = [
    // Authentication Routes
    [
        'method' => 'POST',
        'pattern' => '/auth/register',
        'handler' => 'AuthController@register',
        'auth' => false
    ],
    [
        'method' => 'POST',
        'pattern' => '/auth/login',
        'handler' => 'AuthController@login',
        'auth' => false
    ],
    [
        'method' => 'POST',
        'pattern' => '/auth/refresh',
        'handler' => 'AuthController@refresh',
        'auth' => false
    ],
    [
        'method' => 'POST',
        'pattern' => '/auth/logout',
        'handler' => 'AuthController@logout',
        'auth' => true
    ],
    [
        'method' => 'GET',
        'pattern' => '/auth/me',
        'handler' => 'AuthController@me',
        'auth' => true
    ],

    // User Routes
    [
        'method' => 'GET',
        'pattern' => '/users',
        'handler' => 'UserController@index',
        'auth' => true,
        'roles' => ['admin', 'instructor']
    ],
    [
        'method' => 'GET',
        'pattern' => '/users/:id',
        'handler' => 'UserController@show',
        'auth' => true
    ],
    [
        'method' => 'PUT',
        'pattern' => '/users/:id',
        'handler' => 'UserController@update',
        'auth' => true
    ],
    [
        'method' => 'DELETE',
        'pattern' => '/users/:id',
        'handler' => 'UserController@delete',
        'auth' => true,
        'roles' => ['admin']
    ],

    // Course Routes
    [
        'method' => 'GET',
        'pattern' => '/courses',
        'handler' => 'CourseController@index',
        'auth' => false
    ],
    [
        'method' => 'GET',
        'pattern' => '/courses/:id',
        'handler' => 'CourseController@show',
        'auth' => false
    ],
    [
        'method' => 'POST',
        'pattern' => '/courses',
        'handler' => 'CourseController@create',
        'auth' => true,
        'roles' => ['admin', 'instructor']
    ],
    [
        'method' => 'PUT',
        'pattern' => '/courses/:id',
        'handler' => 'CourseController@update',
        'auth' => true,
        'roles' => ['admin', 'instructor']
    ],

    // Module Routes
    [
        'method' => 'GET',
        'pattern' => '/courses/:courseId/modules',
        'handler' => 'ModuleController@index',
        'auth' => false
    ],
    [
        'method' => 'GET',
        'pattern' => '/modules/:id',
        'handler' => 'ModuleController@show',
        'auth' => false
    ],

    // Lesson Routes
    [
        'method' => 'GET',
        'pattern' => '/modules/:moduleId/lessons',
        'handler' => 'LessonController@index',
        'auth' => false
    ],
    [
        'method' => 'GET',
        'pattern' => '/lessons/:id',
        'handler' => 'LessonController@show',
        'auth' => false
    ],

    // Quiz Routes
    [
        'method' => 'GET',
        'pattern' => '/modules/:moduleId/quiz',
        'handler' => 'QuizController@getByModule',
        'auth' => true
    ],
    [
        'method' => 'POST',
        'pattern' => '/quizzes/:id/submit',
        'handler' => 'QuizController@submit',
        'auth' => true
    ],

    // Progress Routes
    [
        'method' => 'GET',
        'pattern' => '/progress',
        'handler' => 'ProgressController@index',
        'auth' => true
    ],
    [
        'method' => 'POST',
        'pattern' => '/lessons/:id/progress',
        'handler' => 'ProgressController@updateLesson',
        'auth' => true
    ],
    [
        'method' => 'GET',
        'pattern' => '/courses/:id/progress',
        'handler' => 'ProgressController@getCourseProgress',
        'auth' => true
    ],

    // Enrollment Routes
    [
        'method' => 'POST',
        'pattern' => '/courses/:id/enroll',
        'handler' => 'EnrollmentController@enroll',
        'auth' => true
    ],
    [
        'method' => 'GET',
        'pattern' => '/enrollments',
        'handler' => 'EnrollmentController@index',
        'auth' => true
    ],

    // Certificate Routes
    [
        'method' => 'GET',
        'pattern' => '/certificates',
        'handler' => 'CertificateController@index',
        'auth' => true
    ],
    [
        'method' => 'GET',
        'pattern' => '/certificates/:id',
        'handler' => 'CertificateController@show',
        'auth' => true
    ],
];

// Match route
$matchedRoute = null;
$params = [];

foreach ($routes as $route) {
    if ($route['method'] !== $method && $method !== 'OPTIONS') {
        continue;
    }

    // Convert route pattern to regex
    $pattern = preg_replace('/:\w+/', '([^/]+)', $route['pattern']);
    $pattern = '#^' . $pattern . '$#';

    if (preg_match($pattern, $uri, $matches)) {
        array_shift($matches); // Remove full match

        // Extract parameter names
        preg_match_all('/:(\w+)/', $route['pattern'], $paramNames);

        // Build params array
        foreach ($paramNames[1] as $index => $name) {
            $params[$name] = $matches[$index] ?? null;
        }

        $matchedRoute = $route;
        break;
    }
}

// Handle OPTIONS requests (CORS preflight)
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Route not found
if (!$matchedRoute) {
    Response::notFound('Endpoint not found');
}

// Check authentication
if ($matchedRoute['auth']) {
    $user = requireAuth();

    // Check role permissions
    if (isset($matchedRoute['roles'])) {
        requireRole($matchedRoute['roles']);
    }
}

// Parse handler
list($controllerName, $methodName) = explode('@', $matchedRoute['handler']);
$controllerClass = "App\\Controllers\\{$controllerName}";

// Check if controller exists
if (!class_exists($controllerClass)) {
    Response::serverError("Controller {$controllerName} not found");
}

// Instantiate controller
$controller = new $controllerClass();

// Check if method exists
if (!method_exists($controller, $methodName)) {
    Response::serverError("Method {$methodName} not found in {$controllerName}");
}

// Call controller method with params
$controller->$methodName($params);
