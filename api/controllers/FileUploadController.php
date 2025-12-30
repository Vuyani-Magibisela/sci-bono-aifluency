<?php
namespace App\Controllers;

use App\Utils\Response;
use App\Utils\JWTHandler;

/**
 * File Upload Controller
 *
 * Handles file uploads with validation and security
 * Phase B: Complete Core Features
 */
class FileUploadController
{
    private \PDO $pdo;
    private ?array $auth = null;

    // File upload configuration
    private const UPLOAD_BASE_DIR = '/var/www/html/sci-bono-aifluency/uploads';
    private const MAX_FILE_SIZE = 10485760; // 10MB in bytes

    private const ALLOWED_TYPES = [
        'avatar' => [
            'extensions' => ['jpg', 'jpeg', 'png', 'gif'],
            'mime_types' => ['image/jpeg', 'image/png', 'image/gif'],
            'max_size' => 2097152, // 2MB
            'directory' => 'avatars'
        ],
        'project' => [
            'extensions' => ['pdf', 'doc', 'docx', 'zip', 'png', 'jpg', 'jpeg'],
            'mime_types' => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip',
                'image/png',
                'image/jpeg'
            ],
            'max_size' => 10485760, // 10MB
            'directory' => 'projects'
        ],
        'document' => [
            'extensions' => ['pdf', 'doc', 'docx', 'txt'],
            'mime_types' => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain'
            ],
            'max_size' => 5242880, // 5MB
            'directory' => 'documents'
        ]
    ];

    public function __construct()
    {
        global $pdo;
        $this->pdo = $pdo;
    }

    /**
     * Authenticate request
     */
    private function requireAuth(): void
    {
        $headers = getallheaders();
        $token = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? null;

        if (!$token) {
            Response::error('Authorization token required', 401);
            exit;
        }

        $token = str_replace('Bearer ', '', $token);

        try {
            $this->auth = JWTHandler::validateToken($token);
        } catch (\Exception $e) {
            Response::error('Invalid or expired token: ' . $e->getMessage(), 401);
            exit;
        }
    }

    /**
     * Upload file
     *
     * POST /api/upload
     * FormData: file, type (avatar|project|document), metadata (optional JSON)
     *
     * @param array $params Route parameters
     * @return void
     */
    public function upload(array $params = []): void
    {
        $this->requireAuth();
        $userId = $this->auth['user_id'];

        // Check if file was uploaded
        if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
            Response::error('No file uploaded', 400);
            return;
        }

        $file = $_FILES['file'];
        $uploadType = $_POST['type'] ?? 'document';
        $metadata = isset($_POST['metadata']) ? json_decode($_POST['metadata'], true) : [];

        // Validate upload type
        if (!isset(self::ALLOWED_TYPES[$uploadType])) {
            Response::error('Invalid upload type', 400);
            return;
        }

        $config = self::ALLOWED_TYPES[$uploadType];

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessage = $this->getUploadErrorMessage($file['error']);
            Response::error('File upload failed: ' . $errorMessage, 400);
            return;
        }

        // Validate file
        $validation = $this->validateFile($file, $config);
        if (!$validation['valid']) {
            Response::error($validation['error'], 400);
            return;
        }

        // Generate safe filename
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $safeFilename = $this->generateSafeFilename($userId, $uploadType, $extension);

        // Create upload directory if needed
        $uploadDir = self::UPLOAD_BASE_DIR . '/' . $config['directory'] . '/' . $userId;
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                Response::error('Failed to create upload directory', 500);
                return;
            }
        }

        $filePath = $uploadDir . '/' . $safeFilename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            Response::error('Failed to save uploaded file', 500);
            return;
        }

        // Store file record in database
        try {
            $fileId = $this->saveFileRecord([
                'user_id' => $userId,
                'original_filename' => $file['name'],
                'stored_filename' => $safeFilename,
                'file_path' => $filePath,
                'file_type' => $uploadType,
                'mime_type' => $file['type'],
                'file_size' => $file['size'],
                'metadata' => json_encode($metadata)
            ]);

            // Generate access URL
            $fileUrl = '/api/files/' . $fileId;

            Response::success([
                'file_id' => $fileId,
                'filename' => $safeFilename,
                'original_filename' => $file['name'],
                'file_url' => $fileUrl,
                'file_size' => $file['size'],
                'mime_type' => $file['type']
            ], 'File uploaded successfully', 201);

        } catch (\Exception $e) {
            // Clean up uploaded file on database error
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            error_log('File upload database error: ' . $e->getMessage());
            Response::error('Failed to save file record', 500);
        }
    }

    /**
     * Get file (serve file to user)
     *
     * GET /api/files/:id
     *
     * @param array $params Route parameters
     * @return void
     */
    public function getFile(array $params = []): void
    {
        $this->requireAuth();
        $userId = $this->auth['user_id'];

        $fileId = $params['id'] ?? null;
        if (!$fileId) {
            Response::error('File ID is required', 400);
            return;
        }

        // Get file record
        $fileRecord = $this->getFileRecord($fileId);
        if (!$fileRecord) {
            Response::error('File not found', 404);
            return;
        }

        // Check permissions (user can only access their own files or if admin/instructor)
        $userModel = new \App\Models\User($this->pdo);
        $currentUser = $userModel->find($userId);

        if ($fileRecord['user_id'] != $userId && !in_array($currentUser->role, ['admin', 'instructor'])) {
            Response::error('Unauthorized to access this file', 403);
            return;
        }

        // Check if file exists
        if (!file_exists($fileRecord['file_path'])) {
            Response::error('File not found on server', 404);
            return;
        }

        // Serve file
        header('Content-Type: ' . $fileRecord['mime_type']);
        header('Content-Disposition: inline; filename="' . $fileRecord['original_filename'] . '"');
        header('Content-Length: ' . $fileRecord['file_size']);
        header('Cache-Control: private, max-age=3600');

        readfile($fileRecord['file_path']);
        exit;
    }

    /**
     * Delete file
     *
     * DELETE /api/files/:id
     *
     * @param array $params Route parameters
     * @return void
     */
    public function deleteFile(array $params = []): void
    {
        $this->requireAuth();
        $userId = $this->auth['user_id'];

        $fileId = $params['id'] ?? null;
        if (!$fileId) {
            Response::error('File ID is required', 400);
            return;
        }

        // Get file record
        $fileRecord = $this->getFileRecord($fileId);
        if (!$fileRecord) {
            Response::error('File not found', 404);
            return;
        }

        // Check permissions (user can only delete their own files)
        if ($fileRecord['user_id'] != $userId) {
            Response::error('Unauthorized to delete this file', 403);
            return;
        }

        try {
            // Delete physical file
            if (file_exists($fileRecord['file_path'])) {
                unlink($fileRecord['file_path']);
            }

            // Delete database record
            $this->deleteFileRecord($fileId);

            Response::success(null, 'File deleted successfully');

        } catch (\Exception $e) {
            error_log('File deletion error: ' . $e->getMessage());
            Response::error('Failed to delete file', 500);
        }
    }

    /**
     * Get user's uploaded files
     *
     * GET /api/files
     * Query: ?type=avatar|project|document
     *
     * @param array $params Route parameters
     * @return void
     */
    public function getUserFiles(array $params = []): void
    {
        $this->requireAuth();
        $userId = $this->auth['user_id'];

        $type = $_GET['type'] ?? null;

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    id,
                    original_filename,
                    stored_filename,
                    file_type,
                    mime_type,
                    file_size,
                    metadata,
                    created_at
                FROM uploaded_files
                WHERE user_id = :user_id
                " . ($type ? "AND file_type = :file_type" : "") . "
                ORDER BY created_at DESC
            ");

            $params = ['user_id' => $userId];
            if ($type) {
                $params['file_type'] = $type;
            }

            $stmt->execute($params);
            $files = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Add file URLs
            foreach ($files as &$file) {
                $file['file_url'] = '/api/files/' . $file['id'];
                $file['metadata'] = json_decode($file['metadata'], true);
            }

            Response::success([
                'files' => $files,
                'total' => count($files)
            ]);

        } catch (\Exception $e) {
            error_log('Get files error: ' . $e->getMessage());
            Response::error('Failed to retrieve files', 500);
        }
    }

    /**
     * Validate uploaded file
     *
     * @param array $file $_FILES array entry
     * @param array $config Upload configuration
     * @return array ['valid' => bool, 'error' => string]
     */
    private function validateFile(array $file, array $config): array
    {
        // Check file size
        if ($file['size'] > $config['max_size']) {
            $maxSizeMB = $config['max_size'] / 1048576;
            return [
                'valid' => false,
                'error' => "File size exceeds maximum allowed size of {$maxSizeMB}MB"
            ];
        }

        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $config['extensions'])) {
            return [
                'valid' => false,
                'error' => 'File type not allowed. Allowed types: ' . implode(', ', $config['extensions'])
            ];
        }

        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $config['mime_types'])) {
            return [
                'valid' => false,
                'error' => 'Invalid file MIME type'
            ];
        }

        // Additional security: check for executable files
        if ($this->isPotentiallyDangerous($file['tmp_name'])) {
            return [
                'valid' => false,
                'error' => 'File contains potentially dangerous content'
            ];
        }

        return ['valid' => true];
    }

    /**
     * Check if file is potentially dangerous
     *
     * @param string $filePath
     * @return bool
     */
    private function isPotentiallyDangerous(string $filePath): bool
    {
        // Read first 1KB of file
        $handle = fopen($filePath, 'rb');
        $content = fread($handle, 1024);
        fclose($handle);

        // Check for PHP tags
        if (strpos($content, '<?php') !== false || strpos($content, '<?=') !== false) {
            return true;
        }

        // Check for script tags (basic check)
        if (stripos($content, '<script') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Generate safe filename
     *
     * @param int $userId
     * @param string $type
     * @param string $extension
     * @return string
     */
    private function generateSafeFilename(int $userId, string $type, string $extension): string
    {
        $timestamp = time();
        $random = bin2hex(random_bytes(8));
        return "{$type}_{$userId}_{$timestamp}_{$random}.{$extension}";
    }

    /**
     * Save file record to database
     *
     * @param array $data
     * @return int File ID
     */
    private function saveFileRecord(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO uploaded_files
            (user_id, original_filename, stored_filename, file_path, file_type, mime_type, file_size, metadata, created_at)
            VALUES
            (:user_id, :original_filename, :stored_filename, :file_path, :file_type, :mime_type, :file_size, :metadata, NOW())
        ");

        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Get file record from database
     *
     * @param int $fileId
     * @return array|null
     */
    private function getFileRecord(int $fileId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM uploaded_files WHERE id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $fileId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Delete file record from database
     *
     * @param int $fileId
     * @return bool
     */
    private function deleteFileRecord(int $fileId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM uploaded_files WHERE id = :id");
        return $stmt->execute(['id' => $fileId]);
    }

    /**
     * Get upload error message
     *
     * @param int $errorCode
     * @return string
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'File is too large';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'File upload stopped by extension';
            default:
                return 'Unknown upload error';
        }
    }
}
