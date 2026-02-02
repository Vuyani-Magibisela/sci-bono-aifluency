<?php
namespace App\Controllers;

use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Enrollment;
use App\Utils\Response;
use App\Utils\Validator;
use App\Utils\JWTHandler;

/**
 * Lesson Controller
 *
 * Handles lesson and lesson progress operations
 */
class LessonController extends BaseController
{
    private Lesson $lessonModel;
    private LessonProgress $progressModel;
    private Module $moduleModel;
    private Enrollment $enrollmentModel;

    public function __construct(\PDO $pdo)
    {
        parent::__construct($pdo);
        $this->lessonModel = new Lesson($pdo);
        $this->progressModel = new LessonProgress($pdo);
        $this->moduleModel = new Module($pdo);
        $this->enrollmentModel = new Enrollment($pdo);
    }

    /**
     * List lessons
     *
     * GET /api/lessons?module_id=1&published=true
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

        // Get lessons
        if ($moduleId) {
            if ($publishedOnly) {
                $lessons = $this->lessonModel->getPublishedByModule($moduleId, $pageSize, $offset);
                $total = $this->lessonModel->count(['module_id' => $moduleId, 'is_published' => true]);
            } else {
                // Only instructors/admins can see unpublished lessons
                if (!$currentUser || !in_array($currentUser->role, ['admin', 'instructor'])) {
                    $lessons = $this->lessonModel->getPublishedByModule($moduleId, $pageSize, $offset);
                    $total = $this->lessonModel->count(['module_id' => $moduleId, 'is_published' => true]);
                } else {
                    $lessons = $this->lessonModel->getByModule($moduleId, $pageSize, $offset);
                    $total = $this->lessonModel->count(['module_id' => $moduleId]);
                }
            }
        } else {
            if (!$currentUser || !in_array($currentUser->role, ['admin', 'instructor'])) {
                $lessons = $this->lessonModel->all(['is_published' => true], 'order_index ASC', $pageSize, $offset);
                $total = $this->lessonModel->count(['is_published' => true]);
            } else {
                $lessons = $this->lessonModel->all([], 'order_index ASC', $pageSize, $offset);
                $total = $this->lessonModel->count();
            }
        }

        // Add progress for authenticated users
        if ($currentUser) {
            foreach ($lessons as $lesson) {
                $progress = $this->progressModel->getUserLessonProgress($currentUser->id, $lesson->id);
                $lesson->progress = $progress;
            }
        }

        Response::paginated($lessons, $total, $page, $pageSize, 'Lessons retrieved successfully');
    }

    /**
     * Get lesson by ID
     *
     * GET /api/lessons/:id
     *
     * @param array $params Route parameters
     * @return void
     */
    public function show(array $params): void
    {
        if (!isset($params['id'])) {
            Response::error('Lesson ID is required', 400);
        }

        $lessonId = (int)$params['id'];
        $currentUser = JWTHandler::getCurrentUser();

        $lesson = $this->lessonModel->find($lessonId);

        if (!$lesson) {
            Response::notFound('Lesson not found');
        }

        // Check if lesson is published
        if (!$lesson->is_published) {
            if (!$currentUser || !in_array($currentUser->role, ['admin', 'instructor'])) {
                Response::forbidden('This lesson is not published');
            }
        }

        // Get module info
        $lesson->module = $this->moduleModel->find($lesson->module_id);

        // Get next and previous lessons
        $lesson->next_lesson = $this->lessonModel->getNextLesson($lesson->module_id, $lesson->order);
        $lesson->previous_lesson = $this->lessonModel->getPreviousLesson($lesson->module_id, $lesson->order);

        // Add progress for authenticated users
        if ($currentUser) {
            $lesson->progress = $this->progressModel->getUserLessonProgress($currentUser->id, $lessonId);
        }

        Response::success([
            'lesson' => $lesson
        ], 'Lesson retrieved successfully');
    }

