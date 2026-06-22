<?php
namespace App\Controllers;

use App\Models\QuizAttempt;
use App\Models\LessonProgress;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\StudentNote;
use App\Models\Bookmark;
use App\Models\Certificate;
use App\Models\Achievement;
use App\Utils\Response;
use App\Utils\JWTHandler;

/**
 * Advanced Analytics Controller
 *
 * Phase 10: Advanced Analytics Dashboard
 * Provides 17 analytics endpoints for student learning velocity, instructor class insights,
 * and admin system-wide metrics
 */
class AdvancedAnalyticsController extends BaseController
{
    private QuizAttempt $quizAttemptModel;
    private LessonProgress $lessonProgressModel;
    private Enrollment $enrollmentModel;
    private User $userModel;

    public function __construct(\PDO $pdo)
    {
        parent::__construct($pdo);
        $this->quizAttemptModel = new QuizAttempt($pdo);
        $this->lessonProgressModel = new LessonProgress($pdo);
        $this->enrollmentModel = new Enrollment($pdo);
        $this->userModel = new User($pdo);
    }

    // ================================================================
    // DATE RANGE HELPER
    // ================================================================

    /**
     * Resolve date range from query parameters.
     * Supports both preset range (e.g. ?range=30) and custom dates (?start_date=...&end_date=...).
     * Returns [start_date, end_date] as Y-m-d strings.
     */
    private function resolveDateRange(string $defaultRange = '180'): array
    {
        // Custom dates take priority
        if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
            return [$_GET['start_date'], $_GET['end_date']];
        }

        // Preset range in days (e.g. 7, 30, 90, 180, 365)
        $range = $_GET['range'] ?? $defaultRange;
        if ($range === 'all') {
            return ['2000-01-01', date('Y-m-d')];
        }

