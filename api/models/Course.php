<?php
namespace App\Models;

use PDO;

/**
 * Course Model
 *
 * Handles course-related database operations
 */
class Course extends BaseModel
{
    protected string $table = 'courses';
    protected array $fillable = [
        'title',
        'slug',
        'description',
        'objectives',
        'level',
        'duration_hours',
        'thumbnail_url',
        'instructor_id',
        'is_published',
        'is_featured'
    ];
    protected array $hidden = [];

    /**
     * Get all published courses
     *
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array
     */
    public function getPublished(?int $limit = null, ?int $offset = null): array
    {
        return $this->all(['is_published' => true], 'title ASC', $limit, $offset);
    }

    /**
     * Get featured courses
     *
     * @param int|null $limit Optional limit
     * @return array
     */
    public function getFeatured(?int $limit = null): array
    {
        return $this->all(['is_published' => true, 'is_featured' => true], 'title ASC', $limit);
    }

    /**
     * Get course by slug
     *
     * @param string $slug Course slug
     * @return object|null
     */
    public function findBySlug(string $slug): ?object
    {
        return $this->findBy('slug', $slug);
    }

    /**
     * Get courses by instructor
     *
     * @param int $instructorId Instructor user ID
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array
     */
    public function getByInstructor(int $instructorId, ?int $limit = null, ?int $offset = null): array
    {
        return $this->all(['instructor_id' => $instructorId], 'created_at DESC', $limit, $offset);
    }

    /**
     * Get course with modules
     *
     * @param int $courseId Course ID
     * @return object|null Course with modules array
     */
    public function getCourseWithModules(int $courseId): ?object
    {
        try {
            $course = $this->find($courseId);

            if (!$course) {
                return null;
            }

            // Get modules for this course
            $modulesStmt = $this->pdo->prepare("
                SELECT * FROM modules
                WHERE course_id = :course_id
                ORDER BY `order` ASC
            ");
            $modulesStmt->execute(['course_id' => $courseId]);
            $course->modules = $modulesStmt->fetchAll(PDO::FETCH_OBJ);

            return $course;
        } catch (\PDOException $e) {
            error_log("Database error in getCourseWithModules: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get course statistics
     *
     * @param int $courseId Course ID
     * @return array|null Course statistics
     */
    public function getCourseStats(int $courseId): ?array
    {
        try {
            // Get total enrollments
            $enrollmentsStmt = $this->pdo->prepare("
                SELECT COUNT(*) as total
                FROM enrollments
                WHERE course_id = :course_id
            ");
            $enrollmentsStmt->execute(['course_id' => $courseId]);
            $enrollments = $enrollmentsStmt->fetch(PDO::FETCH_ASSOC);

            // Get total modules
            $modulesStmt = $this->pdo->prepare("
                SELECT COUNT(*) as total
                FROM modules
                WHERE course_id = :course_id
            ");
            $modulesStmt->execute(['course_id' => $courseId]);
            $modules = $modulesStmt->fetch(PDO::FETCH_ASSOC);

            // Get total lessons
            $lessonsStmt = $this->pdo->prepare("
                SELECT COUNT(*) as total
                FROM lessons
                WHERE module_id IN (SELECT id FROM modules WHERE course_id = :course_id)
            ");
            $lessonsStmt->execute(['course_id' => $courseId]);
            $lessons = $lessonsStmt->fetch(PDO::FETCH_ASSOC);

            // Get completion rate
            $completionStmt = $this->pdo->prepare("
                SELECT
                    COUNT(DISTINCT CASE WHEN e.completion_percentage = 100 THEN e.user_id END) as completed,
                    COUNT(DISTINCT e.user_id) as total
                FROM enrollments e
                WHERE e.course_id = :course_id
            ");
            $completionStmt->execute(['course_id' => $courseId]);
            $completion = $completionStmt->fetch(PDO::FETCH_ASSOC);

            $completionRate = $completion['total'] > 0
                ? round(($completion['completed'] / $completion['total']) * 100, 2)
                : 0;

            return [
                'total_enrollments' => (int) $enrollments['total'],
                'total_modules' => (int) $modules['total'],
                'total_lessons' => (int) $lessons['total'],
                'completion_rate' => $completionRate,
                'completed_students' => (int) $completion['completed']
            ];
        } catch (\PDOException $e) {
            error_log("Database error in getCourseStats: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Search courses by title or description
     *
     * @param string $searchTerm Search term
     * @param bool $publishedOnly Only published courses
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array
     */
    public function searchCourses(string $searchTerm, bool $publishedOnly = true, ?int $limit = null, ?int $offset = null): array
    {
        try {
            $sql = "SELECT * FROM {$this->table}
                    WHERE (title LIKE :search OR description LIKE :search)";

            $params = ['search' => "%{$searchTerm}%"];

            if ($publishedOnly) {
                $sql .= " AND is_published = 1";
            }

            $sql .= " ORDER BY order ASC";

            if ($limit !== null) {
                $sql .= " LIMIT {$limit}";
                if ($offset !== null) {
                    $sql .= " OFFSET {$offset}";
                }
            }

            return $this->query($sql, $params);
        } catch (\PDOException $e) {
            error_log("Database error in searchCourses: " . $e->getMessage());
            return [];
        }
    }
}
