<?php
namespace App\Controllers;

use App\Models\User;
use App\Utils\Response;
use App\Utils\Validator;
use App\Utils\JWTHandler;

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

        // Role is optional, default to 'student'
        if (isset($data['role'])) {
            $validator->in('role', ['student', 'instructor', 'admin'], 'Invalid role specified');
        }

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

        // Sanitize input
        $userData = [
            'name' => Validator::sanitize($data['name']),
            'email' => Validator::sanitizeEmail($data['email']),
            'password' => $data['password'], // Will be hashed by User model
            'role' => isset($data['role']) ? $data['role'] : 'student',
            'is_active' => true
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
        // Get request data
        $data = $_POST;

        // Validate input
        if (!isset($data['refreshToken']) || empty($data['refreshToken'])) {
            Response::error('Refresh token is required', 400);
        }

        $refreshToken = $data['refreshToken'];

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
}
