<?php
namespace App\Controllers;

use App\Models\Certificate;
use App\Models\Enrollment;
use App\Utils\Response;
use App\Utils\Validator;

/**
 * Certificate Controller
 *
 * Handles certificate operations (Phase 6 schema).
 */
class CertificateController extends BaseController
{
    private Certificate $certificateModel;
    private Enrollment $enrollmentModel;

    public function __construct(\PDO $pdo)
    {
        parent::__construct($pdo);
        $this->certificateModel = new Certificate($pdo);
        $this->enrollmentModel = new Enrollment($pdo);
    }

    /**
     * GET /api/certificates
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

        if ($currentUser->role === 'student') {
            $userId = $currentUser->id;
        }

        if ($userId && $courseId) {
            $certificate = $this->certificateModel->getUserCourseCertificate($userId, $courseId);
            $certificates = $certificate ? [$certificate] : [];
            $total = $certificate ? 1 : 0;
        } elseif ($userId) {
            $certificates = $this->certificateModel->getByUser($userId, $pageSize, $offset);
            $total = $this->certificateModel->count(['user_id' => $userId]);
        } elseif ($courseId) {
            $this->requireRole(['superadmin', 'orgadmin', 'schooladmin', 'teacher']);
            $certificates = $this->certificateModel->getByCourse($courseId, $pageSize, $offset);
            $total = $this->certificateModel->count(['course_id' => $courseId]);
        } else {
            $this->requireRole(['superadmin', 'orgadmin', 'schooladmin']);
            $certificates = $this->certificateModel->getRecent($pageSize);
            $total = $this->certificateModel->count();
        }

        Response::paginated($certificates, $total, $page, $pageSize, 'Certificates retrieved successfully');
    }

    /**
     * GET /api/certificates/my-certificates
     *
     * Returns enriched certificates for the current user, in the shape the
     * student page reads: { certificates: [...] }.
     */
    public function getMyCertificates(array $params = []): void
    {
        $currentUser = $this->getCurrentUser();

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $pageSize = isset($_GET['pageSize']) ? (int)$_GET['pageSize'] : 20;

        if ($page < 1) $page = 1;
        if ($pageSize < 1 || $pageSize > 100) $pageSize = 20;

        $offset = ($page - 1) * $pageSize;

        $certificates = $this->certificateModel->getByUser($currentUser->id, $pageSize, $offset);

        // Normalize legacy issue_date alias for the frontend
        foreach ($certificates as $cert) {
            if (empty($cert->issue_date) && !empty($cert->effective_issue_date)) {
                $cert->issue_date = $cert->effective_issue_date;
            }
        }

        Response::success([
            'certificates' => $certificates
        ], 'My certificates retrieved successfully');
    }

    /**
     * GET /api/certificates/:id
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

        if ($currentUser->role === 'student' && $certificate->user_id != $currentUser->id) {
            Response::forbidden('You do not have permission to view this certificate');
        }

        Response::success([
            'certificate' => $certificate
        ], 'Certificate retrieved successfully');
    }

    /**
     * GET /api/certificates/:id/download
     *
     * Returns the full certificate payload + parsed template config + background
     * image URL, so the client can render and produce the PDF locally
     * (jsPDF + html2canvas).
     */
    public function download(array $params): void
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

        if ($currentUser->role === 'student' && $certificate->user_id != $currentUser->id) {
            Response::forbidden('You do not have permission to download this certificate');
        }

        if ((int)$certificate->is_revoked === 1) {
            Response::error('This certificate has been revoked and cannot be downloaded', 410);
        }

        // Parse template config
        $templateConfig = null;
        if (!empty($certificate->template_data)) {
            $decoded = json_decode($certificate->template_data, true);
            if (is_array($decoded)) {
                $templateConfig = $decoded;
            }
        }

        $backgroundUrl = !empty($certificate->template_background_file_id)
            ? '/api/files/' . (int)$certificate->template_background_file_id . '/public'
            : null;

