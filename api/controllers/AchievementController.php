<?php
namespace App\Controllers;

use App\Models\Achievement;
use App\Models\User;
use App\Utils\Response;
use App\Utils\JWTHandler;

/**
 * Achievement Controller
 *
 * Handles achievement/badge system operations, unlock logic, and leaderboard
 * Phase 6: Quiz Tracking & Grading System
 */
class AchievementController
{
    private Achievement $achievementModel;
    private User $userModel;
    private \PDO $pdo;
    private ?array $auth = null;

    public function __construct()
    {
        global $pdo;
        $this->pdo = $pdo;
        $this->achievementModel = new Achievement($pdo);
        $this->userModel = new User($pdo);
    }

    /**
     * Authenticate request and set auth property
     *
     * @return void
     */
    private function requireAuth(): void
    {
        $headers = getallheaders();
        $token = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? null;

        if (!$token) {
            Response::error('Authorization token required', 401);
            exit;
        }

        // Remove 'Bearer ' prefix if present
        $token = str_replace('Bearer ', '', $token);

        try {
            $this->auth = JWTHandler::validateToken($token);
        } catch (\Exception $e) {
            Response::error('Invalid or expired token: ' . $e->getMessage(), 401);
            exit;
        }
    }

    /**
     * Get all achievements (with optional filtering)
     *
     * GET /api/achievements
     * Query params: ?category_id=1&tier=gold&unlocked_only=true
     *
     * @param array $params Route parameters
     * @return void
     */
    public function index(array $params = []): void
    {
        $this->requireAuth();
        $userId = $this->auth['user_id'];

        // Get query parameters
        $categoryId = $_GET['category_id'] ?? null;
        $tier = $_GET['tier'] ?? null;
        $unlockedOnly = isset($_GET['unlocked_only']) && $_GET['unlocked_only'] === 'true';

        try {
            if ($unlockedOnly) {
                // Get only unlocked achievements for user
                $achievements = $this->achievementModel->getUserAchievements($userId);
            } else {
                // Get all active achievements
                $includeSecret = false; // Don't show secret achievements in list
                $achievements = $this->achievementModel->getAllActive($includeSecret);

                // Mark which ones are unlocked by this user
                $userAchievementIds = array_column(
                    $this->achievementModel->getUserAchievements($userId),
                    'achievement_id'
                );

                foreach ($achievements as &$achievement) {
                    $achievement['unlocked'] = in_array($achievement['id'], $userAchievementIds);
                    $achievement['unlocked_at'] = null;

                    // If unlocked, get unlock date
                    if ($achievement['unlocked']) {
                        $unlockData = $this->achievementModel->getUserAchievementData($userId, $achievement['id']);
                        $achievement['unlocked_at'] = $unlockData['unlocked_at'] ?? null;
                    }
                }
            }

            // Apply filters
            if ($categoryId) {
                $achievements = array_filter($achievements, function($a) use ($categoryId) {
                    return $a['category_id'] == $categoryId;
                });
            }

            if ($tier) {
                $achievements = array_filter($achievements, function($a) use ($tier) {
                    return strtolower($a['tier']) === strtolower($tier);
                });
            }

            // Group by category
            $grouped = [];
            foreach ($achievements as $achievement) {
                $categoryName = $achievement['category_name'] ?? 'Other';
                if (!isset($grouped[$categoryName])) {
                    $grouped[$categoryName] = [
                        'category_id' => $achievement['category_id'],
                        'category_name' => $categoryName,
                        'category_icon' => $achievement['category_icon'] ?? 'fas fa-trophy',
                        'category_color' => $achievement['category_color'] ?? '#FFD700',
                        'achievements' => []
                    ];
                }
                $grouped[$categoryName]['achievements'][] = $achievement;
            }

            Response::success([
                'achievements' => array_values($achievements),
                'grouped' => array_values($grouped),
                'total' => count($achievements)
            ]);

        } catch (\Exception $e) {
            Response::error('Failed to retrieve achievements: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get user's achievements with unlock dates
     *
     * GET /api/achievements/user
     * Optional query: ?user_id=123 (for admins/instructors viewing other users)
     *
     * @param array $params Route parameters
     * @return void
     */
    public function getUserAchievements(array $params = []): void
    {
        $this->requireAuth();
        $requestingUserId = $this->auth['user_id'];

        // Allow viewing other users if admin/instructor
        $targetUserId = $_GET['user_id'] ?? $requestingUserId;

        if ($targetUserId != $requestingUserId) {
            $requestingUser = $this->userModel->find($requestingUserId);
            if (!in_array($requestingUser->role, ['admin', 'instructor'])) {
                Response::error('Unauthorized to view other users\' achievements', 403);
                return;
            }
        }

        try {
            $achievements = $this->achievementModel->getUserAchievements($targetUserId);

            // Group by tier for better display
            $byTier = [
                'platinum' => [],
                'gold' => [],
                'silver' => [],
                'bronze' => []
            ];

            foreach ($achievements as $achievement) {
                $tier = strtolower($achievement['tier']);
                if (isset($byTier[$tier])) {
                    $byTier[$tier][] = $achievement;
                }
            }

            Response::success([
                'achievements' => $achievements,
                'by_tier' => $byTier,
                'total' => count($achievements),
                'user_id' => (int)$targetUserId
            ]);

        } catch (\Exception $e) {
            Response::error('Failed to retrieve user achievements: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get user's achievement points summary
     *
     * GET /api/achievements/points
     * Optional query: ?user_id=123
     *
     * @param array $params Route parameters
     * @return void
     */
    public function getPoints(array $params = []): void
    {
        $this->requireAuth();
        $requestingUserId = $this->auth['user_id'];

        $targetUserId = $_GET['user_id'] ?? $requestingUserId;

        if ($targetUserId != $requestingUserId) {
            $requestingUser = $this->userModel->find($requestingUserId);
            if (!in_array($requestingUser->role, ['admin', 'instructor'])) {
                Response::error('Unauthorized to view other users\' points', 403);
                return;
            }
        }

        try {
            $pointsData = $this->achievementModel->getUserPoints($targetUserId);

            if (!$pointsData) {
                // User has no achievements yet
                $pointsData = [
                    'user_id' => (int)$targetUserId,
                    'total_points' => 0,
                    'achievements_count' => 0,
                    'bronze_count' => 0,
                    'silver_count' => 0,
                    'gold_count' => 0,
                    'platinum_count' => 0,
                    'rank_position' => null,
                    'updated_at' => date('Y-m-d H:i:s')
                ];
            }

            // Get user's rank
            $rank = $this->achievementModel->getUserRank($targetUserId);
            $pointsData['rank_position'] = $rank;

            Response::success($pointsData);

        } catch (\Exception $e) {
            Response::error('Failed to retrieve points data: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get achievement leaderboard
     *
     * GET /api/achievements/leaderboard
     * Query params: ?limit=20&offset=0
     *
     * @param array $params Route parameters
     * @return void
     */
    public function getLeaderboard(array $params = []): void
    {
        $this->requireAuth();

        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

        // Limit cap for performance
        if ($limit > 100) {
            $limit = 100;
        }

        try {
            $leaderboard = $this->achievementModel->getLeaderboard($limit, $offset);

            // Get current user's position if not in top results
            $currentUserId = $this->auth['user_id'];
            $currentUserRank = $this->achievementModel->getUserRank($currentUserId);
            $currentUserPoints = $this->achievementModel->getUserPoints($currentUserId);

            $inLeaderboard = false;
            foreach ($leaderboard as $entry) {
                if ($entry['user_id'] == $currentUserId) {
                    $inLeaderboard = true;
                    break;
                }
            }

            Response::success([
                'leaderboard' => $leaderboard,
                'total_users' => $this->achievementModel->getTotalUsersWithAchievements(),
                'current_user' => [
                    'rank' => $currentUserRank,
                    'points' => $currentUserPoints['total_points'] ?? 0,
                    'achievements_count' => $currentUserPoints['achievements_count'] ?? 0,
                    'in_leaderboard' => $inLeaderboard
                ],
                'limit' => $limit,
                'offset' => $offset
            ]);

        } catch (\Exception $e) {
            Response::error('Failed to retrieve leaderboard: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Check for new achievement unlocks
     *
     * POST /api/achievements/check
     * Body: { "event_type": "quiz_completion", "event_data": {...} }
     *
     * @param array $params Route parameters
     * @return void
     */
    public function checkForUnlocks(array $params = []): void
    {
        $this->requireAuth();
        $userId = $this->auth['user_id'];

        // Get request data
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data || !isset($data['event_type'])) {
            Response::error('event_type is required', 400);
            return;
        }

        $eventType = $data['event_type'];
        $eventData = $data['event_data'] ?? [];

        try {
            // Check for new unlocks
            $newlyUnlocked = $this->achievementModel->checkAndUnlock($userId, $eventType, $eventData);

            Response::success([
                'unlocked' => $newlyUnlocked,
                'count' => count($newlyUnlocked),
                'message' => count($newlyUnlocked) > 0
                    ? 'Unlocked ' . count($newlyUnlocked) . ' new achievement(s)!'
                    : 'No new achievements unlocked'
            ]);

        } catch (\Exception $e) {
            Response::error('Failed to check achievements: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get achievement details
     *
     * GET /api/achievements/:id
     *
     * @param array $params Route parameters
     * @return void
     */
    public function show(array $params = []): void
    {
        $this->requireAuth();
        $userId = $this->auth['user_id'];

        $achievementId = $params['id'] ?? null;
        if (!$achievementId) {
            Response::error('Achievement ID is required', 400);
            return;
        }

        try {
            $achievement = $this->achievementModel->find($achievementId);

            if (!$achievement) {
                Response::error('Achievement not found', 404);
                return;
            }

            // Check if user has unlocked this achievement
            $userAchievementData = $this->achievementModel->getUserAchievementData($userId, $achievementId);
            $achievement->unlocked = !empty($userAchievementData);
            $achievement->unlocked_at = $userAchievementData['unlocked_at'] ?? null;

            // Get unlock progress (if applicable)
            $criteria = json_decode($achievement->unlock_criteria, true);
            $progress = $this->achievementModel->getUnlockProgress($userId, $criteria);
            $achievement->progress = $progress;

            Response::success($achievement);

        } catch (\Exception $e) {
            Response::error('Failed to retrieve achievement: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get achievement categories
     *
     * GET /api/achievements/categories
     *
     * @param array $params Route parameters
     * @return void
     */
    public function getCategories(array $params = []): void
    {
        $this->requireAuth();

        try {
            $stmt = $this->pdo->query("
                SELECT
                    ac.*,
                    COUNT(a.id) as achievement_count
                FROM achievement_categories ac
                LEFT JOIN achievements a ON ac.id = a.category_id AND a.is_active = 1
                WHERE ac.is_active = 1
                GROUP BY ac.id
                ORDER BY ac.order_index ASC
            ");

            $categories = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            Response::success([
                'categories' => $categories,
                'total' => count($categories)
            ]);

        } catch (\Exception $e) {
            Response::error('Failed to retrieve categories: ' . $e->getMessage(), 500);
        }
    }
}