        $days = (int) $range;
        return [
            date('Y-m-d', strtotime("-{$days} days")),
            date('Y-m-d')
        ];
    }

    // ================================================================
    // AUTHENTICATION HELPERS
    // ================================================================

    /**
     * Check if user can access another user's data
     *
     * @param int $targetUserId
     * @return void
     */
    private function checkUserAccess(int $targetUserId): void
    {
        // Use BaseController's requireOwnershipOrRole method
        $this->requireOwnershipOrRole($targetUserId, ['admin', 'instructor']);
    }

    /**
     * Resolve the optional ?school_id= filter for admin analytics.
     * Superadmins may query any school. orgadmin/schooladmin are forced to their own
     * primary_school_id regardless of what they request (defence-in-depth — the UI
     * won't expose the picker to them, but don't trust the client).
     * Returns null when no filter applies (platform-wide view).
     */
    private function resolveSchoolFilter(): ?int
    {
        $requested = isset($_GET['school_id']) && $_GET['school_id'] !== ''
            ? (int)$_GET['school_id']
            : null;
        $currentUser = $this->getCurrentUser();
        $role = $currentUser->role ?? null;

        if ($role === 'superadmin') {
            return $requested;
        }

        // Non-superadmins can only scope to their own school, if any
        $ownSchool = isset($currentUser->primary_school_id) ? (int)$currentUser->primary_school_id : null;
        if ($requested !== null && $ownSchool !== null && $requested !== $ownSchool) {
            Response::forbidden('You may only query your own school');
        }
        return $requested ?? $ownSchool;
    }

    // ================================================================
    // STUDENT ANALYTICS ENDPOINTS (4 endpoints)
    // ================================================================

    /**
     * 1. Get learning velocity over time
     * GET /api/analytics/student/:userId/velocity
     *
     * @param array $params Route parameters
     * @return void
     */
    public function getLearningVelocity(array $params): void
    {
        if (!isset($params['userId'])) {
            Response::error('User ID is required', 400);
        }

        $userId = (int)$params['userId'];
        $this->checkUserAccess($userId);

        // Get query parameters for date filtering
        $dateRange = $_GET['range'] ?? '30'; // days
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;

        try {
            $velocity = $this->quizAttemptModel->getLearningVelocity($userId, [
                'range' => $dateRange,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);

            Response::success($velocity);
        } catch (\Exception $e) {
            Response::error('Failed to calculate learning velocity: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 2. Get time-on-task metrics
     * GET /api/analytics/student/:userId/time-on-task
     *
     * @param array $params Route parameters
     * @return void
     */
    public function getTimeOnTask(array $params): void
    {
        if (!isset($params['userId'])) {
            Response::error('User ID is required', 400);
        }

        $userId = (int)$params['userId'];
        $this->checkUserAccess($userId);

        $courseId = $_GET['course_id'] ?? null;

        try {
            // Note: native prepared statements (EMULATE_PREPARES=false) do not
            // permit reusing the same named placeholder, so each branch of the
            // UNION binds a uniquely-named parameter to the same value.
            $sql = "SELECT
                    'lesson' as content_type,
                    l.id as content_id,
                    l.title as content_title,
                    m.title as module_title,
                    lp.time_spent_minutes as time_minutes,
                    lp.status,
                    lp.updated_at as last_activity
                FROM lesson_progress lp
                INNER JOIN lessons l ON lp.lesson_id = l.id
                INNER JOIN modules m ON l.module_id = m.id
                WHERE lp.user_id = :user_id_lesson";

            $params_sql = [
                'user_id_lesson' => $userId,
                'user_id_quiz' => $userId,
            ];

            if ($courseId) {
                $sql .= " AND m.course_id = :course_id_lesson";
                $params_sql['course_id_lesson'] = $courseId;
                $params_sql['course_id_quiz'] = $courseId;
            }

            $sql .= "
                UNION ALL
                SELECT
                    'quiz' as content_type,
                    q.id as content_id,
                    q.title as content_title,
                    m.title as module_title,
                    -- Fall back to time_taken_minutes for historical attempts that
                    -- predate the controller populating time_spent_seconds.
                    COALESCE(qa.time_spent_seconds / 60, qa.time_taken_minutes, 0) as time_minutes,
                    qa.status,
                    qa.time_completed as last_activity
                FROM quiz_attempts qa
                INNER JOIN quizzes q ON qa.quiz_id = q.id
                INNER JOIN modules m ON q.module_id = m.id
                WHERE qa.user_id = :user_id_quiz";

            if ($courseId) {
                $sql .= " AND m.course_id = :course_id_quiz";
            }

            $sql .= " ORDER BY last_activity DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params_sql);
            $timeOnTask = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Calculate totals
            $totalMinutes = array_sum(array_column($timeOnTask, 'time_minutes'));

            Response::success([
                'time_on_task' => $timeOnTask,
                'total_minutes' => round($totalMinutes, 2),
                'total_hours' => round($totalMinutes / 60, 2)
            ]);
        } catch (\Exception $e) {
            Response::error('Failed to calculate time-on-task: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 3. Get skill proficiency by module/topic
     * GET /api/analytics/student/:userId/skill-proficiency
     *
     * @param array $params Route parameters
     * @return void
     */
    public function getSkillProficiency(array $params): void
    {
        if (!isset($params['userId'])) {
            Response::error('User ID is required', 400);
        }

        $userId = (int)$params['userId'];
        $this->checkUserAccess($userId);

        $courseId = $_GET['course_id'] ?? null;

        try {
            // Native prepared statements (EMULATE_PREPARES=false) require
            // each named placeholder to be unique, so the user_id is bound
            // twice under different names for the lesson and quiz joins.
            $sql = "SELECT
                    m.id as module_id,
                    m.title as module_title,
                    m.`order_index` as module_order,
                    COUNT(DISTINCT lp.lesson_id) as lessons_completed,
                    (SELECT COUNT(*) FROM lessons WHERE module_id = m.id) as total_lessons,
                    ROUND((COUNT(DISTINCT lp.lesson_id) / NULLIF((SELECT COUNT(*) FROM lessons WHERE module_id = m.id), 0)) * 100, 2) as lesson_completion_rate,
                    AVG(qa.score) as avg_quiz_score,
                    COUNT(DISTINCT qa.id) as quiz_attempts,
                    SUM(CASE WHEN qa.passed = 1 THEN 1 ELSE 0 END) as quizzes_passed,
                    SUM(lp.time_spent_minutes) as total_time_spent_minutes,
                    -- Proficiency score: weighted average of completion % and quiz score
                    ROUND((
                        (COUNT(DISTINCT lp.lesson_id) / NULLIF((SELECT COUNT(*) FROM lessons WHERE module_id = m.id), 0)) * 40 +
                        (AVG(IFNULL(qa.score, 0)) / 100) * 60
                    ) * 100, 2) as proficiency_score
                FROM modules m
                LEFT JOIN lessons l ON m.id = l.module_id
                LEFT JOIN lesson_progress lp ON l.id = lp.lesson_id AND lp.user_id = :user_id_lesson AND lp.status = 'completed'
                LEFT JOIN quizzes q ON m.id = q.module_id
                LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id AND qa.user_id = :user_id_quiz";

            $params_sql = [
                'user_id_lesson' => $userId,
                'user_id_quiz' => $userId,
            ];

            if ($courseId) {
                $sql .= " WHERE m.course_id = :course_id";
                $params_sql['course_id'] = $courseId;
            }

            $sql .= " GROUP BY m.id, m.title, m.`order_index`
                      ORDER BY m.`order_index` ASC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params_sql);
            $proficiency = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            Response::success([
                'skill_proficiency' => $proficiency,
                'overall_proficiency' => round(array_sum(array_column($proficiency, 'proficiency_score')) / max(count($proficiency), 1), 2)
            ]);
        } catch (\Exception $e) {
            Response::error('Failed to calculate skill proficiency: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 4. Get struggle indicators (failed attempts, time patterns)
     * GET /api/analytics/student/:userId/struggle-indicators
     *
     * @param array $params Route parameters
     * @return void
     */
    public function getStruggleIndicators(array $params): void
    {
        if (!isset($params['userId'])) {
            Response::error('User ID is required', 400);
        }

        $userId = (int)$params['userId'];
        $this->checkUserAccess($userId);

        try {
            $struggles = $this->quizAttemptModel->getStruggleIndicators($userId);

            Response::success($struggles);
        } catch (\Exception $e) {
            Response::error('Failed to identify struggle indicators: ' . $e->getMessage(), 500);
        }
    }

    // ================================================================
    // INSTRUCTOR ANALYTICS ENDPOINTS (5 endpoints)
    // ================================================================

    /**
     * 5. Get class score distribution
     * GET /api/analytics/instructor/class/:courseId/distribution
     *
     * @param array $params Route parameters
     * @return void
     */
    public function getClassDistribution(array $params): void
    {
        $this->requireRole(['teacher', 'superadmin', 'orgadmin', 'schooladmin']);

        if (!isset($params['courseId'])) {
            Response::error('Course ID is required', 400);
        }

        $courseId = (int)$params['courseId'];
        $quizId = $_GET['quiz_id'] ?? null;
        $schoolId = $this->resolveSchoolFilter();

        try {
            $sql = "SELECT
                    qa.score,
                    qa.user_id,
                    u.name AS student_name,
                    qa.passed,
                    qa.time_completed,
                    q.title as quiz_title,
                    CASE
                        WHEN qa.score BETWEEN 0 AND 20 THEN '0-20%'
                        WHEN qa.score BETWEEN 21 AND 40 THEN '21-40%'
                        WHEN qa.score BETWEEN 41 AND 60 THEN '41-60%'
                        WHEN qa.score BETWEEN 61 AND 80 THEN '61-80%'
                        ELSE '81-100%'
                    END as score_range
                FROM quiz_attempts qa
                INNER JOIN quizzes q ON qa.quiz_id = q.id
                INNER JOIN modules m ON q.module_id = m.id
                INNER JOIN users u ON qa.user_id = u.id
                WHERE m.course_id = :course_id";

            $params_sql = ['course_id' => $courseId];

            if ($quizId) {
                $sql .= " AND qa.quiz_id = :quiz_id";
                $params_sql['quiz_id'] = $quizId;
            }
            if ($schoolId !== null) {
                $sql .= " AND u.primary_school_id = :school_id";
                $params_sql['school_id'] = $schoolId;
            }

            $sql .= " ORDER BY qa.score DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params_sql);
            $attempts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Calculate distribution
            $distribution = [
                '0-20%' => 0,
                '21-40%' => 0,
                '41-60%' => 0,
                '61-80%' => 0,
                '81-100%' => 0
            ];

            foreach ($attempts as $attempt) {
                $distribution[$attempt['score_range']]++;
            }

            // Distinct enrolled students for this course (optionally school-scoped).
            // This is what the instructor's "Total Students" card needs — every enrolled
            // student, not just the subset who have attempted a quiz, and not relying on
            // the v_student_engagement view (which can be empty if students have no
            // lesson_progress rows yet).
            $enrolledSql = "SELECT COUNT(DISTINCT e.user_id) AS total
                            FROM enrollments e
                            INNER JOIN users u ON u.id = e.user_id
                            WHERE e.course_id = :course_id";
            $enrolledParams = ['course_id' => $courseId];
            if ($schoolId !== null) {
                $enrolledSql .= " AND u.primary_school_id = :school_id";
                $enrolledParams['school_id'] = $schoolId;
            }
            $enrolledStmt = $this->pdo->prepare($enrolledSql);
            $enrolledStmt->execute($enrolledParams);
            $totalEnrolled = (int)($enrolledStmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0);

            Response::success([
                'distribution' => $distribution,
                'attempts' => $attempts,
                'total_attempts' => count($attempts),
                'total_enrolled' => $totalEnrolled,
                'average_score' => round(array_sum(array_column($attempts, 'score')) / max(count($attempts), 1), 2)
            ]);
        } catch (\Exception $e) {
            Response::error('Failed to get class distribution: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 6. Get student engagement metrics
     * GET /api/analytics/instructor/engagement/:courseId
     *
     * @param array $params Route parameters
     * @return void
     */
    public function getEngagementMetrics(array $params): void
    {
        $this->requireRole(['teacher', 'superadmin', 'orgadmin', 'schooladmin']);

        if (!isset($params['courseId'])) {
            Response::error('Course ID is required', 400);
        }

        $courseId = (int)$params['courseId'];
        $schoolId = $this->resolveSchoolFilter();

        try {
            $engagement = $this->lessonProgressModel->getEngagementMetrics($courseId, $schoolId);

            Response::success($engagement);
        } catch (\Exception $e) {
            Response::error('Failed to get engagement metrics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 7. Get question effectiveness analysis
     * GET /api/analytics/instructor/question-effectiveness/:quizId
     *
     * @param array $params Route parameters
     * @return void
     */
    public function getQuestionEffectiveness(array $params): void
    {
        $this->requireRole(['teacher', 'superadmin', 'orgadmin', 'schooladmin']);

        if (!isset($params['quizId'])) {
            Response::error('Quiz ID is required', 400);
        }

        $quizId = (int)$params['quizId'];
        $schoolId = $this->resolveSchoolFilter();

        try {
            // Question difficulty and discrimination index.
            // When scoped to a school, restrict the attempts sampled via the qaa → qa → users chain.
            $schoolJoin = '';
            $bind = ['quiz_id' => $quizId];
            if ($schoolId !== null) {
                $schoolJoin = "LEFT JOIN quiz_attempts qa ON qaa.attempt_id = qa.id
                               LEFT JOIN users u ON qa.user_id = u.id AND u.primary_school_id = :school_id";
                $bind['school_id'] = $schoolId;
            }

            $schoolCondition = $schoolId !== null ? " AND u.id IS NOT NULL" : "";

            $sql = "SELECT
                    qq.id as question_id,
                    qq.question_text,
                    qq.question_type,
                    qq.points as max_points,
                    COUNT(qaa.id) as total_responses,
                    SUM(CASE WHEN qaa.is_correct = 1 THEN 1 ELSE 0 END) as correct_count,
                    ROUND((SUM(CASE WHEN qaa.is_correct = 1 THEN 1 ELSE 0 END) / GREATEST(COUNT(qaa.id),1)) * 100, 2) as success_rate,
                    ROUND(100 - ((SUM(CASE WHEN qaa.is_correct = 1 THEN 1 ELSE 0 END) / GREATEST(COUNT(qaa.id),1)) * 100), 2) as difficulty_score,
                    AVG(qaa.time_spent_seconds) as avg_time_seconds,
                    AVG(qaa.points_awarded) as avg_points_awarded
                FROM quiz_questions qq
                LEFT JOIN quiz_attempt_answers qaa ON qq.id = qaa.question_id
                {$schoolJoin}
                WHERE qq.quiz_id = :quiz_id{$schoolCondition}
                GROUP BY qq.id, qq.question_text, qq.question_type, qq.points
                ORDER BY difficulty_score DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($bind);
            $questions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            Response::success([
                'questions' => $questions,
                'quiz_id' => $quizId,
                'total_questions' => count($questions)
            ]);
        } catch (\Exception $e) {
            Response::error('Failed to analyze question effectiveness: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 8. Get at-risk students
     * GET /api/analytics/instructor/at-risk-students/:courseId
     *
     * @param array $params Route parameters
     * @return void
     */
    public function getAtRiskStudents(array $params): void
    {
        $this->requireRole(['teacher', 'superadmin', 'orgadmin', 'schooladmin']);

        if (!isset($params['courseId'])) {
            Response::error('Course ID is required', 400);
        }

        $courseId = (int)$params['courseId'];
        $schoolId = $this->resolveSchoolFilter();

        try {
            // Model returns ['at_risk_students' => [...], 'total_at_risk' => N, 'risk_levels' => {...}, 'risk_threshold' => 60]
            // Flatten so the frontend receives at_risk_students as an array directly.
            $result = $this->userModel->getAtRiskStudents($courseId, 60, $schoolId);

            Response::success([
                'at_risk_students' => $result['at_risk_students'] ?? [],
                'total_at_risk' => $result['total_at_risk'] ?? 0,
                'risk_levels' => $result['risk_levels'] ?? [],
                'risk_threshold' => $result['risk_threshold'] ?? 60,
            ]);
        } catch (\Exception $e) {
            Response::error('Failed to identify at-risk students: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 9. Get grading workload metrics
     * GET /api/analytics/instructor/grading-workload
     *
     * @param array $params Route parameters
     * @return void
     */
    public function getGradingWorkload(array $params): void
    {
        $this->requireRole(['teacher', 'superadmin', 'orgadmin', 'schooladmin']);

        $currentUser = $this->getCurrentUser();
        $schoolId = $this->resolveSchoolFilter();

        // Scope by student's school when available (teachers/schooladmins are locked to
        // their own school by resolveSchoolFilter). Fall back to courses.instructor_id
        // only for superadmins with no school filter, so the platform-wide admin use case
        // still works when querying their own courses.
        $scopeSql = '';
        $scopeBind = [];
        if ($schoolId !== null) {
            $scopeSql = ' AND EXISTS (SELECT 1 FROM users us WHERE us.id = ps.user_id AND us.primary_school_id = :school_id)';
            $scopeBind['school_id'] = $schoolId;
        } else {
            $scopeSql = ' AND c.instructor_id = :instructor_id';
            $scopeBind['instructor_id'] = $currentUser->id;
        }

        try {
            // Pending project submissions
            $projectsSql = "SELECT COUNT(*) as pending_projects
                FROM project_submissions ps
                INNER JOIN projects p ON ps.project_id = p.id
                INNER JOIN modules m ON p.module_id = m.id
                INNER JOIN courses c ON m.course_id = c.id
                WHERE ps.status = 'submitted'
                {$scopeSql}";

            $stmt = $this->pdo->prepare($projectsSql);
            $stmt->execute($scopeBind);
            $pendingProjects = $stmt->fetch(\PDO::FETCH_ASSOC)['pending_projects'];

            // Pending quiz reviews (manual grading)
            $quizScope = $schoolId !== null
                ? ' AND EXISTS (SELECT 1 FROM users us WHERE us.id = qa.user_id AND us.primary_school_id = :school_id)'
                : ' AND c.instructor_id = :instructor_id';
            $quizzesSql = "SELECT COUNT(*) as pending_quizzes
                FROM quiz_attempts qa
                INNER JOIN quizzes q ON qa.quiz_id = q.id
                INNER JOIN modules m ON q.module_id = m.id
                INNER JOIN courses c ON m.course_id = c.id
                WHERE qa.status = 'submitted'
                {$quizScope}";

            $stmt = $this->pdo->prepare($quizzesSql);
            $stmt->execute($scopeBind);
            $pendingQuizzes = $stmt->fetch(\PDO::FETCH_ASSOC)['pending_quizzes'];

            // Recent grading activity
            $recentSql = "SELECT
                    'project' as item_type,
                    ps.id as item_id,
                    p.title as item_title,
                    u.name AS student_name,
                    ps.submitted_at,
                    ps.graded_at,
                    TIMESTAMPDIFF(HOUR, ps.submitted_at, ps.graded_at) as grading_time_hours
                FROM project_submissions ps
                INNER JOIN projects p ON ps.project_id = p.id
                INNER JOIN users u ON ps.user_id = u.id
                INNER JOIN modules m ON p.module_id = m.id
                INNER JOIN courses c ON m.course_id = c.id
                WHERE ps.status = 'graded'
                {$scopeSql}
                AND ps.graded_at IS NOT NULL
                ORDER BY ps.graded_at DESC
                LIMIT 10";

            $stmt = $this->pdo->prepare($recentSql);
            $stmt->execute($scopeBind);
            $recentGrading = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Average grading time
            $avgGradingTime = 0;
            if (count($recentGrading) > 0) {
                $avgGradingTime = round(array_sum(array_column($recentGrading, 'grading_time_hours')) / count($recentGrading), 2);
            }

            Response::success([
                'pending_projects' => $pendingProjects,
                'pending_quizzes' => $pendingQuizzes,
                'total_pending' => $pendingProjects + $pendingQuizzes,
                'recent_grading' => $recentGrading,
                'avg_grading_time_hours' => $avgGradingTime
            ]);
        } catch (\Exception $e) {
            Response::error('Failed to get grading workload: ' . $e->getMessage(), 500);
        }
    }

    // ================================================================
    // ADMIN ANALYTICS ENDPOINTS (6 endpoints)
    // ================================================================

    /**
     * 10. Get enrollment trends over time
     * GET /api/analytics/admin/enrollment-trends
     *
     * @param array $params Route parameters
     * @return void
     */
    public function getEnrollmentTrends(array $params): void
    {
        $this->requireRole(['superadmin', 'orgadmin', 'schooladmin']);

        $groupBy = $_GET['group_by'] ?? 'month'; // 'day', 'week', 'month'
        [$startDate, $endDate] = $this->resolveDateRange();
        $schoolId = $this->resolveSchoolFilter();

        try {
            $trends = $this->enrollmentModel->getEnrollmentTrends([
                'group_by' => $groupBy,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'school_id' => $schoolId
            ]);

            Response::success($trends);
        } catch (\Exception $e) {
            Response::error('Failed to get enrollment trends: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 11. Get course popularity rankings
     * GET /api/analytics/admin/course-popularity
     *
     * @param array $params Route parameters
     * @return void
     */
    public function getCoursePopularity(array $params): void
    {
        $this->requireRole(['superadmin', 'orgadmin', 'schooladmin']);

        [$startDate, $endDate] = $this->resolveDateRange();
        $schoolId = $this->resolveSchoolFilter();

        try {
            // Filter by enrollment date range (and optionally school)
            $enrollmentJoinExtra = $schoolId
                ? 'AND EXISTS (SELECT 1 FROM users u WHERE u.id = e.user_id AND u.primary_school_id = :school_id AND u.is_active = 1)'
                : '';

            $sql = "SELECT
                        c.id as course_id,
                        c.title as course_title,
                        c.description,
                        c.is_published,
                        COUNT(e.id) as total_enrollments,
                        SUM(CASE WHEN e.status = 'active' THEN 1 ELSE 0 END) as active_enrollments,
                        SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) as completions,
                        AVG(e.progress_percentage) as avg_progress_percentage,
                        (SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) / NULLIF(COUNT(e.id), 0) * 100) as completion_rate,
                        MAX(e.enrolled_at) as last_enrollment_date
                    FROM courses c
                    LEFT JOIN enrollments e ON c.id = e.course_id
                        AND e.enrolled_at >= :start_date AND e.enrolled_at <= :end_date
                        $enrollmentJoinExtra
                    GROUP BY c.id, c.title, c.description, c.is_published
                    ORDER BY total_enrollments DESC";

            $execParams = ['start_date' => $startDate, 'end_date' => $endDate];
            if ($schoolId) {
                $execParams['school_id'] = $schoolId;
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($execParams);
            $popularity = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            Response::success([
                'courses' => $popularity,
                'total_courses' => count($popularity)
            ]);
        } catch (\Exception $e) {
            Response::error('Failed to get course popularity: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 12. Get user acquisition trends
     * GET /api/analytics/admin/user-acquisition
     *
     * @param array $params Route parameters
     * @return void
     */
    public function getUserAcquisition(array $params): void
    {
        $this->requireRole(['superadmin', 'orgadmin', 'schooladmin']);

        $groupBy = $_GET['group_by'] ?? 'month';
        [$startDate, $endDate] = $this->resolveDateRange();
        $schoolId = $this->resolveSchoolFilter();

        try {
            $trends = $this->userModel->getAcquisitionTrends([
                'group_by' => $groupBy,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'school_id' => $schoolId
            ]);

            Response::success($trends);
        } catch (\Exception $e) {
            Response::error('Failed to get user acquisition trends: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 13. Get achievement distribution
     * GET /api/analytics/admin/achievement-distribution
     *
     * @param array $params Route parameters
     * @return void
     */
    public function getAchievementDistribution(array $params): void
    {
        $this->requireRole(['superadmin', 'orgadmin', 'schooladmin']);

        [$startDate, $endDate] = $this->resolveDateRange();
        $schoolId = $this->resolveSchoolFilter();

        try {
            // Filter achievements unlocked within the date range (optionally scoped to a school)
            $schoolClause = $schoolId
                ? 'AND EXISTS (SELECT 1 FROM users u WHERE u.id = ua.user_id AND u.primary_school_id = :school_id AND u.is_active = 1)'
                : '';

            $sql = "SELECT
                        a.id as achievement_id,
                        a.name as achievement_title,
                        a.category_id,
                        ac.name as category_name,
                        a.tier,
                        a.points,
                        COUNT(ua.id) as unlock_count,
                        MIN(ua.unlocked_at) as first_unlock_date,
                        MAX(ua.unlocked_at) as last_unlock_date
                    FROM achievements a
                    LEFT JOIN user_achievements ua ON a.id = ua.achievement_id
                        AND ua.unlocked_at >= :start_date AND ua.unlocked_at <= :end_date
                        $schoolClause
                    INNER JOIN achievement_categories ac ON a.category_id = ac.id
                    GROUP BY a.id, a.name, a.category_id, ac.name, a.tier, a.points
                    ORDER BY unlock_count DESC";

            $execParams = ['start_date' => $startDate, 'end_date' => $endDate];
            if ($schoolId) {
                $execParams['school_id'] = $schoolId;
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($execParams);
            $distribution = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Tier breakdown
            $tierBreakdown = [
                'platinum' => 0,
                'gold' => 0,
                'silver' => 0,
                'bronze' => 0
            ];

            foreach ($distribution as $achievement) {
                $tierBreakdown[$achievement['tier']] += $achievement['unlock_count'];
            }

            Response::success([
                'achievements' => $distribution,
                'tier_breakdown' => $tierBreakdown,
                'total_unlocks' => array_sum($tierBreakdown)
            ]);
        } catch (\Exception $e) {
            Response::error('Failed to get achievement distribution: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 14. Get platform usage metrics
     * GET /api/analytics/admin/platform-usage
     *
     * @param array $params Route parameters
     * @return void
     */
    public function getPlatformUsage(array $params): void
    {
        $this->requireRole(['superadmin', 'orgadmin', 'schooladmin']);

        [$startDate, $endDate] = $this->resolveDateRange('30');
        $schoolId = $this->resolveSchoolFilter();

        try {
            $schoolUserClause = $schoolId ? 'AND primary_school_id = :school_id' : '';
            $schoolActivityClause = $schoolId
                ? 'AND EXISTS (SELECT 1 FROM users u WHERE u.id = lesson_progress.user_id AND u.primary_school_id = :school_id AND u.is_active = 1)'
                : '';

            // Active users (logged in within date range)
            $activeUsersSql = "SELECT COUNT(DISTINCT id) as active_users
                FROM users
                WHERE last_login_at >= :start_date AND last_login_at <= :end_date
                $schoolUserClause";

            $execParams = ['start_date' => $startDate, 'end_date' => $endDate];
            if ($schoolId) {
                $execParams['school_id'] = $schoolId;
            }

            $stmt = $this->pdo->prepare($activeUsersSql);
            $stmt->execute($execParams);
            $activeUsers = $stmt->fetch(\PDO::FETCH_ASSOC)['active_users'];

            // Total users (optionally scoped to the school)
            $totalUsersSql = "SELECT COUNT(*) as total_users FROM users WHERE is_active = 1 $schoolUserClause";
            $stmt = $this->pdo->prepare($totalUsersSql);
            $stmt->execute($schoolId ? ['school_id' => $schoolId] : []);
            $totalUsers = $stmt->fetch(\PDO::FETCH_ASSOC)['total_users'];

            // Heatmap data (activity by day of week and hour)
            $heatmapSql = "SELECT
                    DAYOFWEEK(completed_at) as day_of_week,
                    HOUR(completed_at) as hour_of_day,
                    COUNT(*) as activity_count
                FROM lesson_progress
                WHERE completed_at >= :start_date AND completed_at <= :end_date
                AND status = 'completed'
                $schoolActivityClause
                GROUP BY DAYOFWEEK(completed_at), HOUR(completed_at)";

            $stmt = $this->pdo->prepare($heatmapSql);
            $stmt->execute($execParams);
            $heatmapData = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            Response::success([
                'active_users' => $activeUsers,
                'total_users' => $totalUsers,
                'active_percentage' => round(($activeUsers / max($totalUsers, 1)) * 100, 2),
                'heatmap_data' => $heatmapData
            ]);
        } catch (\Exception $e) {
            Response::error('Failed to get platform usage: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 15. Get certificate issuance trends
     * GET /api/analytics/admin/certificate-trends
     *
     * @param array $params Route parameters
     * @return void
     */
    public function getCertificateTrends(array $params): void
    {
        $this->requireRole(['superadmin', 'orgadmin', 'schooladmin']);

        [$startDate, $endDate] = $this->resolveDateRange();
        $schoolId = $this->resolveSchoolFilter();

        try {
            if ($schoolId) {
                // Direct query against certificates (the v_certificate_trends view doesn't expose user_id)
                $sql = "SELECT
                            DATE(cert.issued_date) as issue_date_day,
                            DATE_FORMAT(cert.issued_date, '%Y-%m') as issue_month,
                            cert.course_id,
                            c.title as course_title,
                            COUNT(*) as certificates_issued
                        FROM certificates cert
                        INNER JOIN courses c ON cert.course_id = c.id
                        INNER JOIN users u ON cert.user_id = u.id
                        WHERE DATE(cert.issued_date) >= :start_date
                        AND DATE(cert.issued_date) <= :end_date
                        AND u.primary_school_id = :school_id
                        AND u.is_active = 1
                        GROUP BY DATE(cert.issued_date), DATE_FORMAT(cert.issued_date, '%Y-%m'), cert.course_id, c.title
                        ORDER BY issue_date_day ASC";

                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'school_id' => $schoolId
                ]);
            } else {
                $sql = "SELECT * FROM v_certificate_trends
                        WHERE issue_date_day >= :start_date
                        AND issue_date_day <= :end_date
                        ORDER BY issue_date_day ASC";

                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]);
            }

            $trends = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            Response::success([
                'trends' => $trends,
                'total_certificates' => array_sum(array_column($trends, 'certificates_issued'))
            ]);
        } catch (\Exception $e) {
            Response::error('Failed to get certificate trends: ' . $e->getMessage(), 500);
        }
    }

    // ================================================================
    // SCHOOL OVERVIEW ENDPOINT (superadmin-only)
    // ================================================================

    /**
     * Get full overview for a single school: school metadata, aggregate stats,
     * active-user counts, and a paginated student roster with per-student progress
     * and last-login data.
     *
     * GET /api/analytics/admin/school/:schoolId/overview
     *   ?page=1&per_page=25&sort=last_login_at|progress|name&search=...
     */
    public function getSchoolOverview(array $params): void
    {
        $this->requireRole(['superadmin']);

        if (!isset($params['schoolId'])) {
            Response::error('School ID is required', 400);
        }
        $schoolId = (int)$params['schoolId'];

        $schoolModel = new \App\Models\School($this->pdo);
        $school = $schoolModel->findById($schoolId);
        if (!$school) {
            Response::notFound('School not found');
        }

        $statsOnly = !empty($_GET['stats_only']);

        // Pagination + sort + search
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;

        $sort = $_GET['sort'] ?? 'last_login_at';
        $sortMap = [
            'last_login_at' => 'u.last_login_at DESC',
            'progress' => 'avg_progress DESC',
            'name' => 'u.name ASC',
            'created_at' => 'u.created_at DESC'
        ];
        $orderBy = $sortMap[$sort] ?? $sortMap['last_login_at'];

        $search = trim($_GET['search'] ?? '');

        try {
            // Aggregate stats (reuse School::getDetailedStatistics)
            $stats = $schoolModel->getDetailedStatistics($schoolId);

            // Active users in last 7 / 30 days
            $activeSql = "SELECT
                    SUM(CASE WHEN last_login_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as active_7d,
                    SUM(CASE WHEN last_login_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as active_30d
                FROM users
                WHERE primary_school_id = :school_id AND is_active = 1";
            $stmt = $this->pdo->prepare($activeSql);
            $stmt->execute(['school_id' => $schoolId]);
            $activeRow = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
            $stats['active_7d'] = (int)($activeRow['active_7d'] ?? 0);
            $stats['active_30d'] = (int)($activeRow['active_30d'] ?? 0);

            if ($statsOnly) {
                Response::success([
                    'school' => $school,
                    'stats' => $stats,
                    'students' => ['data' => [], 'pagination' => null]
                ]);
                return;
            }

            // Student roster with per-user aggregates (scoped to students only)
            // ATTR_EMULATE_PREPARES=false forbids reusing one placeholder twice — use two.
            $searchClause = '';
            if ($search !== '') {
                $searchClause = 'AND (u.name LIKE :search_name OR u.email LIKE :search_email)';
            }

            $rosterSql = "SELECT
                    u.id,
                    u.name,
                    u.email,
                    u.last_login_at,
                    u.created_at,
                    u.is_active,
                    COUNT(DISTINCT e.course_id) as courses_enrolled,
                    COALESCE(AVG(e.progress_percentage), 0) as avg_progress,
                    SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) as courses_completed,
                    (SELECT COUNT(*) FROM certificates cert WHERE cert.user_id = u.id) as certificates_earned
                FROM users u
                LEFT JOIN enrollments e ON e.user_id = u.id
                WHERE u.primary_school_id = :school_id
                AND u.role = 'student'
                AND u.is_active = 1
                $searchClause
                GROUP BY u.id, u.name, u.email, u.last_login_at, u.created_at, u.is_active
                ORDER BY $orderBy
                LIMIT :limit OFFSET :offset";

            $stmt = $this->pdo->prepare($rosterSql);
            $stmt->bindValue(':school_id', $schoolId, \PDO::PARAM_INT);
            if ($search !== '') {
                $needle = '%' . $search . '%';
                $stmt->bindValue(':search_name', $needle, \PDO::PARAM_STR);
                $stmt->bindValue(':search_email', $needle, \PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();
            $students = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Total count for pagination
            $countSql = "SELECT COUNT(*) FROM users u
                WHERE u.primary_school_id = :school_id
                AND u.role = 'student'
                AND u.is_active = 1
                $searchClause";
            $stmt = $this->pdo->prepare($countSql);
            $stmt->bindValue(':school_id', $schoolId, \PDO::PARAM_INT);
            if ($search !== '') {
                $needle = '%' . $search . '%';
                $stmt->bindValue(':search_name', $needle, \PDO::PARAM_STR);
                $stmt->bindValue(':search_email', $needle, \PDO::PARAM_STR);
            }
            $stmt->execute();
            $totalStudents = (int)$stmt->fetchColumn();

            Response::success([
                'school' => $school,
                'stats' => $stats,
                'students' => [
                    'data' => $students,
                    'pagination' => [
                        'page' => $page,
                        'per_page' => $perPage,
                        'total' => $totalStudents,
                        'total_pages' => (int)ceil($totalStudents / $perPage)
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Response::error('Failed to get school overview: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get a list of every active school with quick per-school stats:
     * user counts broken down by role, average enrollment progress, and
     * active-user count over the last 30 days. Used to render the
     * "Schools Overview" card grid on the admin analytics page.
     *
     * GET /api/analytics/admin/schools-overview
     */
    public function getSchoolsOverview(): void
    {
        $this->requireRole(['superadmin']);

        try {
            $sql = "SELECT
                    s.id,
                    s.name,
                    o.name AS organization_name,
                    COUNT(DISTINCT u.id) AS total_users,
                    COUNT(DISTINCT CASE WHEN u.role = 'student' THEN u.id END) AS total_students,
                    COUNT(DISTINCT CASE WHEN u.role IN ('teacher','instructor') THEN u.id END) AS total_teachers,
                    COUNT(DISTINCT CASE WHEN u.role IN ('schooladmin','orgadmin','admin','superadmin') THEN u.id END) AS total_admins,
                    COALESCE(AVG(e.progress_percentage), 0) AS avg_progress,
                    COUNT(DISTINCT CASE WHEN u.last_login_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN u.id END) AS active_30d
                FROM schools s
                LEFT JOIN organizations o ON s.organization_id = o.id
                INNER JOIN users u ON u.primary_school_id = s.id AND u.is_active = 1
                LEFT JOIN enrollments e ON e.user_id = u.id
                WHERE s.is_active = 1
                GROUP BY s.id, s.name, o.name
                HAVING total_users > 0
                ORDER BY active_30d DESC, total_users DESC, avg_progress DESC, s.name ASC";

            $stmt = $this->pdo->query($sql);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $schools = array_map(function ($row) {
                return [
                    'id' => (int)$row['id'],
                    'name' => $row['name'],
                    'organization_name' => $row['organization_name'],
                    'total_users' => (int)$row['total_users'],
                    'total_students' => (int)$row['total_students'],
                    'total_teachers' => (int)$row['total_teachers'],
                    'total_admins' => (int)$row['total_admins'],
                    'avg_progress' => round((float)$row['avg_progress'], 1),
                    'active_30d' => (int)$row['active_30d'],
                ];
            }, $rows);

            Response::success(['schools' => $schools]);
        } catch (\Exception $e) {
            Response::error('Failed to get schools overview: ' . $e->getMessage(), 500);
        }
    }
}
