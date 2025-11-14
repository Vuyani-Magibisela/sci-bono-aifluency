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
}
