<?php
namespace App\Models;

use PDO;

/**
 * Enrollment Model
 *
 * Handles course enrollment-related database operations
 */
class Enrollment extends BaseModel
{
    protected string $table = 'enrollments';
    protected array $fillable = [
        'user_id',
        'course_id',
        'status',
        'enrollment_date',
        'completion_date',
        'completion_percentage',
        'last_accessed_at'
    ];
    protected array $hidden = [];

    /**
     * Get enrollments by user
     *
     * @param int $userId User ID
     * @param string|null $status Optional status filter
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array
     */
    public function getByUser(int $userId, ?string $status = null, ?int $limit = null, ?int $offset = null): array
    {
        $conditions = ['user_id' => $userId];

        if ($status !== null) {
            $conditions['status'] = $status;
        }

        return $this->all($conditions, 'enrollment_date DESC', $limit, $offset);
    }

    /**
     * Get enrollments by course
     *
     * @param int $courseId Course ID
     * @param string|null $status Optional status filter
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array
     */
    public function getByCourse(int $courseId, ?string $status = null, ?int $limit = null, ?int $offset = null): array
    {
        $conditions = ['course_id' => $courseId];

        if ($status !== null) {
            $conditions['status'] = $status;
        }

        return $this->all($conditions, 'enrollment_date DESC', $limit, $offset);
    }

