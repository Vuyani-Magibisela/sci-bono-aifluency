<?php
namespace App\Controllers;

use App\Utils\Response;
use App\Utils\Validator;

/**
 * Certificate Template Controller
 *
 * Superadmin manages certificate templates: design (colors/layout/JSON),
 * optional background file (PNG/JPG/PDF uploaded via /api/upload), and
 * assignment to courses.
 */
class CertificateTemplateController extends BaseController
{
    /**
     * GET /api/certificate-templates
     */
    public function index(array $params = []): void
    {
        $this->requireRole(['superadmin', 'orgadmin', 'schooladmin']);

        try {
            $stmt = $this->pdo->query("
                SELECT
                    t.id, t.name, t.description, t.template_type, t.template_data,
                    t.requirements, t.background_file_id, t.scope, t.is_active,
                    t.created_by, t.created_at, t.updated_at,
                    u.name AS created_by_name,
                    (SELECT COUNT(*) FROM courses WHERE certificate_template_id = t.id) AS courses_using,
                    (SELECT COUNT(*) FROM certificates WHERE template_id = t.id)        AS certificates_issued
                FROM certificate_templates t
                LEFT JOIN users u ON t.created_by = u.id
                ORDER BY t.is_active DESC, t.created_at DESC
            ");
            $templates = $stmt->fetchAll(\PDO::FETCH_OBJ);

            foreach ($templates as $tpl) {
                if (!empty($tpl->background_file_id)) {
                    $tpl->background_url = '/api/files/' . (int)$tpl->background_file_id . '/public';
                }
            }

            Response::success(['templates' => $templates], 'Certificate templates retrieved');
        } catch (\PDOException $e) {
            error_log('CertificateTemplate::index error: ' . $e->getMessage());
            Response::serverError('Failed to load certificate templates');
        }
    }

    /**
     * GET /api/certificate-templates/:id
     */
    public function show(array $params): void
    {
        $this->requireRole(['superadmin', 'orgadmin', 'schooladmin']);

        if (!isset($params['id'])) {
            Response::error('Template ID is required', 400);
        }

        $template = $this->fetchTemplate((int)$params['id']);
        if (!$template) {
            Response::notFound('Template not found');
        }

        Response::success(['template' => $template], 'Certificate template retrieved');
    }

    /**
     * POST /api/certificate-templates  (superadmin only)
     *
     * Body: name, description, template_type, template_data (JSON), requirements (JSON),
     *       background_file_id (optional, from /api/upload), scope.
     */
    public function create(array $params = []): void
    {
        $this->requireRole(['superadmin']);
        $currentUser = $this->getCurrentUser();

        $data = $_POST;

        $validator = Validator::make($data);
        $validator->required('name', 'Template name is required')
                  ->required('template_type', 'Template type is required');

        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        $allowedTypes = ['course_completion', 'module_completion', 'quiz_achievement', 'custom'];
        if (!in_array($data['template_type'], $allowedTypes, true)) {
            Response::error('Invalid template_type', 400);
        }

        $templateData = $this->parseJsonField($data['template_data'] ?? null, []);
        $requirements = $this->parseJsonField($data['requirements'] ?? null, ['min_course_completion' => 100]);

        $backgroundFileId = !empty($data['background_file_id']) ? (int)$data['background_file_id'] : null;
        if ($backgroundFileId && !$this->fileExistsAsTemplate($backgroundFileId)) {
            Response::error('Invalid background_file_id (must be a certificate_template upload)', 400);
        }

        $scope = in_array($data['scope'] ?? 'course', ['global', 'course'], true) ? $data['scope'] : 'course';

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO certificate_templates
                    (name, description, template_type, template_data, requirements,
                     background_file_id, scope, is_active, created_by)
                VALUES
                    (:name, :description, :template_type, :template_data, :requirements,
                     :background_file_id, :scope, 1, :created_by)
            ");
            $stmt->execute([
                'name'               => Validator::sanitize($data['name']),
                'description'        => isset($data['description']) ? Validator::sanitize($data['description']) : null,
                'template_type'      => $data['template_type'],
                'template_data'      => json_encode($templateData),
                'requirements'       => json_encode($requirements),
                'background_file_id' => $backgroundFileId,
                'scope'              => $scope,
                'created_by'         => $currentUser->id
            ]);

            $newId = (int)$this->pdo->lastInsertId();
            Response::success(['template' => $this->fetchTemplate($newId)], 'Template created', 201);
        } catch (\PDOException $e) {
            error_log('CertificateTemplate::create error: ' . $e->getMessage());
            Response::serverError('Failed to create template');
        }
    }

