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
                ORDER BY `order_index` ASC
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
     * Build per-module unlock state for a given user in a course.
     *
     * Module 1 (lowest order_index) is always unlocked. Module N is unlocked iff
     * the user has at least one passed quiz attempt for the previous module's
     * module-level quiz. Modules without a published module-level quiz are
     * treated as auto-pass so they don't block downstream modules.
     *
     * @return array Map of [module_id => ['is_unlocked' => bool, 'locked_reason' => string|null]]
     */
    public function getUnlockStatusMap(int $courseId, int $userId): array
    {
        try {
            // One row per module. has_quiz tells us whether any published module-level
            // quiz exists; quiz_passed tells us whether the user has passed any of them.
            // Defensive against modules with multiple published module-level quizzes.
            $stmt = $this->pdo->prepare("
                SELECT m.id, m.title, m.order_index,
                       (
                         SELECT COUNT(*) FROM quizzes q
                         WHERE q.module_id = m.id
                           AND q.lesson_id IS NULL
                           AND q.is_published = 1
                       ) AS quiz_count,
                       (
                         SELECT COUNT(*)
                         FROM quiz_attempts qa
                         JOIN quizzes q ON qa.quiz_id = q.id
                         WHERE q.module_id = m.id
                           AND q.lesson_id IS NULL
                           AND q.is_published = 1
                           AND qa.user_id = :user_id
                           AND qa.passed = 1
                       ) AS pass_count
                FROM modules m
                WHERE m.course_id = :course_id AND m.is_published = 1
                ORDER BY m.order_index ASC
            ");
            $stmt->execute(['course_id' => $courseId, 'user_id' => $userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_OBJ);

            $map = [];
            $previousPassed = true;
            $previousTitle = null;
            foreach ($rows as $row) {
                $unlocked = $previousPassed; // Module 1 always true; later modules depend on prev pass
                $map[(int)$row->id] = [
                    'is_unlocked' => $unlocked,
                    'locked_reason' => $unlocked
                        ? null
                        : ('Pass the quiz in "' . $previousTitle . '" to unlock this module')
                ];
                // For the next iteration: this module is "passed" iff it has no module-level
                // quiz, or the user has passed at least one of its quizzes.
                $previousPassed = ((int)$row->quiz_count === 0) || ((int)$row->pass_count > 0);
                $previousTitle = $row->title;
            }
            return $map;
        } catch (\PDOException $e) {
            error_log("Database error in getUnlockStatusMap: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Check whether a single module is unlocked for a user (used by access-gate
     * checks in LessonController). Returns ['is_unlocked' => bool, 'locked_reason' => ?string].
     */
    public function getUnlockStatusForModule(int $moduleId, int $userId): array
    {
        $module = $this->find($moduleId);
        if (!$module) {
            return ['is_unlocked' => false, 'locked_reason' => 'Module not found'];
        }
        $map = $this->getUnlockStatusMap((int)$module->course_id, $userId);
        if (!isset($map[$moduleId])) {
            // Module not in map (e.g. unpublished). Default to unlocked so we don't block instructors.
            return ['is_unlocked' => true, 'locked_reason' => null];
        }
        return $map[$moduleId];
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
