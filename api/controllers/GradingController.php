<?php
namespace App\Controllers;

use App\Models\QuizAttempt;
use App\Models\User;
use App\Models\Quiz;
use App\Utils\Response;
use App\Utils\JWTHandler;
use App\Utils\Validator;

/**
 * Grading Controller
 *
 * Handles instructor grading operations for quiz attempts
 * Phase 6: Quiz Tracking & Grading System
 */
class GradingController
{
    private QuizAttempt $quizAttemptModel;
    private User $userModel;
    private Quiz $quizModel;
    private \PDO $pdo;
    private ?array $auth = null;

    public function __construct()
    {
        global $pdo;
        $this->pdo = $pdo;
        $this->quizAttemptModel = new QuizAttempt($pdo);
        $this->userModel = new User($pdo);
        $this->quizModel = new Quiz($pdo);
    }

    /**
     * Authenticate request and set auth property
     *
     * @return void
     */
    private function requireAuth(): void
    {
        $headers = getallheaders();
        $token = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? null;

        if (!$token) {
            Response::error('Authorization token required', 401);
            exit;
        }

        // Remove 'Bearer ' prefix if present
        $token = str_replace('Bearer ', '', $token);

        try {
            $this->auth = JWTHandler::validateToken($token);
        } catch (\Exception $e) {
            Response::error('Invalid or expired token: ' . $e->getMessage(), 401);
            exit;
        }
    }

    /**
     * Require instructor or admin role
     *
     * @return void
     */
    private function requireInstructorRole(): void
    {
        $this->requireAuth();
        $user = $this->userModel->find($this->auth['user_id']);

        if (!in_array($user->role, ['instructor', 'admin'])) {
            Response::error('Instructor or admin role required', 403);
            exit;
        }
    }

