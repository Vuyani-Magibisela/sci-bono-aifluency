<?php
namespace App\Controllers;

use App\Models\Enrollment;
use App\Models\Course;
use App\Utils\Response;
use App\Utils\Validator;
use App\Utils\JWTHandler;

/**
 * Enrollment Controller
 *
 * Handles course enrollment operations
 */
class EnrollmentController
{
    private Enrollment $enrollmentModel;
    private Course $courseModel;
    private \PDO $pdo;

    public function __construct()
    {
        global $pdo;
        $this->pdo = $pdo;
        $this->enrollmentModel = new Enrollment($pdo);
        $this->courseModel = new Course($pdo);
    }

    /**
     * Get current authenticated user
     *
     * @return object|null
     */
    private function getCurrentUser(): ?object
    {
        $currentUser = JWTHandler::getCurrentUser();

        if (!$currentUser) {
            Response::unauthorized('Authentication required');
        }

        $token = JWTHandler::extractTokenFromHeader();
        if ($token && JWTHandler::isTokenBlacklisted($token, $this->pdo)) {
            Response::unauthorized('Token has been revoked. Please login again.');
        }

        return $currentUser;
    }

    /**
     * Check if current user has required role(s)
     *
     * @param string|array $roles Required role(s)
     * @return void
     */
    private function requireRole($roles): void
    {
        $currentUser = $this->getCurrentUser();

        if (is_string($roles)) {
            $roles = [$roles];
        }

        if (!in_array($currentUser->role, $roles, true)) {
            Response::forbidden('You do not have permission to perform this action');
        }
    }

    /**
     * List enrollments
     *
     * GET /api/enrollments?user_id=1&course_id=1&status=active
     *
     * @param array $params Route parameters
     * @return void
     */
    public function index(array $params = []): void
    {
        $currentUser = $this->getCurrentUser();

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $pageSize = isset($_GET['pageSize']) ? (int)$_GET['pageSize'] : 20;
        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
        $courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : null;
        $status = isset($_GET['status']) ? $_GET['status'] : null;

        if ($page < 1) $page = 1;
        if ($pageSize < 1 || $pageSize > 100) $pageSize = 20;

        $offset = ($page - 1) * $pageSize;

        // Authorization: students can only see own enrollments
        if ($currentUser->role === 'student') {
            $userId = $currentUser->id;
        }

        // Get enrollments based on filters
        if ($userId && $courseId) {
            $enrollment = $this->enrollmentModel->getUserEnrollment($userId, $courseId);
            $enrollments = $enrollment ? [$enrollment] : [];
            $total = $enrollment ? 1 : 0;
        } elseif ($userId) {
            $enrollments = $this->enrollmentModel->getByUser($userId, $status, $pageSize, $offset);
            $total = $this->enrollmentModel->count(['user_id' => $userId]);
        } elseif ($courseId) {
            // Only instructors/admins can see all enrollments for a course
            $this->requireRole(['admin', 'instructor']);
            $enrollments = $this->enrollmentModel->getByCourse($courseId, $status, $pageSize, $offset);
            $total = $this->enrollmentModel->count(['course_id' => $courseId]);
        } else {
            // Only admins can see all enrollments
            $this->requireRole('admin');
            $enrollments = $this->enrollmentModel->all([], 'enrollment_date DESC', $pageSize, $offset);
            $total = $this->enrollmentModel->count();
        }

        // Enhance with course details
        foreach ($enrollments as $enrollment) {
            $enrollment->course = $this->courseModel->find($enrollment->course_id);
        }

        Response::paginated($enrollments, $total, $page, $pageSize, 'Enrollments retrieved successfully');
    }

    /**
     * Get enrollment by ID
     *
     * GET /api/enrollments/:id
     *
     * @param array $params Route parameters
     * @return void
     */
    public function show(array $params): void
    {
        if (!isset($params['id'])) {
            Response::error('Enrollment ID is required', 400);
        }

        $enrollmentId = (int)$params['id'];
        $currentUser = $this->getCurrentUser();

        $enrollment = $this->enrollmentModel->getEnrollmentWithCourse($enrollmentId);

        if (!$enrollment) {
            Response::notFound('Enrollment not found');
        }

        // Authorization: students can only see own enrollments
        if ($currentUser->role === 'student' && $enrollment->user_id != $currentUser->id) {
            Response::forbidden('You do not have permission to view this enrollment');
        }

        Response::success([
            'enrollment' => $enrollment
        ], 'Enrollment retrieved successfully');
    }

    /**
     * Enroll in a course
     *
     * POST /api/enrollments
     *
     * @param array $params Route parameters
     * @return void
     */
    public function create(array $params = []): void
    {
        $currentUser = $this->getCurrentUser();
        $data = $_POST;

        // Validate input
        $validator = Validator::make($data);
        $validator->required('course_id', 'Course ID is required');

        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        $courseId = (int)$data['course_id'];

        // Check if course exists and is published
        $course = $this->courseModel->find($courseId);

        if (!$course) {
            Response::notFound('Course not found');
        }

        if (!$course->is_published) {
            Response::error('This course is not available for enrollment', 400);
        }

        // Check if already enrolled
        $existing = $this->enrollmentModel->getUserEnrollment($currentUser->id, $courseId);

        if ($existing && in_array($existing->status, ['active', 'completed'])) {
            Response::error('You are already enrolled in this course', 409, [
                'enrollment' => $existing
            ]);
        }

        // Create enrollment
        try {
            $this->enrollmentModel->beginTransaction();

            $enrollmentId = $this->enrollmentModel->enrollUser($currentUser->id, $courseId);

            if (!$enrollmentId) {
                $this->enrollmentModel->rollback();
                Response::serverError('Failed to create enrollment');
            }

            $this->enrollmentModel->commit();

            $enrollment = $this->enrollmentModel->getEnrollmentWithCourse($enrollmentId);

            Response::success([
                'enrollment' => $enrollment
            ], 'Successfully enrolled in course', 201);

        } catch (\PDOException $e) {
            $this->enrollmentModel->rollback();
            error_log('Enrollment creation error: ' . $e->getMessage());
            Response::serverError('An error occurred while enrolling in course');
        }
    }

