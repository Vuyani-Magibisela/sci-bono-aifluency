<?php
namespace App\Controllers;

use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\QuizAttempt;
use App\Utils\Response;
use App\Utils\Validator;
use App\Utils\JWTHandler;

/**
 * Quiz Controller
 *
 * Handles quiz and quiz attempt operations
 */
class QuizController extends BaseController
{
    private Quiz $quizModel;
    private QuizQuestion $questionModel;
    private QuizAttempt $attemptModel;

    public function __construct(\PDO $pdo)
    {
        parent::__construct($pdo);
        $this->quizModel = new Quiz($pdo);
        $this->questionModel = new QuizQuestion($pdo);
        $this->attemptModel = new QuizAttempt($pdo);
    }

    /**
     * List quizzes
     *
     * GET /api/quizzes?module_id=1&published=true
     *
     * @param array $params Route parameters
     * @return void
     */
    public function index(array $params = []): void
    {
        $currentUser = JWTHandler::getCurrentUser();

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $pageSize = isset($_GET['pageSize']) ? (int)$_GET['pageSize'] : 20;
        $moduleId = isset($_GET['module_id']) ? (int)$_GET['module_id'] : null;
        $publishedOnly = isset($_GET['published']) ? filter_var($_GET['published'], FILTER_VALIDATE_BOOLEAN) : true;

        if ($page < 1) $page = 1;
        if ($pageSize < 1 || $pageSize > 100) $pageSize = 20;

        $offset = ($page - 1) * $pageSize;

        // Get quizzes
        if ($moduleId) {
            if ($publishedOnly) {
                $quizzes = $this->quizModel->getPublishedByModule($moduleId, $pageSize, $offset);
                $total = $this->quizModel->count(['module_id' => $moduleId, 'is_published' => true]);
            } else {
                if (!$currentUser || !in_array($currentUser->role, ['admin', 'instructor'])) {
                    $quizzes = $this->quizModel->getPublishedByModule($moduleId, $pageSize, $offset);
                    $total = $this->quizModel->count(['module_id' => $moduleId, 'is_published' => true]);
                } else {
                    $quizzes = $this->quizModel->getByModule($moduleId, $pageSize, $offset);
                    $total = $this->quizModel->count(['module_id' => $moduleId]);
                }
            }
        } else {
            if (!$currentUser || !in_array($currentUser->role, ['admin', 'instructor'])) {
                $quizzes = $this->quizModel->all(['is_published' => true], 'title ASC', $pageSize, $offset);
                $total = $this->quizModel->count(['is_published' => true]);
            } else {
                $quizzes = $this->quizModel->all([], 'title ASC', $pageSize, $offset);
                $total = $this->quizModel->count();
            }
        }

        // Add attempt info for authenticated users
        if ($currentUser) {
            foreach ($quizzes as $quiz) {
                $bestScore = $this->quizModel->getUserBestScore($quiz->id, $currentUser->id);
                $quiz->user_best_score = $bestScore;
                $quiz->can_attempt = $this->quizModel->canUserAttempt($quiz->id, $currentUser->id);
            }
        }

        Response::paginated($quizzes, $total, $page, $pageSize, 'Quizzes retrieved successfully');
    }

    /**
     * Get quiz by ID with questions
     *
     * GET /api/quizzes/:id
     *
     * @param array $params Route parameters
     * @return void
     */
    public function show(array $params): void
    {
        if (!isset($params['id'])) {
            Response::error('Quiz ID is required', 400);
        }

        $quizId = (int)$params['id'];
        $currentUser = JWTHandler::getCurrentUser();

        $quiz = $this->quizModel->find($quizId);

        if (!$quiz) {
            Response::notFound('Quiz not found');
        }

        // Check if quiz is published
        if (!$quiz->is_published) {
            if (!$currentUser || !in_array($currentUser->role, ['admin', 'instructor'])) {
                Response::forbidden('This quiz is not published');
            }
        }

        // Include answers only for instructors/admins
        $includeAnswers = $currentUser && in_array($currentUser->role, ['admin', 'instructor']);

        // Get quiz with questions
        $quiz = $this->quizModel->getQuizWithQuestions($quizId, $includeAnswers);

        // Get quiz statistics for instructors/admins
        if ($includeAnswers) {
            $quiz->statistics = $this->quizModel->getQuizStats($quizId);
        }

        // Add user-specific info for students
        if ($currentUser) {
            $quiz->user_attempts = $this->quizModel->getUserAttempts($quizId, $currentUser->id);
            $quiz->user_best_score = $this->quizModel->getUserBestScore($quizId, $currentUser->id);
            $quiz->can_attempt = $this->quizModel->canUserAttempt($quizId, $currentUser->id);
        }

        Response::success([
            'quiz' => $quiz
        ], 'Quiz retrieved successfully');
    }