    /**
     * Get pending grading queue
     *
     * GET /api/grading/pending
     * Query params: ?quiz_id=123&limit=50&offset=0
     *
     * @param array $params Route parameters
     * @return void
     */
    public function getPendingQueue(array $params = []): void
    {
        $this->requireInstructorRole();

        $quizId = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : null;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

        // Cap limit for performance
        if ($limit > 100) {
            $limit = 100;
        }

        try {
            $pendingAttempts = $this->quizAttemptModel->getPendingGradingAttempts($quizId);

            // Apply pagination
            $total = count($pendingAttempts);
            $pendingAttempts = array_slice($pendingAttempts, $offset, $limit);

            // Enrich with user and quiz data
            foreach ($pendingAttempts as &$attempt) {
                $user = $this->userModel->find($attempt['user_id']);
                $quiz = $this->quizModel->find($attempt['quiz_id']);

                $attempt['user'] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email
                ];

                $attempt['quiz'] = [
                    'id' => $quiz->id ?? null,
                    'title' => $quiz->title ?? 'Unknown Quiz',
                    'lesson_id' => $quiz->lesson_id ?? null
                ];

                // Calculate time spent in readable format
                if ($attempt['time_spent_seconds']) {
                    $minutes = floor($attempt['time_spent_seconds'] / 60);
                    $seconds = $attempt['time_spent_seconds'] % 60;
                    $attempt['time_spent_formatted'] = sprintf('%d min %d sec', $minutes, $seconds);
                }
            }

            Response::success([
                'attempts' => $pendingAttempts,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total
            ]);

        } catch (\Exception $e) {
            Response::error('Failed to retrieve pending grading queue: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Submit instructor grade for quiz attempt
     *
     * POST /api/grading/:attemptId
     * Body: { "score": 85.5, "feedback": "Good work, but..." }
     *
     * @param array $params Route parameters
     * @return void
     */
    public function gradeAttempt(array $params = []): void
    {
        $this->requireInstructorRole();

        $attemptId = $params['attemptId'] ?? null;
        if (!$attemptId) {
            Response::error('Attempt ID is required', 400);
            return;
        }

        // Get request data
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data) {
            Response::error('Invalid JSON data', 400);
            return;
        }

        // Validate input
        $validator = Validator::make($data);
        $validator->required('score', 'Score is required')
                  ->numeric('score', 'Score must be a number')
                  ->min('score', 0, 'Score cannot be negative')
                  ->max('score', 100, 'Score cannot exceed 100');

        if (isset($data['feedback'])) {
            $validator->maxLength('feedback', 2000, 'Feedback must not exceed 2000 characters');
        }

        if ($validator->fails()) {
            Response::error('Validation failed', 400, ['errors' => $validator->errors()]);
            return;
        }

        try {
            // Verify attempt exists
            $attempt = $this->quizAttemptModel->find($attemptId);
            if (!$attempt) {
                Response::error('Quiz attempt not found', 404);
                return;
            }

            // Grade the attempt
            $instructorId = $this->auth['user_id'];
            $score = (float)$data['score'];
            $feedback = $data['feedback'] ?? null;

            $success = $this->quizAttemptModel->gradeAttempt($attemptId, $instructorId, $score, $feedback);

            if (!$success) {
                Response::error('Failed to save grade', 500);
                return;
            }

            // Get updated attempt
            $updatedAttempt = $this->quizAttemptModel->find($attemptId);

            // Get instructor info
            $instructor = $this->userModel->find($instructorId);

            Response::success([
                'message' => 'Grade submitted successfully',
                'attempt' => $updatedAttempt,
                'graded_by' => [
                    'id' => $instructor->id,
                    'name' => $instructor->name
                ]
            ]);

        } catch (\Exception $e) {
            Response::error('Failed to submit grade: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get quiz performance analytics
     *
     * GET /api/grading/analytics/:quizId
     *
     * @param array $params Route parameters
     * @return void
     */
    public function getQuizAnalytics(array $params = []): void
    {
        $this->requireInstructorRole();

        $quizId = $params['quizId'] ?? null;
        if (!$quizId) {
            Response::error('Quiz ID is required', 400);
            return;
        }

        try {
            // Get quiz details
            $quiz = $this->quizModel->find($quizId);
            if (!$quiz) {
                Response::error('Quiz not found', 404);
                return;
            }

            // Get analytics
            $analytics = $this->quizAttemptModel->getQuizAnalytics($quizId);

            // Get all attempts for this quiz (for distribution data)
            $allAttempts = $this->quizAttemptModel->getByQuiz($quizId);

            // Calculate score distribution
            $scoreRanges = [
                '0-20' => 0,
                '21-40' => 0,
                '41-60' => 0,
                '61-80' => 0,
                '81-100' => 0
            ];

            foreach ($allAttempts as $attempt) {
                $score = $attempt->score;
                if ($score >= 0 && $score <= 20) {
                    $scoreRanges['0-20']++;
                } elseif ($score <= 40) {
                    $scoreRanges['21-40']++;
                } elseif ($score <= 60) {
                    $scoreRanges['41-60']++;
                } elseif ($score <= 80) {
                    $scoreRanges['61-80']++;
                } else {
                    $scoreRanges['81-100']++;
                }
            }

            // Get recent attempts (last 10)
            $recentAttempts = array_slice($allAttempts, 0, 10);
            foreach ($recentAttempts as $attempt) {
                $user = $this->userModel->find($attempt->user_id);
                $attempt->user_name = $user->name ?? 'Unknown';
            }

            Response::success([
                'quiz' => [
                    'id' => $quiz->id,
                    'title' => $quiz->title,
                    'lesson_id' => $quiz->lesson_id,
                    'passing_score' => $quiz->passing_score ?? 70
                ],
                'statistics' => [
                    'total_attempts' => count($allAttempts),
                    'unique_students' => (int)$analytics['unique_students'],
                    'average_score' => round((float)$analytics['average_score'], 2),
                    'highest_score' => (float)$analytics['highest_score'],
                    'lowest_score' => min(array_column($allAttempts, 'score')),
                    'avg_time_seconds' => (int)$analytics['avg_time_seconds'],
                    'avg_time_formatted' => $this->formatSeconds((int)$analytics['avg_time_seconds']),
                    'total_passed' => (int)$analytics['total_passed'],
                    'total_failed' => count($allAttempts) - (int)$analytics['total_passed'],
                    'pass_rate' => count($allAttempts) > 0
                        ? round(((int)$analytics['total_passed'] / count($allAttempts)) * 100, 2)
                        : 0
                ],
                'score_distribution' => $scoreRanges,
                'recent_attempts' => $recentAttempts
            ]);

        } catch (\Exception $e) {
            Response::error('Failed to retrieve analytics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get grading history for instructor
     *
     * GET /api/grading/history
     * Query params: ?limit=20&offset=0
     *
     * @param array $params Route parameters
     * @return void
     */
    public function getGradingHistory(array $params = []): void
    {
        $this->requireInstructorRole();
        $instructorId = $this->auth['user_id'];

        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    qa.*,
                    u.name as student_name,
                    u.email as student_email,
                    q.title as quiz_title
                FROM quiz_attempts qa
                JOIN users u ON qa.user_id = u.id
                JOIN quizzes q ON qa.quiz_id = q.id
                WHERE qa.graded_by = :instructor_id
                ORDER BY qa.graded_at DESC
                LIMIT :limit OFFSET :offset
            ");

            $stmt->bindValue(':instructor_id', $instructorId, \PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();

            $history = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Get total count
            $countStmt = $this->pdo->prepare("
                SELECT COUNT(*) as total
                FROM quiz_attempts
                WHERE graded_by = :instructor_id
            ");
            $countStmt->execute(['instructor_id' => $instructorId]);
            $total = $countStmt->fetch(\PDO::FETCH_ASSOC)['total'];

            Response::success([
                'history' => $history,
                'total' => (int)$total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total
            ]);

        } catch (\Exception $e) {
            Response::error('Failed to retrieve grading history: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Helper: Format seconds to readable time
     *
     * @param int $seconds
     * @return string
     */
    private function formatSeconds(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %dm %ds', $hours, $minutes, $secs);
        } elseif ($minutes > 0) {
            return sprintf('%dm %ds', $minutes, $secs);
        } else {
            return sprintf('%ds', $secs);
        }
    }
}
