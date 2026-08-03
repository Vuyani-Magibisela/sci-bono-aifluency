<?php
namespace App\Models;

use PDO;

/**
 * UserBadge Model
 *
 * Per-lesson gamified badges awarded by the v2 course UI on chapter completion.
 * Idempotent: repeated awards of the same (user_id, badge_slug) are silently ignored.
 */
class UserBadge extends BaseModel
{
    protected string $table = 'user_badges';
    protected string $primaryKey = 'id';
    protected array $fillable = ['user_id', 'badge_slug', 'badge_name', 'lesson_id', 'module_id'];
    protected array $hidden = [];

    /**
     * Award a badge to a user. Returns the badge row (whether new or pre-existing).
     * Uses INSERT IGNORE for idempotency against the (user_id, badge_slug) unique key.
     */
    public function award(int $userId, string $slug, ?string $name = null, ?int $lessonId = null, ?int $moduleId = null): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT IGNORE INTO `{$this->table}`
                    (`user_id`, `badge_slug`, `badge_name`, `lesson_id`, `module_id`)
                 VALUES (:uid, :slug, :name, :lesson, :module)"
            );
            $stmt->execute([
                'uid'    => $userId,
                'slug'   => $slug,
                'name'   => $name,
                'lesson' => $lessonId,
                'module' => $moduleId,
            ]);

            $findStmt = $this->pdo->prepare(
                "SELECT * FROM `{$this->table}` WHERE user_id = :uid AND badge_slug = :slug LIMIT 1"
            );
            $findStmt->execute(['uid' => $userId, 'slug' => $slug]);
            $row = $findStmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;

        } catch (\PDOException $e) {
            error_log('UserBadge::award failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * All badges for a user, optionally scoped to a module.
     */
    public function forUser(int $userId, ?int $moduleId = null): array
    {
        try {
            $sql = "SELECT * FROM `{$this->table}` WHERE user_id = :uid";
            $params = ['uid' => $userId];
            if ($moduleId !== null) {
                $sql .= " AND module_id = :mid";
                $params['mid'] = $moduleId;
            }
            $sql .= " ORDER BY earned_at DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            error_log('UserBadge::forUser failed: ' . $e->getMessage());
            return [];
        }
    }
}
