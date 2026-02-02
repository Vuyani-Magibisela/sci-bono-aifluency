<?php
namespace App\Models;

use PDO;

/**
 * User Model
 *
 * Handles user-related database operations
 */
class User extends BaseModel
{
    protected string $table = 'users';
    protected array $fillable = [
        'email',
        'password_hash',
        'name',
        'role',
        'is_active',
        'profile_picture_url',
        'is_verified',
        'verification_token',
        'reset_token',
        'reset_token_expires',
        'last_login_at',
        // Phase 8: Profile enhancements
        'bio',
        'headline',
        'location',
        'website_url',
        'github_url',
        'linkedin_url',
        'twitter_url',
        'is_public_profile',
        'show_email',
        'show_achievements',
        'show_certificates',
        'profile_views_count',
        'last_profile_updated'
    ];
    protected array $hidden = ['password_hash'];

    /**
     * Create a new user
     *
     * @param array $data User data
     * @return int|null User ID or null on failure
     */
    public function createUser(array $data): ?int
    {
        // Hash password if provided
        if (isset($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT);
            unset($data['password']);
        }

        return $this->create($data);
    }

    /**
     * Find user by email
     *
     * @param string $email User email
     * @return object|null
     */
    public function findByEmail(string $email): ?object
    {
        return $this->findBy('email', $email);
    }

