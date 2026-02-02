<?php
namespace App\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Utils\Response;
use App\Utils\Validator;
use App\Utils\JWTHandler;

/**
 * Course Controller
 *
 * Handles course management operations
 */
class CourseController extends BaseController
{
    private Course $courseModel;
    private Enrollment $enrollmentModel;

    public function __construct(\PDO $pdo)
    {
        parent::__construct($pdo);
        $this->courseModel = new Course($pdo);
        $this->enrollmentModel = new Enrollment($pdo);
    }

    /**
     * List all courses
     *
     * GET /api/courses?page=1&pageSize=20&published=true&featured=true&search=AI
     *
     * @param array $params Route parameters
     * @return void
     */
    public function index(array $params = []): void
    {
        // Public endpoint - no authentication required for published courses
        $currentUser = JWTHandler::getCurrentUser();

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $pageSize = isset($_GET['pageSize']) ? (int)$_GET['pageSize'] : 20;
        $publishedOnly = isset($_GET['published']) ? filter_var($_GET['published'], FILTER_VALIDATE_BOOLEAN) : true;
        $featuredOnly = isset($_GET['featured']) ? filter_var($_GET['featured'], FILTER_VALIDATE_BOOLEAN) : false;
        $search = isset($_GET['search']) ? $_GET['search'] : null;
        $instructorId = isset($_GET['instructor_id']) ? (int)$_GET['instructor_id'] : null;

        if ($page < 1) $page = 1;
        if ($pageSize < 1 || $pageSize > 100) $pageSize = 20;

        $offset = ($page - 1) * $pageSize;

        // Get courses based on filters
        if ($search) {
            $courses = $this->courseModel->searchCourses($search, $publishedOnly, $pageSize, $offset);
            $total = count($this->courseModel->searchCourses($search, $publishedOnly));
        } elseif ($featuredOnly) {
            $courses = $this->courseModel->getFeatured($pageSize);
            $total = count($this->courseModel->getFeatured());
        } elseif ($instructorId) {
            $courses = $this->courseModel->getByInstructor($instructorId, $pageSize, $offset);
            $total = $this->courseModel->count(['instructor_id' => $instructorId]);
        } elseif ($publishedOnly) {
            $courses = $this->courseModel->getPublished($pageSize, $offset);
            $total = $this->courseModel->count(['is_published' => true]);
        } else {
            // Only admins/instructors can see unpublished courses
            if (!$currentUser || !in_array($currentUser->role, ['admin', 'instructor'])) {
                $courses = $this->courseModel->getPublished($pageSize, $offset);
                $total = $this->courseModel->count(['is_published' => true]);
            } else {
                $courses = $this->courseModel->all([], 'title ASC', $pageSize, $offset);
                $total = $this->courseModel->count();
            }
        }

        // Add enrollment status for authenticated users
        if ($currentUser) {
            foreach ($courses as $course) {
                $enrollment = $this->enrollmentModel->getUserEnrollment($currentUser->id, $course->id);
                $course->is_enrolled = $enrollment !== null;
                $course->enrollment_status = $enrollment ? $enrollment->status : null;
                $course->completion_percentage = $enrollment ? $enrollment->completion_percentage : 0;
            }
        }

        Response::paginated($courses, $total, $page, $pageSize, 'Courses retrieved successfully');
    }

    /**
     * Get course by ID
     *
     * GET /api/courses/:id
     *
     * @param array $params Route parameters
     * @return void
     */
    public function show(array $params): void
    {
        if (!isset($params['id'])) {
            Response::error('Course ID is required', 400);
        }

        $courseId = (int)$params['id'];
        $currentUser = JWTHandler::getCurrentUser();

        // Get course with modules
        $course = $this->courseModel->getCourseWithModules($courseId);

        if (!$course) {
            Response::notFound('Course not found');
        }

        // Check if course is published (unless admin/instructor)
        if (!$course->is_published) {
            if (!$currentUser || !in_array($currentUser->role, ['admin', 'instructor'])) {
                Response::forbidden('This course is not published');
            }
        }

        // Get course statistics
        $course->statistics = $this->courseModel->getCourseStats($courseId);

        // Add enrollment info for authenticated users
        if ($currentUser) {
            $enrollment = $this->enrollmentModel->getUserEnrollment($currentUser->id, $courseId);
            $course->is_enrolled = $enrollment !== null;
            $course->enrollment_status = $enrollment ? $enrollment->status : null;
            $course->completion_percentage = $enrollment ? $enrollment->completion_percentage : 0;
        }

        Response::success([
            'course' => $course
        ], 'Course retrieved successfully');
    }