    /**
     * Get user's enrollment for specific course
     *
     * @param int $userId User ID
     * @param int $courseId Course ID
     * @return object|null
     */
    public function getUserEnrollment(int $userId, int $courseId): ?object
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM {$this->table}
                WHERE user_id = :user_id AND course_id = :course_id
                LIMIT 1
            ");
            $stmt->execute([
                'user_id' => $userId,
                'course_id' => $courseId
            ]);
            return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
        } catch (\PDOException $e) {
            error_log("Database error in getUserEnrollment: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if user is enrolled in course
     *
     * @param int $userId User ID
     * @param int $courseId Course ID
     * @return bool
     */
    public function isUserEnrolled(int $userId, int $courseId): bool
    {
        $enrollment = $this->getUserEnrollment($userId, $courseId);
        return $enrollment !== null && in_array($enrollment->status, ['active', 'completed']);
    }

    /**
     * Enroll user in course
     *
     * @param int $userId User ID
     * @param int $courseId Course ID
     * @return int|null Enrollment ID
     */
    public function enrollUser(int $userId, int $courseId): ?int
    {
        // Check if already enrolled
        $existing = $this->getUserEnrollment($userId, $courseId);

        if ($existing) {
            // Reactivate if inactive
            if ($existing->status === 'inactive') {
                $this->update($existing->id, [
                    'status' => 'active',
                    'enrollment_date' => date('Y-m-d H:i:s')
                ]);
                return $existing->id;
            }

            // Already enrolled
            return $existing->id;
        }

        // Create new enrollment
        return $this->create([
            'user_id' => $userId,
            'course_id' => $courseId,
            'status' => 'active',
            'enrollment_date' => date('Y-m-d H:i:s'),
            'completion_percentage' => 0
        ]);
    }

    /**
     * Unenroll user from course
     *
     * @param int $userId User ID
     * @param int $courseId Course ID
     * @return bool
     */
    public function unenrollUser(int $userId, int $courseId): bool
    {
        $enrollment = $this->getUserEnrollment($userId, $courseId);

        if (!$enrollment) {
            return false;
        }

        return $this->update($enrollment->id, ['status' => 'inactive']);
    }

    /**
     * Update enrollment progress
     *
     * @param int $enrollmentId Enrollment ID
     * @param float $completionPercentage Completion percentage
     * @return bool
     */
    public function updateProgress(int $enrollmentId, float $completionPercentage): bool
    {
        $data = [
            'completion_percentage' => $completionPercentage,
            'last_accessed_at' => date('Y-m-d H:i:s')
        ];

        // Mark as completed if 100%
        if ($completionPercentage >= 100) {
            $data['status'] = 'completed';
            $data['completion_date'] = date('Y-m-d H:i:s');
        }

        return $this->update($enrollmentId, $data);
    }

    /**
     * Calculate and update user's course progress
     *
     * @param int $userId User ID
     * @param int $courseId Course ID
     * @return float|null Updated completion percentage
     */
    public function calculateProgress(int $userId, int $courseId): ?float
    {
        try {
            // Get total lessons in course
            $totalStmt = $this->pdo->prepare("
                SELECT COUNT(*) as total
                FROM lessons l
                JOIN modules m ON l.module_id = m.id
                WHERE m.course_id = :course_id AND l.is_published = 1
            ");
            $totalStmt->execute(['course_id' => $courseId]);
            $total = $totalStmt->fetch(PDO::FETCH_ASSOC);

            if ($total['total'] == 0) {
                return 0;
            }

            // Get completed lessons for user
            $completedStmt = $this->pdo->prepare("
                SELECT COUNT(*) as completed
                FROM lesson_progress lp
                JOIN lessons l ON lp.lesson_id = l.id
                JOIN modules m ON l.module_id = m.id
                WHERE m.course_id = :course_id
                  AND lp.user_id = :user_id
                  AND lp.status = 'completed'
            ");
            $completedStmt->execute([
                'course_id' => $courseId,
                'user_id' => $userId
            ]);
            $completed = $completedStmt->fetch(PDO::FETCH_ASSOC);

            // Calculate percentage
            $percentage = round(($completed['completed'] / $total['total']) * 100, 2);

            // Update enrollment
            $enrollment = $this->getUserEnrollment($userId, $courseId);
            if ($enrollment) {
                $wasCompleted = $enrollment->completion_percentage >= 100;
                $this->updateProgress($enrollment->id, $percentage);

                // Auto-generate certificate on 100% completion (Phase 6)
                if ($percentage >= 100 && !$wasCompleted) {
                    try {
                        $certificateModel = new \App\Models\Certificate($this->pdo);

                        // Check if certificate doesn't already exist
                        $existingCert = $certificateModel->getUserCourseCertificate($userId, $courseId);

                        if (!$existingCert) {
                            $certificateId = $certificateModel->issueCertificate($userId, $courseId);
                            error_log("Auto-generated certificate ID {$certificateId} for user {$userId}, course {$courseId}");
                        }
                    } catch (\Exception $e) {
                        error_log("Failed to auto-generate certificate: " . $e->getMessage());
                        // Don't fail progress update if certificate generation fails
                    }
                }
            }

            return $percentage;
        } catch (\PDOException $e) {
            error_log("Database error in calculateProgress: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get enrollment with course details
     *
     * @param int $enrollmentId Enrollment ID
     * @return object|null
     */
    public function getEnrollmentWithCourse(int $enrollmentId): ?object
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    e.*,
                    c.title as course_title,
                    c.slug as course_slug,
                    c.thumbnail_url as course_thumbnail
                FROM {$this->table} e
                JOIN courses c ON e.course_id = c.id
                WHERE e.id = :enrollment_id
                LIMIT 1
            ");
            $stmt->execute(['enrollment_id' => $enrollmentId]);
            return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
        } catch (\PDOException $e) {
            error_log("Database error in getEnrollmentWithCourse: " . $e->getMessage());
            return null;
        }
    }

    // ================================================================
    // PHASE 10: ADVANCED ANALYTICS METHODS
    // ================================================================

    /**
     * Get enrollment trends over time
     * Phase 10: Advanced Analytics Dashboard
     *
     * @param array $options Grouping and date range options
     * @return array Enrollment trends data
     */
    public function getEnrollmentTrends(array $options = []): array
    {
        try {
            $groupBy = $options['group_by'] ?? 'month';
            $startDate = $options['start_date'] ?? date('Y-m-d', strtotime('-6 months'));
            $endDate = $options['end_date'] ?? date('Y-m-d');

            // Determine grouping format
            $dateFormat = match($groupBy) {
                'day' => 'DATE(enrolled_at)',
                'week' => 'DATE_FORMAT(enrolled_at, "%Y-%U")',
                'month' => 'DATE_FORMAT(enrolled_at, "%Y-%m")',
                default => 'DATE_FORMAT(enrolled_at, "%Y-%m")'
            };

            $sql = "SELECT
                    $dateFormat as period,
                    COUNT(*) as enrollments_count,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                    SUM(CASE WHEN status = 'dropped' THEN 1 ELSE 0 END) as dropped_count,
                    AVG(progress_percentage) as avg_progress
                FROM {$this->table}
                WHERE DATE(enrolled_at) BETWEEN :start_date AND :end_date
                GROUP BY $dateFormat
                ORDER BY period ASC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            $trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate totals
            $totalEnrollments = array_sum(array_column($trends, 'enrollments_count'));
            $totalCompleted = array_sum(array_column($trends, 'completed_count'));
            $completionRate = $totalEnrollments > 0 ? round(($totalCompleted / $totalEnrollments) * 100, 2) : 0;

            return [
                'trends' => $trends,
                'total_enrollments' => $totalEnrollments,
                'total_completed' => $totalCompleted,
                'completion_rate' => $completionRate,
                'group_by' => $groupBy
            ];
        } catch (\PDOException $e) {
            error_log("Database error in getEnrollmentTrends: " . $e->getMessage());
            return [
                'trends' => [],
                'total_enrollments' => 0,
                'total_completed' => 0,
                'completion_rate' => 0,
                'group_by' => $groupBy
            ];
        }
    }

    /**
     * Get retention metrics (dropout vs completion rates)
     * Phase 10: Advanced Analytics Dashboard
     *
     * @param array $options Date range options
     * @return array Retention metrics
     */
    public function getRetentionMetrics(array $options = []): array
    {
        try {
            $startDate = $options['start_date'] ?? date('Y-m-d', strtotime('-6 months'));
            $endDate = $options['end_date'] ?? date('Y-m-d');

            $sql = "SELECT
                    COUNT(*) as total_enrollments,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'dropped' THEN 1 ELSE 0 END) as dropped,
                    AVG(progress_percentage) as avg_progress,
                    AVG(DATEDIFF(IFNULL(completed_at, NOW()), enrolled_at)) as avg_days_to_completion,
                    AVG(DATEDIFF(last_accessed_at, enrolled_at)) as avg_days_active
                FROM {$this->table}
                WHERE DATE(enrolled_at) BETWEEN :start_date AND :end_date";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            $metrics = $stmt->fetch(PDO::FETCH_ASSOC);

            $total = $metrics['total_enrollments'];
            $retentionRate = $total > 0 ? round((($metrics['active'] + $metrics['completed']) / $total) * 100, 2) : 0;
            $dropoutRate = $total > 0 ? round(($metrics['dropped'] / $total) * 100, 2) : 0;
            $completionRate = $total > 0 ? round(($metrics['completed'] / $total) * 100, 2) : 0;

            return [
                'total_enrollments' => $total,
                'active' => $metrics['active'],
                'completed' => $metrics['completed'],
                'dropped' => $metrics['dropped'],
                'retention_rate' => $retentionRate,
                'dropout_rate' => $dropoutRate,
                'completion_rate' => $completionRate,
                'avg_progress' => round($metrics['avg_progress'], 2),
                'avg_days_to_completion' => round($metrics['avg_days_to_completion'], 2),
                'avg_days_active' => round($metrics['avg_days_active'], 2)
            ];
        } catch (\PDOException $e) {
            error_log("Database error in getRetentionMetrics: " . $e->getMessage());
            return [
                'total_enrollments' => 0,
                'active' => 0,
                'completed' => 0,
                'dropped' => 0,
                'retention_rate' => 0,
                'dropout_rate' => 0,
                'completion_rate' => 0,
                'avg_progress' => 0,
                'avg_days_to_completion' => 0,
                'avg_days_active' => 0
            ];
        }
    }
}
