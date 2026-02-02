<?php
namespace App\Controllers;

use App\Models\Module;
use App\Models\Course;
use App\Utils\Response;
use App\Utils\Validator;
use App\Utils\JWTHandler;

/**
 * Module Controller
 *
 * Handles module management operations
 */
class ModuleController extends BaseController
{
    private Module $moduleModel;
    private Course $courseModel;

    public function __construct(\PDO $pdo)
    {
        parent::__construct($pdo);
        $this->moduleModel = new Module($pdo);
        $this->courseModel = new Course($pdo);
    }

    /**
     * List modules
     *
     * GET /api/modules?course_id=1&published=true
     *
     * @param array $params Route parameters
     * @return void
     */
    public function index(array $params = []): void
    {
        $currentUser = JWTHandler::getCurrentUser();

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $pageSize = isset($_GET['pageSize']) ? (int)$_GET['pageSize'] : 20;
        $courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : null;
        $publishedOnly = isset($_GET['published']) ? filter_var($_GET['published'], FILTER_VALIDATE_BOOLEAN) : true;

        if ($page < 1) $page = 1;
        if ($pageSize < 1 || $pageSize > 100) $pageSize = 20;

        $offset = ($page - 1) * $pageSize;

        // Get modules
        if ($courseId) {
            if ($publishedOnly) {
                $modules = $this->moduleModel->getPublishedByCourse($courseId, $pageSize, $offset);
                $total = $this->moduleModel->count(['course_id' => $courseId, 'is_published' => true]);
            } else {
                // Only instructors/admins can see unpublished modules
                if (!$currentUser || !in_array($currentUser->role, ['admin', 'instructor'])) {
                    $modules = $this->moduleModel->getPublishedByCourse($courseId, $pageSize, $offset);
                    $total = $this->moduleModel->count(['course_id' => $courseId, 'is_published' => true]);
                } else {
                    $modules = $this->moduleModel->getByCourse($courseId, $pageSize, $offset);
                    $total = $this->moduleModel->count(['course_id' => $courseId]);
                }
            }
        } else {
            if (!$currentUser || !in_array($currentUser->role, ['admin', 'instructor'])) {
                $modules = $this->moduleModel->all(['is_published' => true], 'order_index ASC', $pageSize, $offset);
                $total = $this->moduleModel->count(['is_published' => true]);
            } else {
                $modules = $this->moduleModel->all([], 'order_index ASC', $pageSize, $offset);
                $total = $this->moduleModel->count();
            }
        }

        // Add statistics for each module
        foreach ($modules as $module) {
            $module->statistics = $this->moduleModel->getModuleStats($module->id);
        }

        Response::paginated($modules, $total, $page, $pageSize, 'Modules retrieved successfully');
    }

    /**
     * Get module by ID with lessons
     *
     * GET /api/modules/:id
     *
     * @param array $params Route parameters
     * @return void
     */
    public function show(array $params): void
    {
        if (!isset($params['id'])) {
            Response::error('Module ID is required', 400);
        }

        $moduleId = (int)$params['id'];
        $currentUser = JWTHandler::getCurrentUser();

        // Get module with lessons
        $module = $this->moduleModel->getModuleWithLessons($moduleId);

        if (!$module) {
            Response::notFound('Module not found');
        }

        // Check if module is published
        if (!$module->is_published) {
            if (!$currentUser || !in_array($currentUser->role, ['admin', 'instructor'])) {
                Response::forbidden('This module is not published');
            }
        }

        // Get course info
        $module->course = $this->courseModel->find($module->course_id);

        // Get module statistics
        $module->statistics = $this->moduleModel->getModuleStats($moduleId);

        Response::success([
            'module' => $module
        ], 'Module retrieved successfully');
    }

