<?php
namespace App\Controllers;

use App\Models\Project;
use App\Models\ProjectSubmission;
use App\Models\Enrollment;
use App\Utils\Response;
use App\Utils\Validator;
use App\Utils\JWTHandler;

/**
 * Project Controller
 *
 * Handles project and submission operations
 */
class ProjectController extends BaseController
{
    private Project $projectModel;
    private ProjectSubmission $submissionModel;

    public function __construct(\PDO $pdo)
    {
        parent::__construct($pdo);
        $this->projectModel = new Project($pdo);
        $this->submissionModel = new ProjectSubmission($pdo);
    }

    /**
     * List projects
     *
     * GET /api/projects?course_id=1&module_id=1&published=true
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
        $moduleId = isset($_GET['module_id']) ? (int)$_GET['module_id'] : null;
        $publishedOnly = isset($_GET['published']) ? filter_var($_GET['published'], FILTER_VALIDATE_BOOLEAN) : true;

        if ($page < 1) $page = 1;
        if ($pageSize < 1 || $pageSize > 100) $pageSize = 20;

        $offset = ($page - 1) * $pageSize;

        // Get projects
        if ($moduleId) {
            $projects = $this->projectModel->getByModule($moduleId, $pageSize, $offset);
            $total = $this->projectModel->count(['module_id' => $moduleId]);
        } elseif ($courseId) {
            if ($publishedOnly) {
                $projects = $this->projectModel->getPublishedByCourseWithModules($courseId, $pageSize, $offset);
                $total = $this->projectModel->count(['course_id' => $courseId, 'is_published' => true]);
            } else {
                $projects = $this->projectModel->getByCourse($courseId, $pageSize, $offset);
                $total = $this->projectModel->count(['course_id' => $courseId]);
            }
        } else {
            if (!$currentUser || !in_array($currentUser->role, ['superadmin', 'orgadmin', 'schooladmin', 'teacher'])) {
                $projects = $this->projectModel->all(['is_published' => true], 'title ASC', $pageSize, $offset);
                $total = $this->projectModel->count(['is_published' => true]);
            } else {
                $projects = $this->projectModel->all([], 'title ASC', $pageSize, $offset);
                $total = $this->projectModel->count();
            }
        }

        // Add submission info and quiz pass status for students
        if ($currentUser && $currentUser->role === 'student') {
            foreach ($projects as $project) {
                $project->user_submission = $this->submissionModel->getUserSubmission($currentUser->id, $project->id);
                $project->is_overdue = $this->projectModel->isOverdue($project->id);

                // Determine if the module quiz has been passed (unlocks the project)
                if (!empty($project->module_id)) {
                    $stmt = $this->pdo->prepare("
                        SELECT COALESCE(MAX(qa.passed), 0) as quiz_passed
                        FROM quiz_attempts qa
                        JOIN quizzes q ON qa.quiz_id = q.id
                        WHERE qa.user_id = ? AND q.module_id = ?
                    ");
                    $stmt->execute([$currentUser->id, $project->module_id]);
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    // Also unlock if no quiz exists for this module
                    $hasQuiz = $this->pdo->prepare("SELECT COUNT(*) FROM quizzes WHERE module_id = ? AND is_published = 1");
                    $hasQuiz->execute([$project->module_id]);
                    $quizCount = (int)$hasQuiz->fetchColumn();
                    $project->quiz_passed = ($quizCount === 0) || ($row && (bool)$row['quiz_passed']);
                } else {
                    $project->quiz_passed = true;
                }

                // Also unlock if user already submitted (don't lock them out after submitting)
                if ($project->user_submission !== null) {
                    $project->quiz_passed = true;
                }
            }
        }

        // Add statistics for instructors/admins
        if ($currentUser && in_array($currentUser->role, ['admin', 'instructor'])) {
            foreach ($projects as $project) {
                $project->statistics = $this->projectModel->getProjectStats($project->id);
            }
        }

        Response::paginated($projects, $total, $page, $pageSize, 'Projects retrieved successfully');
    }

    /**
     * Get project by ID
     *
     * GET /api/projects/:id
     *
     * @param array $params Route parameters
     * @return void
     */
    public function show(array $params): void
    {
        if (!isset($params['id'])) {
            Response::error('Project ID is required', 400);
        }

        $projectId = (int)$params['id'];
        $currentUser = JWTHandler::getCurrentUser();

        $project = $this->projectModel->find($projectId);

        if (!$project) {
            Response::notFound('Project not found');
        }

        // Check if project is published
        if (!$project->is_published) {
            if (!$currentUser || !in_array($currentUser->role, ['superadmin', 'orgadmin', 'schooladmin', 'teacher'])) {
                Response::forbidden('This project is not published');
            }
        }

        // Add submission info for authenticated users
        if ($currentUser) {
            $project->user_submission = $this->submissionModel->getUserSubmission($currentUser->id, $projectId);
            $project->is_overdue = $this->projectModel->isOverdue($projectId);

            // Add statistics for instructors/admins
            if (in_array($currentUser->role, ['admin', 'instructor'])) {
                $project->statistics = $this->projectModel->getProjectStats($projectId);
            }
        }

        Response::success([
            'project' => $project
        ], 'Project retrieved successfully');
    }