    /**
     * Create new lesson
     *
     * POST /api/lessons
     *
     * @param array $params Route parameters
     * @return void
     */
    public function create(array $params = []): void
    {
        // Only admin and instructor can create lessons
        $this->requireRole(['admin', 'instructor']);

        $data = $_POST;

        // Validate input
        $validator = Validator::make($data);

        $validator->required('module_id', 'Module ID is required')
                  ->required('title', 'Lesson title is required')
                  ->maxLength('title', 255, 'Title must not exceed 255 characters')
                  ->required('slug', 'Lesson slug is required')
                  ->maxLength('slug', 255, 'Slug must not exceed 255 characters');

        if (isset($data['content_type'])) {
            $validator->in('content_type', ['text', 'video', 'interactive'], 'Invalid content type');
        }

        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        $moduleId = (int)$data['module_id'];

        // Check if module exists
        $module = $this->moduleModel->find($moduleId);
        if (!$module) {
            Response::notFound('Module not found');
        }

        // Check slug uniqueness within module
        if ($this->lessonModel->findBySlug(Validator::sanitize($data['slug']), $moduleId)) {
            Response::error('Lesson slug already exists in this module', 409);
        }

        // Prepare lesson data
        $lessonData = [
            'module_id' => $moduleId,
            'title' => Validator::sanitize($data['title']),
            'slug' => Validator::sanitize($data['slug']),
            'content' => isset($data['content']) ? $data['content'] : null,
            'content_type' => isset($data['content_type']) ? $data['content_type'] : 'text',
            'video_url' => isset($data['video_url']) ? $data['video_url'] : null,
            'duration_minutes' => isset($data['duration_minutes']) ? (int)$data['duration_minutes'] : 0,
            'order' => isset($data['order']) ? (int)$data['order'] : 0,
            'objectives' => isset($data['objectives']) ? Validator::sanitize($data['objectives']) : null,
            'is_published' => isset($data['is_published']) ? (bool)$data['is_published'] : false
        ];

        // Create lesson
        try {
            $this->lessonModel->beginTransaction();

            $lessonId = $this->lessonModel->create($lessonData);

            if (!$lessonId) {
                $this->lessonModel->rollback();
                Response::serverError('Failed to create lesson');
            }

            $this->lessonModel->commit();

            $lesson = $this->lessonModel->find($lessonId);

            Response::success([
                'lesson' => $lesson
            ], 'Lesson created successfully', 201);

        } catch (\PDOException $e) {
            $this->lessonModel->rollback();
            error_log('Lesson creation error: ' . $e->getMessage());
            Response::serverError('An error occurred while creating lesson');
        }
    }

    /**
     * Update lesson
     *
     * PUT /api/lessons/:id
     *
     * @param array $params Route parameters
     * @return void
     */
    public function update(array $params): void
    {
        // Only admin and instructor can update lessons
        $this->requireRole(['admin', 'instructor']);

        if (!isset($params['id'])) {
            Response::error('Lesson ID is required', 400);
        }

        $lessonId = (int)$params['id'];

        $lesson = $this->lessonModel->find($lessonId);

        if (!$lesson) {
            Response::notFound('Lesson not found');
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

        if (isset($data['content_type'])) {
            $validator->in('content_type', ['text', 'video', 'interactive']);
        }

        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        // Check slug uniqueness if changed
        if (isset($data['slug']) && $data['slug'] !== $lesson->slug) {
            if ($this->lessonModel->findBySlug(Validator::sanitize($data['slug']), $lesson->module_id)) {
                Response::error('Lesson slug already exists in this module', 409);
            }
        }

        // Prepare update data
        $allowedFields = [
            'title', 'slug', 'content', 'content_type', 'video_url',
            'duration_minutes', 'order', 'objectives', 'is_published'
        ];

        $updateData = [];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                if (in_array($field, ['title', 'slug', 'objectives'])) {
                    $updateData[$field] = Validator::sanitize($data[$field]);
                } else {
                    $updateData[$field] = $data[$field];
                }
            }
        }

        if (empty($updateData)) {
            Response::error('No valid fields provided for update', 400);
        }

