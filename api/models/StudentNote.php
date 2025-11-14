<?php
/**
 * StudentNote Model (Phase 5D Priority 4)
 * Handles student note operations
 */

class StudentNote extends BaseModel {
    protected $table = 'student_notes';
    protected $fillable = ['user_id', 'lesson_id', 'note_content'];

    /**
     * Get all notes for a specific user and lesson
     * @param int $userId User ID
     * @param int $lessonId Lesson ID
     * @return array Notes
     */
    public function getNotesByUserAndLesson($userId, $lessonId) {
        $sql = "SELECT * FROM {$this->table}
                WHERE user_id = ? AND lesson_id = ?
                ORDER BY updated_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $lessonId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all notes for a specific user
     * @param int $userId User ID
     * @param int $limit Optional limit
     * @return array Notes
     */
    public function getNotesByUser($userId, $limit = null) {
        $sql = "SELECT sn.*, l.title as lesson_title, l.module_id, m.title as module_title
                FROM {$this->table} sn
                LEFT JOIN lessons l ON sn.lesson_id = l.id
                LEFT JOIN modules m ON l.module_id = m.id
                WHERE sn.user_id = ?
                ORDER BY sn.updated_at DESC";

        if ($limit) {
            $sql .= " LIMIT ?";
        }

        $stmt = $this->db->prepare($sql);

        if ($limit) {
            $stmt->execute([$userId, $limit]);
        } else {
            $stmt->execute([$userId]);
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get a single note by ID
     * @param int $noteId Note ID
     * @param int $userId User ID (for ownership verification)
     * @return array|null Note data
     */
    public function getNoteById($noteId, $userId) {
        $sql = "SELECT * FROM {$this->table} WHERE id = ? AND user_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$noteId, $userId]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Create or update a note
     * If a note already exists for user+lesson, update it
     * Otherwise, create a new note
     * @param int $userId User ID
     * @param int $lessonId Lesson ID
     * @param string $content Note content
     * @return array Created/updated note
     */
    public function createOrUpdate($userId, $lessonId, $content) {
        // Check if note already exists
        $existing = $this->getNotesByUserAndLesson($userId, $lessonId);

        if (!empty($existing)) {
            // Update existing note
            $noteId = $existing[0]['id'];
            $this->update($noteId, ['note_content' => $content]);
            return $this->find($noteId);
        } else {
            // Create new note
            $noteId = $this->create([
                'user_id' => $userId,
                'lesson_id' => $lessonId,
                'note_content' => $content
            ]);
            return $this->find($noteId);
        }
    }

    /**
     * Delete a note
     * @param int $noteId Note ID
     * @param int $userId User ID (for ownership verification)
     * @return bool Success
     */
    public function deleteNote($noteId, $userId) {
        // Verify ownership
        $note = $this->getNoteById($noteId, $userId);
        if (!$note) {
            return false;
        }

        return $this->delete($noteId);
    }

    /**
     * Get note count for a user
     * @param int $userId User ID
     * @return int Note count
     */
    public function getNoteCount($userId) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['count'];
    }

    /**
     * Search notes by content
     * @param int $userId User ID
     * @param string $searchTerm Search term
     * @return array Matching notes
     */
    public function searchNotes($userId, $searchTerm) {
        $sql = "SELECT sn.*, l.title as lesson_title, l.module_id, m.title as module_title
                FROM {$this->table} sn
                LEFT JOIN lessons l ON sn.lesson_id = l.id
                LEFT JOIN modules m ON l.module_id = m.id
                WHERE sn.user_id = ? AND sn.note_content LIKE ?
                ORDER BY sn.updated_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, '%' . $searchTerm . '%']);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
