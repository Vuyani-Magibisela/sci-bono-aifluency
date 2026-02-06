<?php
/**
 * Organization Model
 *
 * Handles organization data operations
 * Created: Phase 12 (Hierarchical RBAC)
 */

namespace App\Models;

class Organization
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get all organizations
     */
    public function getAll(?int $limit = null, ?int $offset = null): array
    {
        $sql = "SELECT * FROM organizations WHERE is_active = 1 ORDER BY name ASC";

        if ($limit !== null) {
            $sql .= " LIMIT :limit";
            if ($offset !== null) {
                $sql .= " OFFSET :offset";
            }
        }

        $stmt = $this->pdo->prepare($sql);

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
     * Get organization by ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM organizations WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get organization by slug
     */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM organizations WHERE slug = :slug
        ");
        $stmt->execute(['slug' => $slug]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Create new organization
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO organizations (
                name, slug, description, logo_url, website, email, phone,
                address, city, province, country, postal_code, is_active
            ) VALUES (
                :name, :slug, :description, :logo_url, :website, :email, :phone,
                :address, :city, :province, :country, :postal_code, :is_active
            )
        ");

        $stmt->execute([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'logo_url' => $data['logo_url'] ?? null,
            'website' => $data['website'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'province' => $data['province'] ?? null,
            'country' => $data['country'] ?? 'South Africa',
            'postal_code' => $data['postal_code'] ?? null,
            'is_active' => $data['is_active'] ?? true
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update organization
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        $allowedFields = ['name', 'slug', 'description', 'logo_url', 'website', 'email',
                          'phone', 'address', 'city', 'province', 'country', 'postal_code', 'is_active'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE organizations SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete organization
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM organizations WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Get schools in organization
     */
    public function getSchools(int $organizationId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM schools
            WHERE organization_id = :organization_id
            AND is_active = 1
            ORDER BY name ASC
        ");
        $stmt->execute(['organization_id' => $organizationId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get user count for organization
     */
    public function getUserCount(int $organizationId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count
            FROM users
            WHERE primary_organization_id = :organization_id
            AND is_active = 1
        ");
        $stmt->execute(['organization_id' => $organizationId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int) $result['count'];
    }

    /**
     * Get statistics for organization
     */
    public function getStatistics(int $organizationId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) as total_users,
                SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as total_students,
                SUM(CASE WHEN role = 'teacher' THEN 1 ELSE 0 END) as total_teachers,
                SUM(CASE WHEN role = 'schooladmin' THEN 1 ELSE 0 END) as total_school_admins,
                SUM(CASE WHEN role = 'orgadmin' THEN 1 ELSE 0 END) as total_org_admins
            FROM users
            WHERE primary_organization_id = :organization_id
            AND is_active = 1
        ");
        $stmt->execute(['organization_id' => $organizationId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Check if slug is unique
     */
    public function isSlugUnique(string $slug, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) as count FROM organizations WHERE slug = :slug";
        $params = ['slug' => $slug];

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
     * Get total count
     */
    public function getTotalCount(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM organizations WHERE is_active = 1");
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int) $result['count'];
    }
}
