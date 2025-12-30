-- Migration 014: Populate Quizzes for All Modules
-- Phase A: Fix Critical Gaps
-- Creates quizzes for modules 2-6 and distributes quiz questions

-- Insert quizzes for remaining modules (only if they don't exist)
INSERT INTO quizzes (module_id, title, description, passing_score, time_limit_minutes, created_at, updated_at)
SELECT * FROM (SELECT
    2 as module_id,
    'Generative AI Quiz' as title,
    'Test your understanding of generative AI concepts and applications' as description,
    70 as passing_score,
    30 as time_limit_minutes,
    NOW() as created_at,
    NOW() as updated_at
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM quizzes WHERE module_id = 2)
UNION ALL
SELECT * FROM (SELECT
    3, 'Advanced Search Techniques Quiz', 'Assess your knowledge of advanced search strategies and AI-powered search', 70, 25, NOW(), NOW()
) AS tmp2
WHERE NOT EXISTS (SELECT 1 FROM quizzes WHERE module_id = 3)
UNION ALL
SELECT * FROM (SELECT
    4, 'Responsible AI Quiz', 'Evaluate your understanding of AI ethics, bias, and responsible AI practices', 70, 30, NOW(), NOW()
) AS tmp3
WHERE NOT EXISTS (SELECT 1 FROM quizzes WHERE module_id = 4)
UNION ALL
SELECT * FROM (SELECT
    5, 'Microsoft Copilot Quiz', 'Test your proficiency with Microsoft Copilot features and best practices', 70, 25, NOW(), NOW()
) AS tmp4
WHERE NOT EXISTS (SELECT 1 FROM quizzes WHERE module_id = 5)
UNION ALL
SELECT * FROM (SELECT
    6, 'AI Impact on Society Quiz', 'Assess your understanding of AI\'s societal impact and future implications', 70, 30, NOW(), NOW()
) AS tmp5
WHERE NOT EXISTS (SELECT 1 FROM quizzes WHERE module_id = 6);

-- Alternative simpler approach (commenting out complex version above if this works better):
-- INSERT IGNORE INTO quizzes (module_id, title, description, passing_score, time_limit_minutes, created_at, updated_at)
-- VALUES
-- Module 2: Generative AI
(2, 'Generative AI Quiz', 'Test your understanding of generative AI concepts and applications', 70, 30, NOW(), NOW()),

-- Module 3: Advanced Search
(3, 'Advanced Search Techniques Quiz', 'Assess your knowledge of advanced search strategies and AI-powered search', 70, 25, NOW(), NOW()),

-- Module 4: Responsible AI
(4, 'Responsible AI Quiz', 'Evaluate your understanding of AI ethics, bias, and responsible AI practices', 70, 30, NOW(), NOW()),

-- Module 5: Microsoft Copilot
(5, 'Microsoft Copilot Quiz', 'Test your proficiency with Microsoft Copilot features and best practices', 70, 25, NOW(), NOW()),

-- Module 6: AI Impact
(6, 'AI Impact on Society Quiz', 'Assess your understanding of AI\'s societal impact and future implications', 70, 30, NOW(), NOW());

-- Update quiz_questions to distribute across quizzes
-- Assuming 20 questions exist, distribute ~3-4 per quiz

-- Questions 1-4: Keep in Module 1 quiz (AI Foundations)
-- Already assigned to quiz_id = 1

-- Questions 5-8: Assign to Module 2 quiz (Generative AI)
UPDATE quiz_questions
SET quiz_id = (SELECT id FROM quizzes WHERE module_id = 2 LIMIT 1)
WHERE id IN (5, 6, 7, 8);

-- Questions 9-11: Assign to Module 3 quiz (Advanced Search)
UPDATE quiz_questions
SET quiz_id = (SELECT id FROM quizzes WHERE module_id = 3 LIMIT 1)
WHERE id IN (9, 10, 11);

-- Questions 12-15: Assign to Module 4 quiz (Responsible AI)
UPDATE quiz_questions
SET quiz_id = (SELECT id FROM quizzes WHERE module_id = 4 LIMIT 1)
WHERE id IN (12, 13, 14, 15);

-- Questions 16-18: Assign to Module 5 quiz (Microsoft Copilot)
UPDATE quiz_questions
SET quiz_id = (SELECT id FROM quizzes WHERE module_id = 5 LIMIT 1)
WHERE id IN (16, 17, 18);

-- Questions 19-20: Assign to Module 6 quiz (AI Impact)
UPDATE quiz_questions
SET quiz_id = (SELECT id FROM quizzes WHERE module_id = 6 LIMIT 1)
WHERE id IN (19, 20);

-- Verify results
SELECT
    q.id as quiz_id,
    q.title as quiz_title,
    m.title as module_title,
    COUNT(qq.id) as question_count
FROM quizzes q
JOIN modules m ON q.module_id = m.id
LEFT JOIN quiz_questions qq ON qq.quiz_id = q.id
GROUP BY q.id, q.title, m.title
ORDER BY q.id;
