<?php
/**
 * BaseController - Abstract base class for all controllers
 *
 * Provides centralized authentication and authorization methods
 * to eliminate code duplication across 16+ controllers.
 *
 * Created: Phase 11 (MVC Transformation & Code Refactoring)
 * Purpose: Security-first consolidation of auth logic
 */

require_once __DIR__ . '/../utils/JWTHandler.php';
require_once __DIR__ . '/../utils/Response.php';

abstract class BaseController
{
    protected PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get current authenticated user from JWT token
     *
     * Validates JWT token and checks against blacklist.
     * This method is called by all protected endpoints.
     *
     * @return object User object with id, email, role properties
     * @throws Response 401 if not authenticated or token blacklisted
     */
    protected function getCurrentUser(): object
    {
        $currentUser = JWTHandler::getCurrentUser();
        if (!$currentUser) {
            Response::unauthorized('Authentication required');
        }

        // Check if token is blacklisted (user logged out)
        $token = JWTHandler::extractTokenFromHeader();
        if ($token && JWTHandler::isTokenBlacklisted($token, $this->pdo)) {
            Response::unauthorized('Token has been revoked. Please login again.');
        }

        return $currentUser;
    }

    /**
     * Require user to have specific role(s)
     *
     * Used for role-based access control (RBAC).
     * Example: $this->requireRole(['admin', 'instructor']);
     *
     * @param string|array $roles Single role or array of allowed roles
     * @throws Response 403 if user lacks required role
     */
    protected function requireRole($roles): void
    {
        $currentUser = $this->getCurrentUser();

        // Convert single role to array for consistent handling
        if (is_string($roles)) {
            $roles = [$roles];
        }

        if (!in_array($currentUser->role, $roles, true)) {
            Response::forbidden('You do not have permission to perform this action');
        }
    }

    /**
     * Check if user owns a resource (student can only access their own data)
     *
     * Implements ownership-based access control. Students can only access
     * their own data, but admins/instructors can access any user's data.
     *
     * @param int $resourceUserId The user_id of the resource being accessed
     * @param array $allowedRoles Roles that can bypass ownership check (default: admin, instructor)
     * @throws Response 403 if user doesn't own resource and lacks privileged role
     */
    protected function requireOwnershipOrRole($resourceUserId, array $allowedRoles = ['admin', 'instructor']): void
    {
        $currentUser = $this->getCurrentUser();

        // Admins/instructors can access any user's data
        if (in_array($currentUser->role, $allowedRoles, true)) {
            return;
        }

        // Students can only access their own data
        if ((int)$currentUser->id !== (int)$resourceUserId) {
            Response::forbidden('You can only access your own data');
        }
    }

    /**
     * Execute database query with standardized error handling
     *
     * Wraps database operations in try-catch for consistent error responses.
     * Logs errors for debugging while showing safe messages to users.
     *
     * @param callable $callback Database operation to execute
     * @param string $errorMessage Custom error message for user-facing response
     * @return mixed Result from callback
     * @throws Response 500 on database or application error
     */
    protected function executeWithErrorHandling(callable $callback, string $errorMessage = 'Database operation failed')
    {
        try {
            return $callback();
        } catch (PDOException $e) {
            // Log full error for debugging (not shown to user)
            error_log("Database Error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());

            // Show safe error message to user
            Response::error($errorMessage, 500);
        } catch (Exception $e) {
            // Log unexpected errors
            error_log("Application Error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());

            // Generic error for security
            Response::error('An unexpected error occurred', 500);
        }
    }

    /**
     * Validate required parameters exist in request
     *
     * Checks that all required parameters are present and non-empty.
     *
     * @param array $params Parameter array to validate (e.g., $params from route)
     * @param array $required Array of required parameter names
     * @throws Response 400 if any required parameter is missing
     */
    protected function validateRequiredParams(array $params, array $required): void
    {
        $missing = [];

        foreach ($required as $param) {
            if (!isset($params[$param]) || trim($params[$param]) === '') {
                $missing[] = $param;
            }
        }

        if (!empty($missing)) {
            Response::validationError([
                'message' => 'Missing required parameters',
                'missing_fields' => $missing
            ]);
        }
    }
}