    /**
     * Create new quiz
     *
     * POST /api/quizzes
     *
     * @param array $params Route parameters
     * @return void
     */
    public function create(array $params = []): void
    {
        // Only admin and instructor can create quizzes
        $this->requireRole(['admin', 'instructor']);

        $data = $_POST;

        // Validate input
        $validator = Validator::make($data);

        $validator->required('module_id', 'Module ID is required')
                  ->required('title', 'Quiz title is required')
                  ->maxLength('title', 255, 'Title must not exceed 255 characters')
                  ->required('slug', 'Quiz slug is required')
                  ->maxLength('slug', 255, 'Slug must not exceed 255 characters');

        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        $moduleId = (int)$data['module_id'];

        // Check slug uniqueness within module
        if ($this->quizModel->findBySlug(Validator::sanitize($data['slug']), $moduleId)) {
            Response::error('Quiz slug already exists in this module', 409);
        }

        // Prepare quiz data
        $quizData = [
            'module_id' => $moduleId,
            'lesson_id' => isset($data['lesson_id']) ? (int)$data['lesson_id'] : null,
            'title' => Validator::sanitize($data['title']),
            'slug' => Validator::sanitize($data['slug']),
            'description' => isset($data['description']) ? Validator::sanitize($data['description']) : null,
            'passing_score' => isset($data['passing_score']) ? (int)$data['passing_score'] : 70,
            'time_limit_minutes' => isset($data['time_limit_minutes']) ? (int)$data['time_limit_minutes'] : null,
            'max_attempts' => isset($data['max_attempts']) ? (int)$data['max_attempts'] : null,
            'is_published' => isset($data['is_published']) ? (bool)$data['is_published'] : false,
            'order' => isset($data['order']) ? (int)$data['order'] : 0
        ];

        // Create quiz
        try {
            $this->quizModel->beginTransaction();

            $quizId = $this->quizModel->create($quizData);

            if (!$quizId) {
                $this->quizModel->rollback();
                Response::serverError('Failed to create quiz');
            }

            $this->quizModel->commit();

            $quiz = $this->quizModel->find($quizId);

            Response::success([
                'quiz' => $quiz
            ], 'Quiz created successfully', 201);

        } catch (\PDOException $e) {
            $this->quizModel->rollback();
            error_log('Quiz creation error: ' . $e->getMessage());
            Response::serverError('An error occurred while creating quiz');
        }
    }

