<?php
namespace App\Models;

use PDO;

/**
 * Certificate Model
 *
 * Handles certificate-related database operations.
 * Schema: Phase 6 (Migration 012 + 035 consolidation).
 */
class Certificate extends BaseModel
{
    protected string $table = 'certificates';
    protected array $fillable = [
        'user_id',
        'course_id',
        'certificate_number',
        'issued_date',
        'certificate_url',
        'verification_code',
        'template_id',
        'certificate_type',
        'module_id',
        'quiz_id',
        'title',
        'description',
        'completion_date',
        'issue_date',
        'metadata',
        'pdf_path',
        'verification_url',
        'is_revoked',
        'revoked_at',
        'revoked_by',
        'revocation_reason'
    ];
    protected array $hidden = [];

    /**
     * Columns selected for enriched listing (used by getByUser/getRecent/getByCourse).
     * Matches the shape the frontend expects.
     */
    private const SELECT_ENRICHED = "
        c.id, c.certificate_number, c.user_id, c.course_id, c.template_id,
        c.certificate_type, c.title, c.description,
        c.completion_date, c.issue_date,
        COALESCE(c.issue_date, c.issued_date) AS effective_issue_date,
        c.pdf_path, c.verification_url, c.is_revoked,
        c.revoked_at, c.revocation_reason, c.metadata,
        u.name AS student_name, u.email AS student_email,
        co.title AS course_title, co.description AS course_description,
        co.duration_hours AS course_duration
    ";

