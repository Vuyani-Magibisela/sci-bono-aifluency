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
        // Use BaseController's requireOwnershipOrRole with hierarchical admin roles
        $this->requireOwnershipOrRole($userId, ['superadmin', 'orgadmin', 'schooladmin']);
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
        $currentUser = $this->getCurrentUser();

        // Get query parameters
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $pageSize = isset($_GET['pageSize']) ? min(100, max(1, (int)$_GET['pageSize'])) : 20;
        $role = isset($_GET['role']) ? $_GET['role'] : null;
        $search = isset($_GET['search']) ? $_GET['search'] : null;
        $organizationId = isset($_GET['organization_id']) ? (int)$_GET['organization_id'] : null;
        $schoolId = isset($_GET['school_id']) ? (int)$_GET['school_id'] : null;

        // Validate role if provided
        $validRoles = ['student', 'teacher', 'schooladmin', 'orgadmin', 'superadmin'];
        if ($role && !in_array($role, $validRoles)) {
            Response::error('Invalid role specified', 400);
        }

        $offset = ($page - 1) * $pageSize;

        // Apply organizational scoping based on current user's role
        if ($currentUser->role === 'superadmin') {
            // SuperAdmins see all users system-wide
            if ($search) {
                $users = $this->userModel->searchUsers($search, $role, $pageSize, $offset);
                $total = count($this->userModel->searchUsers($search, $role));
            } else if ($role) {
                $users = $this->userModel->getUsersByRole($role, $pageSize, $offset);
                $total = $this->userModel->countByRole($role);
            } else {
                $users = $this->userModel->all([], 'created_at DESC', $pageSize, $offset);
                $total = $this->userModel->count();
            }
        } elseif ($currentUser->role === 'orgadmin') {
            // OrgAdmins see users in their organization(s)
            $managedOrgIds = $this->getManagedOrganizationIds();
            if ($managedOrgIds === null || empty($managedOrgIds)) {
                $users = [];
                $total = 0;
            } else {
                // Use first managed org (or filter if specified)
                $targetOrgId = $organizationId ?? $managedOrgIds[0];
                if (!in_array($targetOrgId, $managedOrgIds)) {
                    Response::forbidden('You cannot access users in this organization');
                }
                $users = $this->userModel->getUsersByOrganization($targetOrgId, $role, $pageSize, $offset);
                $total = count($this->userModel->getUsersByOrganization($targetOrgId, $role, null, null));
            }
        } elseif ($currentUser->role === 'schooladmin') {
            // SchoolAdmins see users in their school only
            if (!$currentUser->primary_school_id) {
                $users = [];
                $total = 0;
            } else {
                $users = $this->userModel->getUsersBySchool($currentUser->primary_school_id, $role, $pageSize, $offset);
                $total = count($this->userModel->getUsersBySchool($currentUser->primary_school_id, $role, null, null));
            }
        } else {
            // Teachers and students cannot list users
            Response::forbidden('Insufficient permissions to list users');
        }

        // Convert objects to arrays and remove sensitive data
        $usersArray = [];
        foreach ($users as $user) {
            // Convert object to array
            $userArray = is_object($user) ? json_decode(json_encode($user), true) : $user;

            // Remove sensitive fields
            unset($userArray['password_hash']);
            unset($userArray['reset_token']);
            unset($userArray['verification_token']);

            $usersArray[] = $userArray;
        }

        // Return response in format expected by frontend
        Response::success([
            'data' => $usersArray,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'totalPages' => ceil($total / $pageSize)
        ], 'Users retrieved successfully');
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
     * Create new user (Phase 12 - Hierarchical RBAC)
     *
     * POST /api/users
     *
     * @param array $params Route parameters
     * @return void
     */
    public function create(array $params): void
    {
        $currentUser = $this->getCurrentUser();

        // Validate required fields
        $this->validateRequiredParams($_POST, ['name', 'email', 'password', 'role', 'primary_organization_id']);

        // Validate email format
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            Response::validationError(['email' => 'Invalid email format']);
        }

        // Check if email already exists
        $existingUser = $this->userModel->findByEmail($_POST['email']);
        if ($existingUser) {
            Response::validationError(['email' => 'Email already registered']);
        }

        // Check if user can assign this role
        if (!$this->canAssignRole($_POST['role'])) {
            Response::forbidden('You cannot assign this role');
        }

        // Validate organizational scoping
        $organizationId = (int)$_POST['primary_organization_id'];
        $managedOrgIds = $this->getManagedOrganizationIds();

        if ($managedOrgIds !== null && !in_array($organizationId, $managedOrgIds)) {
            Response::forbidden('You cannot create users in this organization');
        }

        // Validate school if provided
        if (isset($_POST['primary_school_id']) && $_POST['primary_school_id']) {
            $schoolId = (int)$_POST['primary_school_id'];
            $managedSchoolIds = $this->getManagedSchoolIds();

            if ($managedSchoolIds !== null && !in_array($schoolId, $managedSchoolIds)) {
                Response::forbidden('You cannot assign users to this school');
            }
        }

        // Hash password
        $passwordHash = password_hash($_POST['password'], PASSWORD_BCRYPT);

        // Prepare user data
        $userData = [
            'name' => $_POST['name'],
            'email' => $_POST['email'],
            'password_hash' => $passwordHash,
            'role' => $_POST['role'],
            'primary_organization_id' => $organizationId,
            'primary_school_id' => isset($_POST['primary_school_id']) ? (int)$_POST['primary_school_id'] : null,
            'organizational_title' => $_POST['organizational_title'] ?? null,
            'is_active' => isset($_POST['is_active']) ? (bool)$_POST['is_active'] : true,
            'is_verified' => true // Admin-created users are pre-verified
        ];

        // Create user
        $userId = $this->userModel->create($userData);

        // Get created user
        $user = $this->userModel->find($userId);

        // Convert to array and remove sensitive data
        $userArray = json_decode(json_encode($user), true);
        unset($userArray['password_hash']);

        Response::success($userArray, 'User created successfully', 201);
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
        $currentUser = $this->getCurrentUser();

        // Get user ID from params
        if (!isset($params['id'])) {
            Response::error('User ID is required', 400);
        }

        $userId = (int)$params['id'];

        // Check if user exists
        $targetUser = $this->userModel->find($userId);
        if (!$targetUser) {
            Response::notFound('User not found');
        }

        $isSelf = ($currentUser->id == $userId);
        $isAdminUpdate = in_array($currentUser->role, ['superadmin', 'orgadmin', 'schooladmin']);

        // Get request data
        $data = $_POST;

        // Determine allowed fields based on context
        if ($isSelf && !$isAdminUpdate) {
            // Regular users can only update their own profile fields
            $allowedFields = ['name', 'profile_picture_url', 'bio', 'headline', 'location',
                            'website_url', 'github_url', 'linkedin_url', 'twitter_url'];
        } elseif ($isAdminUpdate) {
            // Admin users managing other users - check hierarchical permissions
            if (!$isSelf) {
                // Check role hierarchy - can't manage equal or higher roles
                $roleHierarchy = [
                    'superadmin' => 5,
                    'orgadmin' => 4,
                    'schooladmin' => 3,
                    'teacher' => 2,
                    'student' => 1
                ];

                $currentLevel = $roleHierarchy[$currentUser->role] ?? 0;
                $targetLevel = $roleHierarchy[$targetUser->role] ?? 0;

                if ($targetLevel >= $currentLevel) {
                    Response::forbidden('You cannot modify users with equal or higher roles');
                }

                // Check organizational scope
                if ($currentUser->role === 'orgadmin') {
                    $managedOrgIds = $this->getManagedOrganizationIds();
                    if ($managedOrgIds !== null && !in_array($targetUser->primary_organization_id, $managedOrgIds)) {
                        Response::forbidden('You can only manage users in your organization');
                    }
                } elseif ($currentUser->role === 'schooladmin') {
                    if ($targetUser->primary_school_id != $currentUser->primary_school_id) {
                        Response::forbidden('You can only manage users in your school');
                    }
                }
            }

            // Admins can update all user management fields
            $allowedFields = ['name', 'email', 'role', 'primary_organization_id', 'primary_school_id',
                            'organizational_title', 'is_active', 'profile_picture_url'];

            // Validate role assignment if provided
            if (isset($data['role']) && !$this->canAssignRole($data['role'])) {
                Response::forbidden('You cannot assign this role');
            }
        } else {
            Response::forbidden('Insufficient permissions');
        }

        // Prepare update data
        $updateData = [];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        // Hash password if provided
        if (isset($data['password']) && !empty($data['password'])) {
            if ($isSelf || $isAdminUpdate) {
                $updateData['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT);
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
                Response::serverError('Failed to update user');
            }

            // Fetch updated user
            $updatedUser = $this->userModel->find($userId);

            // Remove sensitive data by converting to array
            $updatedUserArray = json_decode(json_encode($updatedUser), true);
            unset($updatedUserArray['password_hash']);
            unset($updatedUserArray['reset_token']);
            unset($updatedUserArray['verification_token']);

            Response::success($updatedUserArray, 'User updated successfully');

        } catch (\PDOException $e) {
            error_log('User update error: ' . $e->getMessage());
            Response::serverError('An error occurred while updating user');
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
        $this->requireRole(['superadmin', 'orgadmin', 'schooladmin']);

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

    /**
     * Get statistics for current user
     *
     * GET /api/users/me/stats
     *
     * @param array $params Route parameters
     * @return void
     */
    public function getMyStats(array $params = []): void
    {
        $currentUser = $this->getCurrentUser();

        // Total courses enrolled
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM enrollments WHERE user_id = ? AND status = 'active'");
        $stmt->execute([$currentUser->id]);
        $totalCourses = (int)$stmt->fetch(\PDO::FETCH_OBJ)->count;

        // Completed lessons
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM lesson_completions WHERE user_id = ?");
        $stmt->execute([$currentUser->id]);
        $completedLessons = (int)$stmt->fetch(\PDO::FETCH_OBJ)->count;

        // Total lessons available (from enrolled courses)
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT l.id) as count
            FROM lessons l
            JOIN modules m ON l.module_id = m.id
            JOIN enrollments e ON m.course_id = e.course_id
            WHERE e.user_id = ? AND e.status = 'active'
        ");
        $stmt->execute([$currentUser->id]);
        $totalLessons = (int)$stmt->fetch(\PDO::FETCH_OBJ)->count;

        // Quiz average
        $stmt = $this->pdo->prepare("SELECT AVG(score) as average FROM quiz_attempts WHERE user_id = ?");
        $stmt->execute([$currentUser->id]);
        $result = $stmt->fetch(\PDO::FETCH_OBJ);
        $quizAverage = $result->average ? round((float)$result->average, 2) : 0;

        // Certificates earned
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM certificates WHERE user_id = ?");
        $stmt->execute([$currentUser->id]);
        $certificatesEarned = (int)$stmt->fetch(\PDO::FETCH_OBJ)->count;

        Response::success([
            'total_courses' => $totalCourses,
            'completed_lessons' => $completedLessons,
            'total_lessons' => $totalLessons,
            'quiz_average' => $quizAverage,
            'certificates_earned' => $certificatesEarned
        ], 'User statistics retrieved successfully');
    }
}
