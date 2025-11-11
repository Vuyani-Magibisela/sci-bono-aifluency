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
}
