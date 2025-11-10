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
        'profile_picture',
        'bio',
        'school',
        'grade',
        'last_login'
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
            $stmt = $this->pdo->prepare("UPDATE {$this->table} SET last_login = NOW() WHERE id = :id");
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
                WHERE user_id = :user_id AND completed = TRUE
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
                    WHERE last_login >= DATE_SUB(NOW(), INTERVAL :days DAY)
                    ORDER BY last_login DESC";

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
}
