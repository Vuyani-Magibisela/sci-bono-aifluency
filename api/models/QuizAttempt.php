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
        'completed_at'
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
}
