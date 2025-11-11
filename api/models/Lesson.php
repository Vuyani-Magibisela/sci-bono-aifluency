<?php
namespace App\Models;

use PDO;

/**
 * Lesson Model
 *
 * Handles lesson-related database operations
 */
class Lesson extends BaseModel
{
    protected string $table = 'lessons';
    protected array $fillable = [
        'module_id',
        'title',
        'slug',
        'content',
        'content_type',
        'video_url',
        'duration_minutes',
        'order_index',
        'objectives',
        'is_published'
    ];
    protected array $hidden = [];

    /**
     * Get lessons by module
     *
     * @param int $moduleId Module ID
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array
     */
    public function getByModule(int $moduleId, ?int $limit = null, ?int $offset = null): array
    {
        return $this->all(['module_id' => $moduleId], 'order_index ASC', $limit, $offset);
    }

    /**
     * Get published lessons by module
     *
     * @param int $moduleId Module ID
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array
     */
    public function getPublishedByModule(int $moduleId, ?int $limit = null, ?int $offset = null): array
    {
        return $this->all(['module_id' => $moduleId, 'is_published' => true], 'order_index ASC', $limit, $offset);
    }

    /**
     * Get lesson by slug
     *
     * @param string $slug Lesson slug
     * @param int $moduleId Module ID
     * @return object|null
     */
    public function findBySlug(string $slug, int $moduleId): ?object
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM {$this->table}
                WHERE slug = :slug AND module_id = :module_id
                LIMIT 1
            ");
            $stmt->execute(['slug' => $slug, 'module_id' => $moduleId]);
            return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
        } catch (\PDOException $e) {
            error_log("Database error in findBySlug: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get next lesson in sequence
     *
     * @param int $moduleId Module ID
     * @param int $currentOrder Current lesson order
     * @return object|null
     */
    public function getNextLesson(int $moduleId, int $currentOrder): ?object
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM {$this->table}
                WHERE module_id = :module_id AND `order` > :current_order AND is_published = 1
                ORDER BY `order` ASC
                LIMIT 1
            ");
            $stmt->execute([
                'module_id' => $moduleId,
                'current_order' => $currentOrder
            ]);
            return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
        } catch (\PDOException $e) {
            error_log("Database error in getNextLesson: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get previous lesson in sequence
     *
     * @param int $moduleId Module ID
     * @param int $currentOrder Current lesson order
     * @return object|null
     */
    public function getPreviousLesson(int $moduleId, int $currentOrder): ?object
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM {$this->table}
                WHERE module_id = :module_id AND `order` < :current_order AND is_published = 1
                ORDER BY `order` DESC
                LIMIT 1
            ");
            $stmt->execute([
                'module_id' => $moduleId,
                'current_order' => $currentOrder
            ]);
            return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
        } catch (\PDOException $e) {
            error_log("Database error in getPreviousLesson: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get lesson with user progress
     *
     * @param int $lessonId Lesson ID
     * @param int $userId User ID
     * @return object|null Lesson with progress data
     */
    public function getLessonWithProgress(int $lessonId, int $userId): ?object
    {
        try {
            $lesson = $this->find($lessonId);

            if (!$lesson) {
                return null;
            }

            // Get user progress for this lesson
            $progressStmt = $this->pdo->prepare("
                SELECT * FROM lesson_progress
                WHERE lesson_id = :lesson_id AND user_id = :user_id
                LIMIT 1
            ");
            $progressStmt->execute([
                'lesson_id' => $lessonId,
                'user_id' => $userId
            ]);
            $lesson->progress = $progressStmt->fetch(PDO::FETCH_OBJ);

            return $lesson;
        } catch (\PDOException $e) {
            error_log("Database error in getLessonWithProgress: " . $e->getMessage());
            return null;
        }
    }
}
