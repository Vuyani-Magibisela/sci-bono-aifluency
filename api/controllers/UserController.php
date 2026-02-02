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
class UserController extends BaseController
{
    private User $userModel;

    public function __construct(\PDO $pdo)
    {
        parent::__construct($pdo);
        $this->userModel = new User($pdo);
    }

    /**
     * Check if current user is accessing their own resource or is admin
     *
     * @param int $userId User ID being accessed
     * @return void
     */
    private function requireSelfOrAdmin(int $userId): void
    {
        // Use BaseController's requireOwnershipOrRole with admin-only allowance
        $this->requireOwnershipOrRole($userId, ['admin']);
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

    // ========================================
    // Phase 8: Profile Enhancement Methods
    // ========================================

    /**
     * Update user profile fields
     *
     * PUT /api/users/:id/profile
     *
     * @param array $params Route parameters
     * @return void
     */
    public function updateProfile(array $params): void
    {
        if (!isset($params['id'])) {
            Response::error('User ID is required', 400);
        }

        $userId = (int)$params['id'];

        // Authorization: self only (not even admin can edit others' bios)
        $this->requireSelfOrAdmin($userId);

        // Get request data
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        // Validate input
        $validator = new Validator();

        if (isset($data['bio'])) {
            if (strlen($data['bio']) > 5000) {
                Response::error('Bio must not exceed 5000 characters', 400);
            }
        }

        if (isset($data['headline'])) {
            if (strlen($data['headline']) > 255) {
                Response::error('Headline must not exceed 255 characters', 400);
            }
        }

        if (isset($data['location'])) {
            if (strlen($data['location']) > 255) {
                Response::error('Location must not exceed 255 characters', 400);
            }
        }

        // Validate URLs
        $urlFields = ['website_url', 'github_url', 'linkedin_url', 'twitter_url'];
        foreach ($urlFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                if (!filter_var($data[$field], FILTER_VALIDATE_URL)) {
                    $fieldName = ucfirst(str_replace('_url', '', $field));
                    Response::error("{$fieldName} must be a valid URL", 400);
                }
            }
        }

        // Update profile
        try {
            $updated = $this->userModel->updateProfileFields($userId, $data);

            if (!$updated) {
                Response::error('No valid fields provided for update', 400);
            }

            $updatedUser = $this->userModel->find($userId);

            Response::success([
                'user' => $updatedUser
            ], 'Profile updated successfully');

        } catch (\PDOException $e) {
            error_log('Profile update error: ' . $e->getMessage());
            Response::serverError('An error occurred while updating profile');
        }
    }

    /**
     * Get public profile (privacy-aware)
     *
     * GET /api/users/:id/profile/public
     *
     * @param array $params Route parameters
     * @return void
     */
    public function getPublicProfile(array $params): void
    {
        if (!isset($params['id'])) {
            Response::error('User ID is required', 400);
        }

        $userId = (int)$params['id'];

        // Get public profile data
        $profile = $this->userModel->getPublicProfileData($userId);

        if (!$profile) {
            Response::error('Profile not found or private', 404);
        }

        // Track view if authenticated
        $currentUser = $this->getCurrentUser();
        if ($currentUser && $currentUser->id !== $userId) {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

            $this->userModel->trackProfileView(
                $userId,
                $currentUser->id,
                $ipAddress,
                $userAgent
            );
        }

        Response::success([
            'profile' => $profile
        ], 'Profile retrieved successfully');
    }

    /**
     * Update privacy settings
     *
     * PUT /api/users/:id/profile/privacy
     *
     * @param array $params Route parameters
     * @return void
     */
    public function updatePrivacySettings(array $params): void
    {
        if (!isset($params['id'])) {
            Response::error('User ID is required', 400);
        }

        $userId = (int)$params['id'];

        // Authorization: self only
        $this->requireSelfOrAdmin($userId);

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        // Validate boolean values
        $booleanFields = ['is_public_profile', 'show_email', 'show_achievements', 'show_certificates'];
        foreach ($booleanFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = filter_var($data[$field], FILTER_VALIDATE_BOOLEAN);
            }
        }

        try {
            $updated = $this->userModel->updatePrivacySettings($userId, $data);

            if (!$updated) {
                Response::error('No valid privacy settings provided', 400);
            }

            $updatedUser = $this->userModel->find($userId);

            Response::success([
                'user' => $updatedUser
            ], 'Privacy settings updated successfully');

        } catch (\PDOException $e) {
            error_log('Privacy settings update error: ' . $e->getMessage());
            Response::serverError('An error occurred while updating privacy settings');
        }
    }

    /**
     * Get profile completion percentage
     *
     * GET /api/users/:id/profile/completion
     *
     * @param array $params Route parameters
     * @return void
     */
    public function getProfileCompletion(array $params): void
    {
        if (!isset($params['id'])) {
            Response::error('User ID is required', 400);
        }

        $userId = (int)$params['id'];

        // Authorization: self or admin
        $this->requireSelfOrAdmin($userId);

        $completion = $this->userModel->getProfileCompletionPercentage($userId);

        Response::success([
            'completion_percentage' => $completion
        ], 'Profile completion retrieved successfully');
    }

    /**
     * Search profiles directory
     *
     * GET /api/users/profiles/search?q=term&page=1&pageSize=20
     *
     * @param array $params Route parameters
     * @return void
     */
    public function searchProfiles(array $params = []): void
    {
        // Public endpoint - no auth required for public profiles

        $searchTerm = $_GET['q'] ?? '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $pageSize = isset($_GET['pageSize']) ? (int)$_GET['pageSize'] : 20;

        // Validate pagination
        if ($page < 1) $page = 1;
        if ($pageSize < 1 || $pageSize > 100) $pageSize = 20;

        $offset = ($page - 1) * $pageSize;

        if (empty($searchTerm)) {
            // Return recently active users if no search term
            $users = $this->userModel->getRecentlyActiveUsers(30, $pageSize);
            $total = count($this->userModel->getRecentlyActiveUsers(30));
        } else {
            $users = $this->userModel->searchPublicProfiles($searchTerm, true, $pageSize, $offset);
            // Get total count for pagination
            $total = count($this->userModel->searchPublicProfiles($searchTerm, true));
        }

        $totalPages = ceil($total / $pageSize);

        Response::success([
            'data' => $users,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => $total,
                'totalPages' => $totalPages
            ]
        ], 'Profiles retrieved successfully');
    }
}