        Response::success([
            'certificate'     => $certificate,
            'template_config' => $templateConfig,
            'background_url'  => $backgroundUrl
        ], 'Certificate ready for client-side rendering');
    }

    /**
     * POST /api/certificates
     */
    public function create(array $params = []): void
    {
        $this->requireRole(['superadmin', 'orgadmin', 'schooladmin', 'teacher']);

        $data = $_POST;

        $validator = Validator::make($data);
        $validator->required('user_id', 'User ID is required')
                  ->required('course_id', 'Course ID is required');

        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        $userId = (int)$data['user_id'];
        $courseId = (int)$data['course_id'];
        $templateId = isset($data['template_id']) ? (int)$data['template_id'] : null;

        $existing = $this->certificateModel->getUserCourseCertificate($userId, $courseId);

        if ($existing) {
            Response::error('Certificate already issued for this user and course', 409, [
                'certificate' => $existing
            ]);
        }

        if (!$this->certificateModel->isEligibleForCertificate($userId, $courseId)) {
            Response::error('User has not completed the course requirements', 400, [
                'message' => 'User must complete 100% of the course to be eligible for a certificate'
            ]);
        }

        try {
            $this->certificateModel->beginTransaction();

            $certificateId = $this->certificateModel->issueCertificate($userId, $courseId, $templateId);

            if (!$certificateId) {
                $this->certificateModel->rollback();
                Response::serverError('Failed to issue certificate');
            }

            $this->certificateModel->commit();

            $certificate = $this->certificateModel->getCertificateWithDetails($certificateId);

            $newAchievements = [];
            try {
                $achievementModel = new \App\Models\Achievement($this->pdo);
                $newAchievements = $achievementModel->checkAndUnlock($userId, 'certificate_issued', [
                    'course_id' => $courseId,
                    'certificate_id' => $certificateId
                ]);
            } catch (\Exception $e) {
                error_log('Achievement check error: ' . $e->getMessage());
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
     * PUT /api/certificates/:id
     *
     * Allows admin to revoke / reinstate. Body fields:
     *   - is_revoked (0|1)
     *   - revocation_reason (string, optional)
     */
    public function update(array $params): void
    {
        $this->requireRole(['superadmin', 'orgadmin', 'schooladmin']);

        if (!isset($params['id'])) {
            Response::error('Certificate ID is required', 400);
        }

        $certificateId = (int)$params['id'];
        $currentUser = $this->getCurrentUser();

        $certificate = $this->certificateModel->find($certificateId);

        if (!$certificate) {
            Response::notFound('Certificate not found');
        }

        $data = $_POST;

        if (isset($data['is_revoked'])) {
            $shouldRevoke = (int)$data['is_revoked'] === 1;
            if ($shouldRevoke) {
                $reason = $data['revocation_reason'] ?? null;
                $ok = $this->certificateModel->revoke($certificateId, $currentUser->id, $reason);
            } else {
                $ok = $this->certificateModel->reinstate($certificateId);
            }

            if (!$ok) {
                Response::serverError('Failed to update certificate');
            }

            $updated = $this->certificateModel->getCertificateWithDetails($certificateId);
            Response::success(['certificate' => $updated], 'Certificate updated successfully');
            return;
        }

        Response::error('No valid fields provided for update', 400);
    }

    /**
     * DELETE /api/certificates/:id  (superadmin only)
     */
    public function delete(array $params): void
    {
        $this->requireRole(['superadmin']);

        if (!isset($params['id'])) {
            Response::error('Certificate ID is required', 400);
        }

        $certificateId = (int)$params['id'];

        $certificate = $this->certificateModel->find($certificateId);

        if (!$certificate) {
            Response::notFound('Certificate not found');
        }

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
     * GET /api/certificates/verify/:certificate_number  (public)
     */
    public function verify(array $params): void
    {
        if (!isset($params['certificate_number'])) {
            Response::error('Certificate number is required', 400);
        }

        $certificateNumber = $params['certificate_number'];

        $verification = $this->certificateModel->verifyCertificate($certificateNumber);

        if (!$verification['valid']) {
            // Return success-shaped JSON (so frontend can show "revoked"/"invalid") instead of error
            Response::success([
                'valid'       => false,
                'status'      => $verification['status'] ?? 'invalid',
                'certificate' => $verification['certificate'] ?? null,
                'message'     => $verification['message']
            ], 'Verification result');
            return;
        }

        Response::success([
            'valid'       => true,
            'status'      => 'valid',
            'certificate' => $verification['certificate'],
            'message'     => $verification['message']
        ], 'Certificate verified successfully');
    }

    /**
     * POST /api/certificates/request  (student requests their own certificate)
     */
    public function requestCertificate(array $params = []): void
    {
        $currentUser = $this->getCurrentUser();

        $data = $_POST;

        $validator = Validator::make($data);
        $validator->required('course_id', 'Course ID is required');

        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        $courseId = (int)$data['course_id'];

        $existing = $this->certificateModel->getUserCourseCertificate($currentUser->id, $courseId);

        if ($existing) {
            Response::error('Certificate already issued for this course', 409, [
                'certificate' => $existing
            ]);
        }

        if (!$this->certificateModel->isEligibleForCertificate($currentUser->id, $courseId)) {
            Response::error('You have not completed the course requirements', 400, [
                'message' => 'You must complete 100% of the course to request a certificate'
            ]);
        }

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

    /**
     * GET /api/certificates/admin/stats  (admin tier + instructors)
     *
     * Scoping:
     *   - superadmin: global view
     *   - orgadmin/schooladmin: scoped via getManagedSchoolIds()
     *   - teacher/instructor: scoped to their primary_school_id
     */
    public function adminStats(array $params = []): void
    {
        $this->requireRole(['superadmin', 'orgadmin', 'schooladmin', 'teacher']);

        $schoolIds = $this->resolveCertScopeSchoolIds();

        $stats = $this->certificateModel->getStats($schoolIds);
        $perCourse = $this->certificateModel->getPerCourseBreakdown($schoolIds);
        $recent = $this->certificateModel->getRecent(20, $schoolIds);

        Response::success([
            'stats'      => $stats,
            'per_course' => $perCourse,
            'recent'     => $recent,
            'scope'      => $schoolIds === null ? 'global' : 'schools:' . implode(',', $schoolIds)
        ], 'Certificate stats retrieved successfully');
    }

    /**
     * GET /api/certificates/admin/incomplete?course_id=:id
     *
     * Returns the per-student "what's missing" breakdown for users enrolled in
     * the given course who do not yet hold an active certificate. Scoped by role
     * the same way as adminStats.
     */
    public function adminIncomplete(array $params = []): void
    {
        $this->requireRole(['superadmin', 'orgadmin', 'schooladmin', 'teacher']);

        $courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
        if ($courseId <= 0) {
            Response::error('course_id query parameter is required', 400);
        }

        $schoolIds = $this->resolveCertScopeSchoolIds();
        $rows = $this->certificateModel->getIncompleteEnrollments($courseId, $schoolIds);

        Response::success([
            'course_id'  => $courseId,
            'incomplete' => $rows,
            'count'      => count($rows)
        ], 'Incomplete enrollments retrieved');
    }

    /**
     * Resolve which schools the current user can see in certificate dashboards.
     * Returns null for unrestricted (superadmin); array of school IDs otherwise.
     */
    private function resolveCertScopeSchoolIds(): ?array
    {
        $user = $this->getCurrentUser();
        if ($user->role === 'superadmin') {
            return null;
        }
        if (in_array($user->role, ['teacher', 'instructor'], true)) {
            return $user->primary_school_id ? [(int)$user->primary_school_id] : [];
        }
        // orgadmin/schooladmin → managed schools
        return $this->getManagedSchoolIds();
    }

    /**
     * POST /api/certificates/backfill-missing  (superadmin)
     *
     * Issues certificates for any enrollment that has reached 100% (or status='completed')
     * but has no certificate row. Idempotent — safely re-runnable.
     */
    public function backfillMissing(array $params = []): void
    {
        $this->requireRole(['superadmin']);

        try {
            $stmt = $this->pdo->query("
                SELECT e.user_id, e.course_id
                FROM enrollments e
                LEFT JOIN certificates c
                  ON c.user_id = e.user_id AND c.course_id = e.course_id
                WHERE c.id IS NULL
                  AND (e.progress_percentage >= 100 OR e.status = 'completed')
            ");
            $candidates = $stmt->fetchAll(\PDO::FETCH_OBJ);
        } catch (\PDOException $e) {
            error_log('backfillMissing query error: ' . $e->getMessage());
            Response::serverError('Failed to query enrollments');
            return;
        }

        $issued = 0;
        $failed = 0;
        $failures = [];

        foreach ($candidates as $row) {
            try {
                $certId = $this->certificateModel->issueCertificate(
                    (int)$row->user_id,
                    (int)$row->course_id
                );
                if ($certId) {
                    $issued++;
                } else {
                    $failed++;
                    $failures[] = ['user_id' => (int)$row->user_id, 'course_id' => (int)$row->course_id, 'reason' => 'issueCertificate returned null'];
                }
            } catch (\Exception $e) {
                $failed++;
                $failures[] = ['user_id' => (int)$row->user_id, 'course_id' => (int)$row->course_id, 'reason' => $e->getMessage()];
                error_log("backfillMissing: failed for user {$row->user_id}/course {$row->course_id}: " . $e->getMessage());
            }
        }

        Response::success([
            'candidates' => count($candidates),
            'issued'     => $issued,
            'failed'     => $failed,
            'failures'   => $failures
        ], 'Backfill complete');
    }
}
