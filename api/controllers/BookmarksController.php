<?php
/**
 * BookmarksController (Phase 5D Priority 5)
 * Handles bookmark operations
 */

class BookmarksController {
    private $bookmarkModel;

    public function __construct($db) {
        $this->bookmarkModel = new Bookmark($db);
    }

    /**
     * Get all bookmarks for current user
     * GET /api/bookmarks
     */
    public function index() {
        try {
            // Verify authentication
            $user = JWTHandler::validateToken();
            if (!$user) {
                Response::unauthorized('Authentication required');
                return;
            }

            // Get grouped parameter
            $grouped = isset($_GET['grouped']) && $_GET['grouped'] === 'true';

            if ($grouped) {
                $bookmarks = $this->bookmarkModel->getBookmarksGroupedByModule($user['id']);
            } else {
                $bookmarks = $this->bookmarkModel->getBookmarksByUser($user['id']);
            }

            Response::success([
                'items' => $bookmarks,
                'total' => count($bookmarks)
            ]);

        } catch (Exception $e) {
            error_log("Error fetching bookmarks: " . $e->getMessage());
            Response::error('Failed to fetch bookmarks', 500);
        }
    }

    /**
     * Check if a lesson is bookmarked
     * GET /api/bookmarks/check/:lessonId
     */
    public function checkBookmark($lessonId) {
        try {
            // Verify authentication
            $user = JWTHandler::validateToken();
            if (!$user) {
                Response::unauthorized('Authentication required');
                return;
            }

            // Validate lesson ID
            if (!is_numeric($lessonId) || $lessonId <= 0) {
                Response::badRequest('Invalid lesson ID');
                return;
            }

            $isBookmarked = $this->bookmarkModel->isBookmarked($user['id'], $lessonId);

            Response::success([
                'bookmarked' => $isBookmarked,
                'lesson_id' => (int)$lessonId
            ]);

        } catch (Exception $e) {
            error_log("Error checking bookmark: " . $e->getMessage());
            Response::error('Failed to check bookmark', 500);
        }
    }

    /**
     * Add a bookmark
     * POST /api/bookmarks
     * Body: { lesson_id: number }
     */
    public function create() {
        try {
            // Verify authentication
            $user = JWTHandler::validateToken();
            if (!$user) {
                Response::unauthorized('Authentication required');
                return;
            }

            // Get request body
            $data = json_decode(file_get_contents('php://input'), true);

            // Validate input
            $validator = new Validator();
            $validator->required($data, ['lesson_id']);

            if (!$validator->isValid()) {
                Response::badRequest('Validation failed', $validator->getErrors());
                return;
            }

            // Validate lesson ID
            if (!is_numeric($data['lesson_id']) || $data['lesson_id'] <= 0) {
                Response::badRequest('Invalid lesson ID');
                return;
            }

            // Add bookmark
            $bookmark = $this->bookmarkModel->addBookmark($user['id'], $data['lesson_id']);

            if ($bookmark === false) {
                Response::badRequest('Lesson is already bookmarked');
                return;
            }

            Response::success([
                'message' => 'Bookmark added successfully',
                'bookmark' => $bookmark
            ], 201);

        } catch (Exception $e) {
            error_log("Error creating bookmark: " . $e->getMessage());
            Response::error('Failed to create bookmark', 500);
        }
    }

    /**
     * Remove a bookmark
     * DELETE /api/bookmarks/:lessonId
     */
    public function delete($lessonId) {
        try {
            // Verify authentication
            $user = JWTHandler::validateToken();
            if (!$user) {
                Response::unauthorized('Authentication required');
                return;
            }

            // Validate lesson ID
            if (!is_numeric($lessonId) || $lessonId <= 0) {
                Response::badRequest('Invalid lesson ID');
                return;
            }

            // Remove bookmark
            $success = $this->bookmarkModel->removeBookmarkByLesson($user['id'], $lessonId);

            if (!$success) {
                Response::notFound('Bookmark not found');
                return;
            }

            Response::success([
                'message' => 'Bookmark removed successfully'
            ]);

        } catch (Exception $e) {
            error_log("Error deleting bookmark: " . $e->getMessage());
            Response::error('Failed to delete bookmark', 500);
        }
    }

    /**
     * Toggle bookmark (add or remove)
     * POST /api/bookmarks/toggle
     * Body: { lesson_id: number }
     */
    public function toggle() {
        try {
            // Verify authentication
            $user = JWTHandler::validateToken();
            if (!$user) {
                Response::unauthorized('Authentication required');
                return;
            }

            // Get request body
            $data = json_decode(file_get_contents('php://input'), true);

            // Validate input
            $validator = new Validator();
            $validator->required($data, ['lesson_id']);

            if (!$validator->isValid()) {
                Response::badRequest('Validation failed', $validator->getErrors());
                return;
            }

            // Validate lesson ID
            if (!is_numeric($data['lesson_id']) || $data['lesson_id'] <= 0) {
                Response::badRequest('Invalid lesson ID');
                return;
            }

            // Toggle bookmark
            $result = $this->bookmarkModel->toggleBookmark($user['id'], $data['lesson_id']);

            Response::success($result);

        } catch (Exception $e) {
            error_log("Error toggling bookmark: " . $e->getMessage());
            Response::error('Failed to toggle bookmark', 500);
        }
    }

    /**
     * Get bookmark statistics
     * GET /api/bookmarks/stats
     */
    public function stats() {
        try {
            // Verify authentication
            $user = JWTHandler::validateToken();
            if (!$user) {
                Response::unauthorized('Authentication required');
                return;
            }

            $count = $this->bookmarkModel->getBookmarkCount($user['id']);
            $recentBookmarks = $this->bookmarkModel->getBookmarksByUser($user['id']);

            // Limit to 5 most recent
            $recentBookmarks = array_slice($recentBookmarks, 0, 5);

            Response::success([
                'total_bookmarks' => $count,
                'recent_bookmarks' => $recentBookmarks
            ]);

        } catch (Exception $e) {
            error_log("Error fetching bookmark stats: " . $e->getMessage());
            Response::error('Failed to fetch bookmark statistics', 500);
        }
    }
}
