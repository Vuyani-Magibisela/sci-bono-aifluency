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
        $includeEnrollmentSummary = isset($_GET['include_enrollment_summary'])
            && filter_var($_GET['include_enrollment_summary'], FILTER_VALIDATE_BOOLEAN);

        // Parse is_active: '1'/'true' → true, '0'/'false' → false, anything else → null (no filter)
        $isActive = null;
        if (isset($_GET['is_active']) && $_GET['is_active'] !== '') {
            $raw = strtolower((string)$_GET['is_active']);
            if (in_array($raw, ['1', 'true'], true)) {
                $isActive = true;
            } elseif (in_array($raw, ['0', 'false'], true)) {
                $isActive = false;
            }
        }

        // Enrollment-summary-only filters/sorts (consumed by the school roster branch below).
        // Whitelisted server-side so the frontend can't smuggle arbitrary SQL through ORDER BY / HAVING.
        $progressFilter = null;
        if (isset($_GET['progress_filter']) && in_array($_GET['progress_filter'], ['not-started', 'in-progress', 'completed'], true)) {
            $progressFilter = $_GET['progress_filter'];
        }
        $sortBy = null;
        if (isset($_GET['sort']) && in_array($_GET['sort'], ['name', 'progress', 'last_active'], true)) {
            $sortBy = $_GET['sort'];
        }
        $sortOrder = null;
        if (isset($_GET['order']) && in_array(strtolower((string)$_GET['order']), ['asc', 'desc'], true)) {
            $sortOrder = strtolower((string)$_GET['order']);
        }

        // Validate role if provided
        $validRoles = ['student', 'teacher', 'schooladmin', 'orgadmin', 'superadmin'];
        if ($role && !in_array($role, $validRoles)) {
            Response::error('Invalid role specified', 400);
        }

        // Expand 'teacher' to match legacy 'instructor' role values in the DB.
        // Historical signups used role='instructor'; current signups use 'teacher'. Both represent the same persona.
        $roleFilter = ($role === 'teacher') ? ['teacher', 'instructor'] : $role;

        $offset = ($page - 1) * $pageSize;

        // Enrollment summary branch: used by the instructor students page so Progress
        // and Course columns are meaningful at the school-roster level (no per-course filter).
        // Requires: include_enrollment_summary=true + school_id + role=student.
        if (
            $includeEnrollmentSummary
            && $schoolId
            && $role === 'student'
            && in_array($currentUser->role, ['teacher', 'instructor', 'schooladmin', 'orgadmin', 'superadmin'], true)
        ) {
            // Teachers are locked to their own school.
            $scopedSchoolId = $schoolId;
            if (in_array($currentUser->role, ['teacher', 'instructor'], true)) {
                $scopedSchoolId = isset($currentUser->primary_school_id)
                    ? (int)$currentUser->primary_school_id
                    : 0;
            } elseif ($currentUser->role === 'schooladmin') {
                $scopedSchoolId = isset($currentUser->primary_school_id)
                    ? (int)$currentUser->primary_school_id
                    : 0;
            }

            if ($scopedSchoolId > 0) {
                $enrollmentModel = new \App\Models\Enrollment($this->pdo);
                $summaries = $enrollmentModel->getSchoolStudentSummaries(
                    $scopedSchoolId,
                    $pageSize,
                    $offset,
                    $search,
                    $isActive,
                    $progressFilter,
                    $sortBy,
                    $sortOrder
                );
                // Count must reflect the progress filter (HAVING on aggregates), so use the
                // matching count method on Enrollment rather than the user-only counter.
                $total = $enrollmentModel->countSchoolStudentSummaries(
                    $scopedSchoolId,
                    $search,
                    $isActive,
                    $progressFilter
                );

                $data = array_map(function ($row) {
                    return [
                        'id' => (int)$row->id,
                        'name' => $row->name,
                        'email' => $row->email,
                        'is_active' => (bool)$row->is_active,
                        'created_at' => $row->created_at,
                        'enrollment_count' => (int)$row->enrollment_count,
                        'completed_count' => (int)$row->completed_count,
                        'avg_progress' => round((float)$row->avg_progress, 2),
                        'last_active_at' => $row->last_active_at,
                        'latest_enrolled_at' => $row->latest_enrolled_at,
                        'single_course_title' => $row->single_course_title,
                    ];
                }, $summaries);

                Response::success([
                    'data' => $data,
                    'total' => $total,
                    'page' => $page,
                    'pageSize' => $pageSize,
                    'totalPages' => $pageSize > 0 ? (int)ceil($total / $pageSize) : 0
                ], 'Users retrieved successfully');
                return;
            }
        }

        // Apply organizational scoping based on current user's role
        if ($currentUser->role === 'superadmin') {
            // SuperAdmins see all users system-wide with optional filters
            $users = $this->userModel->getFilteredUsers($roleFilter, $search, $organizationId, $schoolId, $pageSize, $offset, $isActive);
            $total = $this->userModel->countFilteredUsers($roleFilter, $search, $organizationId, $schoolId, $isActive);
        } elseif ($currentUser->role === 'orgadmin') {
            // OrgAdmins see users in their organization(s)
            $managedOrgIds = $this->getManagedOrganizationIds();
            if ($managedOrgIds === null || empty($managedOrgIds)) {
                $users = [];
                $total = 0;
            } else {
                // Use requested org only if it's one they manage, otherwise use their primary org
                $targetOrgId = ($organizationId && in_array($organizationId, $managedOrgIds))
                    ? $organizationId
                    : $managedOrgIds[0];
                $users = $this->userModel->getFilteredUsers($roleFilter, $search, $targetOrgId, $schoolId, $pageSize, $offset, $isActive);
                $total = $this->userModel->countFilteredUsers($roleFilter, $search, $targetOrgId, $schoolId, $isActive);
            }
        } elseif ($currentUser->role === 'schooladmin') {
            // SchoolAdmins see users in their school only
            if (!$currentUser->primary_school_id) {
                $users = [];
                $total = 0;
            } else {
                $users = $this->userModel->getFilteredUsers($roleFilter, $search, null, (int)$currentUser->primary_school_id, $pageSize, $offset, $isActive);
                $total = $this->userModel->countFilteredUsers($roleFilter, $search, null, (int)$currentUser->primary_school_id, $isActive);
            }
        } elseif (in_array($currentUser->role, ['teacher', 'instructor'], true)) {
            // Teachers see users in their school only (view-only).
            // Legacy 'instructor' role is the same persona as 'teacher' — both scoped identically.
            if (!$currentUser->primary_school_id) {
                $users = [];
                $total = 0;
            } else {
                $users = $this->userModel->getFilteredUsers($roleFilter, $search, null, (int)$currentUser->primary_school_id, $pageSize, $offset, $isActive);
                $total = $this->userModel->countFilteredUsers($roleFilter, $search, null, (int)$currentUser->primary_school_id, $isActive);
            }
        } else {
            // Students cannot list users
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

        // Authorization: admin, instructor (school-scoped), or self
        $currentUser = $this->getCurrentUser();

        $isSelf = ($currentUser->id == $userId);
        $isAdmin = in_array($currentUser->role, ['superadmin', 'orgadmin', 'schooladmin'], true);
        $isTeacher = in_array($currentUser->role, ['teacher', 'instructor'], true);

        // Fetch user (needed for school-scope check on teachers)
        $user = $this->userModel->find($userId);

        if (!$user) {
            Response::notFound('User not found');
        }

        // Teachers/instructors may view any user from their own school.
        $sameSchool = $isTeacher
            && !empty($currentUser->primary_school_id)
            && (int)$user->primary_school_id === (int)$currentUser->primary_school_id;

        if (!$isSelf && !$isAdmin && !$sameSchool) {
            Response::forbidden('You do not have permission to view this user');
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

        // Only superadmin can edit other users; any user can edit their own profile
        if (!$isSelf && $currentUser->role !== 'superadmin') {
            Response::forbidden('Only superadmin can edit other users');
        }

        $isAdminUpdate = ($currentUser->role === 'superadmin');

        // Get request data
        $data = $_POST;

        // Determine allowed fields based on context
        if ($isSelf && !$isAdminUpdate) {
            // Regular users can only update their own profile fields
            $allowedFields = ['name', 'profile_picture_url', 'bio', 'headline', 'location',
                            'website_url', 'github_url', 'linkedin_url', 'twitter_url'];
        } elseif ($isAdminUpdate) {
            // SuperAdmin managing other users
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
     * Change own password
     *
     * PUT /api/users/me/password
     *
     * @param array $params Route parameters
     * @return void
     */
    public function changePassword(array $params): void
    {
        $currentUser = $this->getCurrentUser();

        $data = $_POST;

        // Validate required fields
        if (empty($data['current_password'])) {
            Response::error('Current password is required', 400);
        }
        if (empty($data['new_password'])) {
            Response::error('New password is required', 400);
        }
        if (empty($data['confirm_password'])) {
            Response::error('Password confirmation is required', 400);
        }
        if ($data['new_password'] !== $data['confirm_password']) {
            Response::error('New passwords do not match', 400);
        }
        if (strlen($data['new_password']) < 8) {
            Response::error('New password must be at least 8 characters', 400);
        }

        // Verify current password
        $verified = $this->userModel->verifyPassword($currentUser->email, $data['current_password']);
        if (!$verified) {
            Response::error('Current password is incorrect', 401);
        }

        // Update password
        $updated = $this->userModel->updatePassword($currentUser->id, $data['new_password']);
        if (!$updated) {
            Response::serverError('Failed to update password');
        }

        Response::success(null, 'Password changed successfully');
    }

    /**
     * Admin / instructor password reset for another user
     *
     * PUT /api/users/:id/admin-reset-password
     *
     * Authorization:
     *   - superadmin           → any user
     *   - orgadmin             → users in a managed organization
     *   - schooladmin          → users in their primary school
     *   - teacher / instructor → only students in their primary school
     */
    public function adminResetPassword(array $params): void
    {
        if (!isset($params['id'])) {
            Response::error('User ID is required', 400);
        }

        $targetId = (int)$params['id'];
        $currentUser = $this->getCurrentUser();

        if ($currentUser->id == $targetId) {
            Response::error('Use the change-password endpoint to update your own password', 400);
        }

        $target = $this->userModel->find($targetId);
        if (!$target) {
            Response::notFound('User not found');
        }

        // Authorization
        $role = $currentUser->role;
        $allowed = false;
        if ($role === 'superadmin') {
            $allowed = true;
        } elseif ($role === 'orgadmin') {
            $managedOrgIds = $this->getManagedOrganizationIds();
            $allowed = $managedOrgIds !== null
                && in_array((int)$target->primary_organization_id, $managedOrgIds, true);
        } elseif ($role === 'schooladmin') {
            $allowed = !empty($currentUser->primary_school_id)
                && (int)$target->primary_school_id === (int)$currentUser->primary_school_id;
        } elseif (in_array($role, ['teacher', 'instructor'], true)) {
            $allowed = $target->role === 'student'
                && !empty($currentUser->primary_school_id)
                && (int)$target->primary_school_id === (int)$currentUser->primary_school_id;
        }

        if (!$allowed) {
            Response::forbidden('You cannot reset this user\'s password');
        }

        // Validate input
        $data = $_POST;
        if (empty($data['new_password'])) {
            Response::error('New password is required', 400);
        }
        if (empty($data['confirm_password'])) {
            Response::error('Password confirmation is required', 400);
        }
        if ($data['new_password'] !== $data['confirm_password']) {
            Response::error('New passwords do not match', 400);
        }
        if (strlen($data['new_password']) < 8) {
            Response::error('New password must be at least 8 characters', 400);
        }

        $updated = $this->userModel->updatePassword($targetId, $data['new_password']);
        if (!$updated) {
            Response::serverError('Failed to update password');
        }

        error_log("Password reset by user {$currentUser->id} ({$role}) for user {$targetId}");

        Response::success(null, 'Password reset successfully');
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
        // Only superadmin can delete users
        $this->requireRole(['superadmin']);

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
     * Toggle is_active for a student. Used by the instructor students modal
     * for soft account control without touching superadmin-only DELETE.
     *
     * PUT /api/users/:id/deactivate   body: { is_active: bool }
     */
    public function setActive(array $params): void
    {
        if (!isset($params['id'])) {
            Response::error('User ID is required', 400);
        }
        $userId = (int)$params['id'];

        $currentUser = $this->getCurrentUser();
        $this->requireSchoolScope($currentUser, $userId);

        $body = $this->readJsonBody();
        if (!array_key_exists('is_active', $body)) {
            Response::error('is_active is required', 400);
        }
        $isActive = filter_var($body['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($isActive === null) {
            Response::error('is_active must be a boolean', 400);
        }

        if (!$this->userModel->setActiveStatus($userId, $isActive)) {
            Response::serverError('Failed to update account status');
        }

        Response::success(['id' => $userId, 'is_active' => $isActive], 'Account status updated');
    }

    /**
     * Soft-delete a student account as a confirmed duplicate.
     * Sets is_active = 0; superadmin retains the hard-delete path.
     *
     * POST /api/users/:id/mark-duplicate   body: { kept_user_id: int, reason?: string }
     */
    public function markDuplicate(array $params): void
    {
        if (!isset($params['id'])) {
            Response::error('User ID is required', 400);
        }
        $userId = (int)$params['id'];

        $currentUser = $this->getCurrentUser();
        $this->requireSchoolScope($currentUser, $userId);

        $body = $this->readJsonBody();
        $keptUserId = isset($body['kept_user_id']) ? (int)$body['kept_user_id'] : 0;
        if ($keptUserId <= 0 || $keptUserId === $userId) {
            Response::error('A valid kept_user_id (different from this user) is required', 400);
        }

        $kept = $this->userModel->find($keptUserId);
        if (!$kept) {
            Response::notFound('Kept user not found');
        }

        $target = $this->userModel->find($userId);
        if (!$target) {
            Response::notFound('User not found');
        }

        if (!$this->userModel->setActiveStatus($userId, false)) {
            Response::serverError('Failed to mark account as duplicate');
        }

        $reason = isset($body['reason']) ? substr(trim((string)$body['reason']), 0, 500) : '';
        error_log(sprintf(
            'duplicate-account: user_id=%d marked as duplicate of user_id=%d by user_id=%d (role=%s). Reason: %s',
            $userId,
            $keptUserId,
            (int)$currentUser->id,
            (string)$currentUser->role,
            $reason !== '' ? $reason : '(none)'
        ));

        Response::success([
            'id' => $userId,
            'is_active' => false,
            'kept_user_id' => $keptUserId,
        ], 'Account marked as duplicate');
    }

    /**
     * List student accounts in the instructor's school that share a normalised
     * email with at least one other account. Used by the "Duplicates" view on
     * the instructor students page.
     *
     * GET /api/instructor/students/duplicates
     */
    public function listSchoolDuplicates(array $params = []): void
    {
        $currentUser = $this->getCurrentUser();
        $role = $currentUser->role;

        if (in_array($role, ['teacher', 'instructor', 'schooladmin'], true)) {
            $schoolId = isset($currentUser->primary_school_id)
                ? (int)$currentUser->primary_school_id
                : 0;
        } elseif (in_array($role, ['orgadmin', 'superadmin'], true)) {
            $schoolId = isset($_GET['school_id']) ? (int)$_GET['school_id'] : 0;
        } else {
            Response::forbidden('Insufficient permissions');
        }

        if ($schoolId <= 0) {
            Response::success(['data' => [], 'total' => 0], 'No duplicates');
            return;
        }

        $sql = "
            SELECT u.id, u.name, u.email, u.is_active, u.created_at, u.last_login_at,
                   LOWER(TRIM(u.email)) AS norm_email,
                   dup.cnt AS duplicate_count
            FROM users u
            JOIN (
                SELECT LOWER(TRIM(email)) AS norm_email, COUNT(*) AS cnt
                FROM users
                WHERE primary_school_id = :school_id_inner AND role = 'student'
                GROUP BY LOWER(TRIM(email))
                HAVING cnt > 1
            ) dup ON LOWER(TRIM(u.email)) = dup.norm_email
            WHERE u.primary_school_id = :school_id AND u.role = 'student'
            ORDER BY dup.norm_email, u.created_at
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':school_id', $schoolId, \PDO::PARAM_INT);
            $stmt->bindValue(':school_id_inner', $schoolId, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log('listSchoolDuplicates error: ' . $e->getMessage());
            Response::serverError('Failed to load duplicate accounts');
        }

        Response::success(['data' => $rows, 'total' => count($rows)], 'Duplicates retrieved');
    }

    /**
     * Ensure $currentUser can act on $targetUserId.
     * Teachers/schooladmins: same school only. Org/SuperAdmins: always.
     */
    private function requireSchoolScope(object $currentUser, int $targetUserId): void
    {
        $role = $currentUser->role;
        if (in_array($role, ['orgadmin', 'superadmin'], true)) {
            return;
        }
        if (!in_array($role, ['teacher', 'instructor', 'schooladmin'], true)) {
            Response::forbidden('Insufficient permissions');
        }
        if (empty($currentUser->primary_school_id)) {
            Response::forbidden('Not assigned to a school');
        }
        $target = $this->userModel->find($targetUserId);
        if (!$target) {
            Response::notFound('User not found');
        }
        if ((int)$target->primary_school_id !== (int)$currentUser->primary_school_id) {
            Response::forbidden('That student is not in your school');
        }
        if ($target->role !== 'student') {
            Response::forbidden('Only student accounts can be managed here');
        }
    }

    /**
     * Read JSON body, falling back to $_POST for form submissions.
     */
    private function readJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return $_POST ?: [];
    }

    /**
     * Aggregate student stats for an instructor's school.
     *
     * GET /api/instructor/students/stats[?school_id=N]
     *
     * Teachers/instructors are auto-scoped to their primary_school_id (school_id param ignored).
     * SchoolAdmins are auto-scoped likewise. OrgAdmins / SuperAdmins may pass school_id.
     */
    public function getSchoolStudentStats(array $params = []): void
    {
        $currentUser = $this->getCurrentUser();
        $role = $currentUser->role;
        $requestedSchoolId = isset($_GET['school_id']) ? (int)$_GET['school_id'] : 0;

        // Resolve which school to report on
        if (in_array($role, ['teacher', 'instructor', 'schooladmin'], true)) {
            $schoolId = isset($currentUser->primary_school_id)
                ? (int)$currentUser->primary_school_id
                : 0;
        } elseif ($role === 'orgadmin') {
            // OrgAdmins must specify a school within their managed organizations
            $schoolId = $requestedSchoolId;
        } elseif ($role === 'superadmin') {
            $schoolId = $requestedSchoolId;
        } else {
            Response::forbidden('Insufficient permissions');
        }

        if ($schoolId <= 0) {
            Response::success([
                'total_students' => 0,
                'active_count' => 0,
                'avg_progress' => 0,
            ], 'No school assigned');
            return;
        }

        $total = $this->userModel->countFilteredUsers('student', null, null, $schoolId, null);
        $enrollmentModel = new \App\Models\Enrollment($this->pdo);
        $aggregates = $enrollmentModel->getSchoolAggregateStats($schoolId);

        Response::success([
            'total_students' => (int)$total,
            'active_count' => (int)$aggregates['active_count'],
            'avg_progress' => (float)$aggregates['avg_progress'],
        ], 'School student stats retrieved successfully');
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
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM lesson_progress WHERE user_id = ? AND status = 'completed'");
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