    /**
     * Update quiz
     *
     * PUT /api/quizzes/:id
     *
     * @param array $params Route parameters
     * @return void
     */
    public function update(array $params): void
    {
        // Only admin and instructor can update quizzes
        $this->requireRole(['admin', 'instructor']);

        if (!isset($params['id'])) {
            Response::error('Quiz ID is required', 400);
        }

        $quizId = (int)$params['id'];

        $quiz = $this->quizModel->find($quizId);

        if (!$quiz) {
            Response::notFound('Quiz not found');
        }

        $data = $_POST;

        // Validate input
        $validator = Validator::make($data);

        if (isset($data['title'])) {
            $validator->maxLength('title', 255);
        }

        if (isset($data['slug'])) {
            $validator->maxLength('slug', 255);
        }

        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        // Check slug uniqueness if changed
        if (isset($data['slug']) && $data['slug'] !== $quiz->slug) {
            if ($this->quizModel->findBySlug(Validator::sanitize($data['slug']), $quiz->module_id)) {
                Response::error('Quiz slug already exists in this module', 409);
            }
        }

        // Prepare update data
        $allowedFields = [
            'title', 'slug', 'description', 'passing_score', 'time_limit_minutes',
            'max_attempts', 'is_published', 'order', 'lesson_id'
        ];

        $updateData = [];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                if (in_array($field, ['title', 'slug', 'description'])) {
                    $updateData[$field] = Validator::sanitize($data[$field]);
                } else {
                    $updateData[$field] = $data[$field];
                }
            }
        }

        if (empty($updateData)) {
            Response::error('No valid fields provided for update', 400);
        }

        // Update quiz
        try {
            $updated = $this->quizModel->update($quizId, $updateData);

            if (!$updated) {
                Response::serverError('Failed to update quiz');
            }

            $updatedQuiz = $this->quizModel->find($quizId);

            Response::success([
                'quiz' => $updatedQuiz
            ], 'Quiz updated successfully');

        } catch (\PDOException $e) {
            error_log('Quiz update error: ' . $e->getMessage());
            Response::serverError('An error occurred while updating quiz');
        }
    }

    /**
     * Delete quiz
     *
     * DELETE /api/quizzes/:id
     *
     * @param array $params Route parameters
     * @return void
     */
    public function delete(array $params): void
    {
        // Only admin can delete quizzes
        $this->requireRole('admin');

        if (!isset($params['id'])) {
            Response::error('Quiz ID is required', 400);
        }

        $quizId = (int)$params['id'];

        $quiz = $this->quizModel->find($quizId);

        if (!$quiz) {
            Response::notFound('Quiz not found');
        }

        // Delete quiz
        try {
            $this->quizModel->beginTransaction();

            $deleted = $this->quizModel->delete($quizId);

            if (!$deleted) {
                $this->quizModel->rollback();
                Response::serverError('Failed to delete quiz');
            }

            $this->quizModel->commit();

            Response::success([
                'deleted_quiz_id' => $quizId,
                'deleted_quiz_title' => $quiz->title
            ], 'Quiz deleted successfully');

        } catch (\PDOException $e) {
            $this->quizModel->rollback();
            error_log('Quiz deletion error: ' . $e->getMessage());
            Response::serverError('An error occurred while deleting quiz');
        }
    }

    /**
     * Submit quiz attempt
     *
     * POST /api/quizzes/:id/submit
     *
     * @param array $params Route parameters
     * @return void
     */
    public function submitAttempt(array $params): void
    {
        $currentUser = $this->getCurrentUser();

        if (!isset($params['id'])) {
            Response::error('Quiz ID is required', 400);
        }

        $quizId = (int)$params['id'];
        $data = $_POST;

        $quiz = $this->quizModel->find($quizId);

        if (!$quiz || !$quiz->is_published) {
            Response::notFound('Quiz not found');
        }

        // Check if user can attempt
        if (!$this->quizModel->canUserAttempt($quizId, $currentUser->id)) {
            Response::error('You have reached the maximum number of attempts for this quiz', 403);
        }

        // Validate answers
        $validator = Validator::make($data);
        $validator->required('answers', 'Quiz answers are required');

        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        $answers = $data['answers'];
        $timeSpent = isset($data['time_taken_minutes']) ? (int)$data['time_taken_minutes'] : 0;

        // Validate answers and calculate score
        $validation = $this->questionModel->validateAnswers($quizId, $answers);

        if (!$validation['valid']) {
            Response::error($validation['error'] ?? 'Invalid answers', 400);
        }

        $score = $validation['score'];
        $passed = $score >= $quiz->passing_score;

        // Create quiz attempt
        try {
            $this->attemptModel->beginTransaction();

            $attemptId = $this->attemptModel->createAttempt([
                'quiz_id' => $quizId,
                'user_id' => $currentUser->id,
                'score' => $score,
                'answers' => $answers,
                'time_taken_minutes' => $timeSpent,
                'passed' => $passed
            ]);

            if (!$attemptId) {
                $this->attemptModel->rollback();
                Response::serverError('Failed to submit quiz attempt');
            }

            // Insert individual answer records for analytics (Phase 6 - Task 1)
            if (isset($validation['results']) && is_array($validation['results'])) {
                foreach ($validation['results'] as $result) {
                    if (!isset($result['question_id'])) continue;

                    $questionId = (int)$result['question_id'];
                    $userAnswer = $result['student_answer'] ?? $result['selected_answer'] ?? null;
                    $correctAnswer = $result['correct_answer'] ?? null;
                    $questionText = $result['question_text'] ?? '';
                    $isCorrect = isset($result['is_correct']) ? ($result['is_correct'] ? 1 : 0) : 0;
                    $pointsAwarded = $result['points_earned'] ?? $result['points_awarded'] ?? 0;
                    $pointsPossible = $result['points_possible'] ?? 10;

                    // Extract time_spent from answers array if available
                    $timeSpent = 0;
                    if (is_array($answers) && isset($answers[$questionId])) {
                        if (is_array($answers[$questionId]) && isset($answers[$questionId]['time_spent'])) {
                            $timeSpent = (int)$answers[$questionId]['time_spent'];
                        }
                    }

                    $stmt = $this->pdo->prepare("
                        INSERT INTO quiz_attempt_answers
                        (attempt_id, question_id, question_text, user_answer, correct_answer, is_correct, points_awarded, points_possible, time_spent_seconds)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    $stmt->execute([
                        $attemptId,
                        $questionId,
                        $questionText,
                        $userAnswer,
                        $correctAnswer,
                        $isCorrect,
                        $pointsAwarded,
                        $pointsPossible,
                        $timeSpent
                    ]);
                }
            }

            $this->attemptModel->commit();

            $attempt = $this->attemptModel->find($attemptId);

            // Check for achievement unlocks (Phase 6 - Task 4: Enhanced Achievement Triggers)
            $newAchievements = [];
            if (class_exists('App\Models\Achievement')) {
                try {
                    $achievementModel = new \App\Models\Achievement($this->pdo);

                    // Base quiz completion achievement
                    $achievementContext = [
                        'quiz_id' => $quizId,
                        'module_id' => $quiz->module_id ?? null,
                        'score' => $score,
                        'passed' => $passed,
                        'perfect_score' => ($score >= 100),
                        'time_taken_minutes' => $timeSpent
                    ];

                    $newAchievements = $achievementModel->checkAndUnlock(
                        $currentUser->id,
                        'quiz_completion',
                        $achievementContext
                    );

                    // Perfect score achievement (100%)
                    if ($score >= 100) {
                        $perfectAchievements = $achievementModel->checkAndUnlock(
                            $currentUser->id,
                            'perfect_quiz',
                            $achievementContext
                        );
                        $newAchievements = array_merge($newAchievements, $perfectAchievements);
                    }

                    // Speed demon achievement (completed in under 5 minutes)
                    if ($passed && $timeSpent > 0 && $timeSpent < 5) {
                        $speedAchievements = $achievementModel->checkAndUnlock(
                            $currentUser->id,
                            'speed_demon',
                            $achievementContext
                        );
                        $newAchievements = array_merge($newAchievements, $speedAchievements);
                    }

                } catch (\Exception $e) {
                    // Log error but don't fail quiz submission - achievements are non-critical
                    error_log('Achievement unlock error (non-critical): ' . $e->getMessage());
                }
            }

            Response::success([
                'attempt' => $attempt,
                'score' => $score,
                'passed' => $passed,
                'passing_score' => $quiz->passing_score,
                'results' => $validation['results'],
                'achievements_unlocked' => $newAchievements
            ], 'Quiz submitted successfully', 201);

        } catch (\PDOException $e) {
            $this->attemptModel->rollback();
            error_log('Quiz submission error: ' . $e->getMessage());
            Response::serverError('An error occurred while submitting quiz');
        }
    }

    /**
     * Get user's quiz attempts
     *
     * GET /api/quizzes/:id/attempts
     *
     * @param array $params Route parameters
     * @return void
     */
    public function getAttempts(array $params): void
    {
        $currentUser = $this->getCurrentUser();

        if (!isset($params['id'])) {
            Response::error('Quiz ID is required', 400);
        }

        $quizId = (int)$params['id'];

        $quiz = $this->quizModel->find($quizId);

        if (!$quiz) {
            Response::notFound('Quiz not found');
        }

        // Students can only see their own attempts
        // Instructors/admins can see all attempts
        if ($currentUser->role === 'student') {
            $attempts = $this->attemptModel->getUserQuizAttempts($currentUser->id, $quizId);
        } else {
            $attempts = $this->attemptModel->getByQuiz($quizId);
        }

        Response::success([
            'attempts' => $attempts,
            'quiz_title' => $quiz->title
        ], 'Quiz attempts retrieved successfully');
    }

    /**
     * Create quiz question
     *
     * POST /api/quiz-questions
     * Body: {quiz_id, question, options (JSON string or array), correct_answer, explanation, points}
     *
     * @param array $params Route parameters
     * @return void
     */
    public function createQuestion(array $params = []): void
    {
        $this->requireRole(['admin', 'instructor']);
        $data = json_decode(file_get_contents('php://input'), true);

        // Validate required fields
        $validator = new Validator($data ?? []);
        $validator->required('quiz_id')
                  ->required('question')
                  ->required('options')
                  ->required('correct_answer');

        if ($validator->fails()) {
            Response::error('Validation failed', 400, $validator->errors());
        }

        // Type validation
        if (!is_numeric($data['quiz_id']) || !is_numeric($data['correct_answer'])) {
            Response::error('quiz_id and correct_answer must be integers', 400);
        }

        // Verify quiz exists
        $quiz = $this->quizModel->find((int)$data['quiz_id']);
        if (!$quiz) {
            Response::notFound('Quiz not found');
        }

        // Get next order_index
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(MAX(order_index), 0) + 1 as next_order
            FROM quiz_questions
            WHERE quiz_id = ?
        ");
        $stmt->execute([$data['quiz_id']]);
        $nextOrder = $stmt->fetchColumn();

        // Prepare data (map admin field names to DB columns)
        $questionData = [
            'quiz_id' => (int)$data['quiz_id'],
            'question_text' => $data['question'],
            'options' => is_string($data['options']) ? $data['options'] : json_encode($data['options']),
            'correct_option' => (int)$data['correct_answer'], // Map correct_answer → correct_option
            'explanation' => $data['explanation'] ?? null,
            'points' => isset($data['points']) ? (int)$data['points'] : 1,
            'order_index' => $nextOrder
        ];

        // Insert question
        $stmt = $this->pdo->prepare("
            INSERT INTO quiz_questions (quiz_id, question_text, options, correct_option, explanation, points, order_index)
            VALUES (:quiz_id, :question_text, :options, :correct_option, :explanation, :points, :order_index)
        ");

        if (!$stmt->execute($questionData)) {
            Response::serverError('Failed to create question');
        }

        $questionId = $this->pdo->lastInsertId();

        // Fetch the created question
        $stmt = $this->pdo->prepare("SELECT * FROM quiz_questions WHERE id = ?");
        $stmt->execute([$questionId]);
        $question = $stmt->fetch();

        Response::success($question, 'Question created successfully', 201);
    }

    /**
     * Update quiz question
     *
     * PUT /api/quiz-questions/:id
     * Body: {question?, options?, correct_answer?, explanation?, points?}
     *
     * @param array $params Route parameters
     * @return void
     */
    public function updateQuestion(array $params): void
    {
        $this->requireRole(['admin', 'instructor']);
        $questionId = (int)$params['id'];
        $data = json_decode(file_get_contents('php://input'), true);

        // Verify question exists
        $stmt = $this->pdo->prepare("SELECT * FROM quiz_questions WHERE id = ?");
        $stmt->execute([$questionId]);
        $question = $stmt->fetch();

        if (!$question) {
            Response::notFound('Question not found');
        }

        // Build update data (only update provided fields)
        $updateFields = [];
        $updateValues = [];

        if (isset($data['question'])) {
            $updateFields[] = "question_text = ?";
            $updateValues[] = $data['question'];
        }
        if (isset($data['options'])) {
            $updateFields[] = "options = ?";
            $updateValues[] = is_string($data['options']) ? $data['options'] : json_encode($data['options']);
        }
        if (isset($data['correct_answer'])) {
            $updateFields[] = "correct_option = ?";
            $updateValues[] = (int)$data['correct_answer'];
        }
        if (isset($data['explanation'])) {
            $updateFields[] = "explanation = ?";
            $updateValues[] = $data['explanation'];
        }
        if (isset($data['points'])) {
            $updateFields[] = "points = ?";
            $updateValues[] = (int)$data['points'];
        }

        if (empty($updateFields)) {
            Response::badRequest('No fields to update');
        }

        // Add question ID to values
        $updateValues[] = $questionId;

        // Execute update
        $sql = "UPDATE quiz_questions SET " . implode(", ", $updateFields) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);

        if (!$stmt->execute($updateValues)) {
            Response::serverError('Failed to update question');
        }

        // Fetch updated question
        $stmt = $this->pdo->prepare("SELECT * FROM quiz_questions WHERE id = ?");
        $stmt->execute([$questionId]);
        $updated = $stmt->fetch();

        Response::success($updated, 'Question updated successfully');
    }

    /**
     * Delete quiz question
     *
     * DELETE /api/quiz-questions/:id
     *
     * @param array $params Route parameters
     * @return void
     */
    public function deleteQuestion(array $params): void
    {
        $this->requireRole(['admin', 'instructor']);
        $questionId = (int)$params['id'];

        // Verify question exists
        $stmt = $this->pdo->prepare("SELECT * FROM quiz_questions WHERE id = ?");
        $stmt->execute([$questionId]);
        $question = $stmt->fetch();

        if (!$question) {
            Response::notFound('Question not found');
        }

        // Delete question
        $stmt = $this->pdo->prepare("DELETE FROM quiz_questions WHERE id = ?");

        if (!$stmt->execute([$questionId])) {
            Response::serverError('Failed to delete question');
        }

        Response::success('Question deleted successfully');
    }
}
