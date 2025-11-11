<?php
namespace App\Models;

use PDO;

/**
 * Certificate Model
 *
 * Handles certificate-related database operations
 */
class Certificate extends BaseModel
{
    protected string $table = 'certificates';
    protected array $fillable = [
        'user_id',
        'course_id',
        'certificate_number',
        'issue_date',
        'certificate_url'
    ];
    protected array $hidden = [];

    /**
     * Get certificates by user
     *
     * @param int $userId User ID
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array
     */
    public function getByUser(int $userId, ?int $limit = null, ?int $offset = null): array
    {
        return $this->all(['user_id' => $userId], 'issue_date DESC', $limit, $offset);
    }

    /**
     * Get certificates by course
     *
     * @param int $courseId Course ID
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @return array
     */
    public function getByCourse(int $courseId, ?int $limit = null, ?int $offset = null): array
    {
        return $this->all(['course_id' => $courseId], 'issue_date DESC', $limit, $offset);
    }

    /**
     * Get user's certificate for a course
     *
     * @param int $userId User ID
     * @param int $courseId Course ID
     * @return object|null
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
     * Find certificate by certificate number
     *
     * @param string $certificateNumber Certificate number
     * @return object|null
     */
    public function findByCertificateNumber(string $certificateNumber): ?object
    {
        return $this->findBy('certificate_number', $certificateNumber);
    }

    /**
     * Issue certificate
     *
     * @param int $userId User ID
     * @param int $courseId Course ID
     * @param string|null $certificateUrl Optional certificate URL
     * @return int|null Certificate ID
     */
    public function issueCertificate(int $userId, int $courseId, ?string $certificateUrl = null): ?int
    {
        // Check if certificate already exists
        $existing = $this->getUserCourseCertificate($userId, $courseId);

        if ($existing) {
            return $existing->id;
        }

        // Generate unique certificate number
        $certificateNumber = $this->generateCertificateNumber($userId, $courseId);

        return $this->create([
            'user_id' => $userId,
            'course_id' => $courseId,
            'certificate_number' => $certificateNumber,
            'issue_date' => date('Y-m-d H:i:s'),
            'certificate_url' => $certificateUrl
        ]);
    }

    /**
     * Generate unique certificate number
     *
     * @param int $userId User ID
     * @param int $courseId Course ID
     * @return string
     */
    private function generateCertificateNumber(int $userId, int $courseId): string
    {
        // Format: CERT-{YEAR}-{COURSE_ID}-{USER_ID}-{RANDOM}
        $year = date('Y');
        $random = strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 6));

        return sprintf('CERT-%s-%04d-%06d-%s', $year, $courseId, $userId, $random);
    }

    /**
     * Verify certificate authenticity
     *
     * @param string $certificateNumber Certificate number
     * @return array Verification result
     */
    public function verifyCertificate(string $certificateNumber): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    c.*,
                    u.name as user_name,
                    u.email as user_email,
                    co.title as course_title,
                    co.description as course_description
                FROM {$this->table} c
                JOIN users u ON c.user_id = u.id
                JOIN courses co ON c.course_id = co.id
                WHERE c.certificate_number = :certificate_number
                LIMIT 1
            ");
            $stmt->execute(['certificate_number' => $certificateNumber]);
            $certificate = $stmt->fetch(PDO::FETCH_OBJ);

            if (!$certificate) {
                return [
                    'valid' => false,
                    'message' => 'Certificate not found'
                ];
            }

            return [
                'valid' => true,
                'certificate' => $certificate,
                'message' => 'Certificate is valid'
            ];
        } catch (\PDOException $e) {
            error_log("Database error in verifyCertificate: " . $e->getMessage());
            return [
                'valid' => false,
                'message' => 'An error occurred while verifying the certificate'
            ];
        }
    }

    /**
     * Get certificate with user and course details
     *
     * @param int $certificateId Certificate ID
     * @return object|null
     */
    public function getCertificateWithDetails(int $certificateId): ?object
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    c.*,
                    u.name as user_name,
                    u.email as user_email,
                    co.title as course_title,
                    co.description as course_description,
                    co.duration_hours as course_duration
                FROM {$this->table} c
                JOIN users u ON c.user_id = u.id
                JOIN courses co ON c.course_id = co.id
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
     * Check if user is eligible for certificate
     *
     * @param int $userId User ID
     * @param int $courseId Course ID
     * @return bool
     */
    public function isEligibleForCertificate(int $userId, int $courseId): bool
    {
        try {
            // Check if enrollment is complete
            $stmt = $this->pdo->prepare("
                SELECT completion_percentage
                FROM enrollments
                WHERE user_id = :user_id AND course_id = :course_id
                LIMIT 1
            ");
            $stmt->execute([
                'user_id' => $userId,
                'course_id' => $courseId
            ]);
            $enrollment = $stmt->fetch(PDO::FETCH_OBJ);

            if (!$enrollment) {
                return false;
            }

            // Check if completed (100%)
            return $enrollment->completion_percentage >= 100;
        } catch (\PDOException $e) {
            error_log("Database error in isEligibleForCertificate: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get recent certificates
     *
     * @param int|null $limit Optional limit
     * @return array
     */
    public function getRecent(?int $limit = 10): array
    {
        try {
            $sql = "SELECT
                        c.*,
                        u.name as user_name,
                        co.title as course_title
                    FROM {$this->table} c
                    JOIN users u ON c.user_id = u.id
                    JOIN courses co ON c.course_id = co.id
                    ORDER BY c.issue_date DESC";

            if ($limit !== null) {
                $sql .= " LIMIT {$limit}";
            }

            return $this->query($sql, []);
        } catch (\PDOException $e) {
            error_log("Database error in getRecent: " . $e->getMessage());
            return [];
        }
    }
}
