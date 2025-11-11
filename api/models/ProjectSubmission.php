<?php
namespace App\Models;

use PDO;

/**
 * ProjectSubmission Model
 *
 * Handles project submission-related database operations
 */
class ProjectSubmission extends BaseModel
{
    protected string $table = 'project_submissions';
    protected array $fillable = [
        'project_id',
        'user_id',
        'submission_url',
        'submission_text',
        'status',
        'score',
        'feedback',
        'graded_by',
        'submitted_at',
        'graded_at'
    ];
    protected array $hidden = [];

    /**
     * Get submissions by user
     *
     * @param int $userId User ID
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array
     */
    public function getByUser(int $userId, ?int $limit = null, ?int $offset = null): array
    {
        return $this->all(['user_id' => $userId], 'submitted_at DESC', $limit, $offset);
    }

    /**
     * Get submissions by project
     *
     * @param int $projectId Project ID
     * @param string|null $status Optional status filter
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array
     */
    public function getByProject(int $projectId, ?string $status = null, ?int $limit = null, ?int $offset = null): array
    {
        $conditions = ['project_id' => $projectId];

        if ($status !== null) {
            $conditions['status'] = $status;
        }

        return $this->all($conditions, 'submitted_at DESC', $limit, $offset);
    }

    /**
     * Get user's submission for a project
     *
     * @param int $userId User ID
     * @param int $projectId Project ID
     * @return object|null Latest submission or null
     */
    public function getUserSubmission(int $userId, int $projectId): ?object
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM {$this->table}
                WHERE user_id = :user_id AND project_id = :project_id
                ORDER BY submitted_at DESC
                LIMIT 1
            ");
            $stmt->execute([
                'user_id' => $userId,
                'project_id' => $projectId
            ]);
            return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
        } catch (\PDOException $e) {
            error_log("Database error in getUserSubmission: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Submit project
     *
     * @param array $data Submission data
     * @return int|null Submission ID
     */
    public function submitProject(array $data): ?int
    {
        // Set default values
        if (!isset($data['status'])) {
            $data['status'] = 'submitted';
        }

        if (!isset($data['submitted_at'])) {
            $data['submitted_at'] = date('Y-m-d H:i:s');
        }

        return $this->create($data);
    }

    /**
     * Grade submission
     *
     * @param int $submissionId Submission ID
     * @param float $score Score
     * @param string|null $feedback Feedback text
     * @param int $gradedBy User ID of grader
     * @return bool
     */
    public function gradeSubmission(int $submissionId, float $score, ?string $feedback, int $gradedBy): bool
    {
        return $this->update($submissionId, [
            'status' => 'graded',
            'score' => $score,
            'feedback' => $feedback,
            'graded_by' => $gradedBy,
            'graded_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Get submission with project and user details
     *
     * @param int $submissionId Submission ID
     * @return object|null
     */
    public function getSubmissionWithDetails(int $submissionId): ?object
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    ps.*,
                    p.title as project_title,
                    p.max_score as project_max_score,
                    u.name as student_name,
                    u.email as student_email,
                    g.name as grader_name
                FROM {$this->table} ps
                JOIN projects p ON ps.project_id = p.id
                JOIN users u ON ps.user_id = u.id
                LEFT JOIN users g ON ps.graded_by = g.id
                WHERE ps.id = :submission_id
                LIMIT 1
            ");
            $stmt->execute(['submission_id' => $submissionId]);
            return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
        } catch (\PDOException $e) {
            error_log("Database error in getSubmissionWithDetails: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get pending submissions for grading
     *
     * @param int|null $courseId Optional course filter
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array
     */
    public function getPendingSubmissions(?int $courseId = null, ?int $limit = null, ?int $offset = null): array
    {
        try {
            $sql = "SELECT
                        ps.*,
                        p.title as project_title,
                        p.course_id,
                        u.name as student_name
                    FROM {$this->table} ps
                    JOIN projects p ON ps.project_id = p.id
                    JOIN users u ON ps.user_id = u.id
                    WHERE ps.status = 'submitted'";

            $params = [];

            if ($courseId !== null) {
                $sql .= " AND p.course_id = :course_id";
                $params['course_id'] = $courseId;
            }

            $sql .= " ORDER BY ps.submitted_at ASC";

            if ($limit !== null) {
                $sql .= " LIMIT {$limit}";
                if ($offset !== null) {
                    $sql .= " OFFSET {$offset}";
                }
            }

            return $this->query($sql, $params);
        } catch (\PDOException $e) {
            error_log("Database error in getPendingSubmissions: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if submission is late
     *
     * @param int $submissionId Submission ID
     * @return bool
     */
    public function isLate(int $submissionId): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT ps.submitted_at, p.due_date
                FROM {$this->table} ps
                JOIN projects p ON ps.project_id = p.id
                WHERE ps.id = :submission_id
                LIMIT 1
            ");
            $stmt->execute(['submission_id' => $submissionId]);
            $result = $stmt->fetch(PDO::FETCH_OBJ);

            if (!$result || !$result->due_date) {
                return false;
            }

            return strtotime($result->submitted_at) > strtotime($result->due_date);
        } catch (\PDOException $e) {
            error_log("Database error in isLate: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Count submissions by status
     *
     * @param int $projectId Project ID
     * @return array Status counts
     */
    public function countByStatus(int $projectId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    status,
                    COUNT(*) as count
                FROM {$this->table}
                WHERE project_id = :project_id
                GROUP BY status
            ");
            $stmt->execute(['project_id' => $projectId]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $counts = [
                'submitted' => 0,
                'graded' => 0,
                'returned' => 0
            ];

            foreach ($results as $row) {
                $counts[$row['status']] = (int) $row['count'];
            }

            return $counts;
        } catch (\PDOException $e) {
            error_log("Database error in countByStatus: " . $e->getMessage());
            return [];
        }
    }
}
