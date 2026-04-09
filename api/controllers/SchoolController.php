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
        $hasUsers = isset($_GET['has_users']) && $_GET['has_users'];

        if ($currentUser->role === 'superadmin') {
            if ($hasUsers) {
                // Optimised path: only schools with active users, count included
                $schools = $this->schoolModel->getSchoolsWithUsers($organizationId);
                $total = count($schools);
            } else {
                // Normal paginated list
                $schools = $this->schoolModel->getAll($organizationId, $limit, $offset);
                $total = $this->schoolModel->getTotalCount($organizationId);
            }
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

            if ($hasUsers) {
                $schools = $this->schoolModel->getSchoolsWithUsers($organizationId);
                // Scope to managed orgs
                $schools = array_values(array_filter($schools, function($school) use ($managedOrgIds) {
                    return in_array($school['organization_id'], $managedOrgIds);
                }));
                $total = count($schools);
            } else {
                $schools = $this->schoolModel->getSchoolsByOrganizationIds($managedOrgIds);
                if ($organizationId) {
                    $schools = array_filter($schools, function($school) use ($organizationId) {
                        return $school['organization_id'] == $organizationId;
                    });
                }
                $total = count($schools);
                $schools = array_slice($schools, $offset, $limit);
            }
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

        // Enrich with user counts only when not using has_users (which already includes them)
        if (!$hasUsers) {
            $userCounts = $this->schoolModel->getAllUserCounts();
            foreach ($schools as &$school) {
                $school['user_count'] = $userCounts[(int)$school['id']] ?? 0;
            }
        }

        Response::success([
            'schools' => array_values($schools),
            'total' => $total,
            'page' => $page,
            'pages' => $total > 0 ? ceil($total / $limit) : 0
        ]);
    }

    /**
     * GET /api/schools/public
     * Public list of schools for signup dropdown — no authentication required.
     *
     * Query params:
     *   ?search=        Partial name match (min 2 chars)
     *   ?organization_id=  Filter by organization
     */
    public function publicList(array $params): void
    {
        $search         = isset($_GET['search']) ? trim($_GET['search']) : '';
        $organizationId = isset($_GET['organization_id']) ? (int)$_GET['organization_id'] : null;

        $sql = "SELECT s.id, s.name, s.district, s.city, s.school_type,
                       o.organization_type
                FROM schools s
                LEFT JOIN organizations o ON s.organization_id = o.id
                WHERE s.is_active = 1";
        $bindings = [];

        if ($organizationId) {
            $sql .= " AND s.organization_id = :organization_id";
            $bindings[':organization_id'] = $organizationId;
        }

        if (strlen($search) >= 2) {
            $sql .= " AND s.name LIKE :search";
            $bindings[':search'] = '%' . $search . '%';
        }

        $sql .= " ORDER BY s.name ASC LIMIT 5000";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        $schools = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        Response::success(['schools' => $schools, 'total' => count($schools)]);
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
        $school['statistics'] = $this->schoolModel->getDetailedStatistics($schoolId);

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
        $limit = isset($_GET['limit']) ? min(5000, max(1, (int)$_GET['limit'])) : 20;
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
