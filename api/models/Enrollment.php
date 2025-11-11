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
                $this->updateProgress($enrollment->id, $percentage);
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
}
