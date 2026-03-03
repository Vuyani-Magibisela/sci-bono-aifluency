<?php
namespace App\Controllers;

use App\Models\User;
use App\Utils\Response;
use App\Utils\Validator;
use App\Utils\JWTHandler;
use App\Utils\Mailer;

/**
 * Authentication Controller
 *
 * Handles user registration, login, token refresh, logout, and profile retrieval
 */
class AuthController extends BaseController
{
    private User $userModel;

    public function __construct(\PDO $pdo)
    {
        parent::__construct($pdo);
        $this->userModel = new User($pdo);
    }

    /**
     * Register a new user
     *
     * POST /api/auth/register
     *
     * @param array $params Route parameters (not used)
     * @return void
     */
    public function register(array $params = []): void
    {
        // Get request data
        $data = $_POST;

        // Validate input
        $validator = Validator::make($data);

        $validator->required('name', 'Name is required')
                  ->maxLength('name', 255, 'Name must not exceed 255 characters');

        $validator->required('email', 'Email is required')
                  ->email('email', 'Please provide a valid email address');

        $validator->required('password', 'Password is required')
                  ->strongPassword('password');

        $validator->required('password_confirmation', 'Password confirmation is required')
                  ->matches('password_confirmation', 'password', 'Passwords do not match');

        // Role is optional, default to 'student' (no self-registration as orgadmin/superadmin)
        $role = isset($data['role']) ? $data['role'] : 'student';
        if (isset($data['role'])) {
            $validator->in('role', ['student', 'teacher', 'schooladmin'], 'Invalid role specified');
        }

        // Education context — determines signup flow
        $educationContext = isset($data['education_context']) ? $data['education_context'] : 'public_school';
        $allowedContexts = ['public_school', 'private_school', 'homeschool', 'independent_learner', 'afterschool_program'];
        if (!in_array($educationContext, $allowedContexts, true)) {
            Response::validationError(['education_context' => 'Invalid education context']);
        }

        // Context-specific field validation
        if ($educationContext === 'public_school') {
            // Existing public-school flow
            if ($role === 'student') {
                $validator->required('gender', 'Gender is required for students');
                $validator->required('grade', 'Grade is required for students');
                $validator->required('date_of_birth', 'Date of birth is required for students');
            }
            $validator->required('school_id', 'School is required');
        } elseif ($educationContext === 'private_school') {
            $validator->required('school_name', 'School name is required for private school registration');
            if ($role === 'student') {
                $validator->required('gender', 'Gender is required for students');
                $validator->required('grade', 'Grade is required for students');
                $validator->required('date_of_birth', 'Date of birth is required for students');
            }
        } elseif ($educationContext === 'homeschool') {
            $validator->required('provider_name', 'Family/provider name is required for homeschool registration');
            if ($role === 'student') {
                $validator->required('gender', 'Gender is required for students');
                $validator->required('grade', 'Grade is required for students');
                $validator->required('date_of_birth', 'Date of birth is required for students');
            }
        }
        // independent_learner: no school/org required, minimal fields

        // Check for validation errors
        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        // Check if email already exists
        if ($this->userModel->emailExists(Validator::sanitizeEmail($data['email']))) {
            Response::error('Email address is already registered', 409, [
                'email' => 'This email address is already in use'
            ]);
        }

        // Resolve organization and school based on education context
        $primaryOrganizationId = null;
        $primarySchoolId       = null;

        if ($educationContext === 'public_school') {
            // Existing flow — lookup school by ID
            if (!empty($data['school_id'])) {
                $schoolId = (int)$data['school_id'];
                $stmt = $this->pdo->prepare(
                    "SELECT id, organization_id FROM schools WHERE id = :id AND is_active = 1 LIMIT 1"
                );
                $stmt->execute([':id' => $schoolId]);
                $school = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($school) {
                    $primarySchoolId       = $school['id'];
                    $primaryOrganizationId = $school['organization_id'];
                }
            }
        } elseif ($educationContext === 'private_school') {
            // Create or find private school org + school record
            $schoolName = Validator::sanitize($data['school_name']);
            $province   = isset($data['province']) ? Validator::sanitize($data['province']) : null;
            $slug       = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $schoolName));