    /**
     * Create new course
     *
     * POST /api/courses
     *
     * @param array $params Route parameters
     * @return void
     */
    public function create(array $params = []): void
    {
        // Only admin and instructor can create courses
        $this->requireRole(['admin', 'instructor']);
        $currentUser = $this->getCurrentUser();

        $data = $_POST;

        // Validate input
        $validator = Validator::make($data);

        $validator->required('title', 'Course title is required')
                  ->maxLength('title', 255, 'Title must not exceed 255 characters');

        $validator->required('slug', 'Course slug is required')
                  ->maxLength('slug', 255, 'Slug must not exceed 255 characters');

        if (isset($data['description'])) {
            $validator->maxLength('description', 5000, 'Description must not exceed 5000 characters');
        }

        if (isset($data['level'])) {
            $validator->in('level', ['beginner', 'intermediate', 'advanced'], 'Invalid level specified');
        }

        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        // Check slug uniqueness
        if ($this->courseModel->findBySlug(Validator::sanitize($data['slug']))) {
            Response::error('Course slug already exists', 409, [
                'slug' => 'This slug is already in use'
            ]);
        }

        // Prepare course data
        $courseData = [
            'title' => Validator::sanitize($data['title']),
            'slug' => Validator::sanitize($data['slug']),
            'description' => isset($data['description']) ? Validator::sanitize($data['description']) : null,
            'objectives' => isset($data['objectives']) ? Validator::sanitize($data['objectives']) : null,
            'level' => isset($data['level']) ? $data['level'] : 'beginner',
            'duration_hours' => isset($data['duration_hours']) ? (int)$data['duration_hours'] : 0,
            'thumbnail_url' => isset($data['thumbnail_url']) ? $data['thumbnail_url'] : null,
            'instructor_id' => $currentUser->id,
            'is_published' => isset($data['is_published']) ? (bool)$data['is_published'] : false,
            'is_featured' => isset($data['is_featured']) ? (bool)$data['is_featured'] : false,
            'order' => isset($data['order']) ? (int)$data['order'] : 0
        ];

        // Create course
        try {
            $this->courseModel->beginTransaction();

            $courseId = $this->courseModel->create($courseData);

            if (!$courseId) {
                $this->courseModel->rollback();
                Response::serverError('Failed to create course');
            }

            $this->courseModel->commit();

            $course = $this->courseModel->find($courseId);

            Response::success([
                'course' => $course
            ], 'Course created successfully', 201);

        } catch (\PDOException $e) {
            $this->courseModel->rollback();
            error_log('Course creation error: ' . $e->getMessage());
            Response::serverError('An error occurred while creating course');
        }
    }

    /**
     * Update course
     *
     * PUT /api/courses/:id
     *
     * @param array $params Route parameters
     * @return void
     */
    public function update(array $params): void
    {
        if (!isset($params['id'])) {
            Response::error('Course ID is required', 400);
        }

        $courseId = (int)$params['id'];

        // Only admin and course instructor can update
        $currentUser = $this->getCurrentUser();
        $course = $this->courseModel->find($courseId);

        if (!$course) {
            Response::notFound('Course not found');
        }

        $isInstructor = ($currentUser->id == $course->instructor_id);
        $isAdmin = ($currentUser->role === 'admin');

        if (!$isInstructor && !$isAdmin) {
            Response::forbidden('You do not have permission to update this course');
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

        if (isset($data['description'])) {
            $validator->maxLength('description', 5000);
        }

        if (isset($data['level'])) {
            $validator->in('level', ['beginner', 'intermediate', 'advanced']);
        }

        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        // Check slug uniqueness if changed
        if (isset($data['slug']) && $data['slug'] !== $course->slug) {
            if ($this->courseModel->findBySlug(Validator::sanitize($data['slug']))) {
                Response::error('Course slug already exists', 409);
            }
        }

        // Prepare update data
        $allowedFields = [
            'title', 'slug', 'description', 'objectives', 'level',
            'duration_hours', 'thumbnail_url', 'is_published', 'is_featured', 'order'
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

        // Update course
        try {
            $updated = $this->courseModel->update($courseId, $updateData);

            if (!$updated) {
                Response::serverError('Failed to update course');
            }

            $updatedCourse = $this->courseModel->find($courseId);

            Response::success([
                'course' => $updatedCourse
            ], 'Course updated successfully');

        } catch (\PDOException $e) {
            error_log('Course update error: ' . $e->getMessage());
            Response::serverError('An error occurred while updating course');
        }
    }

    /**
     * Delete course
     *
     * DELETE /api/courses/:id
     *
     * @param array $params Route parameters
     * @return void
     */
    public function delete(array $params): void
    {
        // Only admin can delete courses
        $this->requireRole('admin');

        if (!isset($params['id'])) {
            Response::error('Course ID is required', 400);
        }

        $courseId = (int)$params['id'];

        $course = $this->courseModel->find($courseId);

        if (!$course) {
            Response::notFound('Course not found');
        }

        // Delete course
        try {
            $this->courseModel->beginTransaction();

            $deleted = $this->courseModel->delete($courseId);

            if (!$deleted) {
                $this->courseModel->rollback();
                Response::serverError('Failed to delete course');
            }

            $this->courseModel->commit();

            Response::success([
                'deleted_course_id' => $courseId,
                'deleted_course_title' => $course->title
            ], 'Course deleted successfully');

        } catch (\PDOException $e) {
            $this->courseModel->rollback();
            error_log('Course deletion error: ' . $e->getMessage());
            Response::serverError('An error occurred while deleting course');
        }
    }
}
