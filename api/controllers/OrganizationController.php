<?php
/**
 * OrganizationController
 *
 * Handles organization management operations
 * Created: Phase 12 (Hierarchical RBAC)
 */

namespace App\Controllers;

use App\Models\Organization;
use App\Models\User;
use App\Utils\Response;

class OrganizationController extends BaseController
{
    private Organization $organizationModel;
    private User $userModel;

    public function __construct(\PDO $pdo)
    {
        parent::__construct($pdo);
        $this->organizationModel = new Organization($pdo);
        $this->userModel = new User($pdo);
    }

    /**
     * GET /api/organizations
     * List all organizations (SuperAdmin: all, OrgAdmin: their orgs only)
     */
    public function index(array $params): void
    {
        $currentUser = $this->getCurrentUser();

        // Get pagination parameters
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 20;
        $offset = ($page - 1) * $limit;

        if ($currentUser->role === 'superadmin') {
            // SuperAdmins see all organizations
            $organizations = $this->organizationModel->getAll($limit, $offset);
            $total = $this->organizationModel->getTotalCount();
        } else {
            // OrgAdmins see only their organizations
            $managedOrgIds = $this->getManagedOrganizationIds();
            if ($managedOrgIds === null) {
                Response::success([
                    'organizations' => [],
                    'total' => 0,
                    'page' => $page,
                    'pages' => 0
                ]);
                return;
            }

            // Filter organizations by managed IDs
            $allOrgs = $this->organizationModel->getAll();
            $organizations = array_filter($allOrgs, function($org) use ($managedOrgIds) {
                return in_array($org['id'], $managedOrgIds);
            });
            $total = count($organizations);
            $organizations = array_slice($organizations, $offset, $limit);
        }

        // Enrich with statistics
        foreach ($organizations as &$org) {
            $org['user_count'] = $this->organizationModel->getUserCount($org['id']);
            $org['school_count'] = count($this->organizationModel->getSchools($org['id']));
        }

        Response::success([
            'organizations' => array_values($organizations),
            'total' => $total,
            'page' => $page,
            'pages' => ceil($total / $limit)
        ]);
    }

    /**
     * GET /api/organizations/:id
     * Get single organization
     */
    public function show(array $params): void
    {
        $currentUser = $this->getCurrentUser();
        $organizationId = (int)$params['id'];

        // Check permissions
        $this->requireOrganizationManagementPermission($organizationId);

        $organization = $this->organizationModel->findById($organizationId);

        if (!$organization) {
            Response::notFound('Organization not found');
        }

        // Add statistics
        $organization['statistics'] = $this->organizationModel->getStatistics($organizationId);
        $organization['schools'] = $this->organizationModel->getSchools($organizationId);

        Response::success($organization);
    }

    /**
     * POST /api/organizations
     * Create new organization (SuperAdmin only)
     */
    public function create(array $params): void
    {
        $this->requireRole('superadmin');

        // Validate required fields
        $this->validateRequiredParams($_POST, ['name', 'slug']);

        // Check slug uniqueness
        if (!$this->organizationModel->isSlugUnique($_POST['slug'])) {
            Response::validationError(['slug' => 'Organization slug already exists']);
        }

        $organizationId = $this->organizationModel->create($_POST);

        $organization = $this->organizationModel->findById($organizationId);

        Response::success($organization, 'Organization created successfully', 201);
    }

    /**
     * PUT /api/organizations/:id
     * Update organization (SuperAdmin only)
     */
    public function update(array $params): void
    {
        $this->requireRole('superadmin');

        $organizationId = (int)$params['id'];

        $organization = $this->organizationModel->findById($organizationId);
        if (!$organization) {
            Response::notFound('Organization not found');
        }

        // Check slug uniqueness if being updated
        if (isset($_POST['slug']) && $_POST['slug'] !== $organization['slug']) {
            if (!$this->organizationModel->isSlugUnique($_POST['slug'], $organizationId)) {
                Response::validationError(['slug' => 'Organization slug already exists']);
            }
        }

        $this->organizationModel->update($organizationId, $_POST);

        $updated = $this->organizationModel->findById($organizationId);

        Response::success($updated, 'Organization updated successfully');
    }

    /**
     * DELETE /api/organizations/:id
     * Delete organization (SuperAdmin only)
     */
    public function delete(array $params): void
    {
        $this->requireRole('superadmin');

        $organizationId = (int)$params['id'];

        $organization = $this->organizationModel->findById($organizationId);
        if (!$organization) {
            Response::notFound('Organization not found');
        }

        // Check if organization has users
        $userCount = $this->organizationModel->getUserCount($organizationId);
        if ($userCount > 0) {
            Response::validationError([
                'message' => 'Cannot delete organization with active users',
                'user_count' => $userCount
            ]);
        }

        $this->organizationModel->delete($organizationId);

        Response::success(null, 'Organization deleted successfully');
    }

    /**
     * GET /api/organizations/:organizationId/users
     * Get users in organization
     */
    public function getUsers(array $params): void
    {
        $organizationId = (int)$params['organizationId'];

        // Check permissions
        $this->requireOrganizationManagementPermission($organizationId);

        // Get pagination and filter parameters
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 20;
        $offset = ($page - 1) * $limit;
        $role = $_GET['role'] ?? null;

        $users = $this->userModel->getUsersByOrganization($organizationId, $role, $limit, $offset);
        $total = count($this->userModel->getUsersByOrganization($organizationId, $role, null, null));

        Response::success([
            'users' => $users,
            'total' => $total,
            'page' => $page,
            'pages' => ceil($total / $limit)
        ]);
    }
}
