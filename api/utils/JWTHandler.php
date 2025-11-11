<?php
namespace App\Utils;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;

/**
 * JWT Handler Utility Class
 *
 * Handles JWT token generation, verification, and refresh
 */
class JWTHandler
{
    /**
     * Generate an access token
     *
     * @param int $userId User ID
     * @param string $email User email
     * @param string $role User role
     * @return string JWT token
     */
    public static function generateAccessToken(int $userId, string $email, string $role): string
    {
        $issuedAt = time();
        $expiresAt = $issuedAt + JWT_EXPIRY;

        $payload = [
            'iss' => APP_URL,                // Issuer
            'aud' => APP_URL,                // Audience
            'iat' => $issuedAt,              // Issued at
            'exp' => $expiresAt,             // Expires at
            'sub' => $userId,                // Subject (user ID)
            'email' => $email,               // User email
            'role' => $role,                 // User role
            'type' => 'access'               // Token type
        ];

        return JWT::encode($payload, JWT_SECRET, JWT_ALGORITHM);
    }

    /**
     * Generate a refresh token
     *
     * @param int $userId User ID
     * @return string JWT token
     */
    public static function generateRefreshToken(int $userId): string
    {
        $issuedAt = time();
        $expiresAt = $issuedAt + JWT_REFRESH_EXPIRY;

        $payload = [
            'iss' => APP_URL,
            'aud' => APP_URL,
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'sub' => $userId,
            'type' => 'refresh'
        ];

        return JWT::encode($payload, JWT_SECRET, JWT_ALGORITHM);
    }

    /**
     * Verify and decode a JWT token
     *
     * @param string $token JWT token
     * @return object|null Decoded token payload or null if invalid
     */
    public static function verifyToken(string $token): ?object
    {
        try {
            $decoded = JWT::decode($token, new Key(JWT_SECRET, JWT_ALGORITHM));

            // Verify issuer and audience
            if ($decoded->iss !== APP_URL || $decoded->aud !== APP_URL) {
                return null;
            }

            return $decoded;
        } catch (ExpiredException $e) {
            // Token has expired
            return null;
        } catch (SignatureInvalidException $e) {
            // Token signature is invalid
            return null;
        } catch (BeforeValidException $e) {
            // Token is not yet valid
            return null;
        } catch (\Exception $e) {
            // Other errors
            return null;
        }
    }

    /**
     * Extract token from Authorization header
     *
     * @return string|null Token or null if not found
     */
    public static function extractTokenFromHeader(): ?string
    {
        // Try getallheaders() first (available in Apache SAPI)
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (isset($headers['Authorization'])) {
                $authHeader = $headers['Authorization'];
                if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                    return $matches[1];
                }
            }
        }

        // Fallback to $_SERVER for CLI and other environments
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Get the current authenticated user from the token
     *
     * @return object|null User data or null if not authenticated
     */
    public static function getCurrentUser(): ?object
    {
        $token = self::extractTokenFromHeader();

        if (!$token) {
            return null;
        }

        $decoded = self::verifyToken($token);

        if (!$decoded || $decoded->type !== 'access') {
            return null;
        }

        return (object) [
            'id' => $decoded->sub,
            'email' => $decoded->email,
            'role' => $decoded->role
        ];
    }

    /**
     * Check if the current user has a specific role
     *
     * @param string|array $roles Role(s) to check
     * @return bool
     */
    public static function hasRole($roles): bool
    {
        $user = self::getCurrentUser();

        if (!$user) {
            return false;
        }

        if (is_string($roles)) {
            $roles = [$roles];
        }

        return in_array($user->role, $roles, true);
    }

    /**
     * Check if the current user is authenticated
     *
     * @return bool
     */
    public static function isAuthenticated(): bool
    {
        return self::getCurrentUser() !== null;
    }

    /**
     * Refresh an access token using a refresh token
     *
     * @param string $refreshToken Refresh token
     * @param callable $getUserCallback Callback to fetch user data by ID
     * @return array|null ['accessToken' => string, 'refreshToken' => string] or null if invalid
     */
    public static function refreshAccessToken(string $refreshToken, callable $getUserCallback): ?array
    {
        $decoded = self::verifyToken($refreshToken);

        if (!$decoded || $decoded->type !== 'refresh') {
            return null;
        }

        $userId = $decoded->sub;

        // Fetch user data using the callback
        $user = $getUserCallback($userId);

        if (!$user || !$user->is_active) {
            return null;
        }

        // Generate new tokens
        return [
            'accessToken' => self::generateAccessToken($user->id, $user->email, $user->role),
            'refreshToken' => self::generateRefreshToken($user->id)
        ];
    }

    /**
     * Get token expiry time
     *
     * @param string $token JWT token
     * @return int|null Unix timestamp or null if invalid
     */
    public static function getTokenExpiry(string $token): ?int
    {
        $decoded = self::verifyToken($token);

        if (!$decoded) {
            return null;
        }

        return $decoded->exp;
    }

    /**
     * Check if a token is expired
     *
     * @param string $token JWT token
     * @return bool
     */
    public static function isTokenExpired(string $token): bool
    {
        $expiry = self::getTokenExpiry($token);

        if ($expiry === null) {
            return true;
        }

        return time() >= $expiry;
    }

    /**
     * Get remaining time until token expiry
     *
     * @param string $token JWT token
     * @return int|null Seconds remaining or null if invalid
     */
    public static function getTimeUntilExpiry(string $token): ?int
    {
        $expiry = self::getTokenExpiry($token);

        if ($expiry === null) {
            return null;
        }

        $remaining = $expiry - time();

        return max(0, $remaining);
    }

    /**
     * Invalidate a token by adding it to a blacklist
     * Note: This requires a database table to store blacklisted tokens
     *
     * @param string $token JWT token
     * @param \PDO $pdo Database connection
     * @return bool
     */
    public static function blacklistToken(string $token, \PDO $pdo): bool
    {
        $decoded = self::verifyToken($token);

        if (!$decoded) {
            return false;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO token_blacklist (token, user_id, expires_at, created_at)
                VALUES (:token, :user_id, FROM_UNIXTIME(:expires_at), NOW())
            ");

            $stmt->execute([
                'token' => hash('sha256', $token), // Store hash instead of full token
                'user_id' => $decoded->sub,
                'expires_at' => $decoded->exp
            ]);

            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Check if a token is blacklisted
     *
     * @param string $token JWT token
     * @param \PDO $pdo Database connection
     * @return bool
     */
    public static function isTokenBlacklisted(string $token, \PDO $pdo): bool
    {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM token_blacklist
                WHERE token = :token AND expires_at > NOW()
            ");

            $stmt->execute([
                'token' => hash('sha256', $token)
            ]);

            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $result['count'] > 0;
        } catch (\PDOException $e) {
            return false;
        }
    }
}
