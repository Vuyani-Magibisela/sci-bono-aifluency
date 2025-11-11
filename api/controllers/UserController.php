<?php
namespace App\Controllers;

use App\Models\User;
use App\Utils\Response;
use App\Utils\Validator;
use App\Utils\JWTHandler;

/**
 * User Controller
 *
 * Handles user management operations (list, view, update, delete)
 */
class UserController
{
    private User $userModel;
    private \PDO $pdo;

    public function __construct()
    {
        global $pdo;
        $this->pdo = $pdo;
        $this->userModel = new User($pdo);
    }

    /**
     * Get current authenticated user
     *
     * @return object|null Current user or null
     */
    private function getCurrentUser(): ?object
    {
        $currentUser = JWTHandler::getCurrentUser();

        if (!$currentUser) {
            Response::unauthorized('Authentication required');
        }

        // Check if token is blacklisted
        $token = JWTHandler::extractTokenFromHeader();
        if ($token && JWTHandler::isTokenBlacklisted($token, $this->pdo)) {
            Response::unauthorized('Token has been revoked. Please login again.');
        }

        return $currentUser;
    }

    /**
     * Check if current user has required role(s)
     *
     * @param string|array $roles Required role(s)
     * @return void
     */
    private function requireRole($roles): void
    {
        $currentUser = $this->getCurrentUser();

        if (is_string($roles)) {
            $roles = [$roles];
        }

        if (!in_array($currentUser->role, $roles, true)) {
            Response::forbidden('You do not have permission to perform this action');
        }
    }

    /**
     * Check if current user is accessing their own resource or is admin
     *
     * @param int $userId User ID being accessed
     * @return void
     */
    private function requireSelfOrAdmin(int $userId): void
    {
        $currentUser = $this->getCurrentUser();

        $isSelf = ($currentUser->id == $userId);
        $isAdmin = ($currentUser->role === 'admin');

        if (!$isSelf && !$isAdmin) {
            Response::forbidden('You do not have permission to access this resource');
        }
    }

    /**
     * List all users with pagination and filters
     *
     * GET /api/users?page=1&pageSize=20&role=student&search=john
     *
     * @param array $params Route parameters (not used)
     * @return void
     */
    public function index(array $params = []): void
    {
        // Only admin and instructor can list users
        $this->requireRole(['admin', 'instructor']);

        // Get query parameters
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $pageSize = isset($_GET['pageSize']) ? (int)$_GET['pageSize'] : 20;
        $role = isset($_GET['role']) ? $_GET['role'] : null;
        $search = isset($_GET['search']) ? $_GET['search'] : null;

        // Validate page and pageSize
        if ($page < 1) {
            $page = 1;
        }

        if ($pageSize < 1 || $pageSize > 100) {
            $pageSize = 20;
        }

        // Validate role if provided
        if ($role && !in_array($role, ['student', 'instructor', 'admin'])) {
            Response::error('Invalid role specified', 400);
        }

        $offset = ($page - 1) * $pageSize;

        // Get users based on filters
        if ($search) {
            // Search by name or email
            $users = $this->userModel->searchUsers($search, $role, $pageSize, $offset);
            $total = count($this->userModel->searchUsers($search, $role)); // Get total without limit
        } else if ($role) {
            // Filter by role
            $users = $this->userModel->getUsersByRole($role, $pageSize, $offset);
            $total = $this->userModel->countByRole($role);
        } else {
            // Get all users
            $users = $this->userModel->all([], 'created_at DESC', $pageSize, $offset);
            $total = $this->userModel->count();
        }

        // Return paginated response
        Response::paginated($users, $total, $page, $pageSize, 'Users retrieved successfully');
    }

