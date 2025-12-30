<?php
namespace App\Controllers;

use App\Models\Certificate;
use App\Models\Enrollment;
use App\Utils\Response;
use App\Utils\Validator;
use App\Utils\JWTHandler;

/**
 * Certificate Controller
 *
 * Handles certificate operations
 */
class CertificateController
{
    private Certificate $certificateModel;
    private Enrollment $enrollmentModel;
    private \PDO $pdo;

    public function __construct()
    {
        global $pdo;
        $this->pdo = $pdo;
        $this->certificateModel = new Certificate($pdo);
        $this->enrollmentModel = new Enrollment($pdo);
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
     * List certificates
     *
     * GET /api/certificates?user_id=1&course_id=1
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

        if ($page < 1) $page = 1;
        if ($pageSize < 1 || $pageSize > 100) $pageSize = 20;

        $offset = ($page - 1) * $pageSize;

        // Authorization: students can only see own certificates
        if ($currentUser->role === 'student') {
            $userId = $currentUser->id;
        }

        // Get certificates based on filters
        if ($userId && $courseId) {
            $certificate = $this->certificateModel->getUserCourseCertificate($userId, $courseId);
            $certificates = $certificate ? [$certificate] : [];
            $total = $certificate ? 1 : 0;
        } elseif ($userId) {
            $certificates = $this->certificateModel->getByUser($userId, $pageSize, $offset);
            $total = $this->certificateModel->count(['user_id' => $userId]);
        } elseif ($courseId) {
            // Only instructors/admins can see all certificates for a course
            $this->requireRole(['admin', 'instructor']);
            $certificates = $this->certificateModel->getByCourse($courseId, $pageSize, $offset);
            $total = $this->certificateModel->count(['course_id' => $courseId]);
        } else {
            // Only admins can see all certificates
            $this->requireRole('admin');
            $certificates = $this->certificateModel->getRecent($pageSize);
            $total = $this->certificateModel->count();
        }

        Response::paginated($certificates, $total, $page, $pageSize, 'Certificates retrieved successfully');
    }

    /**
     * Get certificate by ID
     *
     * GET /api/certificates/:id
     *
     * @param array $params Route parameters
     * @return void
     */
    public function show(array $params): void
    {
        if (!isset($params['id'])) {
            Response::error('Certificate ID is required', 400);
        }

        $certificateId = (int)$params['id'];
        $currentUser = $this->getCurrentUser();

        $certificate = $this->certificateModel->getCertificateWithDetails($certificateId);

        if (!$certificate) {
            Response::notFound('Certificate not found');
        }

        // Authorization: students can only see own certificates
        if ($currentUser->role === 'student' && $certificate->user_id != $currentUser->id) {
            Response::forbidden('You do not have permission to view this certificate');
        }

        Response::success([
            'certificate' => $certificate
        ], 'Certificate retrieved successfully');
    }

    /**
     * Issue certificate
     *
     * POST /api/certificates
     *
     * @param array $params Route parameters
     * @return void
     */
    public function create(array $params = []): void
    {
        // Only admin and instructor can issue certificates
        $this->requireRole(['admin', 'instructor']);

        $data = $_POST;

        // Validate input
        $validator = Validator::make($data);

        $validator->required('user_id', 'User ID is required')
                  ->required('course_id', 'Course ID is required');

        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        $userId = (int)$data['user_id'];
        $courseId = (int)$data['course_id'];

        // Check if certificate already exists
        $existing = $this->certificateModel->getUserCourseCertificate($userId, $courseId);

        if ($existing) {
            Response::error('Certificate already issued for this user and course', 409, [
                'certificate' => $existing
            ]);
        }

        // Check if user is eligible for certificate
        if (!$this->certificateModel->isEligibleForCertificate($userId, $courseId)) {
            Response::error('User has not completed the course requirements', 400, [
                'message' => 'User must complete 100% of the course to be eligible for a certificate'
            ]);
        }

        $certificateUrl = isset($data['certificate_url']) ? $data['certificate_url'] : null;

        // Issue certificate
        try {
            $this->certificateModel->beginTransaction();

            $certificateId = $this->certificateModel->issueCertificate($userId, $courseId, $certificateUrl);

            if (!$certificateId) {
                $this->certificateModel->rollback();
                Response::serverError('Failed to issue certificate');
            }

            $this->certificateModel->commit();

            $certificate = $this->certificateModel->getCertificateWithDetails($certificateId);

            // Check for achievement unlocks (Phase 6)
            $newAchievements = [];
            try {
                $achievementModel = new \App\Models\Achievement($this->pdo);
                $newAchievements = $achievementModel->checkAndUnlock($userId, 'certificate_issued', [
                    'course_id' => $courseId,
                    'certificate_id' => $certificateId
                ]);
            } catch (\Exception $e) {
                error_log('Achievement check error: ' . $e->getMessage());
                // Don't fail certificate issuance if achievement check fails
            }

            Response::success([
                'certificate' => $certificate,
                'achievements_unlocked' => $newAchievements
            ], 'Certificate issued successfully', 201);

        } catch (\PDOException $e) {
            $this->certificateModel->rollback();
            error_log('Certificate issuance error: ' . $e->getMessage());
            Response::serverError('An error occurred while issuing certificate');
        }
    }