    /**
     * Create new module
     *
     * POST /api/modules
     *
     * @param array $params Route parameters
     * @return void
     */
    public function create(array $params = []): void
    {
        // Only admin and instructor can create modules
        $this->requireRole(['admin', 'instructor']);

        $data = $_POST;

        // Validate input
        $validator = Validator::make($data);

        $validator->required('course_id', 'Course ID is required')
                  ->required('title', 'Module title is required')
                  ->maxLength('title', 255, 'Title must not exceed 255 characters')
                  ->required('slug', 'Module slug is required')
                  ->maxLength('slug', 255, 'Slug must not exceed 255 characters');

        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        $courseId = (int)$data['course_id'];

        // Check if course exists
        $course = $this->courseModel->find($courseId);
        if (!$course) {
            Response::notFound('Course not found');
        }

        // Check slug uniqueness within course
        if ($this->moduleModel->findBySlug(Validator::sanitize($data['slug']), $courseId)) {
            Response::error('Module slug already exists in this course', 409, [
                'slug' => 'This slug is already in use for this course'
            ]);
        }

        // Prepare module data
        $moduleData = [
            'course_id' => $courseId,
            'title' => Validator::sanitize($data['title']),
            'slug' => Validator::sanitize($data['slug']),
            'description' => isset($data['description']) ? Validator::sanitize($data['description']) : null,
            'objectives' => isset($data['objectives']) ? Validator::sanitize($data['objectives']) : null,
            'order' => isset($data['order']) ? (int)$data['order'] : 0,
            'duration_hours' => isset($data['duration_hours']) ? (int)$data['duration_hours'] : 0,
            'is_published' => isset($data['is_published']) ? (bool)$data['is_published'] : false
        ];

        // Create module
        try {
            $this->moduleModel->beginTransaction();

            $moduleId = $this->moduleModel->create($moduleData);

            if (!$moduleId) {
                $this->moduleModel->rollback();
                Response::serverError('Failed to create module');
            }

            $this->moduleModel->commit();

            $module = $this->moduleModel->find($moduleId);

            Response::success([
                'module' => $module
            ], 'Module created successfully', 201);

        } catch (\PDOException $e) {
            $this->moduleModel->rollback();
            error_log('Module creation error: ' . $e->getMessage());
            Response::serverError('An error occurred while creating module');
        }
    }

    /**
     * Update module
     *
     * PUT /api/modules/:id
     *
     * @param array $params Route parameters
     * @return void
     */
    public function update(array $params): void
    {
        // Only admin and instructor can update modules
        $this->requireRole(['admin', 'instructor']);

        if (!isset($params['id'])) {
            Response::error('Module ID is required', 400);
        }

        $moduleId = (int)$params['id'];

        $module = $this->moduleModel->find($moduleId);

        if (!$module) {
            Response::notFound('Module not found');
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
        if (isset($data['slug']) && $data['slug'] !== $module->slug) {
            if ($this->moduleModel->findBySlug(Validator::sanitize($data['slug']), $module->course_id)) {
                Response::error('Module slug already exists in this course', 409);
            }
        }

        // Prepare update data
        $allowedFields = [
            'title', 'slug', 'description', 'objectives', 'order',
            'duration_hours', 'is_published'
        ];

        $updateData = [];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                if (in_array($field, ['title', 'slug', 'description', 'objectives'])) {
                    $updateData[$field] = Validator::sanitize($data[$field]);
                } else {
                    $updateData[$field] = $data[$field];
                }
            }
        }

        if (empty($updateData)) {
            Response::error('No valid fields provided for update', 400);
        }

        // Update module
        try {
            $updated = $this->moduleModel->update($moduleId, $updateData);

            if (!$updated) {
                Response::serverError('Failed to update module');
            }

            $updatedModule = $this->moduleModel->find($moduleId);

            Response::success([
                'module' => $updatedModule
            ], 'Module updated successfully');

        } catch (\PDOException $e) {
            error_log('Module update error: ' . $e->getMessage());
            Response::serverError('An error occurred while updating module');
        }
    }

    /**
     * Delete module
     *
     * DELETE /api/modules/:id
     *
     * @param array $params Route parameters
     * @return void
     */
    public function delete(array $params): void
    {
        // Only admin can delete modules
        $this->requireRole('admin');

        if (!isset($params['id'])) {
            Response::error('Module ID is required', 400);
        }

        $moduleId = (int)$params['id'];

        $module = $this->moduleModel->find($moduleId);

        if (!$module) {
            Response::notFound('Module not found');
        }

        // Delete module
        try {
            $this->moduleModel->beginTransaction();

            $deleted = $this->moduleModel->delete($moduleId);

            if (!$deleted) {
                $this->moduleModel->rollback();
                Response::serverError('Failed to delete module');
            }

            $this->moduleModel->commit();

            Response::success([
                'deleted_module_id' => $moduleId,
                'deleted_module_title' => $module->title
            ], 'Module deleted successfully');

        } catch (\PDOException $e) {
            $this->moduleModel->rollback();
            error_log('Module deletion error: ' . $e->getMessage());
            Response::serverError('An error occurred while deleting module');
        }
    }
}
