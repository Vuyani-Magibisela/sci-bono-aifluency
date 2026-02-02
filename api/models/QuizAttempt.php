<?php
namespace App\Models;

use PDO;

/**
 * QuizAttempt Model
 *
 * Handles quiz attempt-related database operations
 */
class QuizAttempt extends BaseModel
{
    protected string $table = 'quiz_attempts';
    protected array $fillable = [
        'quiz_id',
        'user_id',
        'score',
        'answers',
        'time_taken_minutes',
        'passed',
        'completed_at',
        'attempt_number',
        'time_started',
        'time_completed',
        'time_spent_seconds',
        'ip_address',
        'user_agent',
        'instructor_score',
        'instructor_feedback',
        'graded_by',
        'graded_at',
        'status',
        'total_questions',
        'correct_answers'
    ];
    protected array $hidden = [];

    /**
     * Get attempts by user
     *
     * @param int $userId User ID
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array
     */
    public function getByUser(int $userId, ?int $limit = null, ?int $offset = null): array
    {
        $attempts = $this->all(['user_id' => $userId], 'created_at DESC', $limit, $offset);

        // Parse JSON answers for each attempt
        foreach ($attempts as $attempt) {
            if (is_string($attempt->answers)) {
                $attempt->answers = json_decode($attempt->answers, true);
            }
        }

        return $attempts;
    }

    /**
     * Get attempts by quiz
     *
     * @param int $quizId Quiz ID
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array
     */
    public function getByQuiz(int $quizId, ?int $limit = null, ?int $offset = null): array
    {
        $attempts = $this->all(['quiz_id' => $quizId], 'created_at DESC', $limit, $offset);

        // Parse JSON answers for each attempt
        foreach ($attempts as $attempt) {
            if (is_string($attempt->answers)) {
                $attempt->answers = json_decode($attempt->answers, true);
            }
        }

        return $attempts;
    }