        // Update lesson
        try {
            $updated = $this->lessonModel->update($lessonId, $updateData);

            if (!$updated) {
                Response::serverError('Failed to update lesson');
            }

            $updatedLesson = $this->lessonModel->find($lessonId);

            Response::success([
                'lesson' => $updatedLesson
            ], 'Lesson updated successfully');

        } catch (\PDOException $e) {
            error_log('Lesson update error: ' . $e->getMessage());
            Response::serverError('An error occurred while updating lesson');
        }
    }

    /**
     * Delete lesson
     *
     * DELETE /api/lessons/:id
     *
     * @param array $params Route parameters
     * @return void
     */
    public function delete(array $params): void
    {
        // Only admin can delete lessons
        $this->requireRole('admin');

        if (!isset($params['id'])) {
            Response::error('Lesson ID is required', 400);
        }

        $lessonId = (int)$params['id'];

        $lesson = $this->lessonModel->find($lessonId);

        if (!$lesson) {
            Response::notFound('Lesson not found');
        }

        // Delete lesson
        try {
            $this->lessonModel->beginTransaction();

            $deleted = $this->lessonModel->delete($lessonId);

            if (!$deleted) {
                $this->lessonModel->rollback();
                Response::serverError('Failed to delete lesson');
            }

            $this->lessonModel->commit();

            Response::success([
                'deleted_lesson_id' => $lessonId,
                'deleted_lesson_title' => $lesson->title
            ], 'Lesson deleted successfully');

        } catch (\PDOException $e) {
            $this->lessonModel->rollback();
            error_log('Lesson deletion error: ' . $e->getMessage());
            Response::serverError('An error occurred while deleting lesson');
        }
    }

    /**
     * Start lesson (track progress)
     *
     * POST /api/lessons/:id/start
     *
     * @param array $params Route parameters
     * @return void
     */
    public function startLesson(array $params): void
    {
        $currentUser = $this->getCurrentUser();

        if (!isset($params['id'])) {
            Response::error('Lesson ID is required', 400);
        }

        $lessonId = (int)$params['id'];

        $lesson = $this->lessonModel->find($lessonId);

        if (!$lesson || !$lesson->is_published) {
            Response::notFound('Lesson not found');
        }

        try {
            $progressId = $this->progressModel->startLesson($currentUser->id, $lessonId);

            if (!$progressId) {
                Response::serverError('Failed to start lesson');
            }

            $progress = $this->progressModel->find($progressId);

            Response::success([
                'progress' => $progress
            ], 'Lesson started successfully');

        } catch (\PDOException $e) {
            error_log('Start lesson error: ' . $e->getMessage());
            Response::serverError('An error occurred while starting lesson');
        }
    }

    /**
     * Complete lesson
     *
     * POST /api/lessons/:id/complete
     *
     * @param array $params Route parameters
     * @return void
     */
    public function completeLesson(array $params): void
    {
        $currentUser = $this->getCurrentUser();

        if (!isset($params['id'])) {
            Response::error('Lesson ID is required', 400);
        }

        $lessonId = (int)$params['id'];
        $data = $_POST;

        $lesson = $this->lessonModel->find($lessonId);

        if (!$lesson || !$lesson->is_published) {
            Response::notFound('Lesson not found');
        }

        $timeSpent = isset($data['time_spent_minutes']) ? (int)$data['time_spent_minutes'] : 0;

        try {
            $this->progressModel->beginTransaction();

            $completed = $this->progressModel->completeLesson($currentUser->id, $lessonId, $timeSpent);

            if (!$completed) {
                $this->progressModel->rollback();
                Response::serverError('Failed to complete lesson');
            }

            // Update enrollment progress
            $module = $this->moduleModel->find($lesson->module_id);
            if ($module) {
                // Get course from module and update enrollment
                $stmt = $this->pdo->prepare("SELECT course_id FROM modules WHERE id = :module_id");
                $stmt->execute(['module_id' => $module->id]);
                $result = $stmt->fetch(\PDO::FETCH_OBJ);

                if ($result) {
                    $this->enrollmentModel->calculateProgress($currentUser->id, $result->course_id);
                }
            }

            $this->progressModel->commit();

            $progress = $this->progressModel->getUserLessonProgress($currentUser->id, $lessonId);

            // Check for achievement unlocks (Phase 6)
            $newAchievements = [];
            try {
                $achievementModel = new \App\Models\Achievement($this->pdo);
                $newAchievements = $achievementModel->checkAndUnlock($currentUser->id, 'lesson_completion', [
                    'lesson_id' => $lessonId,
                    'module_id' => $lesson->module_id
                ]);
            } catch (\Exception $e) {
                error_log('Achievement check error: ' . $e->getMessage());
                // Don't fail lesson completion if achievement check fails
            }

            Response::success([
                'progress' => $progress,
                'achievements_unlocked' => $newAchievements
            ], 'Lesson completed successfully');

        } catch (\PDOException $e) {
            $this->progressModel->rollback();
            error_log('Complete lesson error: ' . $e->getMessage());
            Response::serverError('An error occurred while completing lesson');
        }
    }
}