    /**
     * Get user by ID
     *
     * GET /api/users/:id
     *
     * @param array $params Route parameters
     * @return void
     */
    public function show(array $params): void
    {
        // Get user ID from params
        if (!isset($params['id'])) {
            Response::error('User ID is required', 400);
        }

        $userId = (int)$params['id'];

        // Authorization: admin, instructor, or self
        $currentUser = $this->getCurrentUser();

        $isSelf = ($currentUser->id == $userId);
        $isAdminOrInstructor = in_array($currentUser->role, ['admin', 'instructor']);

        if (!$isSelf && !$isAdminOrInstructor) {
            Response::forbidden('You do not have permission to view this user');
        }

        // Fetch user
        $user = $this->userModel->find($userId);

        if (!$user) {
            Response::notFound('User not found');
        }

        // Fetch user statistics
        $stats = $this->userModel->getUserStats($userId);

        // Return response
        Response::success([
            'user' => $user,
            'statistics' => $stats
        ], 'User retrieved successfully');
    }

    /**
     * Update user profile
     *
     * PUT /api/users/:id
     *
     * @param array $params Route parameters
     * @return void
     */
    public function update(array $params): void
    {
        // Get user ID from params
        if (!isset($params['id'])) {
            Response::error('User ID is required', 400);
        }

        $userId = (int)$params['id'];

        // Authorization: admin or self
        $this->requireSelfOrAdmin($userId);

        // Get request data
        $data = $_POST;

        // Validate input
        $validator = Validator::make($data);

        if (isset($data['name'])) {
            $validator->maxLength('name', 255, 'Name must not exceed 255 characters');
        }

        if (isset($data['profile_picture_url'])) {
            $validator->url('profile_picture_url', 'Profile picture URL must be a valid URL');
        }

        // Check for validation errors
        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        // Check if user exists
        $user = $this->userModel->find($userId);

        if (!$user) {
            Response::notFound('User not found');
        }

        // Prepare update data (only allowed fields)
        $updateData = [];

        $allowedFields = ['name', 'profile_picture_url'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $field === 'profile_picture_url'
                    ? $data[$field]
                    : Validator::sanitize($data[$field]);
            }
        }

        // If nothing to update
        if (empty($updateData)) {
            Response::error('No valid fields provided for update', 400);
        }

        // Update user
        try {
            $updated = $this->userModel->update($userId, $updateData);

            if (!$updated) {
                Response::serverError('Failed to update user profile');
            }

            // Fetch updated user
            $updatedUser = $this->userModel->find($userId);

            // Return response
            Response::success([
                'user' => $updatedUser
            ], 'User profile updated successfully');

        } catch (\PDOException $e) {
            error_log('User update error: ' . $e->getMessage());
            Response::serverError('An error occurred while updating user profile');
        }
    }

    /**
     * Delete user (Admin only)
     *
     * DELETE /api/users/:id
     *
     * @param array $params Route parameters
     * @return void
     */
    public function delete(array $params): void
    {
        // Only admin can delete users
        $this->requireRole('admin');

        // Get user ID from params
        if (!isset($params['id'])) {
            Response::error('User ID is required', 400);
        }

        $userId = (int)$params['id'];

        // Get current user
        $currentUser = $this->getCurrentUser();

        // Cannot delete self
        if ($currentUser->id == $userId) {
            Response::error('You cannot delete your own account', 400);
        }

        // Check if user exists
        $user = $this->userModel->find($userId);

        if (!$user) {
            Response::notFound('User not found');
        }

        // Delete user
        try {
            $this->userModel->beginTransaction();

            $deleted = $this->userModel->delete($userId);

            if (!$deleted) {
                $this->userModel->rollback();
                Response::serverError('Failed to delete user');
            }

            $this->userModel->commit();

            // Return success
            Response::success([
                'deleted_user_id' => $userId,
                'deleted_user_email' => $user->email
            ], 'User deleted successfully');

        } catch (\PDOException $e) {
            $this->userModel->rollback();
            error_log('User deletion error: ' . $e->getMessage());
            Response::serverError('An error occurred while deleting user');
        }
    }
}
