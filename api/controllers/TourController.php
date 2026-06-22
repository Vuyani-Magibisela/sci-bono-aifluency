<?php
namespace App\Controllers;

use App\Utils\Response;

class TourController extends BaseController
{
    /**
     * GET /tour-status — return current user's tour-seen flag
     */
    public function getStatus(array $params = []): void
    {
        $user = $this->getCurrentUser();

        Response::success([
            'has_seen_tour' => (int)($user->has_seen_tour ?? 0)
        ], 'Tour status retrieved');
    }

    /**
     * POST /tour-status/seen — mark current user's tour as seen
     */
    public function markSeen(array $params = []): void
    {
        $user = $this->getCurrentUser();

        $this->executeWithErrorHandling(function () use ($user) {
            $stmt = $this->pdo->prepare("UPDATE users SET has_seen_tour = 1 WHERE id = ?");
            $stmt->execute([$user->id]);

            Response::success([
                'has_seen_tour' => 1
            ], 'Tour marked as seen');
        }, 'Failed to update tour status');
    }
}