    /**
     * PUT /api/certificate-templates/:id  (superadmin only)
     */
    public function update(array $params): void
    {
        $this->requireRole(['superadmin']);

        if (!isset($params['id'])) {
            Response::error('Template ID is required', 400);
        }
        $id = (int)$params['id'];

        $template = $this->fetchTemplate($id);
        if (!$template) {
            Response::notFound('Template not found');
        }

        $data = $_POST;
        $sets = [];
        $bind = ['id' => $id];

        if (isset($data['name'])) {
            $sets[] = 'name = :name';
            $bind['name'] = Validator::sanitize($data['name']);
        }
        if (array_key_exists('description', $data)) {
            $sets[] = 'description = :description';
            $bind['description'] = $data['description'] !== null ? Validator::sanitize($data['description']) : null;
        }
        if (isset($data['template_type'])) {
            $allowedTypes = ['course_completion', 'module_completion', 'quiz_achievement', 'custom'];
            if (!in_array($data['template_type'], $allowedTypes, true)) {
                Response::error('Invalid template_type', 400);
            }
            $sets[] = 'template_type = :template_type';
            $bind['template_type'] = $data['template_type'];
        }
        if (array_key_exists('template_data', $data)) {
            $sets[] = 'template_data = :template_data';
            $bind['template_data'] = json_encode($this->parseJsonField($data['template_data'], []));
        }
        if (array_key_exists('requirements', $data)) {
            $sets[] = 'requirements = :requirements';
            $bind['requirements'] = json_encode($this->parseJsonField($data['requirements'], []));
        }
        if (array_key_exists('background_file_id', $data)) {
            $bgId = $data['background_file_id'] === '' || $data['background_file_id'] === null
                ? null
                : (int)$data['background_file_id'];
            if ($bgId !== null && !$this->fileExistsAsTemplate($bgId)) {
                Response::error('Invalid background_file_id', 400);
            }
            $sets[] = 'background_file_id = :background_file_id';
            $bind['background_file_id'] = $bgId;
        }
        if (isset($data['scope']) && in_array($data['scope'], ['global', 'course'], true)) {
            $sets[] = 'scope = :scope';
            $bind['scope'] = $data['scope'];
        }
        if (isset($data['is_active'])) {
            $sets[] = 'is_active = :is_active';
            $bind['is_active'] = (int)((int)$data['is_active'] === 1);
        }

        if (empty($sets)) {
            Response::error('No fields provided for update', 400);
        }

        try {
            $sql = 'UPDATE certificate_templates SET ' . implode(', ', $sets) . ' WHERE id = :id';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($bind);
            Response::success(['template' => $this->fetchTemplate($id)], 'Template updated');
        } catch (\PDOException $e) {
            error_log('CertificateTemplate::update error: ' . $e->getMessage());
            Response::serverError('Failed to update template');
        }
    }

