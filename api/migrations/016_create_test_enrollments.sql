-- Migration 016: Create Test Enrollments
-- Phase A: Fix Critical Gaps
-- Enrolls test users in the AI Fluency course with varying completion levels

-- Enroll all students in the AI Fluency course (course_id = 1)
INSERT INTO enrollments (user_id, course_id, status, progress_percentage, enrolled_at, last_accessed_at)
VALUES
-- User 3: Test Student (50% complete, active learner)
(3, 1, 'active', 50.00, '2025-11-01 09:00:00', NOW()),

-- User 5: Vuyani (25% complete, new student)
(5, 1, 'active', 25.00, '2025-11-15 10:30:00', NOW());

-- Create some lesson progress for the enrolled students
-- User 3 (Test Student) - completed first 3 modules worth of lessons

-- Get count of lessons in first 3 modules
SET @module1_lessons = (SELECT COUNT(*) FROM lessons WHERE module_id = 1);
SET @module2_lessons = (SELECT COUNT(*) FROM lessons WHERE module_id = 2);
SET @module3_lessons = (SELECT COUNT(*) FROM lessons WHERE module_id = 3);

-- Insert completed lesson progress for User 3 (modules 1, 2, 3)
INSERT INTO lesson_progress (user_id, lesson_id, status, time_spent_minutes, completed_at, created_at, updated_at)
SELECT
    3 as user_id,
    l.id as lesson_id,
    'completed' as status,
    FLOOR(15 + (RAND() * 30)) as time_spent_minutes,
    DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 14) DAY) as completed_at,
    DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 14) DAY) as created_at,
    DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 14) DAY) as updated_at
FROM lessons l
WHERE l.module_id IN (1, 2, 3)
ORDER BY l.module_id, l.order_index;

-- Insert in-progress lesson progress for User 3 (first lesson of module 4)
INSERT INTO lesson_progress (user_id, lesson_id, status, time_spent_minutes, created_at, updated_at)
SELECT
    3 as user_id,
    l.id as lesson_id,
    'in_progress' as status,
    FLOOR(5 + (RAND() * 10)) as time_spent_minutes,
    NOW() as created_at,
    NOW() as updated_at
FROM lessons l
WHERE l.module_id = 4
ORDER BY l.order_index
LIMIT 1;

-- Insert completed lesson progress for User 5 (Vuyani - module 1 only)
INSERT INTO lesson_progress (user_id, lesson_id, status, time_spent_minutes, completed_at, created_at, updated_at)
SELECT
    5 as user_id,
    l.id as lesson_id,
    'completed' as status,
    FLOOR(10 + (RAND() * 25)) as time_spent_minutes,
    DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 3) DAY) as completed_at,
    DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 3) DAY) as created_at,
    DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 3) DAY) as updated_at
FROM lessons l
WHERE l.module_id = 1
ORDER BY l.order_index;

-- Create some quiz attempts for the students
-- User 3: Completed quizzes for modules 1-3
INSERT INTO quiz_attempts (
    user_id,
    quiz_id,
    score,
    answers,
    time_taken_minutes,
    passed,
    completed_at,
    attempt_number,
    time_started,
    time_completed,
    time_spent_seconds,
    status,
    total_questions,
    correct_answers,
    created_at,
    updated_at
)
VALUES
-- Module 1 quiz (85% score, passed)
(3, 1, 85.00, '{}', 18, 1, DATE_SUB(NOW(), INTERVAL 12 DAY), 1,
 DATE_SUB(NOW(), INTERVAL 12 DAY), DATE_SUB(NOW(), INTERVAL 12 DAY),
 1080, 'graded', 4, 3, DATE_SUB(NOW(), INTERVAL 12 DAY), DATE_SUB(NOW(), INTERVAL 12 DAY)),

-- Module 2 quiz (90% score, passed)
(3, 2, 90.00, '{}', 22, 1, DATE_SUB(NOW(), INTERVAL 8 DAY), 1,
 DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY),
 1320, 'graded', 4, 4, DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY)),

-- Module 3 quiz (75% score, passed)
(3, 3, 75.00, '{}', 20, 1, DATE_SUB(NOW(), INTERVAL 5 DAY), 1,
 DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY),
 1200, 'graded', 3, 2, DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY)),

-- User 5: Completed module 1 quiz
-- Module 1 quiz (70% score, passed on second attempt)
(5, 1, 65.00, '{}', 25, 0, DATE_SUB(NOW(), INTERVAL 2 DAY), 1,
 DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY),
 1500, 'graded', 4, 2, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),

(5, 1, 70.00, '{}', 20, 1, DATE_SUB(NOW(), INTERVAL 1 DAY), 2,
 DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY),
 1200, 'graded', 4, 3, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY));

-- Update enrollment progress percentages based on actual progress
UPDATE enrollments e
SET progress_percentage = (
    SELECT ROUND((COUNT(DISTINCT lp.lesson_id) * 100.0) / (SELECT COUNT(*) FROM lessons WHERE module_id IN (SELECT id FROM modules WHERE id <= 6)), 2)
    FROM lesson_progress lp
    WHERE lp.user_id = e.user_id AND lp.status = 'completed'
)
WHERE e.course_id = 1;

-- Verify results
SELECT
    e.id as enrollment_id,
    u.name as student_name,
    c.title as course_title,
    e.status,
    e.progress_percentage,
    e.enrolled_at,
    (SELECT COUNT(*) FROM lesson_progress lp WHERE lp.user_id = e.user_id AND lp.status = 'completed') as lessons_completed,
    (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.user_id = e.user_id) as quizzes_attempted
FROM enrollments e
JOIN users u ON e.user_id = u.id
JOIN courses c ON e.course_id = c.id
ORDER BY e.id;