    /**
     * Get user's attempts for specific quiz
     *
     * @param int $userId User ID
     * @param int $quizId Quiz ID
     * @return array
     */
    public function getUserQuizAttempts(int $userId, int $quizId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM {$this->table}
                WHERE user_id = :user_id AND quiz_id = :quiz_id
                ORDER BY created_at DESC
            ");
            $stmt->execute([
                'user_id' => $userId,
                'quiz_id' => $quizId
            ]);
            $attempts = $stmt->fetchAll(PDO::FETCH_OBJ);

            // Parse JSON answers
            foreach ($attempts as $attempt) {
                if (is_string($attempt->answers)) {
                    $attempt->answers = json_decode($attempt->answers, true);
                }
            }

            return $attempts;
        } catch (\PDOException $e) {
            error_log("Database error in getUserQuizAttempts: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get user's best attempt for a quiz
     *
     * @param int $userId User ID
     * @param int $quizId Quiz ID
     * @return object|null
     */
    public function getUserBestAttempt(int $userId, int $quizId): ?object
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM {$this->table}
                WHERE user_id = :user_id AND quiz_id = :quiz_id
                ORDER BY score DESC, created_at DESC
                LIMIT 1
            ");
            $stmt->execute([
                'user_id' => $userId,
                'quiz_id' => $quizId
            ]);
            $attempt = $stmt->fetch(PDO::FETCH_OBJ);

            if ($attempt && is_string($attempt->answers)) {
                $attempt->answers = json_decode($attempt->answers, true);
            }

            return $attempt ?: null;
        } catch (\PDOException $e) {
            error_log("Database error in getUserBestAttempt: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create quiz attempt with JSON answers
     *
     * @param array $data Attempt data
     * @return int|null Attempt ID
     */
    public function createAttempt(array $data): ?int
    {
        // Encode answers as JSON if it's an array
        if (isset($data['answers']) && is_array($data['answers'])) {
            $data['answers'] = json_encode($data['answers']);
        }

        // Set completed_at to now if not provided
        if (!isset($data['completed_at'])) {
            $data['completed_at'] = date('Y-m-d H:i:s');
        }

        return $this->create($data);
    }

    /**
     * Get attempt with quiz and user details
     *
     * @param int $attemptId Attempt ID
     * @return object|null
     */
    public function getAttemptWithDetails(int $attemptId): ?object
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    qa.*,
                    q.title as quiz_title,
                    q.passing_score,
                    u.name as user_name,
                    u.email as user_email
                FROM {$this->table} qa
                JOIN quizzes q ON qa.quiz_id = q.id
                JOIN users u ON qa.user_id = u.id
                WHERE qa.id = :attempt_id
                LIMIT 1
            ");
            $stmt->execute(['attempt_id' => $attemptId]);
            $attempt = $stmt->fetch(PDO::FETCH_OBJ);

            if ($attempt && is_string($attempt->answers)) {
                $attempt->answers = json_decode($attempt->answers, true);
            }

            return $attempt ?: null;
        } catch (\PDOException $e) {
            error_log("Database error in getAttemptWithDetails: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Count user's attempts for a quiz
     *
     * @param int $userId User ID
     * @param int $quizId Quiz ID
     * @return int
     */
    public function countUserAttempts(int $userId, int $quizId): int
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as total
                FROM {$this->table}
                WHERE user_id = :user_id AND quiz_id = :quiz_id
            ");
            $stmt->execute([
                'user_id' => $userId,
                'quiz_id' => $quizId
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return (int) $result['total'];
        } catch (\PDOException $e) {
            error_log("Database error in countUserAttempts: " . $e->getMessage());
            return 0;
        }
    }

    // ========== PHASE 6: ENHANCED QUIZ TRACKING & GRADING ==========

    /**
     * Start a new quiz attempt
     *
     * @param int $userId User ID
     * @param int $quizId Quiz ID
     * @param string|null $ipAddress User's IP address
     * @param string|null $userAgent User's browser user agent
     * @return int|null Attempt ID
     */
    public function startAttempt(int $userId, int $quizId, ?string $ipAddress = null, ?string $userAgent = null): ?int
    {
        try {
            // Get next attempt number
            $attemptNumber = $this->countUserAttempts($userId, $quizId) + 1;

            $data = [
                'user_id' => $userId,
                'quiz_id' => $quizId,
                'attempt_number' => $attemptNumber,
                'time_started' => date('Y-m-d H:i:s'),
                'status' => 'in_progress',
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'score' => 0,
                'total_questions' => 0,
                'correct_answers' => 0,
                'passed' => 0
            ];

            return $this->create($data);
        } catch (\PDOException $e) {
            error_log("Database error in startAttempt: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Submit and finalize quiz attempt
     *
     * @param int $attemptId Attempt ID
     * @param array $answers User's answers
     * @param float $score Final score
     * @param int $totalQuestions Total number of questions
     * @param int $correctAnswers Number of correct answers
     * @param bool $passed Whether the user passed
     * @return bool Success status
     */
    public function submitAttempt(
        int $attemptId,
        array $answers,
        float $score,
        int $totalQuestions,
        int $correctAnswers,
        bool $passed
    ): bool {
        try {
            $attempt = $this->find($attemptId);
            if (!$attempt) {
                return false;
            }

            // Calculate time spent
            $timeSpent = null;
            if ($attempt->time_started) {
                $start = new \DateTime($attempt->time_started);
                $end = new \DateTime();
                $timeSpent = $end->getTimestamp() - $start->getTimestamp();
            }

            $updateData = [
                'answers' => json_encode($answers),
                'score' => $score,
                'total_questions' => $totalQuestions,
                'correct_answers' => $correctAnswers,
                'passed' => $passed ? 1 : 0,
                'time_completed' => date('Y-m-d H:i:s'),
                'time_spent_seconds' => $timeSpent,
                'status' => 'submitted'
            ];

            return $this->update($attemptId, $updateData);
        } catch (\PDOException $e) {
            error_log("Database error in submitAttempt: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Grade attempt by instructor (override automatic score)
     *
     * @param int $attemptId Attempt ID
     * @param int $instructorId Instructor user ID
     * @param float $score New score
     * @param string|null $feedback Instructor feedback
     * @return bool Success status
     */
    public function gradeAttempt(
        int $attemptId,
        int $instructorId,
        float $score,
        ?string $feedback = null
    ): bool {
        try {
            $updateData = [
                'instructor_score' => $score,
                'instructor_feedback' => $feedback,
                'graded_by' => $instructorId,
                'graded_at' => date('Y-m-d H:i:s'),
                'status' => 'graded'
            ];

            return $this->update($attemptId, $updateData);
        } catch (\PDOException $e) {
            error_log("Database error in gradeAttempt: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all attempts pending instructor grading
     *
     * @param int|null $quizId Optional filter by quiz
     * @return array
     */
    public function getPendingGradingAttempts(?int $quizId = null): array
    {
        try {
            $sql = "
                SELECT
                    qa.*,
                    q.title as quiz_title,
                    q.passing_score,
                    u.name as user_name,
                    u.email as user_email
                FROM {$this->table} qa
                JOIN quizzes q ON qa.quiz_id = q.id
                JOIN users u ON qa.user_id = u.id
                WHERE qa.status IN ('submitted', 'reviewed')
                AND qa.instructor_score IS NULL
            ";

            if ($quizId) {
                $sql .= " AND qa.quiz_id = :quiz_id";
            }

            $sql .= " ORDER BY qa.submitted_at DESC";

            $stmt = $this->pdo->prepare($sql);

            if ($quizId) {
                $stmt->execute(['quiz_id' => $quizId]);
            } else {
                $stmt->execute();
            }

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\PDOException $e) {
            error_log("Database error in getPendingGradingAttempts: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get detailed analytics for a quiz
     *
     * @param int $quizId Quiz ID
     * @return array Analytics data
     */
    public function getQuizAnalytics(int $quizId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(DISTINCT user_id) as unique_students,
                    COUNT(*) as total_attempts,
                    AVG(score) as average_score,
                    MAX(score) as highest_score,
                    MIN(score) as lowest_score,
                    AVG(time_spent_seconds) as avg_time_seconds,
                    SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) as total_passed,
                    SUM(CASE WHEN passed = 0 THEN 1 ELSE 0 END) as total_failed,
                    AVG(attempt_number) as avg_attempts_per_user
                FROM {$this->table}
                WHERE quiz_id = :quiz_id AND status IN ('submitted', 'graded', 'reviewed')
            ");
            $stmt->execute(['quiz_id' => $quizId]);
            $analytics = $stmt->fetch(PDO::FETCH_ASSOC);

            // Calculate pass rate
            $totalAttempts = $analytics['total_attempts'] ?? 0;
            $analytics['pass_rate'] = $totalAttempts > 0
                ? ($analytics['total_passed'] / $totalAttempts) * 100
                : 0;

            return $analytics;
        } catch (\PDOException $e) {
            error_log("Database error in getQuizAnalytics: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get student performance summary across all quizzes
     *
     * @param int $userId User ID
     * @return array Performance summary
     */
    public function getStudentPerformanceSummary(int $userId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(DISTINCT quiz_id) as quizzes_taken,
                    COUNT(*) as total_attempts,
                    AVG(score) as average_score,
                    MAX(score) as highest_score,
                    SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) as quizzes_passed,
                    SUM(CASE WHEN passed = 0 THEN 1 ELSE 0 END) as quizzes_failed,
                    AVG(time_spent_seconds) as avg_time_per_quiz,
                    SUM(CASE WHEN score = 100 THEN 1 ELSE 0 END) as perfect_scores
                FROM {$this->table}
                WHERE user_id = :user_id AND status IN ('submitted', 'graded', 'reviewed')
            ");
            $stmt->execute(['user_id' => $userId]);
            $summary = $stmt->fetch(PDO::FETCH_ASSOC);

            // Calculate additional metrics
            $quizzesTaken = $summary['quizzes_taken'] ?? 0;
            $summary['pass_rate'] = $quizzesTaken > 0
                ? ($summary['quizzes_passed'] / $quizzesTaken) * 100
                : 0;

            return $summary;
        } catch (\PDOException $e) {
            error_log("Database error in getStudentPerformanceSummary: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get attempts by status
     *
     * @param string $status Status filter
     * @param int|null $limit Optional limit
     * @return array
     */
    public function getByStatus(string $status, ?int $limit = null): array
    {
        try {
            $sql = "
                SELECT
                    qa.*,
                    q.title as quiz_title,
                    u.name as user_name
                FROM {$this->table} qa
                JOIN quizzes q ON qa.quiz_id = q.id
                JOIN users u ON qa.user_id = u.id
                WHERE qa.status = :status
                ORDER BY qa.time_started DESC
            ";

            if ($limit) {
                $sql .= " LIMIT :limit";
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':status', $status, PDO::PARAM_STR);

            if ($limit) {
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\PDOException $e) {
            error_log("Database error in getByStatus: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get effective score (instructor override takes precedence)
     *
     * @param object $attempt Attempt object
     * @return float Effective score
     */
    public function getEffectiveScore(object $attempt): float
    {
        return $attempt->instructor_score ?? $attempt->score ?? 0.0;
    }

    /**
     * Get performance trends for a user on a specific quiz
     * Phase 6 - Task 2: Performance Trends Analysis
     *
     * @param int $userId User ID
     * @param int $quizId Quiz ID
     * @return array Trend data including attempts, trend direction, improvement metrics
     */
    public static function getPerformanceTrends(int $userId, int $quizId): array
    {
        global $pdo;

        // Get all attempts ordered by date
        $stmt = $pdo->prepare("
            SELECT
                id,
                score,
                time_completed,
                time_spent_seconds,
                passed
            FROM quiz_attempts
            WHERE user_id = ? AND quiz_id = ? AND status IN ('submitted', 'graded')
            ORDER BY time_completed ASC
        ");

        $stmt->execute([$userId, $quizId]);
        $attempts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($attempts)) {
            return [
                'attempts' => [],
                'trend' => 'no_data',
                'avg_improvement' => 0,
                'best_score' => 0,
                'latest_score' => 0
            ];
        }

        // Add attempt numbers
        foreach ($attempts as $index => &$attempt) {
            $attempt['attempt_number'] = $index + 1;
        }

        // Calculate trend
        $scores = array_column($attempts, 'score');
        $trend = self::calculateTrend($scores);
        $avgImprovement = self::calculateAverageImprovement($scores);

        return [
            'attempts' => $attempts,
            'trend' => $trend,
            'avg_improvement' => $avgImprovement,
            'best_score' => max($scores),
            'latest_score' => end($scores)
        ];
    }

    /**
     * Calculate trend direction from scores
     * Phase 6 - Task 2: Performance Trends Analysis
     *
     * @param array $scores Array of scores
     * @return string Trend direction: improving, declining, stable, or insufficient_data
     */
    private static function calculateTrend(array $scores): string
    {
        if (count($scores) < 2) return 'insufficient_data';

        $improvements = 0;
        $declines = 0;

        for ($i = 1; $i < count($scores); $i++) {
            if ($scores[$i] > $scores[$i-1]) $improvements++;
            elseif ($scores[$i] < $scores[$i-1]) $declines++;
        }

        if ($improvements > $declines) return 'improving';
        elseif ($declines > $improvements) return 'declining';
        else return 'stable';
    }

    /**
     * Calculate average score improvement per attempt
     * Phase 6 - Task 2: Performance Trends Analysis
     *
     * @param array $scores Array of scores
     * @return float Average improvement
     */
    private static function calculateAverageImprovement(array $scores): float
    {
        if (count($scores) < 2) return 0;

        $totalChange = end($scores) - $scores[0];
        $numIntervals = count($scores) - 1;

        return round($totalChange / $numIntervals, 2);
    }

    /**
     * Get performance trends across ALL quizzes for a user
     * Phase 6 - Task 2: Performance Trends Analysis
     *
     * @param int $userId User ID
     * @return array Learning curve data
     */
    public static function getUserLearningCurve(int $userId): array
    {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT
                qa.quiz_id,
                q.title as quiz_title,
                qa.score,
                qa.time_completed,
                m.title as module_title
            FROM quiz_attempts qa
            JOIN quizzes q ON qa.quiz_id = q.id
            JOIN modules m ON q.module_id = m.id
            WHERE qa.user_id = ? AND qa.status IN ('submitted', 'graded')
            ORDER BY qa.time_completed ASC
        ");

        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get class comparison data for a user's quiz attempt
     * Phase 6 - Task 3: Class Comparisons & Peer Analysis
     *
     * @param int $userId User ID
     * @param int $quizId Quiz ID
     * @return array Class statistics and user's rank/percentile
     */
    public static function getClassComparison(int $userId, int $quizId): array
    {
        global $pdo;

        // Get user's best score
        $stmt = $pdo->prepare("
            SELECT MAX(score) as best_score
            FROM quiz_attempts
            WHERE user_id = ? AND quiz_id = ? AND status IN ('submitted', 'graded')
        ");
        $stmt->execute([$userId, $quizId]);
        $userScore = $stmt->fetchColumn() ?? 0;

        // Get class statistics
        $stmt = $pdo->prepare("
            SELECT
                AVG(best_scores.score) as class_average,
                MIN(best_scores.score) as class_min,
                MAX(best_scores.score) as class_max,
                COUNT(DISTINCT best_scores.user_id) as total_students
            FROM (
                SELECT user_id, MAX(score) as score
                FROM quiz_attempts
                WHERE quiz_id = ? AND status IN ('submitted', 'graded')
                GROUP BY user_id
            ) as best_scores
        ");
        $stmt->execute([$quizId]);
        $classStats = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Get median score
        $stmt = $pdo->prepare("
            SELECT score
            FROM (
                SELECT user_id, MAX(score) as score
                FROM quiz_attempts
                WHERE quiz_id = ? AND status IN ('submitted', 'graded')
                GROUP BY user_id
            ) as best_scores
            ORDER BY score
        ");
        $stmt->execute([$quizId]);
        $scores = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        $median = self::calculateMedian($scores);

        // Calculate user's rank and percentile
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as better_count
            FROM (
                SELECT user_id, MAX(score) as score
                FROM quiz_attempts
                WHERE quiz_id = ? AND status IN ('submitted', 'graded')
                GROUP BY user_id
            ) as best_scores
            WHERE score > ?
        ");
        $stmt->execute([$quizId, $userScore]);
        $betterCount = $stmt->fetchColumn();

        $rank = $betterCount + 1;
        $totalStudents = (int)$classStats['total_students'];
        $percentile = $totalStudents > 0 ? round((($totalStudents - $rank) / $totalStudents) * 100, 2) : 0;
        $betterThanPercentage = $totalStudents > 1 ? round((($totalStudents - $rank) / ($totalStudents - 1)) * 100, 2) : 0;

        return [
            'user_best_score' => $userScore,
            'class_average' => round($classStats['class_average'], 2),
            'class_median' => $median,
            'class_min' => (int)$classStats['class_min'],
            'class_max' => (int)$classStats['class_max'],
            'percentile' => $percentile,
            'rank' => $rank,
            'total_students' => $totalStudents,
            'better_than_percentage' => $betterThanPercentage
        ];
    }

    /**
     * Get leaderboard for a quiz
     * Phase 6 - Task 3: Class Comparisons & Peer Analysis
     *
     * @param int $quizId Quiz ID
     * @param int $limit Number of top students to return
     * @return array Leaderboard data
     */
    public static function getQuizLeaderboard(int $quizId, int $limit = 10): array
    {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT
                u.id as user_id,
                u.name,
                u.profile_picture_url,
                best_scores.score,
                best_scores.time_completed
            FROM (
                SELECT
                    user_id,
                    MAX(score) as score,
                    MIN(time_completed) as time_completed
                FROM quiz_attempts
                WHERE quiz_id = ? AND status IN ('submitted', 'graded')
                GROUP BY user_id
            ) as best_scores
            JOIN users u ON best_scores.user_id = u.id
            ORDER BY best_scores.score DESC, best_scores.time_completed ASC
            LIMIT ?
        ");

        $stmt->execute([$quizId, $limit]);
        $leaderboard = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Add rank
        foreach ($leaderboard as $index => &$entry) {
            $entry['rank'] = $index + 1;
        }

        return $leaderboard;
    }

    /**
     * Calculate median from array of scores
     * Phase 6 - Task 3: Class Comparisons & Peer Analysis
     *
     * @param array $scores Array of scores
     * @return float Median score
     */
    private static function calculateMedian(array $scores): float
    {
        if (empty($scores)) return 0;

        sort($scores);
        $count = count($scores);
        $middle = floor($count / 2);

        if ($count % 2 == 0) {
            return ($scores[$middle - 1] + $scores[$middle]) / 2;
        } else {
            return $scores[$middle];
        }
    }

    // ================================================================
    // PHASE 10: ADVANCED ANALYTICS METHODS
    // ================================================================

    /**
     * Get learning velocity (pace of progress over time)
     * Phase 10: Advanced Analytics Dashboard
     *
     * @param int $userId User ID
     * @param array $options Date range options
     * @return array Learning velocity data
     */
    public function getLearningVelocity(int $userId, array $options = []): array
    {
        global $pdo;

        $range = $options['range'] ?? '30';
        $startDate = $options['start_date'] ?? null;
        $endDate = $options['end_date'] ?? null;

        // Build date filter
        $dateFilter = '';
        $params = ['user_id' => $userId];

        if ($startDate && $endDate) {
            $dateFilter = "AND DATE(time_completed) BETWEEN :start_date AND :end_date";
            $params['start_date'] = $startDate;
            $params['end_date'] = $endDate;
        } elseif ($range !== 'all') {
            $dateFilter = "AND time_completed >= DATE_SUB(NOW(), INTERVAL :days DAY)";
            $params['days'] = (int)$range;
        }

        // Get quiz attempts over time
        $sql = "SELECT
                DATE(time_completed) as completion_date,
                COUNT(*) as attempts_count,
                AVG(score) as avg_score,
                SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) as passed_count,
                AVG(time_spent_seconds / 60) as avg_time_minutes
            FROM quiz_attempts
            WHERE user_id = :user_id
            AND time_completed IS NOT NULL
            $dateFilter
            GROUP BY DATE(time_completed)
            ORDER BY completion_date ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $velocityData = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Calculate velocity metrics
        $totalAttempts = array_sum(array_column($velocityData, 'attempts_count'));
        $daysActive = count($velocityData);
        $avgAttemptsPerDay = $daysActive > 0 ? round($totalAttempts / $daysActive, 2) : 0;

        // Calculate trend (improving/declining/stable)
        $trend = 'stable';
        if (count($velocityData) >= 2) {
            $firstHalf = array_slice($velocityData, 0, ceil(count($velocityData) / 2));
            $secondHalf = array_slice($velocityData, floor(count($velocityData) / 2));

            $firstHalfAvg = array_sum(array_column($firstHalf, 'avg_score')) / max(count($firstHalf), 1);
            $secondHalfAvg = array_sum(array_column($secondHalf, 'avg_score')) / max(count($secondHalf), 1);

            if ($secondHalfAvg > $firstHalfAvg + 5) {
                $trend = 'improving';
            } elseif ($secondHalfAvg < $firstHalfAvg - 5) {
                $trend = 'declining';
            }
        }

        return [
            'velocity_data' => $velocityData,
            'total_attempts' => $totalAttempts,
            'days_active' => $daysActive,
            'avg_attempts_per_day' => $avgAttemptsPerDay,
            'trend' => $trend
        ];
    }

    /**
     * Get struggle indicators (failed attempts, excessive time, etc.)
     * Phase 10: Advanced Analytics Dashboard
     *
     * @param int $userId User ID
     * @param int|null $quizId Optional quiz ID filter
     * @return array Struggle indicators
     */
    public function getStruggleIndicators(int $userId, ?int $quizId = null): array
    {
        global $pdo;

        $sql = "SELECT
                qa.quiz_id,
                q.title as quiz_title,
                COUNT(qa.id) as total_attempts,
                SUM(CASE WHEN qa.passed = 0 THEN 1 ELSE 0 END) as failed_attempts,
                AVG(qa.score) as avg_score,
                MAX(qa.score) as best_score,
                AVG(qa.time_spent_seconds / 60) as avg_time_minutes,
                MAX(qa.time_spent_seconds / 60) as max_time_minutes,
                -- Struggle score: higher = more struggle
                ROUND((
                    (SUM(CASE WHEN qa.passed = 0 THEN 1 ELSE 0 END) / COUNT(qa.id)) * 40 +
                    ((100 - AVG(qa.score)) / 100) * 30 +
                    (CASE
                        WHEN AVG(qa.time_spent_seconds / 60) > q.time_limit * 1.5 THEN 30
                        WHEN AVG(qa.time_spent_seconds / 60) > q.time_limit THEN 20
                        ELSE 10
                    END)
                ), 2) as struggle_score
            FROM quiz_attempts qa
            INNER JOIN quizzes q ON qa.quiz_id = q.id
            WHERE qa.user_id = :user_id";

        $params = ['user_id' => $userId];

        if ($quizId) {
            $sql .= " AND qa.quiz_id = :quiz_id";
            $params['quiz_id'] = $quizId;
        }

        $sql .= " GROUP BY qa.quiz_id, q.title, q.time_limit
                  ORDER BY struggle_score DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $struggles = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Identify top struggles (struggle_score >= 60)
        $topStruggles = array_filter($struggles, function($s) {
            return $s['struggle_score'] >= 60;
        });

        return [
            'struggles' => $struggles,
            'top_struggles' => array_values($topStruggles),
            'total_quizzes_with_attempts' => count($struggles),
            'quizzes_with_high_struggle' => count($topStruggles)
        ];
    }
}
