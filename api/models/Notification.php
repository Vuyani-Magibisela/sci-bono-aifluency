<?php
namespace App\Models;

use PDO;

class Notification extends BaseModel
{
    protected string $table = 'notifications';
    protected array $fillable = ['user_id', 'type', 'title', 'message', 'link', 'is_read'];

    /**
     * Get unread notifications for a user
     */
    public function getUnread(int $userId): array
    {
        return $this->query(
            "SELECT * FROM {$this->table}
             WHERE user_id = :user_id AND is_read = 0
             ORDER BY created_at DESC
             LIMIT 50",
            ['user_id' => $userId]
        );
    }

    /**
     * Get all notifications for a user (paginated)
     */
    public function getForUser(int $userId, int $limit = 20, int $offset = 0): array
    {
        return $this->query(
            "SELECT * FROM {$this->table}
             WHERE user_id = :user_id
             ORDER BY created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            ['user_id' => $userId]
        );
    }

    /**
     * Count unread notifications
     */
    public function countUnread(int $userId): int
    {
        $result = $this->query(
            "SELECT COUNT(*) as count FROM {$this->table}
             WHERE user_id = :user_id AND is_read = 0",
            ['user_id' => $userId]
        );
        return (int) ($result[0]->count ?? 0);
    }

    /**
     * Mark a single notification as read
     */
    public function markAsRead(int $id, int $userId): bool
    {
        return $this->execute(
            "UPDATE {$this->table} SET is_read = 1
             WHERE id = :id AND user_id = :user_id",
            ['id' => $id, 'user_id' => $userId]
        );
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllRead(int $userId): bool
    {
        return $this->execute(
            "UPDATE {$this->table} SET is_read = 1
             WHERE user_id = :user_id AND is_read = 0",
            ['user_id' => $userId]
        );
    }

    /**
     * Create a notification
     */
    public function createNotification(array $data): ?int
    {
        return $this->create($data);
    }
}
