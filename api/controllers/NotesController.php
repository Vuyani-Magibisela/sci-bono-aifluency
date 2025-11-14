<?php
/**
 * NotesController (Phase 5D Priority 4)
 * Handles student note operations
 */

class NotesController {
    private $noteModel;

    public function __construct($db) {
        $this->noteModel = new StudentNote($db);
    }

    /**
     * Get notes for a specific lesson
     * GET /api/notes/lesson/:lessonId
     */
    public function getNotesByLesson($lessonId) {
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

            // Get notes
            $notes = $this->noteModel->getNotesByUserAndLesson($user['id'], $lessonId);

            Response::success([
                'notes' => $notes,
                'count' => count($notes)
            ]);

        } catch (Exception $e) {
            error_log("Error fetching notes by lesson: " . $e->getMessage());
            Response::error('Failed to fetch notes', 500);
        }
    }

    /**
     * Get all notes for current user
     * GET /api/notes
     */
    public function getAllNotes() {
        try {
            // Verify authentication
            $user = JWTHandler::validateToken();
            if (!$user) {
                Response::unauthorized('Authentication required');
                return;
            }

            // Get optional limit parameter
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : null;

            // Get notes
            $notes = $this->noteModel->getNotesByUser($user['id'], $limit);

            Response::success([
                'items' => $notes,
                'total' => count($notes)
            ]);

        } catch (Exception $e) {
            error_log("Error fetching all notes: " . $e->getMessage());
            Response::error('Failed to fetch notes', 500);
        }
    }

    /**
     * Create or update a note
     * POST /api/notes
     * Body: { lesson_id: number, content: string }
     */
    public function createOrUpdateNote() {
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
            $validator->required($data, ['lesson_id', 'content']);

            if (!$validator->isValid()) {
                Response::badRequest('Validation failed', $validator->getErrors());
                return;
            }

            // Validate lesson ID
            if (!is_numeric($data['lesson_id']) || $data['lesson_id'] <= 0) {
                Response::badRequest('Invalid lesson ID');
                return;
            }

            // Validate content (not empty)
            if (empty(trim($data['content']))) {
                Response::badRequest('Note content cannot be empty');
                return;
            }

            // Create or update note
            $note = $this->noteModel->createOrUpdate(
                $user['id'],
                $data['lesson_id'],
                $data['content']
            );

            Response::success([
                'message' => 'Note saved successfully',
                'note' => $note
            ], 201);

        } catch (Exception $e) {
            error_log("Error creating/updating note: " . $e->getMessage());
            Response::error('Failed to save note', 500);
        }
    }

    /**
     * Delete a note
     * DELETE /api/notes/:noteId
     */
    public function deleteNote($noteId) {
        try {
            // Verify authentication
            $user = JWTHandler::validateToken();
            if (!$user) {
                Response::unauthorized('Authentication required');
                return;
            }

            // Validate note ID
            if (!is_numeric($noteId) || $noteId <= 0) {
                Response::badRequest('Invalid note ID');
                return;
            }

            // Delete note (with ownership verification)
            $success = $this->noteModel->deleteNote($noteId, $user['id']);

            if (!$success) {
                Response::notFound('Note not found or you do not have permission to delete it');
                return;
            }

            Response::success([
                'message' => 'Note deleted successfully'
            ]);

        } catch (Exception $e) {
            error_log("Error deleting note: " . $e->getMessage());
            Response::error('Failed to delete note', 500);
        }
    }

    /**
     * Search notes
     * GET /api/notes/search?q=searchterm
     */
    public function searchNotes() {
        try {
            // Verify authentication
            $user = JWTHandler::validateToken();
            if (!$user) {
                Response::unauthorized('Authentication required');
                return;
            }

            // Get search query
            $searchTerm = isset($_GET['q']) ? trim($_GET['q']) : '';

            if (empty($searchTerm)) {
                Response::badRequest('Search query is required');
                return;
            }

            // Search notes
            $notes = $this->noteModel->searchNotes($user['id'], $searchTerm);

            Response::success([
                'items' => $notes,
                'total' => count($notes),
                'query' => $searchTerm
            ]);

        } catch (Exception $e) {
            error_log("Error searching notes: " . $e->getMessage());
            Response::error('Failed to search notes', 500);
        }
    }

    /**
     * Get note statistics for current user
     * GET /api/notes/stats
     */
    public function getNoteStats() {
        try {
            // Verify authentication
            $user = JWTHandler::validateToken();
            if (!$user) {
                Response::unauthorized('Authentication required');
                return;
            }

            // Get note count
            $count = $this->noteModel->getNoteCount($user['id']);

            // Get recent notes
            $recentNotes = $this->noteModel->getNotesByUser($user['id'], 5);

            Response::success([
                'total_notes' => $count,
                'recent_notes' => $recentNotes
            ]);

        } catch (Exception $e) {
            error_log("Error fetching note stats: " . $e->getMessage());
            Response::error('Failed to fetch note statistics', 500);
        }
    }
}
