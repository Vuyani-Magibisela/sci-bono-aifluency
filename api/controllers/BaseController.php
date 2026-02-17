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

namespace App\Controllers;

use App\Utils\JWTHandler;
use App\Utils\Response;

abstract class BaseController
{
    protected \PDO $pdo;
    private ?object $cachedCurrentUser = null;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get current authenticated user from JWT token
     *
     * Validates JWT token, checks against blacklist, then enriches with
     * full DB record so that primary_organization_id and primary_school_id
     * are available for organizational scoping (fixes Phase 10 JWT gap).
     *
     * @return object Full user object from DB (includes org/school IDs)
     * @throws Response 401 if not authenticated or token blacklisted
     */
    protected function getCurrentUser(): object
    {
        if ($this->cachedCurrentUser !== null) {
            return $this->cachedCurrentUser;
        }

        $jwtUser = JWTHandler::getCurrentUser();
        if (!$jwtUser) {
            Response::unauthorized('Authentication required');
        }

        // Check if token is blacklisted (user logged out)
        $token = JWTHandler::extractTokenFromHeader();
        if ($token && JWTHandler::isTokenBlacklisted($token, $this->pdo)) {
            Response::unauthorized('Token has been revoked. Please login again.');
        }

        // Fetch full user record from DB so org/school IDs are available
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$jwtUser->id]);
        $fullUser = $stmt->fetch(\PDO::FETCH_OBJ);

        if (!$fullUser) {
            Response::unauthorized('User not found or inactive');
        }

        $this->cachedCurrentUser = $fullUser;
        return $this->cachedCurrentUser;
    }

    /**
     * Require user to have specific role(s)
     *
     * Used for role-based access control (RBAC).
     * Example: $this->requireRole(['superadmin', 'orgadmin', 'schooladmin', 'teacher']);
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

    /**
     * Get organization IDs managed by current user
     * Phase 12 - Hierarchical RBAC
     */
    protected function getManagedOrganizationIds(): ?array
    {
        $user = $this->getCurrentUser();

        if ($user->role === 'superadmin') {
            return null; // null means all organizations
        }

        if ($user->role === 'orgadmin') {
            // Return organizations where user is orgadmin
            return $user->primary_organization_id ? [$user->primary_organization_id] : [];
        }

        if ($user->role === 'schooladmin') {
            // Return organization of their school
            return $user->primary_organization_id ? [$user->primary_organization_id] : [];
        }

        return []; // Teachers and students manage no organizations
    }

    /**
     * Get school IDs managed by current user
     * Phase 12 - Hierarchical RBAC
     */
    protected function getManagedSchoolIds(): ?array
    {
        $user = $this->getCurrentUser();

        if ($user->role === 'superadmin') {
            return null; // null means all schools
        }

        if ($user->role === 'orgadmin') {
            // Get all schools in their organization(s)
            $orgIds = $this->getManagedOrganizationIds();
            if (empty($orgIds)) {
                return [];
            }

            // Query schools by organization
            $stmt = $this->pdo->prepare("
                SELECT id FROM schools WHERE organization_id IN (" . implode(',', $orgIds) . ")
            ");
            $stmt->execute();
            return array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'id');
        }

        if ($user->role === 'schooladmin') {
            // Return their school only
            return $user->primary_school_id ? [$user->primary_school_id] : [];
        }

        return []; // Teachers and students manage no schools
    }

    /**
     * Check if user can manage a specific organization
     * Phase 12 - Hierarchical RBAC
     */
    protected function requireOrganizationManagementPermission(int $organizationId): void
    {
        $user = $this->getCurrentUser();

        if ($user->role === 'superadmin') {
            return; // SuperAdmins can manage all organizations
        }

        $managedOrgIds = $this->getManagedOrganizationIds();
        if ($managedOrgIds === null || in_array($organizationId, $managedOrgIds)) {
            return;
        }

        Response::forbidden('You do not have permission to manage this organization');
    }

    /**
     * Check if user can manage a specific school
     * Phase 12 - Hierarchical RBAC
     */
    protected function requireSchoolManagementPermission(object $school): void
    {
        $user = $this->getCurrentUser();

        if ($user->role === 'superadmin') {
            return; // SuperAdmins can manage all schools
        }

        $managedSchoolIds = $this->getManagedSchoolIds();
        if ($managedSchoolIds === null || in_array($school->id, $managedSchoolIds)) {
            return;
        }

        Response::forbidden('You do not have permission to manage this school');
    }

    /**
     * Check if current user can assign a specific role
     * Phase 12 - Hierarchical RBAC
     */
    protected function canAssignRole(string $targetRole): bool
    {
        $user = $this->getCurrentUser();

        $roleHierarchy = [
            'superadmin' => 5,
            'orgadmin' => 4,
            'schooladmin' => 3,
            'teacher' => 2,
            'student' => 1
        ];

        $currentLevel = $roleHierarchy[$user->role] ?? 0;
        $targetLevel = $roleHierarchy[$targetRole] ?? 0;

        // Can only assign roles lower than your own
        return $targetLevel < $currentLevel;
    }
}