    /**
     * Create new project
     *
     * POST /api/projects
     *
     * @param array $params Route parameters
     * @return void
     */
    public function create(array $params = []): void
    {
        // Only superadmin can create projects
        $this->requireRole(['superadmin']);

        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data)) {
            $data = $_POST;
        }

        // Validate input
        $validator = Validator::make($data);

        $validator->required('course_id', 'Course ID is required')
                  ->required('title', 'Project title is required')
                  ->maxLength('title', 255, 'Title must not exceed 255 characters')
                  ->required('slug', 'Project slug is required')
                  ->maxLength('slug', 255, 'Slug must not exceed 255 characters');

        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        $courseId = (int)$data['course_id'];

        // Check slug uniqueness within course
        if ($this->projectModel->findBySlug(Validator::sanitize($data['slug']), $courseId)) {
            Response::error('Project slug already exists in this course', 409);
        }

        // Prepare project data
        $projectData = [
            'course_id' => $courseId,
            'module_id' => isset($data['module_id']) ? (int)$data['module_id'] : null,
            'title' => Validator::sanitize($data['title']),
            'slug' => Validator::sanitize($data['slug']),
            'description' => isset($data['description']) ? Validator::sanitize($data['description']) : null,
            'requirements' => isset($data['requirements']) ? Validator::sanitize($data['requirements']) : null,
            'max_score' => isset($data['max_score']) ? (int)$data['max_score'] : 100,
            'due_date' => isset($data['due_date']) ? $data['due_date'] : null,
            'is_published' => isset($data['is_published']) ? (bool)$data['is_published'] : false,
            'order' => isset($data['order']) ? (int)$data['order'] : 0
        ];

        // Create project
        try {
            $this->projectModel->beginTransaction();

            $projectId = $this->projectModel->create($projectData);

            if (!$projectId) {
                $this->projectModel->rollback();
                Response::serverError('Failed to create project');
            }

            $this->projectModel->commit();

            $project = $this->projectModel->find($projectId);

            Response::success([
                'project' => $project
            ], 'Project created successfully', 201);

        } catch (\PDOException $e) {
            $this->projectModel->rollback();
            error_log('Project creation error: ' . $e->getMessage());
            Response::serverError('An error occurred while creating project');
        }
    }

    /**
     * Update project
     *
     * PUT /api/projects/:id
     *
     * @param array $params Route parameters
     * @return void
     */
    public function update(array $params): void
    {
        // Only superadmin can update projects
        $this->requireRole(['superadmin']);

        if (!isset($params['id'])) {
            Response::error('Project ID is required', 400);
        }

        $projectId = (int)$params['id'];

        $project = $this->projectModel->find($projectId);

        if (!$project) {
            Response::notFound('Project not found');
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data)) {
            $data = $_POST;
        }

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
        if (isset($data['slug']) && $data['slug'] !== $project->slug) {
            if ($this->projectModel->findBySlug(Validator::sanitize($data['slug']), $project->course_id)) {
                Response::error('Project slug already exists in this course', 409);
            }
        }

        // Prepare update data
        $allowedFields = [
            'title', 'slug', 'description', 'requirements', 'max_score',
            'due_date', 'is_published', 'order', 'module_id'
        ];

        $updateData = [];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                if (in_array($field, ['title', 'slug', 'description', 'requirements'])) {
                    $updateData[$field] = Validator::sanitize($data[$field]);
                } else {
                    $updateData[$field] = $data[$field];
                }
            }
        }

        if (empty($updateData)) {
            Response::error('No valid fields provided for update', 400);
        }

        // Update project
        try {
            $updated = $this->projectModel->update($projectId, $updateData);

            if (!$updated) {
                Response::serverError('Failed to update project');
            }

            $updatedProject = $this->projectModel->find($projectId);

            Response::success([
                'project' => $updatedProject
            ], 'Project updated successfully');

        } catch (\PDOException $e) {
            error_log('Project update error: ' . $e->getMessage());
            Response::serverError('An error occurred while updating project');
        }
    }

    /**
     * Delete project
     *
     * DELETE /api/projects/:id
     *
     * @param array $params Route parameters
     * @return void
     */
    public function delete(array $params): void
    {
        // Only superadmin can delete projects
        $this->requireRole(['superadmin']);

        if (!isset($params['id'])) {
            Response::error('Project ID is required', 400);
        }

        $projectId = (int)$params['id'];

        $project = $this->projectModel->find($projectId);

        if (!$project) {
            Response::notFound('Project not found');
        }

        // Delete project
        try {
            $this->projectModel->beginTransaction();

            $deleted = $this->projectModel->delete($projectId);

            if (!$deleted) {
                $this->projectModel->rollback();
                Response::serverError('Failed to delete project');
            }

            $this->projectModel->commit();

            Response::success([
                'deleted_project_id' => $projectId,
                'deleted_project_title' => $project->title
            ], 'Project deleted successfully');

        } catch (\PDOException $e) {
            $this->projectModel->rollback();
            error_log('Project deletion error: ' . $e->getMessage());
            Response::serverError('An error occurred while deleting project');
        }
    }

    /**
     * Submit project
     *
     * POST /api/projects/:id/submit
     *
     * @param array $params Route parameters
     * @return void
     */
    public function submitProject(array $params): void
    {
        $currentUser = $this->getCurrentUser();

        if (!isset($params['id'])) {
            Response::error('Project ID is required', 400);
        }

        $projectId = (int)$params['id'];

        // Accept JSON body or form data
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data)) {
            $data = $_POST;
        }

        $project = $this->projectModel->find($projectId);

        if (!$project || !$project->is_published) {
            Response::notFound('Project not found');
        }

        // Must have either submission_url or submission_text
        if (empty($data['submission_url']) && empty($data['submission_text'])) {
            Response::error('Either submission_url or submission_text is required', 400);
        }

        // Create submission
        try {
            $this->submissionModel->beginTransaction();

            $submissionId = $this->submissionModel->submitProject([
                'project_id' => $projectId,
                'user_id' => $currentUser->id,
                'submission_file_url' => !empty($data['submission_url']) ? $data['submission_url'] : null,
                'submission_text' => !empty($data['submission_text']) ? Validator::sanitize($data['submission_text']) : null
            ]);

            if (!$submissionId) {
                $this->submissionModel->rollback();
                Response::serverError('Failed to submit project');
            }

            $this->submissionModel->commit();

            // Recompute course progress: a submission is the final missing piece
            // for some students, so this triggers the auto-issue path.
            try {
                if (!empty($project->course_id)) {
                    $enrollmentModel = new Enrollment($this->pdo);
                    $enrollmentModel->calculateProgress((int)$currentUser->id, (int)$project->course_id);
                }
            } catch (\Exception $e) {
                error_log('Project submit: progress recalc failed (non-fatal): ' . $e->getMessage());
            }

            $submission = $this->submissionModel->getSubmissionWithDetails($submissionId);

            Response::success([
                'submission' => $submission
            ], 'Project submitted successfully', 201);

        } catch (\PDOException $e) {
            $this->submissionModel->rollback();
            error_log('Project submission error: ' . $e->getMessage());
            Response::serverError('An error occurred while submitting project');
        }
    }

    /**
     * Get project submissions
     *
     * GET /api/projects/:id/submissions
     *
     * @param array $params Route parameters
     * @return void
     */
    public function getSubmissions(array $params): void
    {
        $currentUser = $this->getCurrentUser();

        if (!isset($params['id'])) {
            Response::error('Project ID is required', 400);
        }

        $projectId = (int)$params['id'];

        $project = $this->projectModel->find($projectId);

        if (!$project) {
            Response::notFound('Project not found');
        }

        $status = isset($_GET['status']) ? $_GET['status'] : null;

        // Students can only see their own submission
        if ($currentUser->role === 'student') {
            $submission = $this->submissionModel->getUserSubmission($currentUser->id, $projectId);
            $submissions = $submission ? [$submission] : [];
        } else {
            // Instructors/admins see all submissions
            $submissions = $this->submissionModel->getByProject($projectId, $status);
        }

        Response::success([
            'submissions' => $submissions,
            'project_title' => $project->title
        ], 'Project submissions retrieved successfully');
    }

    /**
     * Get pending project submissions for the caller's grading queue.
     *
     * GET /api/projects/submissions/pending
     * Query params: ?limit=50&offset=0&course_id=1&school_id=2
     *
     * Authorization: teacher/instructor/schooladmin roles are locked to their own
     * primary_school_id regardless of the school_id param. Admins can pass any school_id.
     *
     * @param array $params Route parameters
     * @return void
     */
    public function pendingSubmissions(array $params = []): void
    {
        $this->requireRole(['superadmin', 'orgadmin', 'schooladmin', 'teacher']);
        $currentUser = $this->getCurrentUser();

        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : null;
        $schoolId = isset($_GET['school_id']) ? (int)$_GET['school_id'] : null;

        if ($limit < 1 || $limit > 100) $limit = 50;
        if ($offset < 0) $offset = 0;

        // Teachers/schooladmins are locked to their own school.
        if ($currentUser && in_array($currentUser->role, ['teacher', 'instructor', 'schooladmin'], true)) {
            $schoolId = isset($currentUser->primary_school_id) ? (int)$currentUser->primary_school_id : null;
        }

        try {
            $submissions = $this->submissionModel->getPendingSubmissions($courseId, $schoolId, $limit, $offset);

            Response::success([
                'submissions' => $submissions,
                'total' => count($submissions),
                'limit' => $limit,
                'offset' => $offset
            ], 'Pending submissions retrieved successfully');
        } catch (\Exception $e) {
            error_log('Pending submissions error: ' . $e->getMessage());
            Response::serverError('An error occurred while retrieving pending submissions');
        }
    }

    /**
     * Grade project submission
     *
     * POST /api/projects/submissions/:id/grade
     *
     * @param array $params Route parameters
     * @return void
     */
    public function gradeSubmission(array $params): void
    {
        // Only instructors/admins can grade
        $this->requireRole(['superadmin', 'orgadmin', 'schooladmin', 'teacher']);
        $currentUser = $this->getCurrentUser();

        if (!isset($params['id'])) {
            Response::error('Submission ID is required', 400);
        }

        $submissionId = (int)$params['id'];
        $data = $_POST;

        $submission = $this->submissionModel->find($submissionId);

        if (!$submission) {
            Response::notFound('Submission not found');
        }

        // Validate input
        $validator = Validator::make($data);
        $validator->required('score', 'Score is required');

        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        $score = (float)$data['score'];
        $feedback = isset($data['feedback']) ? Validator::sanitize($data['feedback']) : null;

        // Grade submission
        try {
            $graded = $this->submissionModel->gradeSubmission(
                $submissionId,
                $score,
                $feedback,
                $currentUser->id
            );

            if (!$graded) {
                Response::serverError('Failed to grade submission');
            }

            $gradedSubmission = $this->submissionModel->getSubmissionWithDetails($submissionId);

            Response::success([
                'submission' => $gradedSubmission
            ], 'Submission graded successfully');

        } catch (\PDOException $e) {
            error_log('Grading error: ' . $e->getMessage());
            Response::serverError('An error occurred while grading submission');
        }
    }
}
