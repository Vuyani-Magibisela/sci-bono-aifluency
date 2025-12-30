<?php
namespace App\Models;

use PDO;

/**
 * QuizQuestion Model
 *
 * Handles quiz question-related database operations
 */
class QuizQuestion extends BaseModel
{
    protected string $table = 'quiz_questions';
    protected array $fillable = [
        'quiz_id',
        'question_text',
        'question_type',
        'options',
        'correct_answer',
        'explanation',
        'points',
        'order_index'
    ];
    protected array $hidden = [];

    /**
     * Get questions by quiz
     *
     * @param int $quizId Quiz ID
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array
     */
    public function getByQuiz(int $quizId, ?int $limit = null, ?int $offset = null): array
    {
        $questions = $this->all(['quiz_id' => $quizId], 'order_index ASC', $limit, $offset);

        // Parse JSON options for each question
        foreach ($questions as $question) {
            if (is_string($question->options)) {
                $question->options = json_decode($question->options, true);
            }
        }

        return $questions;
    }

    /**
     * Get questions for student (without answers)
     *
     * @param int $quizId Quiz ID
     * @return array
     */
    public function getQuestionsForStudent(int $quizId): array
    {
        $questions = $this->getByQuiz($quizId);

        // Remove correct answers and explanations
        foreach ($questions as $question) {
            unset($question->correct_answer);
            unset($question->explanation);
        }

        return $questions;
    }

    /**
     * Validate student answers
     *
     * @param int $quizId Quiz ID
     * @param array $studentAnswers Array of question_id => answer
     * @return array Validation results with score
     */
    public function validateAnswers(int $quizId, array $studentAnswers): array
    {
        try {
            $questions = $this->getByQuiz($quizId);

            if (empty($questions)) {
                return [
                    'valid' => false,
                    'error' => 'No questions found for this quiz'
                ];
            }

            $totalPoints = 0;
            $earnedPoints = 0;
            $results = [];

            foreach ($questions as $question) {
                $totalPoints += $question->points;

                $studentAnswer = $studentAnswers[$question->id] ?? null;
                $isCorrect = false;

                // Check if answer is correct based on question type
                if ($question->question_type === 'multiple_choice') {
                    $isCorrect = ($studentAnswer === $question->correct_answer);
                } elseif ($question->question_type === 'true_false') {
                    $isCorrect = (strtolower($studentAnswer) === strtolower($question->correct_answer));
                } elseif ($question->question_type === 'text') {
                    // Case-insensitive comparison for text questions
                    $isCorrect = (strtolower(trim($studentAnswer)) === strtolower(trim($question->correct_answer)));
                }

                if ($isCorrect) {
                    $earnedPoints += $question->points;
                }

                $results[] = [
                    'question_id' => $question->id,
                    'is_correct' => $isCorrect,
                    'student_answer' => $studentAnswer,
                    'correct_answer' => $question->correct_answer,
                    'explanation' => $question->explanation,
                    'points_earned' => $isCorrect ? $question->points : 0,
                    'points_possible' => $question->points
                ];
            }

            $score = $totalPoints > 0 ? round(($earnedPoints / $totalPoints) * 100, 2) : 0;

            return [
                'valid' => true,
                'score' => $score,
                'total_points' => $totalPoints,
                'earned_points' => $earnedPoints,
                'results' => $results
            ];
        } catch (\PDOException $e) {
            error_log("Database error in validateAnswers: " . $e->getMessage());
            return [
                'valid' => false,
                'error' => 'An error occurred while validating answers'
            ];
        }
    }

    /**
     * Create question with JSON options
     *
     * @param array $data Question data
     * @return int|null Question ID
     */
    public function createQuestion(array $data): ?int
    {
        // Encode options as JSON if it's an array
        if (isset($data['options']) && is_array($data['options'])) {
            $data['options'] = json_encode($data['options']);
        }

        return $this->create($data);
    }

    /**
     * Update question with JSON options
     *
     * @param int $questionId Question ID
     * @param array $data Question data
     * @return bool Success status
     */
    public function updateQuestion(int $questionId, array $data): bool
    {
        // Encode options as JSON if it's an array
        if (isset($data['options']) && is_array($data['options'])) {
            $data['options'] = json_encode($data['options']);
        }

        return $this->update($questionId, $data);
    }

    /**
     * Get difficulty statistics for a question
     * Phase 6 - Task 1: Question Difficulty Analysis
     *
     * @param int $questionId Question ID
     * @return array Statistics including total_attempts, success_rate, difficulty_score, avg_time
     */
    public static function getDifficultyStats(int $questionId): array
    {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) as total_attempts,
                SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct_answers,
                SUM(CASE WHEN is_correct = 0 THEN 1 ELSE 0 END) as incorrect_answers,
                ROUND(AVG(CASE WHEN is_correct = 1 THEN 100 ELSE 0 END), 2) as success_rate,
                ROUND(AVG(time_spent_seconds), 2) as avg_time_seconds
            FROM quiz_attempt_answers
            WHERE question_id = ?
        ");

        $stmt->execute([$questionId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$result || $result['total_attempts'] == 0) {
            return [
                'total_attempts' => 0,
                'correct_answers' => 0,
                'incorrect_answers' => 0,
                'success_rate' => 0,
                'difficulty_score' => 0,
                'avg_time_seconds' => 0
            ];
        }

        // Calculate difficulty score (inverse of success rate)
        $result['difficulty_score'] = round(100 - $result['success_rate'], 2);

        return $result;
    }

    /**
     * Get all questions ranked by difficulty for a quiz
     * Phase 6 - Task 1: Question Difficulty Analysis
     *
     * @param int $quizId Quiz ID
     * @return array Questions ranked by difficulty (hardest first)
     */
    public static function getQuestionDifficultyRanking(int $quizId): array
    {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT
                qq.id,
                qq.question_text,
                qq.order_index,
                COUNT(qaa.id) as total_attempts,
                SUM(CASE WHEN qaa.is_correct = 1 THEN 1 ELSE 0 END) as correct_count,
                ROUND(AVG(CASE WHEN qaa.is_correct = 1 THEN 100 ELSE 0 END), 2) as success_rate,
                ROUND(100 - AVG(CASE WHEN qaa.is_correct = 1 THEN 100 ELSE 0 END), 2) as difficulty_score,
                ROUND(AVG(qaa.time_spent_seconds), 2) as avg_time_seconds
            FROM quiz_questions qq
            LEFT JOIN quiz_attempt_answers qaa ON qq.id = qaa.question_id
            WHERE qq.quiz_id = ?
            GROUP BY qq.id, qq.question_text, qq.order_index
            ORDER BY difficulty_score DESC
        ");

        $stmt->execute([$quizId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
