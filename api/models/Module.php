<?php
namespace App\Models;

use PDO;

/**
 * Module Model
 *
 * Handles module-related database operations
 */
class Module extends BaseModel
{
    protected string $table = 'modules';
    protected array $fillable = [
        'course_id',
        'title',
        'slug',
        'description',
        'objectives',
        'order_index',
        'duration_hours',
        'is_published'
    ];
    protected array $hidden = [];

    /**
     * Get modules by course
     *
     * @param int $courseId Course ID
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array
     */
    public function getByCourse(int $courseId, ?int $limit = null, ?int $offset = null): array
    {
        return $this->all(['course_id' => $courseId], 'order_index ASC', $limit, $offset);
    }

    /**
     * Get published modules by course
     *
     * @param int $courseId Course ID
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array
     */
    public function getPublishedByCourse(int $courseId, ?int $limit = null, ?int $offset = null): array
    {
        return $this->all(['course_id' => $courseId, 'is_published' => true], 'order_index ASC', $limit, $offset);
    }

    /**
     * Get module by slug
     *
     * @param string $slug Module slug
     * @param int $courseId Course ID
     * @return object|null
     */
    public function findBySlug(string $slug, int $courseId): ?object
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM {$this->table}
                WHERE slug = :slug AND course_id = :course_id
                LIMIT 1
            ");
            $stmt->execute(['slug' => $slug, 'course_id' => $courseId]);
            return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
        } catch (\PDOException $e) {
            error_log("Database error in findBySlug: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get module with lessons
     *
     * @param int $moduleId Module ID
     * @return object|null Module with lessons array
     */
    public function getModuleWithLessons(int $moduleId): ?object
    {
        try {
            $module = $this->find($moduleId);

            if (!$module) {
                return null;
            }

            // Get lessons for this module
            $lessonsStmt = $this->pdo->prepare("
                SELECT * FROM lessons
                WHERE module_id = :module_id
                ORDER BY `order` ASC
            ");
            $lessonsStmt->execute(['module_id' => $moduleId]);
            $module->lessons = $lessonsStmt->fetchAll(PDO::FETCH_OBJ);

            return $module;
        } catch (\PDOException $e) {
            error_log("Database error in getModuleWithLessons: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get module statistics
     *
     * @param int $moduleId Module ID
     * @return array|null Module statistics
     */
    public function getModuleStats(int $moduleId): ?array
    {
        try {
            // Get total lessons
            $lessonsStmt = $this->pdo->prepare("
                SELECT COUNT(*) as total
                FROM lessons
                WHERE module_id = :module_id
            ");
            $lessonsStmt->execute(['module_id' => $moduleId]);
            $lessons = $lessonsStmt->fetch(PDO::FETCH_ASSOC);

            // Get total quizzes
            $quizzesStmt = $this->pdo->prepare("
                SELECT COUNT(*) as total
                FROM quizzes
                WHERE module_id = :module_id
            ");
            $quizzesStmt->execute(['module_id' => $moduleId]);
            $quizzes = $quizzesStmt->fetch(PDO::FETCH_ASSOC);

            // Get completion stats (students who completed all lessons in this module)
            $completionStmt = $this->pdo->prepare("
                SELECT
                    COUNT(DISTINCT lp.user_id) as started_students,
                    COUNT(DISTINCT CASE WHEN lp.status = 'completed' THEN lp.user_id END) as completed_students
                FROM lesson_progress lp
                JOIN lessons l ON lp.lesson_id = l.id
                WHERE l.module_id = :module_id
            ");
            $completionStmt->execute(['module_id' => $moduleId]);
            $completion = $completionStmt->fetch(PDO::FETCH_ASSOC);

            return [
                'total_lessons' => (int) $lessons['total'],
                'total_quizzes' => (int) $quizzes['total'],
                'started_students' => (int) $completion['started_students'],
                'completed_students' => (int) $completion['completed_students']
            ];
        } catch (\PDOException $e) {
            error_log("Database error in getModuleStats: " . $e->getMessage());
            return null;
        }
    }
}
