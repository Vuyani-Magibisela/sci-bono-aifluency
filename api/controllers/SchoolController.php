<?php
/**
 * SchoolController
 *
 * Handles school management operations
 * Created: Phase 12 (Hierarchical RBAC)
 */

namespace App\Controllers;

use App\Models\School;
use App\Models\User;
use App\Utils\Response;

class SchoolController extends BaseController
{
    private School $schoolModel;
    private User $userModel;

    public function __construct(\PDO $pdo)
    {
        parent::__construct($pdo);
        $this->schoolModel = new School($pdo);
        $this->userModel = new User($pdo);
    }

    /**
     * GET /api/schools
     * List schools (scoped by user role)
     */
    public function index(array $params): void
    {
        $currentUser = $this->getCurrentUser();

        // Get pagination and filter parameters
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 20;
        $offset = ($page - 1) * $limit;
        $organizationId = isset($_GET['organization_id']) ? (int)$_GET['organization_id'] : null;

        if ($currentUser->role === 'superadmin') {
            // SuperAdmins see all schools
            $schools = $this->schoolModel->getAll($organizationId, $limit, $offset);
            $total = $this->schoolModel->getTotalCount($organizationId);
        } elseif ($currentUser->role === 'orgadmin') {
            // OrgAdmins see schools in their organizations
            $managedOrgIds = $this->getManagedOrganizationIds();
            if ($managedOrgIds === null || empty($managedOrgIds)) {
                Response::success([
                    'schools' => [],
                    'total' => 0,
                    'page' => $page,
                    'pages' => 0
                ]);
                return;
            }

            $schools = $this->schoolModel->getSchoolsByOrganizationIds($managedOrgIds);
            if ($organizationId) {
                $schools = array_filter($schools, function($school) use ($organizationId) {
                    return $school['organization_id'] == $organizationId;
                });
            }
            $total = count($schools);
            $schools = array_slice($schools, $offset, $limit);
        } else {
            // SchoolAdmins see only their school
            if ($currentUser->primary_school_id) {
                $school = $this->schoolModel->findById($currentUser->primary_school_id);
                $schools = $school ? [$school] : [];
                $total = count($schools);
            } else {
                $schools = [];
                $total = 0;
            }
        }

        // Enrich with statistics
        foreach ($schools as &$school) {
            $school['user_count'] = $this->schoolModel->getUserCount($school['id']);
        }

        Response::success([
            'schools' => array_values($schools),
            'total' => $total,
            'page' => $page,
            'pages' => $total > 0 ? ceil($total / $limit) : 0
        ]);
    }

    /**
     * GET /api/schools/:id
     * Get single school
     */
    public function show(array $params): void
    {
        $schoolId = (int)$params['id'];

        $school = $this->schoolModel->findById($schoolId);

        if (!$school) {
            Response::notFound('School not found');
        }

        // Check permissions
        $this->requireSchoolManagementPermission((object)$school);

        // Add statistics
        $school['statistics'] = $this->schoolModel->getStatistics($schoolId);

        Response::success($school);
    }

    /**
     * POST /api/schools
     * Create new school (SuperAdmin, OrgAdmin)
     */
    public function create(array $params): void
    {
        $currentUser = $this->getCurrentUser();

        // Validate required fields
        $this->validateRequiredParams($_POST, ['organization_id', 'name', 'slug']);

        $organizationId = (int)$_POST['organization_id'];

        // Check if user can manage this organization
        $this->requireOrganizationManagementPermission($organizationId);

        // Check slug uniqueness within organization
        if (!$this->schoolModel->isSlugUniqueInOrganization($organizationId, $_POST['slug'])) {
            Response::validationError(['slug' => 'School slug already exists in this organization']);
        }

        $schoolId = $this->schoolModel->create($_POST);

        $school = $this->schoolModel->findById($schoolId);

        Response::success($school, 'School created successfully', 201);
    }

    /**
     * PUT /api/schools/:id
     * Update school
     */
    public function update(array $params): void
    {
        $schoolId = (int)$params['id'];

        $school = $this->schoolModel->findById($schoolId);
        if (!$school) {
            Response::notFound('School not found');
        }

        // Check permissions
        $this->requireSchoolManagementPermission((object)$school);

        // Check slug uniqueness if being updated
        if (isset($_POST['slug']) && $_POST['slug'] !== $school['slug']) {
            $orgId = $_POST['organization_id'] ?? $school['organization_id'];
            if (!$this->schoolModel->isSlugUniqueInOrganization($orgId, $_POST['slug'], $schoolId)) {
                Response::validationError(['slug' => 'School slug already exists in this organization']);
            }
        }

        $this->schoolModel->update($schoolId, $_POST);

        $updated = $this->schoolModel->findById($schoolId);

        Response::success($updated, 'School updated successfully');
    }

    /**
     * DELETE /api/schools/:id
     * Delete school (SuperAdmin, OrgAdmin)
     */
    public function delete(array $params): void
    {
        $schoolId = (int)$params['id'];

        $school = $this->schoolModel->findById($schoolId);
        if (!$school) {
            Response::notFound('School not found');
        }

        // Check permissions (SchoolAdmin cannot delete schools)
        $currentUser = $this->getCurrentUser();
        if ($currentUser->role === 'schooladmin') {
            Response::forbidden('SchoolAdmins cannot delete schools');
        }

        $this->requireSchoolManagementPermission((object)$school);

        // Check if school has users
        $userCount = $this->schoolModel->getUserCount($schoolId);
        if ($userCount > 0) {
            Response::validationError([
                'message' => 'Cannot delete school with active users',
                'user_count' => $userCount
            ]);
        }

        $this->schoolModel->delete($schoolId);

        Response::success(null, 'School deleted successfully');
    }

    /**
     * GET /api/schools/:schoolId/users
     * Get users in school
     */
    public function getUsers(array $params): void
    {
        $schoolId = (int)$params['schoolId'];

        $school = $this->schoolModel->findById($schoolId);
        if (!$school) {
            Response::notFound('School not found');
        }

        // Check permissions
        $this->requireSchoolManagementPermission((object)$school);

        // Get pagination and filter parameters
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 20;
        $offset = ($page - 1) * $limit;
        $role = $_GET['role'] ?? null;

        $users = $this->userModel->getUsersBySchool($schoolId, $role, $limit, $offset);
        $total = count($this->userModel->getUsersBySchool($schoolId, $role, null, null));

        Response::success([
            'users' => $users,
            'total' => $total,
            'page' => $page,
            'pages' => ceil($total / $limit)
        ]);
    }
}
