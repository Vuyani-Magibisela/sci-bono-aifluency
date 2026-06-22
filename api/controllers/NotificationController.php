<?php
namespace App\Controllers;

use App\Models\Notification;
use App\Utils\Response;

class NotificationController extends BaseController
{
    private Notification $notification;

    public function __construct(\PDO $pdo)
    {
        parent::__construct($pdo);
        $this->notification = new Notification($pdo);
    }

    /**
     * GET /notifications — list user's notifications
     */
    public function index(array $params = []): void
    {
        $user = $this->getCurrentUser();

        $limit = min((int) ($_GET['limit'] ?? 20), 50);
        $offset = max((int) ($_GET['offset'] ?? 0), 0);

        $notifications = $this->notification->getForUser((int) $user->id, $limit, $offset);

        Response::success([
            'notifications' => $notifications,
            'unread_count' => $this->notification->countUnread((int) $user->id)
        ]);
    }

    /**
     * GET /notifications/unread-count — badge count
     */
    public function unreadCount(array $params = []): void
    {
        $user = $this->getCurrentUser();
        $count = $this->notification->countUnread((int) $user->id);

        Response::success(['unread_count' => $count]);
    }

    /**
     * PUT /notifications/:id/read — mark single as read
     */
    public function markRead(array $params = []): void
    {
        $user = $this->getCurrentUser();
        $id = (int) ($params['id'] ?? 0);

        if ($id <= 0) {
            Response::error('Invalid notification ID', 400);
        }

        $this->notification->markAsRead($id, (int) $user->id);
        Response::success(null, 'Notification marked as read');
    }

    /**
     * PUT /notifications/read-all — mark all as read
     */
    public function markAllRead(array $params = []): void
    {
        $user = $this->getCurrentUser();
        $this->notification->markAllRead((int) $user->id);
        Response::success(null, 'All notifications marked as read');
    }
}
