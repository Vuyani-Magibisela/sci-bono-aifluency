<?php
namespace App\Models;

use PDO;

/**
 * Achievement Model
 *
 * Handles achievement/badge system with unlock logic
 * Phase 6: Quiz Tracking & Grading System
 */
class Achievement extends BaseModel
{
    protected string $table = 'achievements';
    protected array $fillable = [
        'category_id',
        'name',
        'description',
        'badge_icon',
        'badge_color',
        'tier',
        'points',
        'unlock_criteria',
        'is_secret',
        'is_active'
    ];
    protected array $hidden = [];

    /**
     * Get all active achievements
     *
     * @param bool $includeSecret Include secret achievements
     * @return array
     */
    public function getAllActive(bool $includeSecret = false): array
    {
        try {
            $sql = "
                SELECT
                    a.*,
                    ac.name as category_name,
                    ac.icon as category_icon,
                    ac.color as category_color
                FROM {$this->table} a
                JOIN achievement_categories ac ON a.category_id = ac.id
                WHERE a.is_active = 1
            ";

            if (!$includeSecret) {
                $sql .= " AND a.is_secret = 0";
            }

            $sql .= " ORDER BY ac.display_order, a.tier, a.points";

            $stmt = $this->pdo->query($sql);
            $achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Parse JSON criteria
            foreach ($achievements as &$achievement) {
                if (isset($achievement['unlock_criteria']) && is_string($achievement['unlock_criteria'])) {
                    $achievement['unlock_criteria'] = json_decode($achievement['unlock_criteria'], true);
                }
            }

            return $achievements;
        } catch (\PDOException $e) {
            error_log("Database error in getAllActive: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get user's unlocked achievements
     *
     * @param int $userId User ID
     * @return array
     */
    public function getUserAchievements(int $userId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    a.*,
                    ac.name as category_name,
                    ac.icon as category_icon,
                    ua.unlocked_at,
                    ua.progress_data
                FROM user_achievements ua
                JOIN {$this->table} a ON ua.achievement_id = a.id
                JOIN achievement_categories ac ON a.category_id = ac.id
                WHERE ua.user_id = :user_id
                ORDER BY ua.unlocked_at DESC
            ");
            $stmt->execute(['user_id' => $userId]);
            $achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Parse JSON fields
            foreach ($achievements as &$achievement) {
                if (isset($achievement['unlock_criteria']) && is_string($achievement['unlock_criteria'])) {
                    $achievement['unlock_criteria'] = json_decode($achievement['unlock_criteria'], true);
                }
                if (isset($achievement['progress_data']) && is_string($achievement['progress_data'])) {
                    $achievement['progress_data'] = json_decode($achievement['progress_data'], true);
                }
            }

            return $achievements;
        } catch (\PDOException $e) {
            error_log("Database error in getUserAchievements: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if user has unlocked achievement
     *
     * @param int $userId User ID
     * @param int $achievementId Achievement ID
     * @return bool
     */
    public function hasUnlocked(int $userId, int $achievementId): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM user_achievements
                WHERE user_id = :user_id AND achievement_id = :achievement_id
            ");
            $stmt->execute([
                'user_id' => $userId,
                'achievement_id' => $achievementId
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result['count'] > 0;
        } catch (\PDOException $e) {
            error_log("Database error in hasUnlocked: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Unlock achievement for user
     *
     * @param int $userId User ID
     * @param int $achievementId Achievement ID
     * @param array|null $progressData Optional progress data
     * @return bool Success status
     */
    public function unlockAchievement(int $userId, int $achievementId, ?array $progressData = null): bool
    {
        try {
            // Check if already unlocked
            if ($this->hasUnlocked($userId, $achievementId)) {
                return true;
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO user_achievements
                (user_id, achievement_id, progress_data, unlocked_at)
                VALUES
                (:user_id, :achievement_id, :progress_data, NOW())
            ");

            return $stmt->execute([
                'user_id' => $userId,
                'achievement_id' => $achievementId,
                'progress_data' => $progressData ? json_encode($progressData) : null
            ]);
        } catch (\PDOException $e) {
            error_log("Database error in unlockAchievement: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check and unlock achievements for user based on criteria
     *
     * @param int $userId User ID
     * @param string $eventType Event type (lesson_completion, quiz_score, etc)
     * @param array $eventData Event-specific data
     * @return array Newly unlocked achievements
     */
    public function checkAndUnlock(int $userId, string $eventType, array $eventData = []): array
    {
        try {
            $newlyUnlocked = [];

            // Get all active achievements that user hasn't unlocked yet
            $stmt = $this->pdo->prepare("
                SELECT a.*
                FROM {$this->table} a
                WHERE a.is_active = 1
                AND a.id NOT IN (
                    SELECT achievement_id FROM user_achievements WHERE user_id = :user_id
                )
            ");
            $stmt->execute(['user_id' => $userId]);
            $achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($achievements as $achievement) {
                $criteria = json_decode($achievement['unlock_criteria'], true);

                if ($this->checkCriteria($userId, $criteria, $eventType, $eventData)) {
                    if ($this->unlockAchievement($userId, $achievement['id'])) {
                        $newlyUnlocked[] = $achievement;
                    }
                }
            }

            return $newlyUnlocked;
        } catch (\PDOException $e) {
            error_log("Database error in checkAndUnlock: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if achievement criteria is met
     *
     * @param int $userId User ID
     * @param array $criteria Unlock criteria
     * @param string $eventType Current event type
     * @param array $eventData Event data
     * @return bool Criteria met
     */
    private function checkCriteria(int $userId, array $criteria, string $eventType, array $eventData): bool
    {
        $type = $criteria['type'] ?? '';

        // Only check if event type matches criteria type
        if ($type !== $eventType) {
            return false;
        }

        try {
            switch ($type) {
                case 'lesson_completion':
                    return $this->checkLessonCompletion($userId, $criteria);

                case 'module_completion':
                    return $this->checkModuleCompletion($userId, $criteria);

                case 'course_completion':
                    return $this->checkCourseCompletion($userId, $criteria);

                case 'quiz_score':
                    return $this->checkQuizScore($userId, $criteria, $eventData);

                case 'quiz_first_attempt':
                    return $this->checkQuizFirstAttempt($userId, $criteria, $eventData);

                case 'quiz_attempts':
                    return $this->checkQuizAttempts($userId, $criteria);

                case 'notes_created':
                    return $this->checkNotesCreated($userId, $criteria);

                case 'bookmarks_created':
                    return $this->checkBookmarksCreated($userId, $criteria);

                case 'consecutive_login_days':
                    return $this->checkConsecutiveLogins($userId, $criteria);

                case 'total_points':
                    return $this->checkTotalPoints($userId, $criteria);

                default:
                    return false;
            }
        } catch (\PDOException $e) {
            error_log("Database error in checkCriteria: " . $e->getMessage());
            return false;
        }
    }

    private function checkLessonCompletion(int $userId, array $criteria): bool
    {
        $requiredCount = $criteria['count'] ?? 1;
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT lesson_id) as count
            FROM lesson_progress
            WHERE user_id = :user_id AND completion_percentage >= 100
        ");
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] >= $requiredCount;
    }

    private function checkModuleCompletion(int $userId, array $criteria): bool
    {
        $requiredCount = $criteria['count'] ?? 1;
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count
            FROM enrollments e
            WHERE e.user_id = :user_id
            AND e.id IN (
                SELECT enrollment_id FROM module_progress
                WHERE completion_percentage >= 100
            )
        ");
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] >= $requiredCount;
    }

    private function checkCourseCompletion(int $userId, array $criteria): bool
    {
        $minCompletion = $criteria['min_completion'] ?? 100;
        $stmt = $this->pdo->prepare("
            SELECT completion_percentage
            FROM enrollments
            WHERE user_id = :user_id
            ORDER BY completion_percentage DESC
            LIMIT 1
        ");
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result && $result['completion_percentage'] >= $minCompletion;
    }

    private function checkQuizScore(int $userId, array $criteria, array $eventData): bool
    {
        $minScore = $criteria['min_score'] ?? 70;
        $requiredCount = $criteria['count'] ?? 1;
        $allQuizzes = $criteria['all_quizzes'] ?? false;

        if ($allQuizzes) {
            // Check if user scored min_score on ALL quizzes
            $stmt = $this->pdo->prepare("
                SELECT COUNT(DISTINCT quiz_id) as passed_count,
                       (SELECT COUNT(*) FROM quizzes WHERE is_active = 1) as total_quizzes
                FROM quiz_attempts
                WHERE user_id = :user_id
                AND score >= :min_score
            ");
            $stmt->execute([
                'user_id' => $userId,
                'min_score' => $minScore
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['passed_count'] == $result['total_quizzes'];
        } else {
            // Check if user scored min_score on at least 'count' quizzes
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM quiz_attempts
                WHERE user_id = :user_id AND score >= :min_score
            ");
            $stmt->execute([
                'user_id' => $userId,
                'min_score' => $minScore
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] >= $requiredCount;
        }
    }

    private function checkQuizFirstAttempt(int $userId, array $criteria, array $eventData): bool
    {
        $minScore = $criteria['min_score'] ?? 70;
        $quizId = $eventData['quiz_id'] ?? null;

        if (!$quizId) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            SELECT score, attempt_number
            FROM quiz_attempts
            WHERE user_id = :user_id AND quiz_id = :quiz_id
            ORDER BY attempt_number ASC
            LIMIT 1
        ");
        $stmt->execute([
            'user_id' => $userId,
            'quiz_id' => $quizId
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result && $result['attempt_number'] == 1 && $result['score'] >= $minScore;
    }

    private function checkQuizAttempts(int $userId, array $criteria): bool
    {
        $requiredCount = $criteria['count'] ?? 10;
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count
            FROM quiz_attempts
            WHERE user_id = :user_id
        ");
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] >= $requiredCount;
    }

    private function checkNotesCreated(int $userId, array $criteria): bool
    {
        $requiredCount = $criteria['count'] ?? 5;
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count
            FROM student_notes
            WHERE user_id = :user_id
        ");
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] >= $requiredCount;
    }

    private function checkBookmarksCreated(int $userId, array $criteria): bool
    {
        $requiredCount = $criteria['count'] ?? 10;
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count
            FROM bookmarks
            WHERE user_id = :user_id
        ");
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] >= $requiredCount;
    }

    private function checkConsecutiveLogins(int $userId, array $criteria): bool
    {
        // This would require a login_history table
        // For now, return false as placeholder
        return false;
    }

    private function checkTotalPoints(int $userId, array $criteria): bool
    {
        $minPoints = $criteria['min_points'] ?? 1000;
        $stmt = $this->pdo->prepare("
            SELECT total_points
            FROM user_achievement_points
            WHERE user_id = :user_id
        ");
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result && $result['total_points'] >= $minPoints;
    }

    /**
     * Get leaderboard (top users by achievement points)
     *
     * @param int $limit Number of users to return
     * @return array
     */
    public function getLeaderboard(int $limit = 10): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    uap.*,
                    u.name as user_name,
                    u.email as user_email
                FROM user_achievement_points uap
                JOIN users u ON uap.user_id = u.id
                ORDER BY uap.total_points DESC, uap.achievements_count DESC
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Database error in getLeaderboard: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get user's achievement points summary
     *
     * @param int $userId User ID
     * @return array|null
     */
    public function getUserPoints(int $userId): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM user_achievement_points
                WHERE user_id = :user_id
            ");
            $stmt->execute(['user_id' => $userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ?: null;
        } catch (\PDOException $e) {
            error_log("Database error in getUserPoints: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get achievement categories
     *
     * @return array
     */
    public function getCategories(): array
    {
        try {
            $stmt = $this->pdo->query("
                SELECT * FROM achievement_categories
                ORDER BY display_order
            ");

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Database error in getCategories: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get user progress towards an achievement
     *
     * @param int $userId User ID
     * @param int $achievementId Achievement ID
     * @return array Progress data
     */
    public function getUserProgress(int $userId, int $achievementId): array
    {
        try {
            // Get achievement details
            $achievement = $this->find($achievementId);
            if (!$achievement) {
                return ['progress' => 0, 'total' => 100, 'percentage' => 0];
            }

            $criteria = json_decode($achievement->unlock_criteria, true);
            $type = $criteria['type'] ?? '';

            // Calculate progress based on type
            $progress = 0;
            $total = 1;

            switch ($type) {
                case 'lesson_completion':
                    $total = $criteria['count'] ?? 1;
                    $stmt = $this->pdo->prepare("
                        SELECT COUNT(DISTINCT lesson_id) as count
                        FROM lesson_progress
                        WHERE user_id = :user_id AND completion_percentage >= 100
                    ");
                    $stmt->execute(['user_id' => $userId]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $progress = $result['count'];
                    break;

                case 'quiz_score':
                    $total = $criteria['count'] ?? 1;
                    $minScore = $criteria['min_score'] ?? 70;
                    $stmt = $this->pdo->prepare("
                        SELECT COUNT(*) as count
                        FROM quiz_attempts
                        WHERE user_id = :user_id AND score >= :min_score
                    ");
                    $stmt->execute(['user_id' => $userId, 'min_score' => $minScore]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $progress = $result['count'];
                    break;

                case 'notes_created':
                    $total = $criteria['count'] ?? 5;
                    $stmt = $this->pdo->prepare("
                        SELECT COUNT(*) as count FROM student_notes WHERE user_id = :user_id
                    ");
                    $stmt->execute(['user_id' => $userId]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $progress = $result['count'];
                    break;

                case 'bookmarks_created':
                    $total = $criteria['count'] ?? 10;
                    $stmt = $this->pdo->prepare("
                        SELECT COUNT(*) as count FROM bookmarks WHERE user_id = :user_id
                    ");
                    $stmt->execute(['user_id' => $userId]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $progress = $result['count'];
                    break;
            }

            $percentage = $total > 0 ? min(100, ($progress / $total) * 100) : 0;

            return [
                'progress' => $progress,
                'total' => $total,
                'percentage' => round($percentage, 2),
                'unlocked' => $this->hasUnlocked($userId, $achievementId)
            ];
        } catch (\PDOException $e) {
            error_log("Database error in getUserProgress: " . $e->getMessage());
            return ['progress' => 0, 'total' => 100, 'percentage' => 0];
        }
    }
}