    /**
     * Get certificates by user (enriched).
     */
    public function getByUser(int $userId, ?int $limit = null, ?int $offset = null): array
    {
        try {
            $sql = "SELECT " . self::SELECT_ENRICHED . "
                    FROM {$this->table} c
                    JOIN users u ON c.user_id = u.id
                    JOIN courses co ON c.course_id = co.id
                    WHERE c.user_id = :user_id
                    ORDER BY COALESCE(c.issue_date, c.issued_date) DESC";

            $params = ['user_id' => $userId];

            if ($limit !== null) {
                $sql .= " LIMIT " . (int)$limit;
                if ($offset !== null) {
                    $sql .= " OFFSET " . (int)$offset;
                }
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\PDOException $e) {
            error_log("Database error in Certificate::getByUser: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get certificates by course (enriched).
     */
    public function getByCourse(int $courseId, ?int $limit = null, ?int $offset = null): array
    {
        try {
            $sql = "SELECT " . self::SELECT_ENRICHED . "
                    FROM {$this->table} c
                    JOIN users u ON c.user_id = u.id
                    JOIN courses co ON c.course_id = co.id
                    WHERE c.course_id = :course_id
                    ORDER BY COALESCE(c.issue_date, c.issued_date) DESC";

            $params = ['course_id' => $courseId];

            if ($limit !== null) {
                $sql .= " LIMIT " . (int)$limit;
                if ($offset !== null) {
                    $sql .= " OFFSET " . (int)$offset;
                }
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\PDOException $e) {
            error_log("Database error in Certificate::getByCourse: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get user's certificate for a course (raw row).
     */
    public function getUserCourseCertificate(int $userId, int $courseId): ?object
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
            error_log("Database error in getUserCourseCertificate: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Find certificate by certificate number.
     */
    public function findByCertificateNumber(string $certificateNumber): ?object
    {
        return $this->findBy('certificate_number', $certificateNumber);
    }

    /**
     * Issue a course-completion certificate.
     *
     * Resolves template via course's certificate_template_id, falling back to a
     * global default. Populates Phase 6 columns. Idempotent: if a certificate
     * already exists for this (user, course), returns its id.
     */
    public function issueCertificate(int $userId, int $courseId, ?int $templateId = null): ?int
    {
        $existing = $this->getUserCourseCertificate($userId, $courseId);
        if ($existing) {
            return (int)$existing->id;
        }

        // Resolve course details + template
        $course = null;
        try {
            $courseStmt = $this->pdo->prepare("
                SELECT id, title, description, certificate_template_id
                FROM courses WHERE id = :id LIMIT 1
            ");
            $courseStmt->execute(['id' => $courseId]);
            $course = $courseStmt->fetch(PDO::FETCH_OBJ);
        } catch (\PDOException $e) {
            error_log("Database error in issueCertificate (course lookup): " . $e->getMessage());
            return null;
        }

        if (!$course) {
            return null;
        }

        // Template resolution: explicit param → course-bound → global default
        $resolvedTemplateId = $templateId
            ?? ($course->certificate_template_id ? (int)$course->certificate_template_id : null)
            ?? $this->getDefaultTemplateId();

        if (!$resolvedTemplateId) {
            error_log("issueCertificate: no template available (course {$courseId})");
            return null;
        }

        $certificateNumber = $this->generateCertificateNumber($userId, $courseId);
        $today = date('Y-m-d');

        // Build verification URL relative to host
        $host = $_SERVER['HTTP_HOST'] ?? 'sci-bono-ai-hub.co.za';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $verificationUrl = sprintf('%s://%s/student/certificates.html?verify=%s', $scheme, $host, $certificateNumber);

        return $this->create([
            'user_id'            => $userId,
            'course_id'          => $courseId,
            'template_id'        => $resolvedTemplateId,
            'certificate_number' => $certificateNumber,
            'certificate_type'   => 'course_completion',
            'title'              => $course->title,
            'description'        => 'Has successfully completed the course "' . $course->title . '".',
            'completion_date'    => $today,
            'issue_date'         => $today,
            'issued_date'        => $today,        // legacy column, keep populated for back-compat
            'verification_url'   => $verificationUrl,
            'verification_code'  => $certificateNumber, // legacy column required by old schema if present
            'is_revoked'         => 0
        ]);
    }

    /**
     * Get the default global course-completion template ID.
     */
    private function getDefaultTemplateId(): ?int
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM certificate_templates
                WHERE template_type = 'course_completion'
                  AND is_active = 1
                  AND scope = 'global'
                ORDER BY id ASC
                LIMIT 1
            ");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_OBJ);
            return $row ? (int)$row->id : null;
        } catch (\PDOException $e) {
            error_log("Database error in getDefaultTemplateId: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate unique certificate number.
     * Format: CERT-{YEAR}-{COURSE_ID}-{USER_ID}-{RANDOM}
     */
    private function generateCertificateNumber(int $userId, int $courseId): string
    {
        $year = date('Y');
        $random = strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 6));
        return sprintf('CERT-%s-%04d-%06d-%s', $year, $courseId, $userId, $random);
    }

    /**
     * Verify certificate authenticity.
     * Logs the verification attempt to certificate_verification_log.
     */
    public function verifyCertificate(string $certificateNumber): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    c.*,
                    u.name AS student_name,
                    u.email AS student_email,
                    co.title AS course_title,
                    co.description AS course_description
                FROM {$this->table} c
                JOIN users u ON c.user_id = u.id
                JOIN courses co ON c.course_id = co.id
                WHERE c.certificate_number = :certificate_number
                LIMIT 1
            ");
            $stmt->execute(['certificate_number' => $certificateNumber]);
            $certificate = $stmt->fetch(PDO::FETCH_OBJ);

            if (!$certificate) {
                $this->logVerification($certificateNumber, 'invalid');
                return [
                    'valid' => false,
                    'status' => 'invalid',
                    'message' => 'Certificate not found'
                ];
            }

            if ((int)$certificate->is_revoked === 1) {
                $this->logVerification($certificateNumber, 'revoked');
                return [
                    'valid' => false,
                    'status' => 'revoked',
                    'message' => 'Certificate has been revoked',
                    'certificate' => $certificate
                ];
            }

            $this->logVerification($certificateNumber, 'valid');
            return [
                'valid' => true,
                'status' => 'valid',
                'certificate' => $certificate,
                'message' => 'Certificate is valid'
            ];
        } catch (\PDOException $e) {
            error_log("Database error in verifyCertificate: " . $e->getMessage());
            return [
                'valid' => false,
                'status' => 'error',
                'message' => 'An error occurred while verifying the certificate'
            ];
        }
    }

    /**
     * Append a verification attempt to the audit log.
     */
    private function logVerification(string $certificateNumber, string $result): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO certificate_verification_log
                    (certificate_number, ip_address, user_agent, verification_result)
                VALUES
                    (:cn, :ip, :ua, :result)
            ");
            $stmt->execute([
                'cn'     => $certificateNumber,
                'ip'     => $_SERVER['REMOTE_ADDR']     ?? null,
                'ua'     => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'result' => $result
            ]);
        } catch (\PDOException $e) {
            // Non-critical — don't fail the parent operation
            error_log("logVerification error: " . $e->getMessage());
        }
    }

    /**
     * Get certificate with user, course, and template details for rendering/download.
     */
    public function getCertificateWithDetails(int $certificateId): ?object
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    c.*,
                    COALESCE(c.issue_date, c.issued_date) AS effective_issue_date,
                    u.name AS student_name,
                    u.email AS student_email,
                    co.title AS course_title,
                    co.description AS course_description,
                    co.duration_hours AS course_duration,
                    t.name AS template_name,
                    t.template_data AS template_data,
                    t.background_file_id AS template_background_file_id
                FROM {$this->table} c
                JOIN users u ON c.user_id = u.id
                JOIN courses co ON c.course_id = co.id
                LEFT JOIN certificate_templates t ON c.template_id = t.id
                WHERE c.id = :certificate_id
                LIMIT 1
            ");
            $stmt->execute(['certificate_id' => $certificateId]);
            return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
        } catch (\PDOException $e) {
            error_log("Database error in getCertificateWithDetails: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if user is eligible for a course-completion certificate.
     *
     * Composite rule (replaces the legacy progress_percentage >= 100 check):
     *   1. User is enrolled in the course.
     *   2. Every published lesson in every published module has lesson_progress.status = 'completed'.
     *   3. Every published module-level quiz (one per module) has at least one passed attempt.
     *   4. Every published project in the course has a project_submissions row (any status).
     *
     * If a course has no lessons / no quizzes / no projects, that dimension is treated as auto-pass
     * (vacuously true) — so the rule still works for sparse courses.
     */
    public function isEligibleForCertificate(int $userId, int $courseId): bool
    {
        try {
            $enrolled = $this->pdo->prepare("
                SELECT 1 FROM enrollments WHERE user_id = :u AND course_id = :c LIMIT 1
            ");
            $enrolled->execute(['u' => $userId, 'c' => $courseId]);
            if (!$enrolled->fetchColumn()) {
                return false;
            }

            $lessons = $this->countLessonProgress($userId, $courseId);
            if ($lessons['total'] > 0 && $lessons['completed'] < $lessons['total']) {
                return false;
            }
            $quizzes = $this->countQuizProgress($userId, $courseId);
            if ($quizzes['total'] > 0 && $quizzes['passed'] < $quizzes['total']) {
                return false;
            }
            $projects = $this->countProjectProgress($userId, $courseId);
            if ($projects['total'] > 0 && $projects['submitted'] < $projects['total']) {
                return false;
            }
            return true;
        } catch (\PDOException $e) {
            error_log("Database error in isEligibleForCertificate: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Lesson progress counts for a user/course. Used by isEligibleForCertificate
     * and by the admin "what's missing" diagnostic. Only published lessons in
     * published modules count toward 'total'.
     *
     * @return array{total:int, completed:int}
     */
    public function countLessonProgress(int $userId, int $courseId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN lp.status = 'completed' THEN 1 ELSE 0 END) AS completed
                FROM lessons l
                JOIN modules m ON l.module_id = m.id
                LEFT JOIN lesson_progress lp
                       ON lp.lesson_id = l.id AND lp.user_id = :user_id
                WHERE m.course_id = :course_id
                  AND m.is_published = 1
                  AND l.is_published = 1
            ");
            $stmt->execute(['user_id' => $userId, 'course_id' => $courseId]);
            $row = $stmt->fetch(PDO::FETCH_OBJ);
            return [
                'total'     => (int)($row->total ?? 0),
                'completed' => (int)($row->completed ?? 0),
            ];
        } catch (\PDOException $e) {
            error_log("countLessonProgress: " . $e->getMessage());
            return ['total' => 0, 'completed' => 0];
        }
    }

    /**
     * Module-quiz pass counts for a user/course. Counts one published
     * module-level quiz per module (lesson_id IS NULL) — that's the AI Fluency model.
     *
     * @return array{total:int, passed:int, missing_titles:array<string>}
     */
    public function countQuizProgress(int $userId, int $courseId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT q.id, q.title,
                       (
                         SELECT MAX(qa.passed)
                         FROM quiz_attempts qa
                         WHERE qa.user_id = :user_id AND qa.quiz_id = q.id
                       ) AS passed
                FROM quizzes q
                JOIN modules m ON q.module_id = m.id
                WHERE m.course_id = :course_id
                  AND m.is_published = 1
                  AND q.is_published = 1
                  AND q.lesson_id IS NULL
                ORDER BY m.order_index ASC
            ");
            $stmt->execute(['user_id' => $userId, 'course_id' => $courseId]);
            $rows = $stmt->fetchAll(PDO::FETCH_OBJ);
            $total = count($rows);
            $passed = 0;
            $missing = [];
            foreach ($rows as $row) {
                if ((int)$row->passed === 1) {
                    $passed++;
                } else {
                    $missing[] = 'Quiz: ' . $row->title;
                }
            }
            return ['total' => $total, 'passed' => $passed, 'missing_titles' => $missing];
        } catch (\PDOException $e) {
            error_log("countQuizProgress: " . $e->getMessage());
            return ['total' => 0, 'passed' => 0, 'missing_titles' => []];
        }
    }

    /**
     * Project submission counts for a user/course. A project counts as
     * "submitted" once any submission row exists, regardless of grading status.
     *
     * @return array{total:int, submitted:int, missing_titles:array<string>}
     */
    public function countProjectProgress(int $userId, int $courseId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT p.id, p.title,
                       (
                         SELECT COUNT(*) FROM project_submissions ps
                         WHERE ps.user_id = :user_id AND ps.project_id = p.id
                       ) AS submission_count
                FROM projects p
                WHERE p.course_id = :course_id
                  AND p.is_published = 1
                ORDER BY p.module_id ASC, p.id ASC
            ");
            $stmt->execute(['user_id' => $userId, 'course_id' => $courseId]);
            $rows = $stmt->fetchAll(PDO::FETCH_OBJ);
            $total = count($rows);
            $submitted = 0;
            $missing = [];
            foreach ($rows as $row) {
                if ((int)$row->submission_count > 0) {
                    $submitted++;
                } else {
                    $missing[] = 'Project: ' . $row->title;
                }
            }
            return ['total' => $total, 'submitted' => $submitted, 'missing_titles' => $missing];
        } catch (\PDOException $e) {
            error_log("countProjectProgress: " . $e->getMessage());
            return ['total' => 0, 'submitted' => 0, 'missing_titles' => []];
        }
    }

    /**
     * Get recent certificates (enriched).
     *
     * @param int|null $limit
     * @param array|null $schoolIds Optional school filter. null = no filter (superadmin),
     *                              empty array = no rows (user manages no schools),
     *                              non-empty = restrict to users at those schools.
     */
    public function getRecent(?int $limit = 10, ?array $schoolIds = null): array
    {
        try {
            if (is_array($schoolIds) && empty($schoolIds)) {
                return [];
            }

            $where = '';
            $params = [];
            if (is_array($schoolIds)) {
                $placeholders = [];
                foreach ($schoolIds as $i => $sid) {
                    $key = 'sid' . $i;
                    $placeholders[] = ':' . $key;
                    $params[$key] = (int)$sid;
                }
                $where = 'WHERE u.primary_school_id IN (' . implode(',', $placeholders) . ')';
            }

            $sql = "SELECT " . self::SELECT_ENRICHED . "
                    FROM {$this->table} c
                    JOIN users u ON c.user_id = u.id
                    JOIN courses co ON c.course_id = co.id
                    $where
                    ORDER BY COALESCE(c.issue_date, c.issued_date) DESC";

            if ($limit !== null) {
                $sql .= " LIMIT " . (int)$limit;
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\PDOException $e) {
            error_log("Database error in Certificate::getRecent: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Revoke a certificate.
     */
    public function revoke(int $certificateId, int $revokedByUserId, ?string $reason = null): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE {$this->table}
                SET is_revoked = 1,
                    revoked_at = NOW(),
                    revoked_by = :by,
                    revocation_reason = :reason
                WHERE id = :id
            ");
            return $stmt->execute([
                'id'     => $certificateId,
                'by'     => $revokedByUserId,
                'reason' => $reason
            ]);
        } catch (\PDOException $e) {
            error_log("Database error in Certificate::revoke: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reinstate (un-revoke) a certificate.
     */
    public function reinstate(int $certificateId): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE {$this->table}
                SET is_revoked = 0,
                    revoked_at = NULL,
                    revoked_by = NULL,
                    revocation_reason = NULL
                WHERE id = :id
            ");
            return $stmt->execute(['id' => $certificateId]);
        } catch (\PDOException $e) {
            error_log("Database error in Certificate::reinstate: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Aggregate stats for admin dashboard.
     *
     * @param array|null $schoolIds Optional school filter. null = global (superadmin),
     *                              empty array = no rows, non-empty = restrict to those schools.
     */
    public function getStats(?array $schoolIds = null): array
    {
        try {
            if (is_array($schoolIds) && empty($schoolIds)) {
                return ['total' => 0, 'active' => 0, 'revoked' => 0, 'last_30_days' => 0, 'this_month' => 0];
            }

            $join = '';
            $where = '';
            $params = [];
            if (is_array($schoolIds)) {
                $placeholders = [];
                foreach ($schoolIds as $i => $sid) {
                    $key = 'sid' . $i;
                    $placeholders[] = ':' . $key;
                    $params[$key] = (int)$sid;
                }
                $join = 'JOIN users u ON c.user_id = u.id';
                $where = 'WHERE u.primary_school_id IN (' . implode(',', $placeholders) . ')';
            }

            $sql = "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN c.is_revoked = 0 THEN 1 ELSE 0 END) AS active,
                    SUM(CASE WHEN c.is_revoked = 1 THEN 1 ELSE 0 END) AS revoked,
                    SUM(CASE WHEN COALESCE(c.issue_date, c.issued_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS last_30_days,
                    SUM(CASE WHEN MONTH(COALESCE(c.issue_date, c.issued_date)) = MONTH(CURDATE())
                              AND YEAR(COALESCE(c.issue_date, c.issued_date)) = YEAR(CURDATE())
                             THEN 1 ELSE 0 END) AS this_month
                FROM {$this->table} c
                $join
                $where";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_OBJ);
            return [
                'total'        => (int)($row->total ?? 0),
                'active'       => (int)($row->active ?? 0),
                'revoked'      => (int)($row->revoked ?? 0),
                'last_30_days' => (int)($row->last_30_days ?? 0),
                'this_month'   => (int)($row->this_month ?? 0),
            ];
        } catch (\PDOException $e) {
            error_log("Database error in Certificate::getStats: " . $e->getMessage());
            return ['total' => 0, 'active' => 0, 'revoked' => 0, 'last_30_days' => 0, 'this_month' => 0];
        }
    }

    /**
     * Per-course breakdown for admin management page.
     *
     * The "Completed" column counts enrolled users who satisfy the new composite
     * rule (lessons + quizzes + projects). Falls back to enrollments.status='completed'
     * for back-compat with rows whose flag was set under the old lesson-only rule.
     *
     * @param array|null $schoolIds Optional school filter (see getStats).
     */
    public function getPerCourseBreakdown(?array $schoolIds = null): array
    {
        try {
            if (is_array($schoolIds) && empty($schoolIds)) {
                return [];
            }

            // School IDs are ints — cast and inline them. Named placeholders can't be
            // reused across multiple subqueries with EMULATE_PREPARES=false, and ints
            // pose no SQL-injection risk after casting.
            $schoolFilterEnrolled = '';
            $schoolFilterCerts = '';
            if (is_array($schoolIds)) {
                $clean = array_map('intval', $schoolIds);
                $list = implode(',', $clean);
                $schoolFilterEnrolled = "AND eu.primary_school_id IN ($list)";
                $schoolFilterCerts = "AND cu.primary_school_id IN ($list)";
            }

            // Count distinct users who meet the new composite rule, per course.
            // Note: certificate count is also school-scoped via the certs join.
            $sql = "SELECT
                    co.id AS course_id,
                    co.title AS course_title,
                    co.certificate_template_id AS template_id,
                    t.name AS template_name,
                    (
                        SELECT COUNT(DISTINCT e.id)
                        FROM enrollments e
                        " . (is_array($schoolIds) ? "JOIN users eu ON eu.id = e.user_id" : "") . "
                        WHERE e.course_id = co.id
                        $schoolFilterEnrolled
                    ) AS enrollments,
                    (
                        SELECT COUNT(DISTINCT e.user_id)
                        FROM enrollments e
                        " . (is_array($schoolIds) ? "JOIN users eu ON eu.id = e.user_id" : "") . "
                        WHERE e.course_id = co.id
                          AND (
                              e.status = 'completed'
                              OR (
                                  -- composite rule: lessons + quizzes + projects all done
                                  NOT EXISTS (
                                      SELECT 1 FROM lessons l
                                      JOIN modules m ON l.module_id = m.id
                                      LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.user_id = e.user_id
                                      WHERE m.course_id = co.id AND m.is_published = 1 AND l.is_published = 1
                                        AND (lp.status IS NULL OR lp.status <> 'completed')
                                  )
                                  AND NOT EXISTS (
                                      SELECT 1 FROM quizzes q
                                      JOIN modules m ON q.module_id = m.id
                                      WHERE m.course_id = co.id AND m.is_published = 1
                                        AND q.is_published = 1 AND q.lesson_id IS NULL
                                        AND NOT EXISTS (
                                            SELECT 1 FROM quiz_attempts qa
                                            WHERE qa.user_id = e.user_id AND qa.quiz_id = q.id AND qa.passed = 1
                                        )
                                  )
                                  AND NOT EXISTS (
                                      SELECT 1 FROM projects p
                                      WHERE p.course_id = co.id AND p.is_published = 1
                                        AND NOT EXISTS (
                                            SELECT 1 FROM project_submissions ps
                                            WHERE ps.user_id = e.user_id AND ps.project_id = p.id
                                        )
                                  )
                              )
                          )
                          $schoolFilterEnrolled
                    ) AS completed,
                    (
                        SELECT COUNT(DISTINCT c.id)
                        FROM certificates c
                        " . (is_array($schoolIds) ? "JOIN users cu ON cu.id = c.user_id" : "") . "
                        WHERE c.course_id = co.id
                        $schoolFilterCerts
                    ) AS certificates_issued,
                    (
                        SELECT SUM(CASE WHEN c.is_revoked = 1 THEN 1 ELSE 0 END)
                        FROM certificates c
                        " . (is_array($schoolIds) ? "JOIN users cu ON cu.id = c.user_id" : "") . "
                        WHERE c.course_id = co.id
                        $schoolFilterCerts
                    ) AS revoked
                FROM courses co
                LEFT JOIN certificate_templates t ON co.certificate_template_id = t.id
                ORDER BY certificates_issued DESC, co.title ASC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\PDOException $e) {
            error_log("Database error in Certificate::getPerCourseBreakdown: " . $e->getMessage());
            return [];
        }
    }

    /**
     * List enrollments for a course where the student is NOT certified yet,
     * with a per-student breakdown of what's missing.
     *
     * Returns rows shaped for the admin/instructor "What's missing" diagnostic.
     * Each row: user_id, name, email, school_name, lessons_completed/total,
     * quizzes_passed/total, projects_submitted/total, missing[] (titles),
     * eligible_now (true if all artifacts done — backfill candidate).
     *
     * @param array|null $schoolIds Optional school filter (see getStats).
     */
    public function getIncompleteEnrollments(int $courseId, ?array $schoolIds = null): array
    {
        try {
            if (is_array($schoolIds) && empty($schoolIds)) {
                return [];
            }

            $params = ['course_id' => $courseId];
            $schoolFilter = '';
            if (is_array($schoolIds)) {
                $placeholders = [];
                foreach ($schoolIds as $i => $sid) {
                    $key = 'sid' . $i;
                    $placeholders[] = ':' . $key;
                    $params[$key] = (int)$sid;
                }
                $schoolFilter = ' AND u.primary_school_id IN (' . implode(',', $placeholders) . ')';
            }

            // Pull users who are enrolled but have no active certificate for this course.
            $sql = "SELECT u.id AS user_id, u.name, u.email,
                           s.name AS school_name,
                           e.progress_percentage,
                           e.status AS enrollment_status
                    FROM enrollments e
                    JOIN users u ON u.id = e.user_id
                    LEFT JOIN schools s ON s.id = u.primary_school_id
                    LEFT JOIN certificates c
                           ON c.user_id = e.user_id AND c.course_id = e.course_id AND c.is_revoked = 0
                    WHERE e.course_id = :course_id
                      AND c.id IS NULL
                      $schoolFilter
                    ORDER BY u.name ASC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $users = $stmt->fetchAll(PDO::FETCH_OBJ);

            $rows = [];
            foreach ($users as $u) {
                $userId = (int)$u->user_id;
                $lessons  = $this->countLessonProgress($userId, $courseId);
                $quizzes  = $this->countQuizProgress($userId, $courseId);
                $projects = $this->countProjectProgress($userId, $courseId);

                $missing = array_merge(
                    $quizzes['missing_titles'],
                    $projects['missing_titles']
                );
                if ($lessons['total'] > 0 && $lessons['completed'] < $lessons['total']) {
                    $missing[] = sprintf('Lessons: %d of %d completed', $lessons['completed'], $lessons['total']);
                }

                $eligibleNow = (
                    ($lessons['total']  === 0 || $lessons['completed']  === $lessons['total']) &&
                    ($quizzes['total']  === 0 || $quizzes['passed']     === $quizzes['total']) &&
                    ($projects['total'] === 0 || $projects['submitted'] === $projects['total'])
                );

                $rows[] = (object)[
                    'user_id'             => $userId,
                    'name'                => $u->name,
                    'email'               => $u->email,
                    'school_name'         => $u->school_name,
                    'progress_percentage' => (float)($u->progress_percentage ?? 0),
                    'enrollment_status'   => $u->enrollment_status,
                    'lessons_completed'   => $lessons['completed'],
                    'lessons_total'       => $lessons['total'],
                    'quizzes_passed'      => $quizzes['passed'],
                    'quizzes_total'       => $quizzes['total'],
                    'projects_submitted'  => $projects['submitted'],
                    'projects_total'      => $projects['total'],
                    'missing'             => $missing,
                    'eligible_now'        => $eligibleNow,
                ];
            }
            return $rows;
        } catch (\PDOException $e) {
            error_log("Database error in Certificate::getIncompleteEnrollments: " . $e->getMessage());
            return [];
        }
    }
}