    /**
     * Update enrollment progress
     *
     * PUT /api/enrollments/:id
     *
     * @param array $params Route parameters
     * @return void
     */
    public function update(array $params): void
    {
        if (!isset($params['id'])) {
            Response::error('Enrollment ID is required', 400);
        }

        $enrollmentId = (int)$params['id'];
        $currentUser = $this->getCurrentUser();

        $enrollment = $this->enrollmentModel->find($enrollmentId);

        if (!$enrollment) {
            Response::notFound('Enrollment not found');
        }

        // Authorization: students can only update own enrollments
        if ($currentUser->role === 'student' && $enrollment->user_id != $currentUser->id) {
            Response::forbidden('You do not have permission to update this enrollment');
        }

        $data = $_POST;

        // For students: only allow updating last_accessed_at
        // For admins/instructors: allow updating status and completion
        $updateData = [];

        if (in_array($currentUser->role, ['admin', 'instructor'])) {
            if (isset($data['status'])) {
                $validator = Validator::make($data);
                $validator->in('status', ['active', 'completed', 'inactive']);

                if ($validator->fails()) {
                    Response::validationError($validator->errors());
                }

                $updateData['status'] = $data['status'];
            }

            if (isset($data['completion_percentage'])) {
                $updateData['completion_percentage'] = (float)$data['completion_percentage'];

                if ($updateData['completion_percentage'] >= 100) {
                    $updateData['completion_date'] = date('Y-m-d H:i:s');
                    $updateData['status'] = 'completed';
                }
            }
        }

        // Update last accessed timestamp
        $updateData['last_accessed_at'] = date('Y-m-d H:i:s');

        if (empty($updateData)) {
            Response::error('No valid fields provided for update', 400);
        }

        // Update enrollment
        try {
            $updated = $this->enrollmentModel->update($enrollmentId, $updateData);

            if (!$updated) {
                Response::serverError('Failed to update enrollment');
            }

            $updatedEnrollment = $this->enrollmentModel->getEnrollmentWithCourse($enrollmentId);

            Response::success([
                'enrollment' => $updatedEnrollment
            ], 'Enrollment updated successfully');

        } catch (\PDOException $e) {
            error_log('Enrollment update error: ' . $e->getMessage());
            Response::serverError('An error occurred while updating enrollment');
        }
    }

    /**
     * Unenroll from course
     *
     * DELETE /api/enrollments/:id
     *
     * @param array $params Route parameters
     * @return void
     */
    public function delete(array $params): void
    {
        if (!isset($params['id'])) {
            Response::error('Enrollment ID is required', 400);
        }

        $enrollmentId = (int)$params['id'];
        $currentUser = $this->getCurrentUser();

        $enrollment = $this->enrollmentModel->find($enrollmentId);

        if (!$enrollment) {
            Response::notFound('Enrollment not found');
        }

        // Authorization: students can unenroll themselves, admins can unenroll anyone
        $isSelf = ($currentUser->id == $enrollment->user_id);
        $isAdmin = ($currentUser->role === 'admin');

        if (!$isSelf && !$isAdmin) {
            Response::forbidden('You do not have permission to delete this enrollment');
        }

        // Set status to inactive instead of deleting
        try {
            $updated = $this->enrollmentModel->update($enrollmentId, [
                'status' => 'inactive'
            ]);

            if (!$updated) {
                Response::serverError('Failed to unenroll from course');
            }

            Response::success([
                'unenrolled_enrollment_id' => $enrollmentId,
                'course_id' => $enrollment->course_id
            ], 'Successfully unenrolled from course');

        } catch (\PDOException $e) {
            error_log('Enrollment deletion error: ' . $e->getMessage());
            Response::serverError('An error occurred while unenrolling from course');
        }
    }

    /**
     * Calculate and update enrollment progress
     *
     * POST /api/enrollments/:id/calculate-progress
     *
     * @param array $params Route parameters
     * @return void
     */
    public function calculateProgress(array $params): void
    {
        if (!isset($params['id'])) {
            Response::error('Enrollment ID is required', 400);
        }

        $enrollmentId = (int)$params['id'];
        $currentUser = $this->getCurrentUser();

        $enrollment = $this->enrollmentModel->find($enrollmentId);

        if (!$enrollment) {
            Response::notFound('Enrollment not found');
        }

        // Authorization: student can calculate own progress, admins/instructors can calculate any
        if ($currentUser->role === 'student' && $enrollment->user_id != $currentUser->id) {
            Response::forbidden('You do not have permission to update this enrollment');
        }

        try {
            $percentage = $this->enrollmentModel->calculateProgress($enrollment->user_id, $enrollment->course_id);

            if ($percentage === null) {
                Response::serverError('Failed to calculate progress');
            }

            $updatedEnrollment = $this->enrollmentModel->find($enrollmentId);

            Response::success([
                'enrollment' => $updatedEnrollment,
                'completion_percentage' => $percentage
            ], 'Progress calculated successfully');

        } catch (\PDOException $e) {
            error_log('Progress calculation error: ' . $e->getMessage());
            Response::serverError('An error occurred while calculating progress');
        }
    }
}
