<?php
namespace App\Controllers;

use App\Utils\Response;
use App\Utils\Validator;
use App\Utils\JWTHandler;

class FeedbackController extends BaseController
{
    /**
     * POST /feedback — Submit feedback (public, no auth required)
     */
    public function submit(array $params = []): void
    {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = Validator::make($data)
            ->required('feedback_type', 'Feedback type is required')
            ->in('feedback_type', ['bug', 'suggestion', 'question', 'other'], 'Invalid feedback type')
            ->required('message', 'Message is required')
            ->minLength('message', 10, 'Message must be at least 10 characters')
            ->maxLength('message', 5000, 'Message must not exceed 5000 characters');

        if (!empty($data['contact_email'])) {
            $validator->email('contact_email', 'Invalid email address');
        }

        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        // Try to get authenticated user (optional)
        $userId = null;
        try {
            $jwtUser = JWTHandler::getCurrentUser();
            if ($jwtUser) {
                $token = JWTHandler::extractTokenFromHeader();
                if (!$token || !JWTHandler::isTokenBlacklisted($token, $this->pdo)) {
                    $userId = $jwtUser->id;
                }
            }
        } catch (\Exception $e) {
            // Anonymous submission — that's fine
        }

        $feedbackType = Validator::sanitize($data['feedback_type']);
        $message = Validator::sanitize($data['message']);
        $contactEmail = !empty($data['contact_email']) ? Validator::sanitizeEmail($data['contact_email']) : null;
        $pageUrl = !empty($data['page_url']) ? substr(Validator::sanitize($data['page_url']), 0, 500) : null;
        $userAgent = !empty($data['user_agent']) ? substr(Validator::sanitize($data['user_agent']), 0, 500) : null;
        $screenResolution = !empty($data['screen_resolution']) ? substr(Validator::sanitize($data['screen_resolution']), 0, 50) : null;
        $browserInfo = !empty($data['browser_info']) ? json_encode($data['browser_info']) : null;

        $this->executeWithErrorHandling(function () use (
            $userId, $feedbackType, $message, $contactEmail,
            $pageUrl, $userAgent, $screenResolution, $browserInfo
        ) {
            $stmt = $this->pdo->prepare("
                INSERT INTO user_feedback
                    (user_id, feedback_type, message, contact_email, page_url, user_agent, screen_resolution, browser_info)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId, $feedbackType, $message, $contactEmail,
                $pageUrl, $userAgent, $screenResolution, $browserInfo
            ]);

            Response::success([
                'id' => (int) $this->pdo->lastInsertId()
            ], 'Thank you for your feedback!', 201);
        }, 'Failed to submit feedback');
    }

    /**
     * GET /feedback — Admin: list all feedback (paginated, filterable)
     */
    public function index(array $params = []): void
    {
        $this->requireRole(['superadmin', 'orgadmin']);

        $page = max((int) ($_GET['page'] ?? 1), 1);
        $pageSize = min(max((int) ($_GET['page_size'] ?? 20), 1), 100);
        $offset = ($page - 1) * $pageSize;

        $status = $_GET['status'] ?? null;
        $type = $_GET['type'] ?? null;

        $where = [];
        $bindings = [];

        if ($status && in_array($status, ['new', 'in_review', 'resolved', 'dismissed'])) {
            $where[] = 'f.status = ?';
            $bindings[] = $status;
        }

        if ($type && in_array($type, ['bug', 'suggestion', 'question', 'other'])) {
            $where[] = 'f.feedback_type = ?';
            $bindings[] = $type;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $this->executeWithErrorHandling(function () use ($whereClause, $bindings, $pageSize, $offset, $page) {
            // Get total count
            $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM user_feedback f $whereClause");
            $countStmt->execute($bindings);
            $total = (int) $countStmt->fetchColumn();

            // Get paginated results
            $stmt = $this->pdo->prepare("
                SELECT f.*,
                       u.name as user_name, u.email as user_email,
                       r.name as resolver_name
                FROM user_feedback f
                LEFT JOIN users u ON f.user_id = u.id
                LEFT JOIN users r ON f.resolved_by = r.id
                $whereClause
                ORDER BY f.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $allBindings = array_merge($bindings, [$pageSize, $offset]);
            $stmt->execute($allBindings);
            $feedback = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Decode browser_info JSON
            foreach ($feedback as &$item) {
                if ($item['browser_info']) {
                    $item['browser_info'] = json_decode($item['browser_info'], true);
                }
            }

            Response::paginated($feedback, $total, $page, $pageSize, 'Feedback retrieved');
        }, 'Failed to retrieve feedback');
    }

    /**
     * GET /feedback/:id — Admin: single feedback detail
     */
    public function show(array $params = []): void
    {
        $this->requireRole(['superadmin', 'orgadmin']);
        $id = (int) ($params['id'] ?? 0);

        if ($id <= 0) {
            Response::error('Invalid feedback ID', 400);
        }

        $this->executeWithErrorHandling(function () use ($id) {
            $stmt = $this->pdo->prepare("
                SELECT f.*,
                       u.name as user_name, u.email as user_email,
                       r.name as resolver_name
                FROM user_feedback f
                LEFT JOIN users u ON f.user_id = u.id
                LEFT JOIN users r ON f.resolved_by = r.id
                WHERE f.id = ?
            ");
            $stmt->execute([$id]);
            $feedback = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$feedback) {
                Response::notFound('Feedback not found');
            }

            if ($feedback['browser_info']) {
                $feedback['browser_info'] = json_decode($feedback['browser_info'], true);
            }

            Response::success($feedback, 'Feedback retrieved');
        }, 'Failed to retrieve feedback');
    }

    /**
     * PUT /feedback/:id/status — Admin: update feedback status
     */
    public function updateStatus(array $params = []): void
    {
        $this->requireRole(['superadmin', 'orgadmin']);
        $id = (int) ($params['id'] ?? 0);

        if ($id <= 0) {
            Response::error('Invalid feedback ID', 400);
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = Validator::make($data)
            ->required('status', 'Status is required')
            ->in('status', ['new', 'in_review', 'resolved', 'dismissed'], 'Invalid status');

        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        $currentUser = $this->getCurrentUser();
        $newStatus = Validator::sanitize($data['status']);
        $adminNotes = !empty($data['admin_notes']) ? Validator::sanitize($data['admin_notes']) : null;

        $this->executeWithErrorHandling(function () use ($id, $newStatus, $adminNotes, $currentUser) {
            $resolvedBy = null;
            $resolvedAt = null;

            if (in_array($newStatus, ['resolved', 'dismissed'])) {
                $resolvedBy = $currentUser->id;
                $resolvedAt = date('Y-m-d H:i:s');
            }

            $stmt = $this->pdo->prepare("
                UPDATE user_feedback
                SET status = ?, admin_notes = ?, resolved_by = ?, resolved_at = ?
                WHERE id = ?
            ");
            $stmt->execute([$newStatus, $adminNotes, $resolvedBy, $resolvedAt, $id]);

            if ($stmt->rowCount() === 0) {
                Response::notFound('Feedback not found');
            }

            Response::success(null, 'Feedback status updated');
        }, 'Failed to update feedback status');
    }
}
