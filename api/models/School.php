<?php
/**
 * School Model
 *
 * Handles school data operations
 * Created: Phase 12 (Hierarchical RBAC)
 */

namespace App\Models;

class School
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get all schools (optionally filtered by organization)
     */
    public function getAll(?int $organizationId = null, ?int $limit = null, ?int $offset = null): array
    {
        $sql = "SELECT s.*, o.name as organization_name
                FROM schools s
                LEFT JOIN organizations o ON s.organization_id = o.id
                WHERE s.is_active = 1";

        if ($organizationId !== null) {
            $sql .= " AND s.organization_id = :organization_id";
        }

        $sql .= " ORDER BY o.name ASC, s.name ASC";

        if ($limit !== null) {
            $sql .= " LIMIT :limit";
            if ($offset !== null) {
                $sql .= " OFFSET :offset";
            }
        }

        $stmt = $this->pdo->prepare($sql);

        if ($organizationId !== null) {
            $stmt->bindValue(':organization_id', $organizationId, \PDO::PARAM_INT);
        }

        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            if ($offset !== null) {
                $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            }
        }

        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get school by ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT s.*, o.name as organization_name
            FROM schools s
            LEFT JOIN organizations o ON s.organization_id = o.id
            WHERE s.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get school by slug within organization
     */
    public function findBySlug(int $organizationId, string $slug): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM schools
            WHERE organization_id = :organization_id
            AND slug = :slug
        ");
        $stmt->execute([
            'organization_id' => $organizationId,
            'slug' => $slug
        ]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Create new school
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO schools (
                organization_id, name, slug, description, school_type,
                logo_url, email, phone, address, city, province, country,
                postal_code, principal_name, principal_email, principal_phone,
                total_students, total_teachers, is_active
            ) VALUES (
                :organization_id, :name, :slug, :description, :school_type,
                :logo_url, :email, :phone, :address, :city, :province, :country,
                :postal_code, :principal_name, :principal_email, :principal_phone,
                :total_students, :total_teachers, :is_active
            )
        ");

        $stmt->execute([
            'organization_id' => $data['organization_id'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'school_type' => $data['school_type'] ?? 'combined',
            'logo_url' => $data['logo_url'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'province' => $data['province'] ?? null,
            'country' => $data['country'] ?? 'South Africa',
            'postal_code' => $data['postal_code'] ?? null,
            'principal_name' => $data['principal_name'] ?? null,
            'principal_email' => $data['principal_email'] ?? null,
            'principal_phone' => $data['principal_phone'] ?? null,
            'total_students' => $data['total_students'] ?? 0,
            'total_teachers' => $data['total_teachers'] ?? 0,
            'is_active' => $data['is_active'] ?? true
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update school
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        $allowedFields = ['organization_id', 'name', 'slug', 'description', 'school_type',
                          'logo_url', 'email', 'phone', 'address', 'city', 'province',
                          'country', 'postal_code', 'principal_name', 'principal_email',
                          'principal_phone', 'total_students', 'total_teachers', 'is_active'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE schools SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete school
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM schools WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Get organization for school
     */
    public function getOrganization(int $schoolId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT o.*
            FROM organizations o
            INNER JOIN schools s ON o.id = s.organization_id
            WHERE s.id = :school_id
        ");
        $stmt->execute(['school_id' => $schoolId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get user count for school
     */
    public function getUserCount(int $schoolId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count
            FROM users
            WHERE primary_school_id = :school_id
            AND is_active = 1
        ");
        $stmt->execute(['school_id' => $schoolId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int) $result['count'];
    }

    /**
     * Get statistics for school
     */
    public function getStatistics(int $schoolId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) as total_users,
                SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as total_students,
                SUM(CASE WHEN role = 'teacher' THEN 1 ELSE 0 END) as total_teachers,
                SUM(CASE WHEN role = 'schooladmin' THEN 1 ELSE 0 END) as total_school_admins
            FROM users
            WHERE primary_school_id = :school_id
            AND is_active = 1
        ");
        $stmt->execute(['school_id' => $schoolId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Get schools by multiple organization IDs
     */
    public function getSchoolsByOrganizationIds(array $organizationIds): array
    {
        if (empty($organizationIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($organizationIds), '?'));
        $sql = "SELECT s.*, o.name as organization_name
                FROM schools s
                LEFT JOIN organizations o ON s.organization_id = o.id
                WHERE s.organization_id IN ($placeholders)
                AND s.is_active = 1
                ORDER BY o.name ASC, s.name ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($organizationIds);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Check if slug is unique within organization
     */
    public function isSlugUniqueInOrganization(int $organizationId, string $slug, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) as count FROM schools
                WHERE organization_id = :organization_id
                AND slug = :slug";
        $params = [
            'organization_id' => $organizationId,
            'slug' => $slug
        ];

        if ($excludeId !== null) {
            $sql .= " AND id != :id";
            $params['id'] = $excludeId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result['count'] == 0;
    }

    /**
     * Get total count (optionally filtered by organization)
     */
    public function getTotalCount(?int $organizationId = null): int
    {
        $sql = "SELECT COUNT(*) as count FROM schools WHERE is_active = 1";
        $params = [];

        if ($organizationId !== null) {
            $sql .= " AND organization_id = :organization_id";
            $params['organization_id'] = $organizationId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int) $result['count'];
    }
}