    /**
     * Find user by email with password (for authentication)
     *
     * @param string $email User email
     * @return object|null User object with password_hash
     */
    public function findByEmailWithPassword(string $email): ?object
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
        } catch (\PDOException $e) {
            error_log("Database error in findByEmailWithPassword: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Verify user password
     *
     * @param string $email User email
     * @param string $password Plain text password
     * @return object|null User object if valid, null otherwise
     */
    public function verifyPassword(string $email, string $password): ?object
    {
        $user = $this->findByEmailWithPassword($email);

        if (!$user || !password_verify($password, $user->password_hash)) {
            return null;
        }

        // Remove password hash from returned object
        unset($user->password_hash);

        return $user;
    }

    /**
     * Update user password
     *
     * @param int $userId User ID
     * @param string $newPassword New plain text password
     * @return bool Success status
     */
    public function updatePassword(int $userId, string $newPassword): bool
    {
        try {
            $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);

            $stmt = $this->pdo->prepare("UPDATE {$this->table} SET password_hash = :password_hash WHERE id = :id");
            return $stmt->execute([
                'password_hash' => $passwordHash,
                'id' => $userId
            ]);
        } catch (\PDOException $e) {
            error_log("Database error in updatePassword: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update last login timestamp
     *
     * @param int $userId User ID
     * @return bool Success status
     */
    public function updateLastLogin(int $userId): bool
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE {$this->table} SET last_login_at = NOW() WHERE id = :id");
            return $stmt->execute(['id' => $userId]);
        } catch (\PDOException $e) {
            error_log("Database error in updateLastLogin: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if email is already registered
     *
     * @param string $email Email to check
     * @param int|null $excludeId Optional user ID to exclude from check
     * @return bool
     */
    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        return $this->exists('email', $email, $excludeId);
    }

    /**
     * Activate or deactivate a user
     *
     * @param int $userId User ID
     * @param bool $isActive Active status
     * @return bool Success status
     */
    public function setActiveStatus(int $userId, bool $isActive): bool
    {
        return $this->update($userId, ['is_active' => $isActive ? 1 : 0]);
    }

    /**
     * Get all students
     *
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array
     */
    public function getStudents(?int $limit = null, ?int $offset = null): array
    {
        return $this->all(['role' => 'student'], 'created_at DESC', $limit, $offset);
    }

    /**
     * Get all instructors
     *
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array
     */
    public function getInstructors(?int $limit = null, ?int $offset = null): array
    {
        return $this->all(['role' => 'instructor'], 'created_at DESC', $limit, $offset);
    }

    /**
     * Get users by role
     *
     * @param string $role User role
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array
     */
    public function getUsersByRole(string $role, ?int $limit = null, ?int $offset = null): array
    {
        return $this->all(['role' => $role], 'created_at DESC', $limit, $offset);
    }

    /**
     * Search users by name or email
     *
     * @param string $searchTerm Search term
     * @param string|null $role Optional role filter
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array
     */
    public function searchUsers(string $searchTerm, ?string $role = null, ?int $limit = null, ?int $offset = null): array
    {
        try {
            $sql = "SELECT * FROM {$this->table}
                    WHERE (name LIKE :search OR email LIKE :search)";

            $params = ['search' => "%{$searchTerm}%"];

            if ($role !== null) {
                $sql .= " AND role = :role";
                $params['role'] = $role;
            }

            $sql .= " ORDER BY name ASC";

            if ($limit !== null) {
                $sql .= " LIMIT {$limit}";
                if ($offset !== null) {
                    $sql .= " OFFSET {$offset}";
                }
            }

            $results = $this->query($sql, $params);

            return array_map([$this, 'hideFields'], $results);
        } catch (\PDOException $e) {
            error_log("Database error in searchUsers: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get user statistics
     *
     * @param int $userId User ID
     * @return array|null User statistics or null on failure
     */
    public function getUserStats(int $userId): ?array
    {
        try {
            // Get enrollment count
            $enrollmentStmt = $this->pdo->prepare("
                SELECT COUNT(*) as total
                FROM enrollments
                WHERE user_id = :user_id
            ");
            $enrollmentStmt->execute(['user_id' => $userId]);
            $enrollments = $enrollmentStmt->fetch(PDO::FETCH_ASSOC);

            // Get completed lessons count
            $completedLessonsStmt = $this->pdo->prepare("
                SELECT COUNT(*) as total
                FROM lesson_progress
                WHERE user_id = :user_id AND status = 'completed'
            ");
            $completedLessonsStmt->execute(['user_id' => $userId]);
            $completedLessons = $completedLessonsStmt->fetch(PDO::FETCH_ASSOC);

            // Get quiz attempts count
            $quizAttemptsStmt = $this->pdo->prepare("
                SELECT COUNT(*) as total
                FROM quiz_attempts
                WHERE user_id = :user_id
            ");
            $quizAttemptsStmt->execute(['user_id' => $userId]);
            $quizAttempts = $quizAttemptsStmt->fetch(PDO::FETCH_ASSOC);

            // Get average quiz score
            $avgScoreStmt = $this->pdo->prepare("
                SELECT AVG(score) as avg_score
                FROM quiz_attempts
                WHERE user_id = :user_id
            ");
            $avgScoreStmt->execute(['user_id' => $userId]);
            $avgScore = $avgScoreStmt->fetch(PDO::FETCH_ASSOC);

            // Get certificates count
            $certificatesStmt = $this->pdo->prepare("
                SELECT COUNT(*) as total
                FROM certificates
                WHERE user_id = :user_id
            ");
            $certificatesStmt->execute(['user_id' => $userId]);
            $certificates = $certificatesStmt->fetch(PDO::FETCH_ASSOC);

            return [
                'enrollments' => (int) $enrollments['total'],
                'completed_lessons' => (int) $completedLessons['total'],
                'quiz_attempts' => (int) $quizAttempts['total'],
                'average_quiz_score' => round((float) $avgScore['avg_score'] ?? 0, 2),
                'certificates' => (int) $certificates['total']
            ];
        } catch (\PDOException $e) {
            error_log("Database error in getUserStats: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get recently active users
     *
     * @param int $days Number of days to look back
     * @param int|null $limit Optional limit
     * @return array
     */
    public function getRecentlyActiveUsers(int $days = 7, ?int $limit = null): array
    {
        try {
            $sql = "SELECT * FROM {$this->table}
                    WHERE last_login_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                    ORDER BY last_login_at DESC";

            if ($limit !== null) {
                $sql .= " LIMIT {$limit}";
            }

            $results = $this->query($sql, ['days' => $days]);

            return array_map([$this, 'hideFields'], $results);
        } catch (\PDOException $e) {
            error_log("Database error in getRecentlyActiveUsers: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Count users by role
     *
     * @param string $role User role
     * @return int
     */
    public function countByRole(string $role): int
    {
        return $this->count(['role' => $role]);
    }

    /**
     * Get all roles
     *
     * @return array
     */
    public function getRoles(): array
    {
        return ['student', 'instructor', 'admin'];
    }

    // ========================================
    // Phase 8: Profile Enhancement Methods
    // ========================================

    /**
     * Update profile fields
     *
     * @param int $userId User ID
     * @param array $profileData Profile fields to update
     * @return bool Success status
     */
    public function updateProfileFields(int $userId, array $profileData): bool
    {
        try {
            $allowedFields = [
                'bio', 'headline', 'location', 'website_url',
                'github_url', 'linkedin_url', 'twitter_url'
            ];

            $updateData = [];
            foreach ($allowedFields as $field) {
                if (isset($profileData[$field])) {
                    $updateData[$field] = $profileData[$field];
                }
            }

            if (empty($updateData)) {
                return false;
            }

            $updateData['last_profile_updated'] = date('Y-m-d H:i:s');

            return $this->update($userId, $updateData);
        } catch (\PDOException $e) {
            error_log("Error updating profile fields: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get public profile data (respects privacy settings)
     *
     * @param int $userId User ID
     * @return array|null Profile data or null if private/not found
     */
    public function getPublicProfileData(int $userId): ?array
    {
        try {
            $user = $this->find($userId);

            if (!$user || $user->is_public_profile != 1) {
                return null;
            }

            // Build public profile array
            $profile = [
                'id' => $user->id,
                'name' => $user->name,
                'profile_picture_url' => $user->profile_picture_url,
                'bio' => $user->bio,
                'headline' => $user->headline,
                'location' => $user->location,
                'role' => $user->role,
                'created_at' => $user->created_at,
                'last_profile_updated' => $user->last_profile_updated,
                'profile_views_count' => $user->profile_views_count
            ];

            // Conditionally include based on privacy settings
            if ($user->show_email) {
                $profile['email'] = $user->email;
            }

            // Social links
            $profile['social_links'] = [
                'website' => $user->website_url,
                'github' => $user->github_url,
                'linkedin' => $user->linkedin_url,
                'twitter' => $user->twitter_url
            ];

            // Statistics
            $stats = $this->getUserStats($userId);
            $profile['statistics'] = $stats;

            // Include achievements if public
            if ($user->show_achievements) {
                $profile['show_achievements'] = true;
            }

            // Include certificates if public
            if ($user->show_certificates) {
                $profile['show_certificates'] = true;
            }

            return $profile;
        } catch (\PDOException $e) {
            error_log("Error getting public profile: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update privacy settings
     *
     * @param int $userId User ID
     * @param array $privacyData Privacy settings
     * @return bool Success status
     */
    public function updatePrivacySettings(int $userId, array $privacyData): bool
    {
        try {
            $allowedFields = [
                'is_public_profile', 'show_email',
                'show_achievements', 'show_certificates'
            ];

            $updateData = [];
            foreach ($allowedFields as $field) {
                if (isset($privacyData[$field])) {
                    // Convert to boolean, then to integer for MySQL TINYINT
                    $updateData[$field] = (int) (bool) $privacyData[$field];
                }
            }

            if (empty($updateData)) {
                return false;
            }

            return $this->update($userId, $updateData);
        } catch (\PDOException $e) {
            error_log("Error updating privacy settings: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get profile completion percentage
     *
     * @param int $userId User ID
     * @return int Completion percentage (0-100)
     */
    public function getProfileCompletionPercentage(int $userId): int
    {
        try {
            $user = $this->find($userId);

            if (!$user) {
                return 0;
            }

            $fields = [
                'name' => !empty($user->name),
                'email' => !empty($user->email),
                'profile_picture_url' => !empty($user->profile_picture_url),
                'bio' => !empty($user->bio),
                'headline' => !empty($user->headline),
                'location' => !empty($user->location),
                'website_url' => !empty($user->website_url),
                'github_url' => !empty($user->github_url),
                'linkedin_url' => !empty($user->linkedin_url),
                'twitter_url' => !empty($user->twitter_url)
            ];

            $completed = count(array_filter($fields));
            $total = count($fields);

            return (int) round(($completed / $total) * 100);
        } catch (\PDOException $e) {
            error_log("Error calculating profile completion: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Track profile view
     *
     * @param int $viewedUserId User being viewed
     * @param int $viewerUserId User viewing the profile
     * @param string $ipAddress IP address
     * @param string $userAgent User agent
     * @return bool Success status
     */
    public function trackProfileView(int $viewedUserId, int $viewerUserId, string $ipAddress, string $userAgent): bool
    {
        try {
            // Don't track self-views
            if ($viewedUserId === $viewerUserId) {
                return false;
            }

            // Insert view record
            $stmt = $this->pdo->prepare("
                INSERT INTO profile_views
                (viewer_user_id, viewed_user_id, ip_address, user_agent)
                VALUES (:viewer_id, :viewed_id, :ip, :user_agent)
            ");

            $stmt->execute([
                'viewer_id' => $viewerUserId,
                'viewed_id' => $viewedUserId,
                'ip' => $ipAddress,
                'user_agent' => $userAgent
            ]);

            // Increment view counter
            $stmt = $this->pdo->prepare("
                UPDATE users
                SET profile_views_count = profile_views_count + 1
                WHERE id = :user_id
            ");

            return $stmt->execute(['user_id' => $viewedUserId]);
        } catch (\PDOException $e) {
            error_log("Error tracking profile view: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Search users by name, email, or headline (public profiles only)
     *
     * @param string $searchTerm Search term
     * @param bool $publicOnly Only return users with public profiles
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array
     */
    public function searchPublicProfiles(string $searchTerm, bool $publicOnly = true, ?int $limit = null, ?int $offset = null): array
    {
        try {
            $searchPattern = "%{$searchTerm}%";

            $sql = "SELECT
                        id, name, email, profile_picture_url, headline,
                        location, role, profile_views_count, created_at
                    FROM {$this->table}
                    WHERE (name LIKE ? OR email LIKE ? OR headline LIKE ?)";

            $params = [$searchPattern, $searchPattern, $searchPattern];

            if ($publicOnly) {
                $sql .= " AND is_public_profile = 1";
            }

            $sql .= " ORDER BY name ASC";

            if ($limit !== null) {
                $sql .= " LIMIT ?";
                $params[] = $limit;
                if ($offset !== null) {
                    $sql .= " OFFSET ?";
                    $params[] = $offset;
                }
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Error searching public profiles: " . $e->getMessage());
            return [];
        }
    }

    // ================================================================
    // PHASE 10: ADVANCED ANALYTICS METHODS
    // ================================================================

    /**
     * Get user acquisition trends over time
     * Phase 10: Advanced Analytics Dashboard
     *
     * @param array $options Grouping and date range options
     * @return array User acquisition trends
     */
    public function getAcquisitionTrends(array $options = []): array
    {
        try {
            $groupBy = $options['group_by'] ?? 'month';
            $startDate = $options['start_date'] ?? date('Y-m-d', strtotime('-6 months'));
            $endDate = $options['end_date'] ?? date('Y-m-d');

            // Determine grouping format
            $dateFormat = match($groupBy) {
                'day' => 'DATE(created_at)',
                'week' => 'DATE_FORMAT(created_at, "%Y-%U")',
                'month' => 'DATE_FORMAT(created_at, "%Y-%m")',
                default => 'DATE_FORMAT(created_at, "%Y-%m")'
            };

            $sql = "SELECT
                    $dateFormat as period,
                    COUNT(*) as new_users_count,
                    SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as students_count,
                    SUM(CASE WHEN role = 'instructor' THEN 1 ELSE 0 END) as instructors_count,
                    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins_count,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_count
                FROM {$this->table}
                WHERE DATE(created_at) BETWEEN :start_date AND :end_date
                GROUP BY $dateFormat
                ORDER BY period ASC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            $trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate totals
            $totalUsers = array_sum(array_column($trends, 'new_users_count'));
            $totalActive = array_sum(array_column($trends, 'active_count'));
            $activationRate = $totalUsers > 0 ? round(($totalActive / $totalUsers) * 100, 2) : 0;

            return [
                'trends' => $trends,
                'total_new_users' => $totalUsers,
                'total_active' => $totalActive,
                'activation_rate' => $activationRate,
                'group_by' => $groupBy
            ];
        } catch (\PDOException $e) {
            error_log("Database error in getAcquisitionTrends: " . $e->getMessage());
            return [
                'trends' => [],
                'total_new_users' => 0,
                'total_active' => 0,
                'activation_rate' => 0,
                'group_by' => $groupBy
            ];
        }
    }

    /**
     * Get at-risk students for a specific course
     * Phase 10: Advanced Analytics Dashboard
     *
     * @param int $courseId Course ID
     * @param int $riskThreshold Minimum risk score (0-100, default 60)
     * @return array At-risk students data
     */
    public function getAtRiskStudents(int $courseId, int $riskThreshold = 60): array
    {
        try {
            // Use the v_at_risk_students view
            $sql = "SELECT
                    user_id,
                    first_name,
                    last_name,
                    email,
                    course_title,
                    progress_percentage,
                    enrolled_at,
                    days_since_last_access,
                    last_accessed_at,
                    avg_quiz_score,
                    failed_quiz_count,
                    risk_score,
                    CASE
                        WHEN risk_score >= 80 THEN 'critical'
                        WHEN risk_score >= 60 THEN 'high'
                        WHEN risk_score >= 40 THEN 'moderate'
                        ELSE 'low'
                    END as risk_level
                FROM v_at_risk_students
                WHERE course_id = :course_id
                AND risk_score >= :risk_threshold
                ORDER BY risk_score DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'course_id' => $courseId,
                'risk_threshold' => $riskThreshold
            ]);
            $atRiskStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Categorize by risk level
            $riskLevels = [
                'critical' => count(array_filter($atRiskStudents, fn($s) => $s['risk_score'] >= 80)),
                'high' => count(array_filter($atRiskStudents, fn($s) => $s['risk_score'] >= 60 && $s['risk_score'] < 80)),
                'moderate' => count(array_filter($atRiskStudents, fn($s) => $s['risk_score'] >= 40 && $s['risk_score'] < 60))
            ];

            return [
                'at_risk_students' => $atRiskStudents,
                'total_at_risk' => count($atRiskStudents),
                'risk_levels' => $riskLevels,
                'risk_threshold' => $riskThreshold
            ];
        } catch (\PDOException $e) {
            error_log("Database error in getAtRiskStudents: " . $e->getMessage());
            return [
                'at_risk_students' => [],
                'total_at_risk' => 0,
                'risk_levels' => [
                    'critical' => 0,
                    'high' => 0,
                    'moderate' => 0
                ],
                'risk_threshold' => $riskThreshold
            ];
        }
    }
}
