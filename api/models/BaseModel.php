<?php
namespace App\Models;

use PDO;
use PDOException;

/**
 * Base Model Class
 *
 * Provides common database operations for all models
 */
abstract class BaseModel
{
    protected PDO $pdo;
    protected string $table;
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected array $hidden = [];

    /**
     * Constructor
     *
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Find a record by ID
     *
     * @param int $id Record ID
     * @return object|null
     */
    public function find(int $id): ?object
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1");
            $stmt->execute(['id' => $id]);
            $result = $stmt->fetch(PDO::FETCH_OBJ);

            return $result ? $this->hideFields($result) : null;
        } catch (PDOException $e) {
            error_log("Database error in find: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Find a record by a specific column
     *
     * @param string $column Column name
     * @param mixed $value Column value
     * @return object|null
     */
    public function findBy(string $column, $value): ?object
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE {$column} = :value LIMIT 1");
            $stmt->execute(['value' => $value]);
            $result = $stmt->fetch(PDO::FETCH_OBJ);

            return $result ? $this->hideFields($result) : null;
        } catch (PDOException $e) {
            error_log("Database error in findBy: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all records
     *
     * @param array $conditions Optional WHERE conditions
     * @param string $orderBy Optional ORDER BY clause
     * @param int|null $limit Optional LIMIT
     * @param int|null $offset Optional OFFSET
     * @return array
     */
    public function all(array $conditions = [], string $orderBy = '', ?int $limit = null, ?int $offset = null): array
    {
        try {
            $sql = "SELECT * FROM {$this->table}";

            // Add WHERE conditions
            if (!empty($conditions)) {
                $where = [];
                foreach ($conditions as $key => $value) {
                    $where[] = "{$key} = :{$key}";
                }
                $sql .= " WHERE " . implode(' AND ', $where);
            }

            // Add ORDER BY
            if ($orderBy) {
                $sql .= " ORDER BY {$orderBy}";
            }

            // Add LIMIT and OFFSET
            if ($limit !== null) {
                $sql .= " LIMIT {$limit}";
                if ($offset !== null) {
                    $sql .= " OFFSET {$offset}";
                }
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($conditions);
            $results = $stmt->fetchAll(PDO::FETCH_OBJ);

            return array_map([$this, 'hideFields'], $results);
        } catch (PDOException $e) {
            error_log("Database error in all: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Create a new record
     *
     * @param array $data Record data
     * @return int|null Inserted ID or null on failure
     */
    public function create(array $data): ?int
    {
        try {
            // Filter to only fillable fields
            $filteredData = $this->filterFillable($data);

            if (empty($filteredData)) {
                return null;
            }

            $columns = array_keys($filteredData);
            $placeholders = array_map(fn($col) => ":{$col}", $columns);

            $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ")
                    VALUES (" . implode(', ', $placeholders) . ")";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($filteredData);

            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Database error in create: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update a record by ID
     *
     * @param int $id Record ID
     * @param array $data Updated data
     * @return bool Success status
     */
    public function update(int $id, array $data): bool
    {
        try {
            // Filter to only fillable fields
            $filteredData = $this->filterFillable($data);

            if (empty($filteredData)) {
                return false;
            }

            $setParts = [];
            foreach (array_keys($filteredData) as $column) {
                $setParts[] = "{$column} = :{$column}";
            }

            $sql = "UPDATE {$this->table} SET " . implode(', ', $setParts) .
                   " WHERE {$this->primaryKey} = :id";

            $filteredData['id'] = $id;

            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($filteredData);
        } catch (PDOException $e) {
            error_log("Database error in update: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a record by ID
     *
     * @param int $id Record ID
     * @return bool Success status
     */
    public function delete(int $id): bool
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id");
            return $stmt->execute(['id' => $id]);
        } catch (PDOException $e) {
            error_log("Database error in delete: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Count records
     *
     * @param array $conditions Optional WHERE conditions
     * @return int
     */
    public function count(array $conditions = []): int
    {
        try {
            $sql = "SELECT COUNT(*) as total FROM {$this->table}";

            if (!empty($conditions)) {
                $where = [];
                foreach ($conditions as $key => $value) {
                    $where[] = "{$key} = :{$key}";
                }
                $sql .= " WHERE " . implode(' AND ', $where);
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($conditions);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return (int) $result['total'];
        } catch (PDOException $e) {
            error_log("Database error in count: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check if a record exists
     *
     * @param string $column Column name
     * @param mixed $value Column value
     * @param int|null $excludeId Optional ID to exclude from check
     * @return bool
     */
    public function exists(string $column, $value, ?int $excludeId = null): bool
    {
        try {
            $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE {$column} = :value";

            $params = ['value' => $value];

            if ($excludeId !== null) {
                $sql .= " AND {$this->primaryKey} != :exclude_id";
                $params['exclude_id'] = $excludeId;
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result['total'] > 0;
        } catch (PDOException $e) {
            error_log("Database error in exists: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Execute a raw SQL query
     *
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @return array
     */
    protected function query(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Database error in query: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Execute a raw SQL statement (INSERT, UPDATE, DELETE)
     *
     * @param string $sql SQL statement
     * @param array $params Statement parameters
     * @return bool Success status
     */
    protected function execute(string $sql, array $params = []): bool
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Database error in execute: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Filter data to only fillable fields
     *
     * @param array $data Input data
     * @return array Filtered data
     */
    protected function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            return $data;
        }

        return array_intersect_key($data, array_flip($this->fillable));
    }

    /**
     * Hide specified fields from result
     *
     * @param object $result Database result
     * @return object
     */
    protected function hideFields(object $result): object
    {
        if (empty($this->hidden)) {
            return $result;
        }

        $resultArray = (array) $result;

        foreach ($this->hidden as $field) {
            unset($resultArray[$field]);
        }

        return (object) $resultArray;
    }

    /**
     * Begin a database transaction
     *
     * @return bool
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit a database transaction
     *
     * @return bool
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Rollback a database transaction
     *
     * @return bool
     */
    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }
}