    /**
     * DELETE /api/certificate-templates/:id  (superadmin only)
     *
     * Soft-delete (is_active=0) if any certificates reference it,
     * hard-delete otherwise.
     */
    public function delete(array $params): void
    {
        $this->requireRole(['superadmin']);

        if (!isset($params['id'])) {
            Response::error('Template ID is required', 400);
        }
        $id = (int)$params['id'];

        $template = $this->fetchTemplate($id);
        if (!$template) {
            Response::notFound('Template not found');
        }

        try {
            // Count refs in certificates
            $cntStmt = $this->pdo->prepare('SELECT COUNT(*) AS c FROM certificates WHERE template_id = :id');
            $cntStmt->execute(['id' => $id]);
            $refs = (int)$cntStmt->fetch(\PDO::FETCH_OBJ)->c;

            if ($refs > 0) {
                $u = $this->pdo->prepare('UPDATE certificate_templates SET is_active = 0 WHERE id = :id');
                $u->execute(['id' => $id]);
                Response::success([
                    'soft_deleted' => true,
                    'certificates_using' => $refs
                ], 'Template has issued certificates — deactivated instead of deleted');
                return;
            }

            // Detach from any courses using it
            $detach = $this->pdo->prepare('UPDATE courses SET certificate_template_id = NULL WHERE certificate_template_id = :id');
            $detach->execute(['id' => $id]);

            $d = $this->pdo->prepare('DELETE FROM certificate_templates WHERE id = :id');
            $d->execute(['id' => $id]);

            Response::success(['deleted' => true], 'Template deleted');
        } catch (\PDOException $e) {
            error_log('CertificateTemplate::delete error: ' . $e->getMessage());
            Response::serverError('Failed to delete template');
        }
    }

    /**
     * POST /api/courses/:id/certificate-template  (superadmin only)
     *
     * Body: { template_id: int|null }
     */
    public function assignToCourse(array $params): void
    {
        $this->requireRole(['superadmin']);

        if (!isset($params['id'])) {
            Response::error('Course ID is required', 400);
        }
        $courseId = (int)$params['id'];

        $data = $_POST;
        $templateId = array_key_exists('template_id', $data) && $data['template_id'] !== '' && $data['template_id'] !== null
            ? (int)$data['template_id']
            : null;

        if ($templateId !== null) {
            $template = $this->fetchTemplate($templateId);
            if (!$template) {
                Response::error('Template not found', 404);
            }
        }

        try {
            // Verify course exists
            $cs = $this->pdo->prepare('SELECT id FROM courses WHERE id = :id LIMIT 1');
            $cs->execute(['id' => $courseId]);
            if (!$cs->fetch(\PDO::FETCH_OBJ)) {
                Response::notFound('Course not found');
            }

            $u = $this->pdo->prepare('UPDATE courses SET certificate_template_id = :tid WHERE id = :id');
            $u->execute(['tid' => $templateId, 'id' => $courseId]);

            Response::success([
                'course_id'   => $courseId,
                'template_id' => $templateId
            ], $templateId === null ? 'Template detached from course' : 'Template assigned to course');
        } catch (\PDOException $e) {
            error_log('CertificateTemplate::assignToCourse error: ' . $e->getMessage());
            Response::serverError('Failed to assign template');
        }
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function fetchTemplate(int $id): ?object
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    t.*,
                    u.name AS created_by_name,
                    (SELECT COUNT(*) FROM courses WHERE certificate_template_id = t.id) AS courses_using,
                    (SELECT COUNT(*) FROM certificates WHERE template_id = t.id)        AS certificates_issued
                FROM certificate_templates t
                LEFT JOIN users u ON t.created_by = u.id
                WHERE t.id = :id
                LIMIT 1
            ");
            $stmt->execute(['id' => $id]);
            $t = $stmt->fetch(\PDO::FETCH_OBJ);
            if (!$t) return null;
            if (!empty($t->background_file_id)) {
                $t->background_url = '/api/files/' . (int)$t->background_file_id . '/public';
            }
            return $t;
        } catch (\PDOException $e) {
            error_log('fetchTemplate error: ' . $e->getMessage());
            return null;
        }
    }

    private function fileExistsAsTemplate(int $fileId): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM uploaded_files
                WHERE id = :id AND file_type = 'certificate_template'
                LIMIT 1
            ");
            $stmt->execute(['id' => $fileId]);
            return $stmt->fetch(\PDO::FETCH_OBJ) !== false;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Accepts JSON string or array; returns array (or default on parse failure).
     */
    private function parseJsonField($value, array $default): array
    {
        if (is_array($value)) return $value;
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) return $decoded;
        }
        return $default;
    }
}
