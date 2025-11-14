<?php
/**
 * Bookmark Model (Phase 5D Priority 5)
 * Handles bookmark operations
 */

class Bookmark extends BaseModel {
    protected $table = 'bookmarks';
    protected $fillable = ['user_id', 'lesson_id'];

    /**
     * Get all bookmarks for a specific user
     * @param int $userId User ID
     * @return array Bookmarks with lesson details
     */
    public function getBookmarksByUser($userId) {
        $sql = "SELECT b.*,
                       l.title as lesson_title,
                       l.module_id,
                       l.order_index as lesson_order,
                       m.title as module_title,
                       m.order_index as module_order
                FROM {$this->table} b
                LEFT JOIN lessons l ON b.lesson_id = l.id
                LEFT JOIN modules m ON l.module_id = m.id
                WHERE b.user_id = ?
                ORDER BY b.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if a lesson is bookmarked by user
     * @param int $userId User ID
     * @param int $lessonId Lesson ID
     * @return bool True if bookmarked
     */
    public function isBookmarked($userId, $lessonId) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}
                WHERE user_id = ? AND lesson_id = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $lessonId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }

    /**
     * Add a bookmark
     * @param int $userId User ID
     * @param int $lessonId Lesson ID
     * @return array|bool Bookmark data or false if already exists
     */
    public function addBookmark($userId, $lessonId) {
        // Check if already bookmarked
        if ($this->isBookmarked($userId, $lessonId)) {
            return false;
        }

        // Create bookmark
        $bookmarkId = $this->create([
            'user_id' => $userId,
            'lesson_id' => $lessonId
        ]);

        return $this->find($bookmarkId);
    }

    /**
     * Remove a bookmark by lesson ID
     * @param int $userId User ID
     * @param int $lessonId Lesson ID
     * @return bool Success
     */
    public function removeBookmarkByLesson($userId, $lessonId) {
        $sql = "DELETE FROM {$this->table}
                WHERE user_id = ? AND lesson_id = ?";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$userId, $lessonId]);
    }

    /**
     * Remove a bookmark by ID
     * @param int $bookmarkId Bookmark ID
     * @param int $userId User ID (for ownership verification)
     * @return bool Success
     */
    public function removeBookmark($bookmarkId, $userId) {
        // Verify ownership
        $bookmark = $this->find($bookmarkId);
        if (!$bookmark || $bookmark['user_id'] != $userId) {
            return false;
        }

        return $this->delete($bookmarkId);
    }

    /**
     * Get bookmark count for a user
     * @param int $userId User ID
     * @return int Bookmark count
     */
    public function getBookmarkCount($userId) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['count'];
    }

    /**
     * Get bookmarks grouped by module
     * @param int $userId User ID
     * @return array Bookmarks grouped by module
     */
    public function getBookmarksGroupedByModule($userId) {
        $bookmarks = $this->getBookmarksByUser($userId);
        $grouped = [];

        foreach ($bookmarks as $bookmark) {
            $moduleId = $bookmark['module_id'];
            $moduleTitle = $bookmark['module_title'] ?? 'Unknown Module';

            if (!isset($grouped[$moduleId])) {
                $grouped[$moduleId] = [
                    'module_id' => $moduleId,
                    'module_title' => $moduleTitle,
                    'module_order' => $bookmark['module_order'] ?? 0,
                    'lessons' => []
                ];
            }

            $grouped[$moduleId]['lessons'][] = $bookmark;
        }

        // Sort by module order
        usort($grouped, function($a, $b) {
            return $a['module_order'] - $b['module_order'];
        });

        return array_values($grouped);
    }

    /**
     * Toggle bookmark (add if doesn't exist, remove if exists)
     * @param int $userId User ID
     * @param int $lessonId Lesson ID
     * @return array Status and bookmark state
     */
    public function toggleBookmark($userId, $lessonId) {
        if ($this->isBookmarked($userId, $lessonId)) {
            $this->removeBookmarkByLesson($userId, $lessonId);
            return [
                'bookmarked' => false,
                'message' => 'Bookmark removed'
            ];
        } else {
            $this->addBookmark($userId, $lessonId);
            return [
                'bookmarked' => true,
                'message' => 'Bookmark added'
            ];
        }
    }
}
