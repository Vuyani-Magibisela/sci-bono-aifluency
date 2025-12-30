<?php
namespace App\Models;

use PDO;

/**
 * Project Model
 *
 * Handles project-related database operations
 */
class Project extends BaseModel
{
    protected string $table = 'projects';
    protected array $fillable = [
        'course_id',
        'module_id',
        'title',
        'slug',
        'description',
        'requirements',
        'max_score',
        'due_date',
        'is_published',
        'order'
    ];
    protected array $hidden = [];

    /**
     * Get projects by course
     *
     * @param int $courseId Course ID
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array
     */
    public function getByCourse(int $courseId, ?int $limit = null, ?int $offset = null): array
    {
        return $this->all(['course_id' => $courseId], 'title ASC', $limit, $offset);
    }

    /**
     * Get projects by module
     *
     * @param int $moduleId Module ID
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array
     */
    public function getByModule(int $moduleId, ?int $limit = null, ?int $offset = null): array
    {
        return $this->all(['module_id' => $moduleId], 'title ASC', $limit, $offset);
    }

    /**
     * Get published projects by course
     *
     * @param int $courseId Course ID
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array
     */
    public function getPublishedByCourse(int $courseId, ?int $limit = null, ?int $offset = null): array
    {
        return $this->all(['course_id' => $courseId, 'is_published' => true], 'title ASC', $limit, $offset);
    }

    /**
     * Get project by slug
     *
     * @param string $slug Project slug
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
     * Get project with user's submission
     *
     * @param int $projectId Project ID
     * @param int $userId User ID
     * @return object|null Project with submission data
     */
    public function getProjectWithSubmission(int $projectId, int $userId): ?object
    {
        try {
            $project = $this->find($projectId);

            if (!$project) {
                return null;
            }

            // Get user's submission for this project
            $submissionStmt = $this->pdo->prepare("
                SELECT * FROM project_submissions
                WHERE project_id = :project_id AND user_id = :user_id
                ORDER BY submitted_at DESC
                LIMIT 1
            ");
            $submissionStmt->execute([
                'project_id' => $projectId,
                'user_id' => $userId
            ]);
            $project->submission = $submissionStmt->fetch(PDO::FETCH_OBJ);

            return $project;
        } catch (\PDOException $e) {
            error_log("Database error in getProjectWithSubmission: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get project statistics
     *
     * @param int $projectId Project ID
     * @return array|null Project statistics
     */
    public function getProjectStats(int $projectId): ?array
    {
        try {
            // Get submission stats
            $submissionsStmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) as total_submissions,
                    COUNT(DISTINCT user_id) as unique_students,
                    COUNT(CASE WHEN status = 'graded' THEN 1 END) as graded_submissions,
                    COUNT(CASE WHEN status = 'submitted' THEN 1 END) as pending_submissions,
                    AVG(CASE WHEN score IS NOT NULL THEN score END) as average_score,
                    MAX(score) as highest_score
                FROM project_submissions
                WHERE project_id = :project_id
            ");
            $submissionsStmt->execute(['project_id' => $projectId]);
            $stats = $submissionsStmt->fetch(PDO::FETCH_ASSOC);

            return [
                'total_submissions' => (int) $stats['total_submissions'],
                'unique_students' => (int) $stats['unique_students'],
                'graded_submissions' => (int) $stats['graded_submissions'],
                'pending_submissions' => (int) $stats['pending_submissions'],
                'average_score' => round((float) ($stats['average_score'] ?? 0), 2),
                'highest_score' => $stats['highest_score'] !== null ? (float) $stats['highest_score'] : null
            ];
        } catch (\PDOException $e) {
            error_log("Database error in getProjectStats: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if project is overdue
     *
     * @param int $projectId Project ID
     * @return bool
     */
    public function isOverdue(int $projectId): bool
    {
        $project = $this->find($projectId);

        if (!$project || !$project->due_date) {
            return false;
        }

        return strtotime($project->due_date) < time();
    }

    /**
     * Get upcoming projects (not yet due)
     *
     * @param int $courseId Course ID
     * @param int|null $limit Optional limit
     * @return array
     */
    public function getUpcoming(int $courseId, ?int $limit = null): array
    {
        try {
            $sql = "SELECT * FROM {$this->table}
                    WHERE course_id = :course_id
                      AND is_published = 1
                      AND due_date >= NOW()
                    ORDER BY due_date ASC";

            if ($limit !== null) {
                $sql .= " LIMIT {$limit}";
            }

            return $this->query($sql, ['course_id' => $courseId]);
        } catch (\PDOException $e) {
            error_log("Database error in getUpcoming: " . $e->getMessage());
            return [];
        }
    }
}
