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
                WHERE lp.user_id = :user_id";

            $params_sql = ['user_id' => $userId];

            if ($courseId) {
                $sql .= " AND m.course_id = :course_id";
                $params_sql['course_id'] = $courseId;
            }

            $sql .= "
                UNION ALL
                SELECT
                    'quiz' as content_type,
                    q.id as content_id,
                    q.title as content_title,
                    m.title as module_title,
                    (qa.time_spent_seconds / 60) as time_minutes,
                    qa.status,
                    qa.time_completed as last_activity
                FROM quiz_attempts qa
                INNER JOIN quizzes q ON qa.quiz_id = q.id
                INNER JOIN modules m ON q.module_id = m.id
                WHERE qa.user_id = :user_id";

            if ($courseId) {
                $sql .= " AND m.course_id = :course_id";
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
            $sql = "SELECT
                    m.id as module_id,
                    m.title as module_title,
                    m.`order` as module_order,
                    COUNT(DISTINCT lp.lesson_id) as lessons_completed,
                    (SELECT COUNT(*) FROM lessons WHERE module_id = m.id) as total_lessons,
                    ROUND((COUNT(DISTINCT lp.lesson_id) / (SELECT COUNT(*) FROM lessons WHERE module_id = m.id)) * 100, 2) as lesson_completion_rate,
                    AVG(qa.score) as avg_quiz_score,
                    COUNT(DISTINCT qa.id) as quiz_attempts,
                    SUM(CASE WHEN qa.passed = 1 THEN 1 ELSE 0 END) as quizzes_passed,
                    SUM(lp.time_spent_minutes) as total_time_spent_minutes,
                    -- Proficiency score: weighted average of completion % and quiz score
                    ROUND((
                        (COUNT(DISTINCT lp.lesson_id) / (SELECT COUNT(*) FROM lessons WHERE module_id = m.id)) * 40 +
                        (AVG(IFNULL(qa.score, 0)) / 100) * 60
                    ) * 100, 2) as proficiency_score
                FROM modules m
                LEFT JOIN lessons l ON m.id = l.module_id
                LEFT JOIN lesson_progress lp ON l.id = lp.lesson_id AND lp.user_id = :user_id AND lp.status = 'completed'
                LEFT JOIN quizzes q ON m.id = q.module_id
                LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id AND qa.user_id = :user_id";

            $params_sql = ['user_id' => $userId];

            if ($courseId) {
                $sql .= " WHERE m.course_id = :course_id";
                $params_sql['course_id'] = $courseId;
            }

            $sql .= " GROUP BY m.id, m.title, m.`order`
                      ORDER BY m.`order` ASC";

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
        $this->requireRole(['instructor', 'admin']);

        if (!isset($params['courseId'])) {
            Response::error('Course ID is required', 400);
        }

        $courseId = (int)$params['courseId'];
        $quizId = $_GET['quiz_id'] ?? null;

        try {
            $sql = "SELECT
                    qa.score,
                    qa.user_id,
                    u.first_name,
                    u.last_name,
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

            Response::success([
                'distribution' => $distribution,
                'attempts' => $attempts,
                'total_attempts' => count($attempts),
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
        $this->requireRole(['instructor', 'admin']);

        if (!isset($params['courseId'])) {
            Response::error('Course ID is required', 400);
        }

        $courseId = (int)$params['courseId'];

        try {
            $engagement = $this->lessonProgressModel->getEngagementMetrics($courseId);

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
        $this->requireRole(['instructor', 'admin']);

        if (!isset($params['quizId'])) {
            Response::error('Quiz ID is required', 400);
        }

        $quizId = (int)$params['quizId'];

        try {
            // Question difficulty and discrimination index
            $sql = "SELECT
                    qq.id as question_id,
                    qq.question_text,
                    qq.question_type,
                    qq.points as max_points,
                    COUNT(qaa.id) as total_responses,
                    SUM(CASE WHEN qaa.is_correct = 1 THEN 1 ELSE 0 END) as correct_count,
                    ROUND((SUM(CASE WHEN qaa.is_correct = 1 THEN 1 ELSE 0 END) / COUNT(qaa.id)) * 100, 2) as success_rate,
                    ROUND(100 - ((SUM(CASE WHEN qaa.is_correct = 1 THEN 1 ELSE 0 END) / COUNT(qaa.id)) * 100), 2) as difficulty_score,
                    AVG(qaa.time_spent_seconds) as avg_time_seconds,
                    AVG(qaa.points_awarded) as avg_points_awarded
                FROM quiz_questions qq
                LEFT JOIN quiz_attempt_answers qaa ON qq.id = qaa.question_id
                WHERE qq.quiz_id = :quiz_id
                GROUP BY qq.id, qq.question_text, qq.question_type, qq.points
                ORDER BY difficulty_score DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['quiz_id' => $quizId]);
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
        $this->requireRole(['instructor', 'admin']);

        if (!isset($params['courseId'])) {
            Response::error('Course ID is required', 400);
        }

        $courseId = (int)$params['courseId'];

        try {
            $atRiskStudents = $this->userModel->getAtRiskStudents($courseId);

            Response::success([
                'at_risk_students' => $atRiskStudents,
                'total_at_risk' => count($atRiskStudents)
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
        $this->requireRole(['instructor', 'admin']);

        $currentUser = $this->getCurrentUser();

        try {
            // Pending project submissions
            $projectsSql = "SELECT COUNT(*) as pending_projects
                FROM project_submissions ps
                INNER JOIN projects p ON ps.project_id = p.id
                INNER JOIN modules m ON p.module_id = m.id
                INNER JOIN courses c ON m.course_id = c.id
                WHERE ps.status = 'submitted'
                AND c.instructor_id = :instructor_id";

            $stmt = $this->pdo->prepare($projectsSql);
            $stmt->execute(['instructor_id' => $currentUser->id]);
            $pendingProjects = $stmt->fetch(\PDO::FETCH_ASSOC)['pending_projects'];

            // Pending quiz reviews (manual grading)
            $quizzesSql = "SELECT COUNT(*) as pending_quizzes
                FROM quiz_attempts qa
                INNER JOIN quizzes q ON qa.quiz_id = q.id
                INNER JOIN modules m ON q.module_id = m.id
                INNER JOIN courses c ON m.course_id = c.id
                WHERE qa.status = 'submitted'
                AND c.instructor_id = :instructor_id";

            $stmt = $this->pdo->prepare($quizzesSql);
            $stmt->execute(['instructor_id' => $currentUser->id]);
            $pendingQuizzes = $stmt->fetch(\PDO::FETCH_ASSOC)['pending_quizzes'];

            // Recent grading activity
            $recentSql = "SELECT
                    'project' as item_type,
                    ps.id as item_id,
                    p.title as item_title,
                    u.first_name,
                    u.last_name,
                    ps.submitted_at,
                    ps.graded_at,
                    TIMESTAMPDIFF(HOUR, ps.submitted_at, ps.graded_at) as grading_time_hours
                FROM project_submissions ps
                INNER JOIN projects p ON ps.project_id = p.id
                INNER JOIN users u ON ps.user_id = u.id
                INNER JOIN modules m ON p.module_id = m.id
                INNER JOIN courses c ON m.course_id = c.id
                WHERE ps.status = 'graded'
                AND c.instructor_id = :instructor_id
                AND ps.graded_at IS NOT NULL
                ORDER BY ps.graded_at DESC
                LIMIT 10";

            $stmt = $this->pdo->prepare($recentSql);
            $stmt->execute(['instructor_id' => $currentUser->id]);
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
        $this->requireRole(['admin']);

        $groupBy = $_GET['group_by'] ?? 'month'; // 'day', 'week', 'month'
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-6 months'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');

        try {
            $trends = $this->enrollmentModel->getEnrollmentTrends([
                'group_by' => $groupBy,
                'start_date' => $startDate,
                'end_date' => $endDate
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
        $this->requireRole(['admin']);

        try {
            $sql = "SELECT * FROM v_course_popularity
                    ORDER BY total_enrollments DESC";

            $stmt = $this->pdo->query($sql);
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
        $this->requireRole(['admin']);

        $groupBy = $_GET['group_by'] ?? 'month';
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-6 months'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');

        try {
            $trends = $this->userModel->getAcquisitionTrends([
                'group_by' => $groupBy,
                'start_date' => $startDate,
                'end_date' => $endDate
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
        $this->requireRole(['admin']);

        try {
            $sql = "SELECT * FROM v_achievement_distribution
                    ORDER BY unlock_count DESC";

            $stmt = $this->pdo->query($sql);
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
        $this->requireRole(['admin']);

        $dateRange = $_GET['range'] ?? '30'; // days

        try {
            // Active users
            $activeUsersSql = "SELECT COUNT(DISTINCT id) as active_users
                FROM users
                WHERE last_login_at >= DATE_SUB(NOW(), INTERVAL :days DAY)";

            $stmt = $this->pdo->prepare($activeUsersSql);
            $stmt->execute(['days' => $dateRange]);
            $activeUsers = $stmt->fetch(\PDO::FETCH_ASSOC)['active_users'];

            // Total users
            $totalUsersSql = "SELECT COUNT(*) as total_users FROM users WHERE is_active = 1";
            $stmt = $this->pdo->query($totalUsersSql);
            $totalUsers = $stmt->fetch(\PDO::FETCH_ASSOC)['total_users'];

            // Heatmap data (activity by day of week and hour)
            $heatmapSql = "SELECT
                    DAYOFWEEK(completed_at) as day_of_week,
                    HOUR(completed_at) as hour_of_day,
                    COUNT(*) as activity_count
                FROM lesson_progress
                WHERE completed_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                AND status = 'completed'
                GROUP BY DAYOFWEEK(completed_at), HOUR(completed_at)";

            $stmt = $this->pdo->prepare($heatmapSql);
            $stmt->execute(['days' => $dateRange]);
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
        $this->requireRole(['admin']);

        $groupBy = $_GET['group_by'] ?? 'month';
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-6 months'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');

        try {
            $sql = "SELECT * FROM v_certificate_trends
                    WHERE issue_date_day >= :start_date
                    AND issue_date_day <= :end_date
                    ORDER BY issue_date_day ASC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            $trends = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            Response::success([
                'trends' => $trends,
                'total_certificates' => array_sum(array_column($trends, 'certificates_issued'))
            ]);
        } catch (\Exception $e) {
            Response::error('Failed to get certificate trends: ' . $e->getMessage(), 500);
        }
    }
}
