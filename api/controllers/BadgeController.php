<?php
namespace App\Controllers;

use App\Models\UserBadge;
use App\Utils\Response;

/**
 * BadgeController
 *
 * Per-lesson gamified badges awarded by the v2 course UI on chapter completion.
 * Idempotent: repeated awards of the same (user_id, badge_slug) are no-ops.
 */
class BadgeController extends BaseController
{
    private UserBadge $badgeModel;

    public function __construct(\PDO $pdo)
    {
        parent::__construct($pdo);
        $this->badgeModel = new UserBadge($pdo);
    }

    /**
     * POST /api/badges
     * Body: { badge_slug, badge_name?, lesson_id?, module_id? }
     * Awards the badge to the authenticated user. Returns the badge row.
     */
    public function award(array $params = []): void
    {
        $currentUser = $this->getCurrentUser();

        $slug = isset($_POST['badge_slug']) ? trim((string)$_POST['badge_slug']) : '';
        if ($slug === '' || strlen($slug) > 64 || !preg_match('/^[a-z0-9_\-]+$/i', $slug)) {
            Response::error('badge_slug is required and must be [a-z0-9_-]+, max 64 chars', 400);
            return;
        }

        $name     = isset($_POST['badge_name']) ? (string)$_POST['badge_name'] : null;
        $lessonId = isset($_POST['lesson_id']) && $_POST['lesson_id'] !== '' ? (int)$_POST['lesson_id'] : null;
        $moduleId = isset($_POST['module_id']) && $_POST['module_id'] !== '' ? (int)$_POST['module_id'] : null;

        $row = $this->badgeModel->award((int)$currentUser->id, $slug, $name, $lessonId, $moduleId);
        if ($row === null) {
            Response::error('Failed to record badge', 500);
            return;
        }

        Response::success(['badge' => $row], 'Badge awarded');
    }

    /**
     * GET /api/badges/my-badges[?module_id=]
     */
    public function myBadges(array $params = []): void
    {
        $currentUser = $this->getCurrentUser();
        $moduleId = isset($_GET['module_id']) && $_GET['module_id'] !== '' ? (int)$_GET['module_id'] : null;
        $badges = $this->badgeModel->forUser((int)$currentUser->id, $moduleId);
        Response::success(['items' => $badges, 'total' => count($badges)], 'Badges retrieved');
    }
}