    /**
     * Update certificate
     *
     * PUT /api/certificates/:id
     *
     * @param array $params Route parameters
     * @return void
     */
    public function update(array $params): void
    {
        // Only admin can update certificates
        $this->requireRole('admin');

        if (!isset($params['id'])) {
            Response::error('Certificate ID is required', 400);
        }

        $certificateId = (int)$params['id'];

        $certificate = $this->certificateModel->find($certificateId);

        if (!$certificate) {
            Response::notFound('Certificate not found');
        }

        $data = $_POST;

        // Only allow updating certificate_url
        if (!isset($data['certificate_url'])) {
            Response::error('No valid fields provided for update', 400);
        }

        $updateData = [
            'certificate_url' => $data['certificate_url']
        ];

        // Update certificate
        try {
            $updated = $this->certificateModel->update($certificateId, $updateData);

            if (!$updated) {
                Response::serverError('Failed to update certificate');
            }

            $updatedCertificate = $this->certificateModel->getCertificateWithDetails($certificateId);

            Response::success([
                'certificate' => $updatedCertificate
            ], 'Certificate updated successfully');

        } catch (\PDOException $e) {
            error_log('Certificate update error: ' . $e->getMessage());
            Response::serverError('An error occurred while updating certificate');
        }
    }

    /**
     * Delete certificate
     *
     * DELETE /api/certificates/:id
     *
     * @param array $params Route parameters
     * @return void
     */
    public function delete(array $params): void
    {
        // Only admin can delete certificates
        $this->requireRole('admin');

        if (!isset($params['id'])) {
            Response::error('Certificate ID is required', 400);
        }

        $certificateId = (int)$params['id'];

        $certificate = $this->certificateModel->find($certificateId);

        if (!$certificate) {
            Response::notFound('Certificate not found');
        }

        // Delete certificate
        try {
            $this->certificateModel->beginTransaction();

            $deleted = $this->certificateModel->delete($certificateId);

            if (!$deleted) {
                $this->certificateModel->rollback();
                Response::serverError('Failed to delete certificate');
            }

            $this->certificateModel->commit();

            Response::success([
                'deleted_certificate_id' => $certificateId,
                'certificate_number' => $certificate->certificate_number
            ], 'Certificate deleted successfully');

        } catch (\PDOException $e) {
            $this->certificateModel->rollback();
            error_log('Certificate deletion error: ' . $e->getMessage());
            Response::serverError('An error occurred while deleting certificate');
        }
    }

    /**
     * Verify certificate
     *
     * GET /api/certificates/verify/:certificate_number
     *
     * @param array $params Route parameters
     * @return void
     */
    public function verify(array $params): void
    {
        // Public endpoint - no authentication required

        if (!isset($params['certificate_number'])) {
            Response::error('Certificate number is required', 400);
        }

        $certificateNumber = $params['certificate_number'];

        $verification = $this->certificateModel->verifyCertificate($certificateNumber);

        if (!$verification['valid']) {
            Response::error($verification['message'], 404);
        }

        Response::success([
            'valid' => true,
            'certificate' => $verification['certificate'],
            'message' => $verification['message']
        ], 'Certificate verified successfully');
    }

    /**
     * Request certificate issuance (for students)
     *
     * POST /api/certificates/request
     *
     * @param array $params Route parameters
     * @return void
     */
    public function requestCertificate(array $params = []): void
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

        // Check if certificate already exists
        $existing = $this->certificateModel->getUserCourseCertificate($currentUser->id, $courseId);

        if ($existing) {
            Response::error('Certificate already issued for this course', 409, [
                'certificate' => $existing
            ]);
        }

        // Check if user is eligible for certificate
        if (!$this->certificateModel->isEligibleForCertificate($currentUser->id, $courseId)) {
            Response::error('You have not completed the course requirements', 400, [
                'message' => 'You must complete 100% of the course to request a certificate'
            ]);
        }

        // Issue certificate automatically
        try {
            $this->certificateModel->beginTransaction();

            $certificateId = $this->certificateModel->issueCertificate($currentUser->id, $courseId);

            if (!$certificateId) {
                $this->certificateModel->rollback();
                Response::serverError('Failed to issue certificate');
            }

            $this->certificateModel->commit();

            $certificate = $this->certificateModel->getCertificateWithDetails($certificateId);

            Response::success([
                'certificate' => $certificate
            ], 'Certificate issued successfully', 201);

        } catch (\PDOException $e) {
            $this->certificateModel->rollback();
            error_log('Certificate request error: ' . $e->getMessage());
            Response::serverError('An error occurred while requesting certificate');
        }
    }
}