            // Check if org already exists
            $stmt = $this->pdo->prepare(
                "SELECT id FROM organizations WHERE slug = :slug AND organization_type = 'school_private' LIMIT 1"
            );
            $stmt->execute([':slug' => $slug]);
            $existingOrg = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($existingOrg) {
                $primaryOrganizationId = $existingOrg['id'];
            } else {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO organizations (name, slug, organization_type, province, is_active)
                     VALUES (:name, :slug, 'school_private', :province, 1)"
                );
                $stmt->execute([':name' => $schoolName, ':slug' => $slug, ':province' => $province]);
                $primaryOrganizationId = $this->pdo->lastInsertId();
            }

            // Find or create school under this org
            $stmt = $this->pdo->prepare(
                "SELECT id FROM schools WHERE organization_id = :org_id AND name = :name LIMIT 1"
            );
            $stmt->execute([':org_id' => $primaryOrganizationId, ':name' => $schoolName]);
            $existingSchool = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($existingSchool) {
                $primarySchoolId = $existingSchool['id'];
            } else {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO schools (organization_id, name, slug, school_type, province, is_active)
                     VALUES (:org_id, :name, :slug, 'private', :province, 1)"
                );
                $stmt->execute([
                    ':org_id'   => $primaryOrganizationId,
                    ':name'     => $schoolName,
                    ':slug'     => $slug,
                    ':province' => $province,
                ]);
                $primarySchoolId = $this->pdo->lastInsertId();
            }
        } elseif ($educationContext === 'homeschool') {
            // Create individual home educator org
            $providerName = Validator::sanitize($data['provider_name']);
            $province     = isset($data['province']) ? Validator::sanitize($data['province']) : null;
            $slug         = 'homeschool-' . strtolower(preg_replace('/[^a-z0-9]+/i', '-', $providerName)) . '-' . time();

            $stmt = $this->pdo->prepare(
                "INSERT INTO organizations (name, slug, organization_type, province, is_active)
                 VALUES (:name, :slug, 'individual_home_educator', :province, 1)"
            );
            $stmt->execute([':name' => $providerName, ':slug' => $slug, ':province' => $province]);
            $primaryOrganizationId = $this->pdo->lastInsertId();
        }
        // independent_learner: no org/school — both stay null

        // Sanitize input
        $userData = [
            'name'                    => Validator::sanitize($data['name']),
            'email'                   => Validator::sanitizeEmail($data['email']),
            'password'                => $data['password'], // Will be hashed by User model
            'role'                    => $role,
            'education_context'       => $educationContext,
            'is_active'               => true,
            'contact_number'          => isset($data['contact_number']) ? Validator::sanitize($data['contact_number']) : null,
            'gender'                  => isset($data['gender']) ? $data['gender'] : null,
            'grade'                   => isset($data['grade']) ? Validator::sanitize($data['grade']) : null,
            'date_of_birth'           => isset($data['date_of_birth']) ? $data['date_of_birth'] : null,
            'primary_school_id'       => $primarySchoolId,
            'primary_organization_id' => $primaryOrganizationId,
        ];

        // Create user
        try {
            $this->userModel->beginTransaction();

            $userId = $this->userModel->createUser($userData);

            if (!$userId) {
                $this->userModel->rollback();
                Response::serverError('Failed to create user account');
            }

            $this->userModel->commit();

            // Fetch created user
            $user = $this->userModel->find($userId);

            if (!$user) {
                Response::serverError('User created but could not be retrieved');
            }

            // Generate JWT tokens
            $accessToken = JWTHandler::generateAccessToken($user->id, $user->email, $user->role);
            $refreshToken = JWTHandler::generateRefreshToken($user->id);

            // Update last login
            $this->userModel->updateLastLogin($user->id);

            // Return response
            Response::success([
                'user' => $user,
                'tokens' => [
                    'accessToken' => $accessToken,
                    'refreshToken' => $refreshToken,
                    'expiresIn' => JWT_EXPIRY
                ]
            ], 'User registered successfully', 201);

        } catch (\PDOException $e) {
            $this->userModel->rollback();
            error_log('Registration error: ' . $e->getMessage());
            Response::serverError('An error occurred during registration');
        }
    }

    /**
     * Login user
     *
     * POST /api/auth/login
     *
     * @param array $params Route parameters (not used)
     * @return void
     */
    public function login(array $params = []): void
    {
        // Get request data
        $data = $_POST;

        // Validate input
        $validator = Validator::make($data);

        $validator->required('email', 'Email is required')
                  ->email('email', 'Please provide a valid email address');

        $validator->required('password', 'Password is required');

        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        // Sanitize email
        $email = Validator::sanitizeEmail($data['email']);
        $password = $data['password'];

        // Verify credentials
        $user = $this->userModel->verifyPassword($email, $password);

        if (!$user) {
            Response::error('Invalid email or password', 401);
        }

        // Check if account is active
        if (!$user->is_active) {
            Response::error('Your account has been deactivated. Please contact support.', 403);
        }

        // Generate JWT tokens
        $accessToken = JWTHandler::generateAccessToken($user->id, $user->email, $user->role);
        $refreshToken = JWTHandler::generateRefreshToken($user->id);

        // Update last login
        $this->userModel->updateLastLogin($user->id);

        // Fetch updated user with statistics
        $user = $this->userModel->find($user->id);
        $stats = $this->userModel->getUserStats($user->id);

        // Return response
        Response::success([
            'user' => $user,
            'statistics' => $stats,
            'tokens' => [
                'accessToken' => $accessToken,
                'refreshToken' => $refreshToken,
                'expiresIn' => JWT_EXPIRY
            ]
        ], 'Login successful');
    }

    /**
     * Refresh access token using refresh token
     *
     * POST /api/auth/refresh
     *
     * @param array $params Route parameters (not used)
     * @return void
     */
    public function refresh(array $params = []): void
    {
        // index.php already parses the JSON body into $_POST.
        // Accept both snake_case (frontend sends 'refresh_token')
        // and camelCase ('refreshToken') for backwards compatibility.
        $refreshToken = $_POST['refresh_token'] ?? $_POST['refreshToken'] ?? null;

        // Validate input
        if (empty($refreshToken)) {
            Response::error('Refresh token is required', 400);
        }

        // Refresh tokens using callback to fetch user
        $getUserCallback = function($userId) {
            return $this->userModel->find($userId);
        };

        $tokens = JWTHandler::refreshAccessToken($refreshToken, $getUserCallback);

        if (!$tokens) {
            Response::error('Invalid or expired refresh token', 401);
        }

        // Return new tokens
        Response::success([
            'tokens' => [
                'accessToken' => $tokens['accessToken'],
                'refreshToken' => $tokens['refreshToken'],
                'expiresIn' => JWT_EXPIRY
            ]
        ], 'Token refreshed successfully');
    }

    /**
     * Logout user (blacklist token)
     *
     * POST /api/auth/logout
     *
     * @param array $params Route parameters (not used)
     * @return void
     */
    public function logout(array $params = []): void
    {
        // Extract token from header
        $token = JWTHandler::extractTokenFromHeader();

        if (!$token) {
            Response::error('No token provided', 401);
        }

        // Verify token
        $decoded = JWTHandler::verifyToken($token);

        if (!$decoded) {
            Response::error('Invalid token', 401);
        }

        // Blacklist token
        $blacklisted = JWTHandler::blacklistToken($token, $this->pdo);

        if (!$blacklisted) {
            Response::serverError('Failed to logout. Please try again.');
        }

        // Return success
        Response::success(null, 'Logged out successfully');
    }

    /**
     * Get current authenticated user profile
     *
     * GET /api/auth/me
     *
     * @param array $params Route parameters (not used)
     * @return void
     */
    public function me(array $params = []): void
    {
        // Extract token from header
        $token = JWTHandler::extractTokenFromHeader();

        if (!$token) {
            Response::unauthorized('Authentication required');
        }

        // Get current user from token
        $currentUser = JWTHandler::getCurrentUser();

        if (!$currentUser) {
            Response::unauthorized('Invalid or expired token');
        }

        // Check if token is blacklisted
        if (JWTHandler::isTokenBlacklisted($token, $this->pdo)) {
            Response::unauthorized('Token has been revoked. Please login again.');
        }

        // Fetch full user profile
        $user = $this->userModel->find($currentUser->id);

        if (!$user) {
            Response::notFound('User not found');
        }

        // Check if account is still active
        if (!$user->is_active) {
            Response::error('Your account has been deactivated', 403);
        }

        // Fetch user statistics
        $stats = $this->userModel->getUserStats($user->id);

        // Return response
        Response::success([
            'user' => $user,
            'statistics' => $stats
        ], 'User profile retrieved successfully');
    }

    /**
     * Request a password reset link
     *
     * POST /api/auth/forgot-password
     */
    public function forgotPassword(array $params = []): void
    {
        $data = $_POST;

        $validator = Validator::make($data);
        $validator->required('email', 'Email is required')
                  ->email('email', 'Please provide a valid email address');

        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        $email = Validator::sanitizeEmail($data['email']);

        // Always return success to prevent email enumeration
        $successMessage = 'If an account with that email exists, a password reset link has been sent.';

        $user = $this->userModel->findByEmail($email);

        if ($user) {
            try {
                // Generate token
                $rawToken = bin2hex(random_bytes(32));
                $hashedToken = hash('sha256', $rawToken);
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Store hashed token in DB
                $stmt = $this->pdo->prepare(
                    "UPDATE users SET reset_token = :token, reset_token_expires = :expiry WHERE id = :id"
                );
                $stmt->execute([
                    ':token'  => $hashedToken,
                    ':expiry' => $expiry,
                    ':id'     => $user->id,
                ]);

                // Build reset URL
                $resetUrl = APP_URL . '/public/reset-password.html?token=' . $rawToken;

                // Send email
                $htmlBody = $this->buildResetEmail($user->name ?? 'User', $resetUrl);
                Mailer::send($email, 'Password Reset - Sci-Bono AI Hub', $htmlBody);

            } catch (\Exception $e) {
                error_log('Password reset error: ' . $e->getMessage());
                // Don't expose the error to the user
            }
        }

        Response::success(null, $successMessage);
    }

    /**
     * Reset password using token
     *
     * POST /api/auth/reset-password
     */
    public function resetPassword(array $params = []): void
    {
        $data = $_POST;

        $validator = Validator::make($data);
        $validator->required('token', 'Reset token is required');
        $validator->required('password', 'Password is required')
                  ->strongPassword('password');
        $validator->required('password_confirmation', 'Password confirmation is required')
                  ->matches('password_confirmation', 'password', 'Passwords do not match');

        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        $hashedToken = hash('sha256', $data['token']);

        // Find user with valid token
        $stmt = $this->pdo->prepare(
            "SELECT id FROM users WHERE reset_token = :token AND reset_token_expires > NOW() LIMIT 1"
        );
        $stmt->execute([':token' => $hashedToken]);
        $user = $stmt->fetch(\PDO::FETCH_OBJ);

        if (!$user) {
            Response::error('Invalid or expired reset link. Please request a new one.', 400);
        }

        // Update password and clear token
        $newPasswordHash = password_hash($data['password'], PASSWORD_BCRYPT);
        $stmt = $this->pdo->prepare(
            "UPDATE users SET password_hash = :password, reset_token = NULL, reset_token_expires = NULL WHERE id = :id"
        );
        $stmt->execute([
            ':password' => $newPasswordHash,
            ':id'       => $user->id,
        ]);

        Response::success(null, 'Password has been reset successfully. You can now login with your new password.');
    }

    /**
     * Build the HTML email body for password reset
     */
    private function buildResetEmail(string $name, string $resetUrl): string
    {
        return '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: linear-gradient(135deg, #4B6EFB, #7B68EE); padding: 30px; border-radius: 10px 10px 0 0; text-align: center;">
                <h1 style="color: #ffffff; margin: 0; font-size: 24px;">Sci-Bono AI Hub</h1>
            </div>
            <div style="background: #ffffff; padding: 30px; border: 1px solid #e0e0e0; border-top: none; border-radius: 0 0 10px 10px;">
                <h2 style="color: #333; margin-top: 0;">Password Reset Request</h2>
                <p style="color: #555; line-height: 1.6;">Hi ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ',</p>
                <p style="color: #555; line-height: 1.6;">We received a request to reset your password. Click the button below to set a new password:</p>
                <div style="text-align: center; margin: 30px 0;">
                    <a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '"
                       style="background: #4B6EFB; color: #ffffff; padding: 14px 30px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;">
                        Reset My Password
                    </a>
                </div>
                <p style="color: #555; line-height: 1.6;">This link will expire in <strong>1 hour</strong>.</p>
                <p style="color: #555; line-height: 1.6;">If you didn\'t request a password reset, you can safely ignore this email. Your password will remain unchanged.</p>
                <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
                <p style="color: #999; font-size: 12px;">If the button doesn\'t work, copy and paste this link into your browser:<br>
                    <a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '" style="color: #4B6EFB; word-break: break-all;">'
                    . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '</a>
                </p>
            </div>
        </div>';
    }
}
