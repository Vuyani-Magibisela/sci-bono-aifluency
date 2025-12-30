<?php
namespace App\Controllers;

use App\Models\QuizQuestion;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Utils\Response;
use App\Utils\JWTHandler;

/**
 * Analytics Controller
 *
 * Handles analytics endpoints for quiz performance, question difficulty,
 * performance trends, and class comparisons
 * Phase 6: Quiz Tracking & Grading System - Analytics
 */
class AnalyticsController
{
    private QuizAttempt $quizAttemptModel;
    private User $userModel;
    private \PDO $pdo;
    private ?array $auth = null;

    public function __construct()
    {
        global $pdo;
        $this->pdo = $pdo;
        $this->quizAttemptModel = new QuizAttempt($pdo);
        $this->userModel = new User($pdo);
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
     * Require specific role(s)
     *
     * @param array $roles Allowed roles
     * @return void
     */
    private function requireRole(array $roles): void
    {
        $this->requireAuth();
        $user = $this->userModel->find($this->auth['user_id']);

        if (!in_array($user->role, $roles)) {
            $rolesStr = implode(' or ', $roles);
            Response::error("$rolesStr role required", 403);
            exit;
        }
    }

    /**
     * Get currently authenticated user
     *
     * @return object User object
     */
    private function getCurrentUser(): object
    {
        $this->requireAuth();
        return $this->userModel->find($this->auth['user_id']);
    }

    /**
     * Get statistics for a specific question
     * GET /api/analytics/questions/:questionId
     *
     * @param array $params Route parameters
     * @return void
     */
    public function getQuestionStats(array $params): void
    {
        $this->requireRole(['admin', 'instructor']);

        if (!isset($params['questionId'])) {
            Response::error('Question ID is required', 400);
        }

        $questionId = (int)$params['questionId'];
        $stats = QuizQuestion::getDifficultyStats($questionId);

        Response::success($stats, 'Question statistics retrieved');
    }

    /**
     * Get difficulty ranking for all questions in a quiz
     * GET /api/analytics/quiz/:quizId/questions
     *
     * @param array $params Route parameters
     * @return void
     */
    public function getQuizQuestionDifficulty(array $params): void
    {
        $this->requireRole(['admin', 'instructor']);

        if (!isset($params['quizId'])) {
            Response::error('Quiz ID is required', 400);
        }

        $quizId = (int)$params['quizId'];
        $ranking = QuizQuestion::getQuestionDifficultyRanking($quizId);

        Response::success($ranking, 'Question difficulty ranking retrieved');
    }

    /**
     * Get performance trends for a user on a specific quiz
     * GET /api/analytics/trends/:userId/:quizId
     *
     * @param array $params Route parameters
     * @return void
     */
    public function getPerformanceTrends(array $params): void
    {
        if (!isset($params['userId']) || !isset($params['quizId'])) {
            Response::error('User ID and Quiz ID are required', 400);
        }

        $userId = (int)$params['userId'];
        $quizId = (int)$params['quizId'];

        // Security: Only allow users to view own trends, or admin/instructor
        $currentUser = $this->getCurrentUser();
        if ($currentUser->id !== $userId && !in_array($currentUser->role, ['admin', 'instructor'])) {
            Response::error('You can only view your own performance trends', 403);
        }

        $trends = QuizAttempt::getPerformanceTrends($userId, $quizId);
        Response::success($trends, 'Performance trends retrieved');
    }

    /**
     * Get learning curve across all quizzes for a user
     * GET /api/analytics/learning-curve/:userId
     *
     * @param array $params Route parameters
     * @return void
     */
    public function getUserLearningCurve(array $params): void
    {
        if (!isset($params['userId'])) {
            Response::error('User ID is required', 400);
        }

        $userId = (int)$params['userId'];

        // Security check
        $currentUser = $this->getCurrentUser();
        if ($currentUser->id !== $userId && !in_array($currentUser->role, ['admin', 'instructor'])) {
            Response::error('You can only view your own learning curve', 403);
        }

        $curve = QuizAttempt::getUserLearningCurve($userId);
        Response::success($curve, 'Learning curve retrieved');
    }

    /**
     * Get class comparison for a user on a quiz
     * GET /api/analytics/comparison/:quizId/:userId
     *
     * @param array $params Route parameters
     * @return void
     */
    public function getClassComparison(array $params): void
    {
        if (!isset($params['quizId']) || !isset($params['userId'])) {
            Response::error('Quiz ID and User ID are required', 400);
        }

        $quizId = (int)$params['quizId'];
        $userId = (int)$params['userId'];

        // Security check
        $currentUser = $this->getCurrentUser();
        if ($currentUser->id !== $userId && !in_array($currentUser->role, ['admin', 'instructor'])) {
            Response::error('You can only view your own comparison data', 403);
        }

        $comparison = QuizAttempt::getClassComparison($userId, $quizId);
        Response::success($comparison, 'Class comparison retrieved');
    }

    /**
     * Get leaderboard for a quiz
     * GET /api/analytics/leaderboard/:quizId
     *
     * @param array $params Route parameters
     * @return void
     */
    public function getQuizLeaderboard(array $params): void
    {
        $this->requireAuth();

        if (!isset($params['quizId'])) {
            Response::error('Quiz ID is required', 400);
        }

        $quizId = (int)$params['quizId'];
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

        $leaderboard = QuizAttempt::getQuizLeaderboard($quizId, $limit);
        Response::success($leaderboard, 'Leaderboard retrieved');
    }
}
