<?php
namespace App\Models;

use PDO;

/**
 * LessonProgress Model
 *
 * Handles lesson progress-related database operations
 */
class LessonProgress extends BaseModel
{
    protected string $table = 'lesson_progress';
    protected array $fillable = [
        'user_id',
        'lesson_id',
        'status',
        'time_spent_minutes',
        'completed_at'
    ];
    protected array $hidden = [];

    /**
     * Get progress by user
     *
     * @param int $userId User ID
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array
     */
    public function getByUser(int $userId, ?int $limit = null, ?int $offset = null): array
    {
        return $this->all(['user_id' => $userId], 'updated_at DESC', $limit, $offset);
    }

    /**
     * Get progress by lesson
     *
     * @param int $lessonId Lesson ID
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array
     */
    public function getByLesson(int $lessonId, ?int $limit = null, ?int $offset = null): array
    {
        return $this->all(['lesson_id' => $lessonId], 'updated_at DESC', $limit, $offset);
    }

    /**
     * Get user's progress for specific lesson
     *
     * @param int $userId User ID
     * @param int $lessonId Lesson ID
     * @return object|null
     */
    public function getUserLessonProgress(int $userId, int $lessonId): ?object
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM {$this->table}
                WHERE user_id = :user_id AND lesson_id = :lesson_id
                LIMIT 1
            ");
            $stmt->execute([
                'user_id' => $userId,
                'lesson_id' => $lessonId
            ]);
            return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
        } catch (\PDOException $e) {
            error_log("Database error in getUserLessonProgress: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Start lesson (create progress record)
     *
     * @param int $userId User ID
     * @param int $lessonId Lesson ID
     * @return int|null Progress ID
     */
    public function startLesson(int $userId, int $lessonId): ?int
    {
        // Check if progress already exists
        $existing = $this->getUserLessonProgress($userId, $lessonId);

        if ($existing) {
            // Update status to in_progress if not_started
            if ($existing->status === 'not_started') {
                $this->update($existing->id, ['status' => 'in_progress']);
            }
            return $existing->id;
        }

        // Create new progress record
        return $this->create([
            'user_id' => $userId,
            'lesson_id' => $lessonId,
            'status' => 'in_progress',
            'time_spent_minutes' => 0
        ]);
    }

    /**
     * Complete lesson
     *
     * @param int $userId User ID
     * @param int $lessonId Lesson ID
     * @param int $timeSpent Time spent in minutes
     * @return bool
     */
    public function completeLesson(int $userId, int $lessonId, int $timeSpent = 0): bool
    {
        $progress = $this->getUserLessonProgress($userId, $lessonId);

        if (!$progress) {
            // Create completed progress record
            $this->create([
                'user_id' => $userId,
                'lesson_id' => $lessonId,
                'status' => 'completed',
                'time_spent_minutes' => $timeSpent,
                'completed_at' => date('Y-m-d H:i:s')
            ]);
            return true;
        }

        // Update existing progress
        return $this->update($progress->id, [
            'status' => 'completed',
            'time_spent_minutes' => $timeSpent,
            'completed_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Update lesson time spent
     *
     * @param int $userId User ID
     * @param int $lessonId Lesson ID
     * @param int $additionalMinutes Additional minutes to add
     * @return bool
     */
    public function updateTimeSpent(int $userId, int $lessonId, int $additionalMinutes): bool
    {
        $progress = $this->getUserLessonProgress($userId, $lessonId);

        if (!$progress) {
            return $this->startLesson($userId, $lessonId) !== null;
        }

        $newTime = ($progress->time_spent_minutes ?? 0) + $additionalMinutes;

        return $this->update($progress->id, [
            'time_spent_minutes' => $newTime
        ]);
    }

    /**
     * Get user's progress for a module
     *
     * @param int $userId User ID
     * @param int $moduleId Module ID
     * @return array Progress records
     */
    public function getUserModuleProgress(int $userId, int $moduleId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT lp.*, l.title as lesson_title, l.order as lesson_order
                FROM {$this->table} lp
                JOIN lessons l ON lp.lesson_id = l.id
                WHERE lp.user_id = :user_id AND l.module_id = :module_id
                ORDER BY l.order ASC
            ");
            $stmt->execute([
                'user_id' => $userId,
                'module_id' => $moduleId
            ]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\PDOException $e) {
            error_log("Database error in getUserModuleProgress: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get user's progress for a course
     *
     * @param int $userId User ID
     * @param int $courseId Course ID
     * @return array Progress records
     */
    public function getUserCourseProgress(int $userId, int $courseId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    lp.*,
                    l.title as lesson_title,
                    l.order as lesson_order,
                    m.title as module_title,
                    m.order as module_order
                FROM {$this->table} lp
                JOIN lessons l ON lp.lesson_id = l.id
                JOIN modules m ON l.module_id = m.id
                WHERE lp.user_id = :user_id AND m.course_id = :course_id
                ORDER BY m.order ASC, l.order ASC
            ");
            $stmt->execute([
                'user_id' => $userId,
                'course_id' => $courseId
            ]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\PDOException $e) {
            error_log("Database error in getUserCourseProgress: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Calculate module completion percentage
     *
     * @param int $userId User ID
     * @param int $moduleId Module ID
     * @return float Completion percentage
     */
    public function getModuleCompletionPercentage(int $userId, int $moduleId): float
    {
        try {
            // Get total lessons in module
            $totalStmt = $this->pdo->prepare("
                SELECT COUNT(*) as total
                FROM lessons
                WHERE module_id = :module_id AND is_published = 1
            ");
            $totalStmt->execute(['module_id' => $moduleId]);
            $total = $totalStmt->fetch(PDO::FETCH_ASSOC);

            if ($total['total'] == 0) {
                return 0;
            }

            // Get completed lessons
            $completedStmt = $this->pdo->prepare("
                SELECT COUNT(*) as completed
                FROM {$this->table} lp
                JOIN lessons l ON lp.lesson_id = l.id
                WHERE l.module_id = :module_id
                  AND lp.user_id = :user_id
                  AND lp.status = 'completed'
            ");
            $completedStmt->execute([
                'module_id' => $moduleId,
                'user_id' => $userId
            ]);
            $completed = $completedStmt->fetch(PDO::FETCH_ASSOC);

            return round(($completed['completed'] / $total['total']) * 100, 2);
        } catch (\PDOException $e) {
            error_log("Database error in getModuleCompletionPercentage: " . $e->getMessage());
            return 0;
        }
    }

    // ================================================================
    // PHASE 10: ADVANCED ANALYTICS METHODS
    // ================================================================

    /**
     * Get engagement metrics for a course
     * Phase 10: Advanced Analytics Dashboard
     *
     * @param int $courseId Course ID
     * @return array Engagement metrics including time spent, notes, bookmarks
     */
    public function getEngagementMetrics(int $courseId): array
    {
        try {
            // Use the database view for student engagement
            $sql = "SELECT
                    user_id,
                    first_name,
                    last_name,
                    email,
                    lessons_accessed,
                    lessons_completed,
                    total_time_minutes,
                    notes_created,
                    bookmarks_created,
                    last_lesson_activity,
                    enrolled_at,
                    progress_percentage,
                    enrollment_status,
                    -- Engagement score (0-100)
                    ROUND((
                        (lessons_completed / GREATEST(lessons_accessed, 1)) * 30 +
                        LEAST((total_time_minutes / 60), 100) * 0.2 +
                        LEAST(notes_created * 5, 30) +
                        LEAST(bookmarks_created * 5, 20)
                    ), 2) as engagement_score
                FROM v_student_engagement
                WHERE course_id = :course_id
                ORDER BY engagement_score DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['course_id' => $courseId]);
            $engagementData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate aggregates
            $totalStudents = count($engagementData);
            $avgEngagement = $totalStudents > 0 ?
                round(array_sum(array_column($engagementData, 'engagement_score')) / $totalStudents, 2) : 0;

            return [
                'engagement_data' => $engagementData,
                'total_students' => $totalStudents,
                'avg_engagement_score' => $avgEngagement,
                'high_engagement_count' => count(array_filter($engagementData, fn($e) => $e['engagement_score'] >= 70)),
                'low_engagement_count' => count(array_filter($engagementData, fn($e) => $e['engagement_score'] < 40))
            ];
        } catch (\PDOException $e) {
            error_log("Database error in getEngagementMetrics: " . $e->getMessage());
            return [
                'engagement_data' => [],
                'total_students' => 0,
                'avg_engagement_score' => 0,
                'high_engagement_count' => 0,
                'low_engagement_count' => 0
            ];
        }
    }

    /**
     * Get completion heatmap data (activity by day/hour)
     * Phase 10: Advanced Analytics Dashboard
     *
     * @param int $courseId Course ID
     * @param array $options Date range options
     * @return array Heatmap data
     */
    public function getCompletionHeatmap(int $courseId, array $options = []): array
    {
        try {
            $dateRange = $options['range'] ?? '30';
            $startDate = $options['start_date'] ?? null;
            $endDate = $options['end_date'] ?? null;

            // Build date filter
            $dateFilter = '';
            $params = ['course_id' => $courseId];

            if ($startDate && $endDate) {
                $dateFilter = "AND DATE(lp.completed_at) BETWEEN :start_date AND :end_date";
                $params['start_date'] = $startDate;
                $params['end_date'] = $endDate;
            } elseif ($dateRange !== 'all') {
                $dateFilter = "AND lp.completed_at >= DATE_SUB(NOW(), INTERVAL :days DAY)";
                $params['days'] = (int)$dateRange;
            }

            $sql = "SELECT
                    DAYOFWEEK(lp.completed_at) as day_of_week,
                    HOUR(lp.completed_at) as hour_of_day,
                    COUNT(*) as completion_count,
                    AVG(lp.time_spent_minutes) as avg_time_spent
                FROM {$this->table} lp
                INNER JOIN lessons l ON lp.lesson_id = l.id
                INNER JOIN modules m ON l.module_id = m.id
                WHERE m.course_id = :course_id
                AND lp.status = 'completed'
                AND lp.completed_at IS NOT NULL
                $dateFilter
                GROUP BY DAYOFWEEK(lp.completed_at), HOUR(lp.completed_at)
                ORDER BY day_of_week, hour_of_day";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $heatmapData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Find peak activity
            $peakActivity = null;
            $maxCompletions = 0;
            foreach ($heatmapData as $data) {
                if ($data['completion_count'] > $maxCompletions) {
                    $maxCompletions = $data['completion_count'];
                    $peakActivity = $data;
                }
            }

            return [
                'heatmap_data' => $heatmapData,
                'peak_activity' => $peakActivity,
                'total_completions' => array_sum(array_column($heatmapData, 'completion_count'))
            ];
        } catch (\PDOException $e) {
            error_log("Database error in getCompletionHeatmap: " . $e->getMessage());
            return [
                'heatmap_data' => [],
                'peak_activity' => null,
                'total_completions' => 0
            ];
        }
    }
}
