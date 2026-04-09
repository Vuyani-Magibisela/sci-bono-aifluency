<?php
namespace App\Controllers;

use App\Utils\Response;

/**
 * Admin Controller
 *
 * Handles admin dashboard operations (statistics, activity feed)
 */
class AdminController extends BaseController
{
    public function __construct(\PDO $pdo)
    {
        parent::__construct($pdo);
    }

    /**
     * Get system statistics for admin dashboard
     *
     * Returns aggregated counts of users, courses, enrollments, etc.
     * Requires admin role (superadmin, orgadmin, or schooladmin)
     *
     * @param array $params Route parameters (unused)
     * @return void
     */
    public function getStats(array $params): void
    {
        try {
            $schoolIds = $this->getManagedSchoolIds();

            if ($schoolIds === null) {
                // Superadmin: system-wide stats
                $sql = "
                    SELECT
                        (SELECT COUNT(*) FROM users WHERE role NOT IN ('superadmin', 'schooladmin')) as total_users,
                        (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students,
                        (SELECT COUNT(*) FROM users WHERE role IN ('instructor', 'teacher')) as total_teachers,
                        (SELECT COUNT(*) FROM courses) as total_courses,
                        (SELECT COUNT(*) FROM enrollments) as total_enrollments,
                        (SELECT COUNT(*) FROM certificates) as total_certificates,
                        (SELECT COUNT(DISTINCT user_id) FROM lesson_progress
                         WHERE DATE(updated_at) = UTC_DATE()) as active_users_today
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute();
            } else {
                // Scoped by school(s)
                if (empty($schoolIds)) {
                    // No managed schools — return zeros
                    Response::success([
                        'total_users' => 0, 'total_students' => 0, 'total_teachers' => 0,
                        'total_courses' => 0, 'total_enrollments' => 0, 'total_certificates' => 0,
                        'active_users_today' => 0
                    ]);
                    return;
                }

                $placeholders = implode(',', array_fill(0, count($schoolIds), '?'));
                $sql = "
                    SELECT
                        (SELECT COUNT(*) FROM users WHERE role NOT IN ('superadmin', 'schooladmin') AND primary_school_id IN ($placeholders)) as total_users,
                        (SELECT COUNT(*) FROM users WHERE role = 'student' AND primary_school_id IN ($placeholders)) as total_students,
                        (SELECT COUNT(*) FROM users WHERE role IN ('instructor', 'teacher') AND primary_school_id IN ($placeholders)) as total_teachers,
                        (SELECT COUNT(*) FROM courses) as total_courses,
                        (SELECT COUNT(*) FROM enrollments WHERE user_id IN (SELECT id FROM users WHERE primary_school_id IN ($placeholders))) as total_enrollments,
                        (SELECT COUNT(*) FROM certificates WHERE user_id IN (SELECT id FROM users WHERE primary_school_id IN ($placeholders))) as total_certificates,
                        (SELECT COUNT(DISTINCT lp.user_id) FROM lesson_progress lp
                         INNER JOIN users u ON lp.user_id = u.id
                         WHERE DATE(lp.updated_at) = UTC_DATE() AND u.primary_school_id IN ($placeholders)) as active_users_today
                ";
                // Each subquery needs its own set of school ID bindings (6 subqueries)
                $bindValues = [];
                for ($i = 0; $i < 6; $i++) {
                    $bindValues = array_merge($bindValues, $schoolIds);
                }
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($bindValues);
            }

            $stats = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Defensive handling: ensure all fields present with defaults
            $defaultStats = [
                'total_users' => 0,
                'total_students' => 0,
                'total_teachers' => 0,
                'total_courses' => 0,
                'total_enrollments' => 0,
                'total_certificates' => 0,
                'active_users_today' => 0
            ];

            // Merge to ensure all fields exist (handles empty database)
            $stats = $stats ? array_merge($defaultStats, $stats) : $defaultStats;

            // Convert all values to integers
            foreach ($stats as $key => $value) {
                $stats[$key] = (int)$value;
            }

            Response::success($stats);

        } catch (\PDOException $e) {
            error_log("Admin stats DB error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            Response::error('Failed to retrieve statistics', 500);
        } catch (\Exception $e) {
            error_log("Admin stats error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            Response::error('An unexpected error occurred', 500);
        }
    }

    /**
     * Get consolidated user statistics for dashboard
     *
     * Returns enrolled users, school/org breakdowns, course progress, and activity metrics.
     * Scoped by getManagedSchoolIds() — superadmin sees all, others see their scope.
     *
     * @param array $params Route parameters (unused)
     * @return void
     */
    public function getUserStats(array $params): void
    {
        try {
            $schoolIds = $this->getManagedSchoolIds();

            // Default metrics
            $defaultCourseProgress = [
                'total_enrollments' => 0, 'active_enrollments' => 0,
                'completed_enrollments' => 0, 'avg_progress' => 0, 'completion_rate' => 0
            ];
            $metricsResult = [
                'active_users_today' => 0, 'active_users_7days' => 0,
                'recent_signups_30days' => 0, 'total_certificates' => 0,
                'avg_quiz_score' => 0, 'total_quiz_attempts' => 0
            ];

            if ($schoolIds !== null && empty($schoolIds)) {
                Response::success([
                    'enrolled_users' => 0, 'total_schools' => 0, 'total_organizations' => 0,
                    'schools' => [], 'organizations' => [],
                    'course_progress' => $defaultCourseProgress, 'metrics' => $metricsResult
                ]);
                return;
            }

            $placeholders = ($schoolIds !== null)
                ? implode(',', array_fill(0, count($schoolIds), '?'))
                : '';

            // 1. Enrolled users count (exclude admin roles)
            if ($schoolIds === null) {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE role NOT IN ('superadmin', 'schooladmin')");
                $stmt->execute();
            } else {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE role NOT IN ('superadmin', 'schooladmin') AND primary_school_id IN ($placeholders)");
                $stmt->execute($schoolIds);
            }
            $enrolledUsers = (int)$stmt->fetchColumn();

            // 2. Schools — direct count + breakdown
            $totalSchoolCount = 0;
            $schools = [];
            try {
                if ($schoolIds === null) {
                    // Only count schools that have at least one student or teacher
                    $stmt = $this->pdo->prepare("
                        SELECT COUNT(*) FROM schools s
                        WHERE EXISTS (SELECT 1 FROM users u WHERE u.primary_school_id = s.id AND u.role IN ('student', 'teacher', 'instructor'))
                    ");
                    $stmt->execute();
                    $totalSchoolCount = (int)$stmt->fetchColumn();

                    $stmt = $this->pdo->prepare("
                        SELECT s.id, s.name, COALESCE(o.name, 'No Organization') as organization_name,
                            (SELECT COUNT(*) FROM users u WHERE u.primary_school_id = s.id AND u.role = 'student') as student_count,
                            (SELECT COUNT(*) FROM users u WHERE u.primary_school_id = s.id AND u.role IN ('teacher', 'instructor')) as teacher_count,
                            (SELECT COUNT(*) FROM users u WHERE u.primary_school_id = s.id AND u.role NOT IN ('superadmin', 'schooladmin')) as user_count
                        FROM schools s
                        LEFT JOIN organizations o ON s.organization_id = o.id
                        HAVING user_count > 0
                        ORDER BY user_count DESC
                    ");
                    $stmt->execute();
                } else {
                    $stmt = $this->pdo->prepare("
                        SELECT COUNT(*) FROM schools s
                        WHERE s.id IN ($placeholders)
                        AND EXISTS (SELECT 1 FROM users u WHERE u.primary_school_id = s.id AND u.role IN ('student', 'teacher', 'instructor'))
                    ");
                    $stmt->execute($schoolIds);
                    $totalSchoolCount = (int)$stmt->fetchColumn();

                    $stmt = $this->pdo->prepare("
                        SELECT s.id, s.name, COALESCE(o.name, 'No Organization') as organization_name,
                            (SELECT COUNT(*) FROM users u WHERE u.primary_school_id = s.id AND u.role = 'student') as student_count,
                            (SELECT COUNT(*) FROM users u WHERE u.primary_school_id = s.id AND u.role IN ('teacher', 'instructor')) as teacher_count,
                            (SELECT COUNT(*) FROM users u WHERE u.primary_school_id = s.id AND u.role NOT IN ('superadmin', 'schooladmin')) as user_count
                        FROM schools s
                        LEFT JOIN organizations o ON s.organization_id = o.id
                        WHERE s.id IN ($placeholders)
                        HAVING user_count > 0
                        ORDER BY user_count DESC
                    ");
                    $stmt->execute($schoolIds);
                }
                $schools = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($schools as &$s) {
                    $s['id'] = (int)$s['id'];
                    $s['student_count'] = (int)$s['student_count'];
                    $s['teacher_count'] = (int)$s['teacher_count'];
                    $s['user_count'] = (int)$s['user_count'];
                }
                unset($s);
            } catch (\PDOException $e) {
                error_log("user-stats schools query: " . $e->getMessage());
            }

            // 3. Organizations — direct count + breakdown
            $totalOrgCount = 0;
            $organizations = [];
            try {
                if ($schoolIds === null) {
                    // Only count orgs that have schools with enrolled students/teachers
                    $stmt = $this->pdo->prepare("
                        SELECT COUNT(DISTINCT o.id) FROM organizations o
                        INNER JOIN schools sc ON sc.organization_id = o.id
                        WHERE EXISTS (SELECT 1 FROM users u WHERE u.primary_school_id = sc.id AND u.role IN ('student', 'teacher', 'instructor'))
                    ");
                    $stmt->execute();
                    $totalOrgCount = (int)$stmt->fetchColumn();

                    $stmt = $this->pdo->prepare("
                        SELECT o.id, o.name,
                            (SELECT COUNT(*) FROM schools sc WHERE sc.organization_id = o.id AND EXISTS (SELECT 1 FROM users u WHERE u.primary_school_id = sc.id AND u.role IN ('student', 'teacher', 'instructor'))) as school_count,
                            (SELECT COUNT(*) FROM users u INNER JOIN schools sc ON u.primary_school_id = sc.id WHERE sc.organization_id = o.id AND u.role NOT IN ('superadmin', 'schooladmin')) as user_count
                        FROM organizations o
                        HAVING user_count > 0
                        ORDER BY user_count DESC
                    ");
                    $stmt->execute();
                } else {
                    $stmt = $this->pdo->prepare("
                        SELECT COUNT(DISTINCT o.id) FROM organizations o
                        INNER JOIN schools sc ON sc.organization_id = o.id
                        WHERE sc.id IN ($placeholders)
                    ");
                    $stmt->execute($schoolIds);
                    $totalOrgCount = (int)$stmt->fetchColumn();

                    $stmt = $this->pdo->prepare("
                        SELECT o.id, o.name,
                            (SELECT COUNT(*) FROM schools sc WHERE sc.organization_id = o.id AND sc.id IN ($placeholders)) as school_count,
                            (SELECT COUNT(*) FROM users u WHERE u.primary_school_id IN ($placeholders) AND u.role NOT IN ('superadmin', 'schooladmin') AND u.primary_school_id IN (SELECT sc2.id FROM schools sc2 WHERE sc2.organization_id = o.id)) as user_count
                        FROM organizations o
                        WHERE o.id IN (SELECT DISTINCT sc3.organization_id FROM schools sc3 WHERE sc3.id IN ($placeholders))
                        ORDER BY user_count DESC
                    ");
                    $stmt->execute(array_merge($schoolIds, $schoolIds, $schoolIds));
                }
                $organizations = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($organizations as &$org) {
                    $org['id'] = (int)$org['id'];
                    $org['school_count'] = (int)$org['school_count'];
                    $org['user_count'] = (int)$org['user_count'];
                }
                unset($org);
            } catch (\PDOException $e) {
                error_log("user-stats organizations query: " . $e->getMessage());
            }

            // 4. Course progress
            $courseProgress = $defaultCourseProgress;
            try {
                if ($schoolIds === null) {
                    $stmt = $this->pdo->prepare("
                        SELECT
                            COUNT(*) as total_enrollments,
                            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_enrollments,
                            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_enrollments,
                            ROUND(AVG(progress_percentage), 1) as avg_progress
                        FROM enrollments
                    ");
                    $stmt->execute();
                } else {
                    $stmt = $this->pdo->prepare("
                        SELECT
                            COUNT(*) as total_enrollments,
                            SUM(CASE WHEN e.status = 'active' THEN 1 ELSE 0 END) as active_enrollments,
                            SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) as completed_enrollments,
                            ROUND(AVG(e.progress_percentage), 1) as avg_progress
                        FROM enrollments e
                        INNER JOIN users u ON e.user_id = u.id
                        WHERE u.primary_school_id IN ($placeholders)
                    ");
                    $stmt->execute($schoolIds);
                }
                $cp = $stmt->fetch(\PDO::FETCH_ASSOC);
                $totalEnrollments = (int)($cp['total_enrollments'] ?? 0);
                $completedEnrollments = (int)($cp['completed_enrollments'] ?? 0);
                $courseProgress = [
                    'total_enrollments' => $totalEnrollments,
                    'active_enrollments' => (int)($cp['active_enrollments'] ?? 0),
                    'completed_enrollments' => $completedEnrollments,
                    'avg_progress' => (float)($cp['avg_progress'] ?? 0),
                    'completion_rate' => $totalEnrollments > 0 ? round(($completedEnrollments / $totalEnrollments) * 100, 1) : 0
                ];
            } catch (\PDOException $e) {
                error_log("user-stats enrollments query: " . $e->getMessage());
            }

            // 5. Activity metrics — each in its own try/catch

            // Recent signups
            if ($schoolIds === null) {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND role NOT IN ('superadmin', 'schooladmin')");
                $stmt->execute();
            } else {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND role NOT IN ('superadmin', 'schooladmin') AND primary_school_id IN ($placeholders)");
                $stmt->execute($schoolIds);
            }
            $metricsResult['recent_signups_30days'] = (int)$stmt->fetchColumn();

            // Active users — use last_login_at from users table (more reliable than lesson_progress)
            try {
                if ($schoolIds === null) {
                    $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE DATE(last_login_at) = CURDATE() AND role NOT IN ('superadmin', 'schooladmin')");
                    $stmt->execute();
                    $metricsResult['active_users_today'] = (int)$stmt->fetchColumn();

                    $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE last_login_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND role NOT IN ('superadmin', 'schooladmin')");
                    $stmt->execute();
                    $metricsResult['active_users_7days'] = (int)$stmt->fetchColumn();
                } else {
                    $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE DATE(last_login_at) = CURDATE() AND role NOT IN ('superadmin', 'schooladmin') AND primary_school_id IN ($placeholders)");
                    $stmt->execute($schoolIds);
                    $metricsResult['active_users_today'] = (int)$stmt->fetchColumn();

                    $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE last_login_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND role NOT IN ('superadmin', 'schooladmin') AND primary_school_id IN ($placeholders)");
                    $stmt->execute($schoolIds);
                    $metricsResult['active_users_7days'] = (int)$stmt->fetchColumn();
                }
            } catch (\PDOException $e) {
                error_log("user-stats active users query: " . $e->getMessage());
            }

            // Certificates
            try {
                if ($schoolIds === null) {
                    $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM certificates");
                    $stmt->execute();
                } else {
                    $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM certificates WHERE user_id IN (SELECT id FROM users WHERE primary_school_id IN ($placeholders))");
                    $stmt->execute($schoolIds);
                }
                $metricsResult['total_certificates'] = (int)$stmt->fetchColumn();
            } catch (\PDOException $e) {
                error_log("user-stats certificates query: " . $e->getMessage());
            }

            // Quiz metrics
            try {
                if ($schoolIds === null) {
                    $stmt = $this->pdo->prepare("SELECT ROUND(AVG(score), 1) FROM quiz_attempts WHERE status = 'submitted' AND score IS NOT NULL");
                    $stmt->execute();
                    $metricsResult['avg_quiz_score'] = (float)($stmt->fetchColumn() ?: 0);

                    $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM quiz_attempts WHERE status = 'submitted'");
                    $stmt->execute();
                    $metricsResult['total_quiz_attempts'] = (int)$stmt->fetchColumn();
                } else {
                    $stmt = $this->pdo->prepare("SELECT ROUND(AVG(qa.score), 1) FROM quiz_attempts qa INNER JOIN users u ON qa.user_id = u.id WHERE qa.status = 'submitted' AND qa.score IS NOT NULL AND u.primary_school_id IN ($placeholders)");
                    $stmt->execute($schoolIds);
                    $metricsResult['avg_quiz_score'] = (float)($stmt->fetchColumn() ?: 0);

                    $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM quiz_attempts qa INNER JOIN users u ON qa.user_id = u.id WHERE qa.status = 'submitted' AND u.primary_school_id IN ($placeholders)");
                    $stmt->execute($schoolIds);
                    $metricsResult['total_quiz_attempts'] = (int)$stmt->fetchColumn();
                }
            } catch (\PDOException $e) {
                error_log("user-stats quiz query: " . $e->getMessage());
            }

            Response::success([
                'enrolled_users' => $enrolledUsers,
                'total_schools' => $totalSchoolCount,
                'total_organizations' => $totalOrgCount,
                'schools' => $schools,
                'organizations' => $organizations,
                'course_progress' => $courseProgress,
                'metrics' => $metricsResult
            ]);

        } catch (\PDOException $e) {
            error_log("Admin user-stats DB error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            Response::error('Failed to retrieve user statistics: ' . $e->getMessage(), 500);
        } catch (\Exception $e) {
            error_log("Admin user-stats error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            Response::error('An unexpected error occurred: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get recent platform activity for admin dashboard
     *
     * Returns array of recent events (user registrations, enrollments, certificates, etc.)
     * Requires admin role (superadmin, orgadmin, or schooladmin)
     *
     * @param array $params Route parameters (unused)
     * @return void
     */
    /**
     * Check if a database table exists
     */
    private function tableExists(string $table): bool
    {
        try {
            $stmt = $this->pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function getActivity(array $params): void
    {
        try {
            // Get limit from query parameter (default 10)
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $limit = max(1, min($limit, 100)); // Clamp between 1-100

            // Determine school scoping
            $schoolIds = $this->getManagedSchoolIds();
            $schoolFilter = '';
            $schoolFilterUser = ''; // For queries that reference users table directly
            $bindParams = [];

            if ($schoolIds !== null) {
                if (empty($schoolIds)) {
                    Response::success([]);
                    return;
                }
                $placeholders = implode(',', array_fill(0, count($schoolIds), '?'));
                $schoolFilter = " AND primary_school_id IN ($placeholders)";
                $schoolFilterUser = $schoolFilter; // Same filter on users table
            }

            // Build UNION ALL dynamically based on which tables exist
            $subqueries = [];

            // Core tables (always expected)
            $subqueries[] = "
                SELECT
                    'user_registered' as type,
                    CONCAT('New user registered: ', COALESCE(name, email)) as description,
                    created_at,
                    id as entity_id,
                    id as user_id,
                    NULL as course_id
                FROM users
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                $schoolFilterUser
            ";
            if ($schoolIds !== null) {
                $bindParams = array_merge($bindParams, $schoolIds);
            }

            if ($this->tableExists('courses')) {
                $subqueries[] = "
                    SELECT
                        'course_created' as type,
                        CONCAT('New course published: ', COALESCE(title, 'Untitled Course')) as description,
                        created_at,
                        id as entity_id,
                        NULL as user_id,
                        id as course_id
                    FROM courses
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ";
            }

            if ($this->tableExists('enrollments') && $this->tableExists('courses')) {
                $enrollSchoolJoin = ($schoolIds !== null)
                    ? " AND u.primary_school_id IN (" . implode(',', array_fill(0, count($schoolIds), '?')) . ")"
                    : "";
                $subqueries[] = "
                    SELECT
                        'enrollment' as type,
                        CONCAT(COALESCE(u.name, u.email), ' enrolled in ', COALESCE(c.title, 'Unknown Course')) as description,
                        e.enrolled_at as created_at,
                        e.id as entity_id,
                        e.user_id,
                        e.course_id
                    FROM enrollments e
                    INNER JOIN users u ON e.user_id = u.id
                    INNER JOIN courses c ON e.course_id = c.id
                    WHERE e.enrolled_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    $enrollSchoolJoin
                ";
                if ($schoolIds !== null) {
                    $bindParams = array_merge($bindParams, $schoolIds);
                }
            }

            if ($this->tableExists('certificates') && $this->tableExists('courses')) {
                $certSchoolJoin = ($schoolIds !== null)
                    ? " AND u.primary_school_id IN (" . implode(',', array_fill(0, count($schoolIds), '?')) . ")"
                    : "";
                $subqueries[] = "
                    SELECT
                        'certificate_issued' as type,
                        CONCAT(COALESCE(u.name, u.email), ' earned certificate: ', COALESCE(c.title, 'Unknown Course')) as description,
                        cert.issued_date as created_at,
                        cert.id as entity_id,
                        cert.user_id,
                        cert.course_id
                    FROM certificates cert
                    INNER JOIN users u ON cert.user_id = u.id
                    INNER JOIN courses c ON cert.course_id = c.id
                    WHERE cert.issued_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    $certSchoolJoin
                ";
                if ($schoolIds !== null) {
                    $bindParams = array_merge($bindParams, $schoolIds);
                }
            }

            if ($this->tableExists('quiz_attempts') && $this->tableExists('quizzes') && $this->tableExists('modules') && $this->tableExists('courses')) {
                $quizSchoolJoin = ($schoolIds !== null)
                    ? " AND u.primary_school_id IN (" . implode(',', array_fill(0, count($schoolIds), '?')) . ")"
                    : "";
                $subqueries[] = "
                    SELECT
                        'quiz_completed' as type,
                        CONCAT(COALESCE(u.name, u.email), ' completed quiz in ', COALESCE(c.title, 'Unknown Course')) as description,
                        qa.submitted_at as created_at,
                        qa.id as entity_id,
                        qa.user_id,
                        NULL as course_id
                    FROM quiz_attempts qa
                    INNER JOIN users u ON qa.user_id = u.id
                    INNER JOIN quizzes q ON qa.quiz_id = q.id
                    INNER JOIN modules m ON q.module_id = m.id
                    INNER JOIN courses c ON m.course_id = c.id
                    WHERE qa.submitted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                      AND qa.status = 'submitted'
                    $quizSchoolJoin
                ";
                if ($schoolIds !== null) {
                    $bindParams = array_merge($bindParams, $schoolIds);
                }
            }

            if ($this->tableExists('project_submissions') && $this->tableExists('projects')) {
                $projSchoolJoin = ($schoolIds !== null)
                    ? " AND u.primary_school_id IN (" . implode(',', array_fill(0, count($schoolIds), '?')) . ")"
                    : "";
                $subqueries[] = "
                    SELECT
                        'project_submitted' as type,
                        CONCAT(COALESCE(u.name, u.email), ' submitted project: ', COALESCE(p.title, 'Unknown Project')) as description,
                        ps.submitted_at as created_at,
                        ps.id as entity_id,
                        ps.user_id,
                        NULL as course_id
                    FROM project_submissions ps
                    INNER JOIN users u ON ps.user_id = u.id
                    INNER JOIN projects p ON ps.project_id = p.id
                    WHERE ps.submitted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    $projSchoolJoin
                ";
                if ($schoolIds !== null) {
                    $bindParams = array_merge($bindParams, $schoolIds);
                }
            }

            $sql = "SELECT * FROM (" . implode(" UNION ALL ", $subqueries) . ") as activity ORDER BY created_at DESC LIMIT ?";
            $bindParams[] = $limit;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($bindParams);
            $activities = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            Response::success($activities ?: []);

        } catch (\PDOException $e) {
            error_log("Admin activity DB error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            Response::error('Failed to retrieve activity', 500);
        } catch (\Exception $e) {
            error_log("Admin activity error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            Response::error('An unexpected error occurred', 500);
        }
    }
}
