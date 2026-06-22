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
        'enrolled_at',
        'completed_at',
        'progress_percentage',
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

        return $this->all($conditions, 'enrolled_at DESC', $limit, $offset);
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

        return $this->all($conditions, 'enrolled_at DESC', $limit, $offset);
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
                    'enrolled_at' => date('Y-m-d H:i:s')
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
            'enrolled_at' => date('Y-m-d H:i:s'),
            'progress_percentage' => 0
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
     * @param bool|null $courseComplete Set true to flip status to 'completed';
     *                  false to keep/reset to 'active'; null to leave status untouched.
     *                  (Course-completion is now lessons + quizzes + projects, not just lesson %.)
     * @return bool
     */
    public function updateProgress(int $enrollmentId, float $completionPercentage, ?bool $courseComplete = null): bool
    {
        $data = [
            'progress_percentage' => $completionPercentage,
            'last_accessed_at' => date('Y-m-d H:i:s')
        ];

        if ($courseComplete === true) {
            $data['status'] = 'completed';
            $data['completed_at'] = date('Y-m-d H:i:s');
        } elseif ($courseComplete === false) {
            // Don't downgrade if user already explicitly dropped the course.
            $current = $this->find($enrollmentId);
            if ($current && $current->status === 'completed') {
                $data['status'] = 'active';
                $data['completed_at'] = null;
            }
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

            // Calculate percentage (lesson-completion fraction — still useful for the progress bar)
            $percentage = round(($completed['completed'] / $total['total']) * 100, 2);

            // Update enrollment
            $enrollment = $this->getUserEnrollment($userId, $courseId);
            if ($enrollment) {
                // Composite course-completion rule: lessons + quizzes + projects.
                // Already-certified users are grandfathered as 'completed' so the
                // stricter new rule doesn't retroactively flip their status.
                $certificateModel = new \App\Models\Certificate($this->pdo);
                $existingCert = $certificateModel->getUserCourseCertificate($userId, $courseId);
                $eligible = $existingCert
                    ? true
                    : $certificateModel->isEligibleForCertificate($userId, $courseId);
                $this->updateProgress($enrollment->id, $percentage, $eligible);

                // Auto-generate certificate when eligibility is met and a cert is missing.
                // Self-healing: every calculateProgress call retries issuance for any stuck
                // enrollment. Idempotency is enforced by getUserCourseCertificate + issueCertificate.
                if ($eligible) {
                    try {
                        $existingCert = $certificateModel->getUserCourseCertificate($userId, $courseId);
                        if (!$existingCert) {
                            $certificateId = $certificateModel->issueCertificate($userId, $courseId);
                            error_log("Auto-generated certificate ID {$certificateId} for user {$userId}, course {$courseId}");
                        }
                    } catch (\Exception $e) {
                        error_log("Failed to auto-generate certificate: " . $e->getMessage());
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

    /**
     * Get enrollments for a specific course, filtered to a school's students only.
     *
     * @param int $schoolId users.primary_school_id
     * @param int $courseId
     * @param string|null $status Optional enrollment status
     * @param int|null $limit
     * @param int|null $offset
     * @return array Enrollment rows (as objects)
     */
    public function getBySchoolAndCourse(int $schoolId, int $courseId, ?string $status = null, ?int $limit = null, ?int $offset = null): array
    {
        $sql = "SELECT e.*
                FROM {$this->table} e
                INNER JOIN users u ON u.id = e.user_id AND u.primary_school_id = :school_id
                WHERE e.course_id = :course_id";
        $bind = ['school_id' => $schoolId, 'course_id' => $courseId];
        if ($status !== null) {
            $sql .= " AND e.status = :status";
            $bind['status'] = $status;
        }
        $sql .= " ORDER BY e.enrolled_at DESC";
        if ($limit !== null) {
            $sql .= " LIMIT {$limit}";
            if ($offset !== null) {
                $sql .= " OFFSET {$offset}";
            }
        }
        return $this->query($sql, $bind);
    }

    /**
     * Count enrollments for a course, scoped to a school's students.
     */
    public function countBySchoolAndCourse(int $schoolId, int $courseId, ?string $status = null): int
    {
        $sql = "SELECT COUNT(*) AS total
                FROM {$this->table} e
                INNER JOIN users u ON u.id = e.user_id AND u.primary_school_id = :school_id
                WHERE e.course_id = :course_id";
        $bind = ['school_id' => $schoolId, 'course_id' => $courseId];
        if ($status !== null) {
            $sql .= " AND e.status = :status";
            $bind['status'] = $status;
        }
        $rows = $this->query($sql, $bind);
        return isset($rows[0]) ? (int) $rows[0]->total : 0;
    }

    /**
     * Aggregate enrollment summary per student for a given school.
     *
     * Returns one row per student at the school with:
     *   - id, name, email, is_active, created_at (from users)
     *   - enrollment_count: count of distinct courses the student is enrolled in
     *   - completed_count: count of enrollments with status='completed' (drives the "Completed" progress filter)
     *   - avg_progress: average progress_percentage across their enrollments (0 if none)
     *   - last_active_at: MAX(enrollments.last_accessed_at) — drives the active/stale badge
     *   - latest_enrolled_at: most recent enrolled_at timestamp (null if none)
     *   - single_course_title: course title when enrollment_count = 1; MAX() otherwise (UI decides)
     *
     * Used to populate the instructor students table so Progress and Course columns
     * are meaningful without requiring a per-course filter.
     *
     * @param int $schoolId users.primary_school_id
     * @param int|null $limit
     * @param int|null $offset
     * @param string|null $search matches name/email (case-insensitive)
     * @param bool|null $isActive filter on users.is_active
     * @param string|null $progressFilter one of: 'not-started', 'in-progress', 'completed' (null = no filter)
     * @param string|null $sortBy one of: 'name', 'progress', 'last_active' (null/unknown = 'name')
     * @param string|null $sortOrder 'asc' or 'desc' (default 'asc')
     * @return array Array of student summary objects
     */
    public function getSchoolStudentSummaries(
        int $schoolId,
        ?int $limit = null,
        ?int $offset = null,
        ?string $search = null,
        ?bool $isActive = null,
        ?string $progressFilter = null,
        ?string $sortBy = null,
        ?string $sortOrder = null
    ): array {
        [$where, $having, $params] = $this->buildSchoolStudentSummaryClauses($schoolId, $search, $isActive, $progressFilter);
        $orderBy = $this->buildSchoolStudentSummaryOrderBy($sortBy, $sortOrder);

        $sql = "SELECT
                    u.id, u.name, u.email, u.is_active, u.created_at,
                    COUNT(DISTINCT e.course_id) AS enrollment_count,
                    COUNT(DISTINCT CASE WHEN e.status = 'completed' THEN e.course_id END) AS completed_count,
                    COALESCE(AVG(e.progress_percentage), 0) AS avg_progress,
                    MAX(e.last_accessed_at) AS last_active_at,
                    MAX(e.enrolled_at) AS latest_enrolled_at,
                    MAX(c.title) AS single_course_title
                FROM users u
                LEFT JOIN enrollments e ON e.user_id = u.id
                LEFT JOIN courses c ON c.id = e.course_id
                WHERE {$where}
                GROUP BY u.id, u.name, u.email, u.is_active, u.created_at
                {$having}
                ORDER BY {$orderBy}";
        if ($limit !== null) {
            $sql .= " LIMIT {$limit}";
            if ($offset !== null) {
                $sql .= " OFFSET {$offset}";
            }
        }
        return $this->query($sql, $params);
    }

    /**
     * Count of distinct students matching getSchoolStudentSummaries() filters.
     * Must mirror the same WHERE + HAVING so pagination totals reflect the active filter
     * (especially the progress filter, which is applied via HAVING on aggregates).
     */
    public function countSchoolStudentSummaries(
        int $schoolId,
        ?string $search = null,
        ?bool $isActive = null,
        ?string $progressFilter = null
    ): int {
        [$where, $having, $params] = $this->buildSchoolStudentSummaryClauses($schoolId, $search, $isActive, $progressFilter);

        $sql = "SELECT COUNT(*) AS cnt FROM (
                    SELECT u.id
                    FROM users u
                    LEFT JOIN enrollments e ON e.user_id = u.id
                    WHERE {$where}
                    GROUP BY u.id
                    {$having}
                ) AS sub";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_OBJ);
        return $row ? (int)$row->cnt : 0;
    }

    /**
     * Shared WHERE/HAVING builder for getSchoolStudentSummaries and countSchoolStudentSummaries.
     * Returns [whereClause, havingClause, params] — havingClause is '' or a full "HAVING ..." string.
     *
     * Progress filter semantics (must match the frontend badge logic):
     *   - 'completed'    → student has at least one enrollment with status='completed'
     *   - 'in-progress'  → some progress recorded, but no course fully completed
     *   - 'not-started'  → no enrollments, or every enrollment is at 0% and not completed
     */
    private function buildSchoolStudentSummaryClauses(
        int $schoolId,
        ?string $search,
        ?bool $isActive,
        ?string $progressFilter
    ): array {
        $params = ['school_id' => $schoolId];
        $where = "u.primary_school_id = :school_id AND u.role = 'student'";

        if ($search !== null && trim($search) !== '') {
            // Distinct placeholders for each occurrence — the project's PDO config sets
            // ATTR_EMULATE_PREPARES=false (api/config/database.php), under which native
            // prepared statements forbid reusing one named placeholder across multiple
            // tokens. Reusing :q here silently threw PDOException, BaseModel::query()
            // swallowed it and returned [], producing "No Students Found" for every search.
            $where .= " AND (LOWER(u.name) LIKE :q_name OR LOWER(u.email) LIKE :q_email)";
            $needle = '%' . strtolower(trim($search)) . '%';
            $params['q_name'] = $needle;
            $params['q_email'] = $needle;
        }

        if ($isActive !== null) {
            $where .= " AND u.is_active = :is_active";
            $params['is_active'] = $isActive ? 1 : 0;
        }

        $having = '';
        switch ($progressFilter) {
            case 'completed':
                $having = "HAVING COUNT(DISTINCT CASE WHEN e.status = 'completed' THEN e.course_id END) >= 1";
                break;
            case 'in-progress':
                $having = "HAVING COUNT(DISTINCT CASE WHEN e.status = 'completed' THEN e.course_id END) = 0"
                       . " AND COALESCE(MAX(e.progress_percentage), 0) > 0";
                break;
            case 'not-started':
                $having = "HAVING COUNT(DISTINCT CASE WHEN e.status = 'completed' THEN e.course_id END) = 0"
                       . " AND COALESCE(MAX(e.progress_percentage), 0) = 0";
                break;
        }

        return [$where, $having, $params];
    }

    /**
     * Whitelisted ORDER BY for getSchoolStudentSummaries. Unknown values fall back to name ASC.
     * For last_active sorts, NULLs are placed last in both directions so unknown-activity rows
     * never push known-activity rows off the visible page.
     */
    private function buildSchoolStudentSummaryOrderBy(?string $sortBy, ?string $sortOrder): string
    {
        $dir = (strtolower((string)$sortOrder) === 'desc') ? 'DESC' : 'ASC';
        switch ($sortBy) {
            case 'progress':
                return "avg_progress {$dir}, u.name ASC";
            case 'last_active':
                return "last_active_at IS NULL, last_active_at {$dir}, u.name ASC";
            case 'name':
            default:
                return "u.name {$dir}";
        }
    }

    /**
     * Aggregate stats for an instructor's school: active learners (any enrollment
     * touched in the last 7 days) and average progress across all enrollments.
     *
     * @param int $schoolId users.primary_school_id
     * @return array{active_count:int, avg_progress:float}
     */
    public function getSchoolAggregateStats(int $schoolId): array
    {
        $activeSql = "SELECT COUNT(DISTINCT u.id) AS active_count
                      FROM users u
                      INNER JOIN enrollments e ON e.user_id = u.id
                      WHERE u.primary_school_id = :school_id
                        AND u.role = 'student'
                        AND e.last_accessed_at >= NOW() - INTERVAL 7 DAY";

        $progressSql = "SELECT COALESCE(AVG(e.progress_percentage), 0) AS avg_progress
                        FROM enrollments e
                        INNER JOIN users u ON u.id = e.user_id
                        WHERE u.primary_school_id = :school_id
                          AND u.role = 'student'";

        try {
            $stmt = $this->pdo->prepare($activeSql);
            $stmt->execute(['school_id' => $schoolId]);
            $activeRow = $stmt->fetch(PDO::FETCH_OBJ);

            $stmt = $this->pdo->prepare($progressSql);
            $stmt->execute(['school_id' => $schoolId]);
            $progressRow = $stmt->fetch(PDO::FETCH_OBJ);

            return [
                'active_count' => (int)($activeRow->active_count ?? 0),
                'avg_progress' => round((float)($progressRow->avg_progress ?? 0), 2),
            ];
        } catch (\PDOException $e) {
            error_log('Database error in getSchoolAggregateStats: ' . $e->getMessage());
            return ['active_count' => 0, 'avg_progress' => 0.0];
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
            $schoolId = isset($options['school_id']) ? (int)$options['school_id'] : null;

            // Determine grouping format
            $dateFormat = match($groupBy) {
                'day' => 'DATE(e.enrolled_at)',
                'week' => 'DATE_FORMAT(e.enrolled_at, "%Y-%U")',
                'month' => 'DATE_FORMAT(e.enrolled_at, "%Y-%m")',
                default => 'DATE_FORMAT(e.enrolled_at, "%Y-%m")'
            };

            $schoolJoin = $schoolId
                ? 'INNER JOIN users u ON e.user_id = u.id AND u.primary_school_id = :school_id AND u.is_active = 1'
                : '';

            $sql = "SELECT
                    $dateFormat as period,
                    COUNT(*) as enrollments_count,
                    SUM(CASE WHEN e.status = 'active' THEN 1 ELSE 0 END) as active_count,
                    SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                    SUM(CASE WHEN e.status = 'dropped' THEN 1 ELSE 0 END) as dropped_count,
                    AVG(e.progress_percentage) as avg_progress
                FROM {$this->table} e
                $schoolJoin
                WHERE DATE(e.enrolled_at) BETWEEN :start_date AND :end_date
                GROUP BY $dateFormat
                ORDER BY period ASC";

            $params = [
                'start_date' => $startDate,
                'end_date' => $endDate
            ];
            if ($schoolId) {
                $params['school_id'] = $schoolId;
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
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
