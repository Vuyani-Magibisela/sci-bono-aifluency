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

    // Course Routes (5 endpoints)
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
    [
        'method' => 'DELETE',
        'pattern' => '/courses/:id',
        'handler' => 'CourseController@delete',
        'auth' => true,
        'roles' => ['admin']
    ],

    // Module Routes (5 endpoints)
    [
        'method' => 'GET',
        'pattern' => '/modules',
        'handler' => 'ModuleController@index',
        'auth' => false
    ],
    [
        'method' => 'GET',
        'pattern' => '/modules/:id',
        'handler' => 'ModuleController@show',
        'auth' => false
    ],
    [
        'method' => 'POST',
        'pattern' => '/modules',
        'handler' => 'ModuleController@create',
        'auth' => true,
        'roles' => ['admin', 'instructor']
    ],
    [
        'method' => 'PUT',
        'pattern' => '/modules/:id',
        'handler' => 'ModuleController@update',
        'auth' => true,
        'roles' => ['admin', 'instructor']
    ],
    [
        'method' => 'DELETE',
        'pattern' => '/modules/:id',
        'handler' => 'ModuleController@delete',
        'auth' => true,
        'roles' => ['admin']
    ],

    // Lesson Routes (7 endpoints)
    [
        'method' => 'GET',
        'pattern' => '/lessons',
        'handler' => 'LessonController@index',
        'auth' => false
    ],
    [
        'method' => 'GET',
        'pattern' => '/lessons/:id',
        'handler' => 'LessonController@show',
        'auth' => false
    ],
    [
        'method' => 'POST',
        'pattern' => '/lessons',
        'handler' => 'LessonController@create',
        'auth' => true,
        'roles' => ['admin', 'instructor']
    ],
    [
        'method' => 'PUT',
        'pattern' => '/lessons/:id',
        'handler' => 'LessonController@update',
        'auth' => true,
        'roles' => ['admin', 'instructor']
    ],
    [
        'method' => 'DELETE',
        'pattern' => '/lessons/:id',
        'handler' => 'LessonController@delete',
        'auth' => true,
        'roles' => ['admin']
    ],
    [
        'method' => 'POST',
        'pattern' => '/lessons/:id/start',
        'handler' => 'LessonController@startLesson',
        'auth' => true
    ],
    [
        'method' => 'POST',
        'pattern' => '/lessons/:id/complete',
        'handler' => 'LessonController@completeLesson',
        'auth' => true
    ],

    // Quiz Routes (7 endpoints)
    [
        'method' => 'GET',
        'pattern' => '/quizzes',
        'handler' => 'QuizController@index',
        'auth' => false
    ],
    [
        'method' => 'GET',
        'pattern' => '/quizzes/:id',
        'handler' => 'QuizController@show',
        'auth' => false
    ],
    [
        'method' => 'POST',
        'pattern' => '/quizzes',
        'handler' => 'QuizController@create',
        'auth' => true,
        'roles' => ['admin', 'instructor']
    ],
    [
        'method' => 'PUT',
        'pattern' => '/quizzes/:id',
        'handler' => 'QuizController@update',
        'auth' => true,
        'roles' => ['admin', 'instructor']
    ],
    [
        'method' => 'DELETE',
        'pattern' => '/quizzes/:id',
        'handler' => 'QuizController@delete',
        'auth' => true,
        'roles' => ['admin']
    ],
    [
        'method' => 'POST',
        'pattern' => '/quizzes/:id/submit',
        'handler' => 'QuizController@submitAttempt',
        'auth' => true
    ],
    [
        'method' => 'GET',
        'pattern' => '/quizzes/:id/attempts',
        'handler' => 'QuizController@getAttempts',
        'auth' => true
    ],

    // Project Routes (8 endpoints)
    [
        'method' => 'GET',
        'pattern' => '/projects',
        'handler' => 'ProjectController@index',
        'auth' => false
    ],
    [
        'method' => 'GET',
        'pattern' => '/projects/:id',
        'handler' => 'ProjectController@show',
        'auth' => false
    ],
    [
        'method' => 'POST',
        'pattern' => '/projects',
        'handler' => 'ProjectController@create',
        'auth' => true,
        'roles' => ['admin', 'instructor']
    ],
    [
        'method' => 'PUT',
        'pattern' => '/projects/:id',
        'handler' => 'ProjectController@update',
        'auth' => true,
        'roles' => ['admin', 'instructor']
    ],
    [
        'method' => 'DELETE',
        'pattern' => '/projects/:id',
        'handler' => 'ProjectController@delete',
        'auth' => true,
        'roles' => ['admin']
    ],
    [
        'method' => 'POST',
        'pattern' => '/projects/:id/submit',
        'handler' => 'ProjectController@submitProject',
        'auth' => true
    ],
    [
        'method' => 'GET',
        'pattern' => '/projects/:id/submissions',
        'handler' => 'ProjectController@getSubmissions',
        'auth' => true
    ],
    [
        'method' => 'POST',
        'pattern' => '/projects/submissions/:id/grade',
        'handler' => 'ProjectController@gradeSubmission',
        'auth' => true,
        'roles' => ['admin', 'instructor']
    ],

    // Enrollment Routes (6 endpoints)
    [
        'method' => 'GET',
        'pattern' => '/enrollments',
        'handler' => 'EnrollmentController@index',
        'auth' => true
    ],
    [
        'method' => 'GET',
        'pattern' => '/enrollments/:id',
        'handler' => 'EnrollmentController@show',
        'auth' => true
    ],
    [
        'method' => 'POST',
        'pattern' => '/enrollments',
        'handler' => 'EnrollmentController@create',
        'auth' => true
    ],
    [
        'method' => 'PUT',
        'pattern' => '/enrollments/:id',
        'handler' => 'EnrollmentController@update',
        'auth' => true
    ],
    [
        'method' => 'DELETE',
        'pattern' => '/enrollments/:id',
        'handler' => 'EnrollmentController@delete',
        'auth' => true
    ],
    [
        'method' => 'POST',
        'pattern' => '/enrollments/:id/calculate-progress',
        'handler' => 'EnrollmentController@calculateProgress',
        'auth' => true
    ],

    // Certificate Routes (7 endpoints)
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
    [
        'method' => 'POST',
        'pattern' => '/certificates',
        'handler' => 'CertificateController@create',
        'auth' => true,
        'roles' => ['admin', 'instructor']
    ],
    [
        'method' => 'PUT',
        'pattern' => '/certificates/:id',
        'handler' => 'CertificateController@update',
        'auth' => true,
        'roles' => ['admin']
    ],
    [
        'method' => 'DELETE',
        'pattern' => '/certificates/:id',
        'handler' => 'CertificateController@delete',
        'auth' => true,
        'roles' => ['admin']
    ],
    [
        'method' => 'GET',
        'pattern' => '/certificates/verify/:certificate_number',
        'handler' => 'CertificateController@verify',
        'auth' => false
    ],
    [
        'method' => 'POST',
        'pattern' => '/certificates/request',
        'handler' => 'CertificateController@requestCertificate',
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
