<?php
namespace App\Models;

use PDO;

/**
 * Quiz Model
 *
 * Handles quiz-related database operations
 */
class Quiz extends BaseModel
{
    protected string $table = 'quizzes';
    protected array $fillable = [
        'module_id',
        'lesson_id',
        'title',
        'slug',
        'description',
        'passing_score',
        'time_limit_minutes',
        'max_attempts',
        'is_published'
    ];
    protected array $hidden = [];

    /**
     * Get quizzes by module
     *
     * @param int $moduleId Module ID
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array
     */
    public function getByModule(int $moduleId, ?int $limit = null, ?int $offset = null): array
    {
        return $this->all(['module_id' => $moduleId], 'title ASC', $limit, $offset);
    }

    /**
     * Get quiz by lesson
     *
     * @param int $lessonId Lesson ID
     * @return object|null
     */
    public function getByLesson(int $lessonId): ?object
    {
        return $this->findBy('lesson_id', $lessonId);
    }

    /**
     * Get published quizzes by module
     *
     * @param int $moduleId Module ID
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array
     */
    public function getPublishedByModule(int $moduleId, ?int $limit = null, ?int $offset = null): array
    {
        return $this->all(['module_id' => $moduleId, 'is_published' => true], 'title ASC', $limit, $offset);
    }

    /**
     * Get quiz by slug
     *
     * @param string $slug Quiz slug
     * @param int $moduleId Module ID
     * @return object|null
     */
    public function findBySlug(string $slug, int $moduleId): ?object
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM {$this->table}
                WHERE slug = :slug AND module_id = :module_id
                LIMIT 1
            ");
            $stmt->execute(['slug' => $slug, 'module_id' => $moduleId]);
            return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
        } catch (\PDOException $e) {
            error_log("Database error in findBySlug: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get quiz with questions
     *
     * @param int $quizId Quiz ID
     * @param bool $includeAnswers Include correct answers (for instructors/admins)
     * @return object|null Quiz with questions array
     */
    public function getQuizWithQuestions(int $quizId, bool $includeAnswers = false): ?object
    {
        try {
            $quiz = $this->find($quizId);

            if (!$quiz) {
                return null;
            }

            // Get questions for this quiz
            $questionsStmt = $this->pdo->prepare("
                SELECT * FROM quiz_questions
                WHERE quiz_id = :quiz_id
                ORDER BY `order` ASC
            ");
            $questionsStmt->execute(['quiz_id' => $quizId]);
            $questions = $questionsStmt->fetchAll(PDO::FETCH_OBJ);

            // Process questions
            foreach ($questions as $question) {
                // Parse options JSON
                $question->options = json_decode($question->options, true);

                // Remove correct answer if not authorized
                if (!$includeAnswers) {
                    unset($question->correct_answer);
                    unset($question->explanation);
                }
            }

            $quiz->questions = $questions;

            return $quiz;
        } catch (\PDOException $e) {
            error_log("Database error in getQuizWithQuestions: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get user's attempts for a quiz
     *
     * @param int $quizId Quiz ID
     * @param int $userId User ID
     * @return array Quiz attempts
     */
    public function getUserAttempts(int $quizId, int $userId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM quiz_attempts
                WHERE quiz_id = :quiz_id AND user_id = :user_id
                ORDER BY created_at DESC
            ");
            $stmt->execute([
                'quiz_id' => $quizId,
                'user_id' => $userId
            ]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\PDOException $e) {
            error_log("Database error in getUserAttempts: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get user's best score for a quiz
     *
     * @param int $quizId Quiz ID
     * @param int $userId User ID
     * @return float|null Best score or null if no attempts
     */
    public function getUserBestScore(int $quizId, int $userId): ?float
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT MAX(score) as best_score
                FROM quiz_attempts
                WHERE quiz_id = :quiz_id AND user_id = :user_id
            ");
            $stmt->execute([
                'quiz_id' => $quizId,
                'user_id' => $userId
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result['best_score'] !== null ? (float) $result['best_score'] : null;
        } catch (\PDOException $e) {
            error_log("Database error in getUserBestScore: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if user can attempt quiz
     *
     * @param int $quizId Quiz ID
     * @param int $userId User ID
     * @return bool True if user can attempt
     */
    public function canUserAttempt(int $quizId, int $userId): bool
    {
        try {
            $quiz = $this->find($quizId);

            if (!$quiz || !$quiz->is_published) {
                return false;
            }

            // Check max attempts
            if ($quiz->max_attempts !== null && $quiz->max_attempts > 0) {
                $attemptsStmt = $this->pdo->prepare("
                    SELECT COUNT(*) as total
                    FROM quiz_attempts
                    WHERE quiz_id = :quiz_id AND user_id = :user_id
                ");
                $attemptsStmt->execute([
                    'quiz_id' => $quizId,
                    'user_id' => $userId
                ]);
                $attempts = $attemptsStmt->fetch(PDO::FETCH_ASSOC);

                if ($attempts['total'] >= $quiz->max_attempts) {
                    return false;
                }
            }

            return true;
        } catch (\PDOException $e) {
            error_log("Database error in canUserAttempt: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get quiz statistics
     *
     * @param int $quizId Quiz ID
     * @return array|null Quiz statistics
     */
    public function getQuizStats(int $quizId): ?array
    {
        try {
            // Get total attempts
            $attemptsStmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) as total_attempts,
                    COUNT(DISTINCT user_id) as unique_students,
                    AVG(score) as average_score,
                    MAX(score) as highest_score,
                    MIN(score) as lowest_score
                FROM quiz_attempts
                WHERE quiz_id = :quiz_id
            ");
            $attemptsStmt->execute(['quiz_id' => $quizId]);
            $stats = $attemptsStmt->fetch(PDO::FETCH_ASSOC);

            // Get passing rate
            $quiz = $this->find($quizId);
            $passingScore = $quiz->passing_score ?? 70;

            $passingStmt = $this->pdo->prepare("
                SELECT
                    COUNT(DISTINCT user_id) as passed_students
                FROM quiz_attempts
                WHERE quiz_id = :quiz_id AND score >= :passing_score
            ");
            $passingStmt->execute([
                'quiz_id' => $quizId,
                'passing_score' => $passingScore
            ]);
            $passing = $passingStmt->fetch(PDO::FETCH_ASSOC);

            $passingRate = $stats['unique_students'] > 0
                ? round(($passing['passed_students'] / $stats['unique_students']) * 100, 2)
                : 0;

            return [
                'total_attempts' => (int) $stats['total_attempts'],
                'unique_students' => (int) $stats['unique_students'],
                'average_score' => round((float) ($stats['average_score'] ?? 0), 2),
                'highest_score' => round((float) ($stats['highest_score'] ?? 0), 2),
                'lowest_score' => round((float) ($stats['lowest_score'] ?? 0), 2),
                'passed_students' => (int) $passing['passed_students'],
                'passing_rate' => $passingRate
            ];
        } catch (\PDOException $e) {
            error_log("Database error in getQuizStats: " . $e->getMessage());
            return null;
        }
    }
}
