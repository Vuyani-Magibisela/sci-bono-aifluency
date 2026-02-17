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
                        (SELECT COUNT(*) FROM users) as total_users,
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
                        (SELECT COUNT(*) FROM users WHERE primary_school_id IN ($placeholders)) as total_users,
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
