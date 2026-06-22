-- Migration 031: Add admin_response column to user_feedback
-- Stores the public-facing reply from admin to be shown to the user
-- (distinct from admin_notes which is internal only)

ALTER TABLE user_feedback
ADD COLUMN admin_response TEXT NULL AFTER admin_notes;
