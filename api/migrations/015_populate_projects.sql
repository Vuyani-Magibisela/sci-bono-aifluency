-- Migration 015: Populate Projects for All Modules
-- Phase A: Fix Critical Gaps
-- Creates practical projects for each of the 6 modules

-- Insert projects for all modules
INSERT INTO projects (module_id, title, description, instructions, requirements, max_score, is_published, created_at, updated_at)
VALUES
-- Module 1: AI Foundations
(
    1,
    'AI Concept Map Project',
    'Create a comprehensive concept map that illustrates your understanding of fundamental AI concepts and their relationships.',
    'Create a visual concept map that includes: AI definition, machine learning, types of learning (supervised, unsupervised, reinforcement), neural networks, and how they interconnect. Use any digital tool (Draw.io, Lucidchart, PowerPoint, etc.) or hand-draw and photograph your map.',
    '- Include at least 15 key AI concepts\n- Show clear relationships between concepts with labeled arrows\n- Use color coding to group related concepts\n- Include a legend explaining your color scheme\n- Submit as PDF or image file (PNG/JPG)\n- Include a 200-word written explanation of your map',
    100,
    1,
    NOW(),
    NOW()
),

-- Module 2: Generative AI
(
    2,
    'Generative AI Application Showcase',
    'Explore and document your experience using generative AI tools for a practical task.',
    'Choose one generative AI tool (ChatGPT, DALL-E, Midjourney, etc.) and complete a creative project. Document your process, prompts used, iterations, and final results. Reflect on the AI''s capabilities and limitations.',
    '- Select and introduce your chosen AI tool\n- Define your creative goal/task\n- Document at least 5 different prompts you used\n- Show iterations and refinements\n- Present final outputs (images, text, or other)\n- Write a 500-word reflection on the experience\n- Discuss ethical considerations\n- Submit as PDF with embedded media',
    100,
    1,
    NOW(),
    NOW()
),

-- Module 3: Advanced Search
(
    3,
    'Advanced Search Strategy Report',
    'Demonstrate mastery of advanced search techniques by solving a complex information challenge.',
    'You are a researcher investigating "The impact of AI on employment in your country." Use advanced search operators, Boolean logic, and AI-powered search tools to find credible sources. Document your search strategy and findings.',
    '- Define your research question clearly\n- Document 10 different search queries using:\n  * Boolean operators (AND, OR, NOT)\n  * Search operators (site:, filetype:, intitle:, etc.)\n  * Date range filtering\n- Use at least 3 different search engines/tools\n- Evaluate source credibility for each result\n- Create an annotated bibliography (10 sources minimum)\n- Submit as PDF (1500-2000 words)',
    100,
    1,
    NOW(),
    NOW()
),

-- Module 4: Responsible AI
(
    4,
    'AI Ethics Case Study Analysis',
    'Analyze a real-world AI ethics scenario and propose responsible solutions.',
    'Choose a documented case of AI bias, privacy violation, or ethical concern (e.g., facial recognition bias, hiring algorithm discrimination, deepfakes). Analyze the case through multiple ethical frameworks and propose improvements.',
    '- Describe the case (who, what, when, where)\n- Identify ethical issues and stakeholders affected\n- Analyze using at least 2 ethical frameworks:\n  * Utilitarian approach\n  * Rights-based approach\n  * Fairness/justice approach\n- Propose 5 concrete recommendations for improvement\n- Include citations (minimum 5 credible sources)\n- Address potential trade-offs and challenges\n- Submit as PDF (2000-2500 words)',
    100,
    1,
    NOW(),
    NOW()
),

-- Module 5: Microsoft Copilot
(
    5,
    'Microsoft Copilot Productivity Challenge',
    'Demonstrate proficiency with Microsoft Copilot across multiple Microsoft 365 applications.',
    'Complete a series of practical tasks using Microsoft Copilot in Word, Excel, PowerPoint, and Outlook. Document your prompts, outputs, and productivity gains.',
    'Complete ALL of the following tasks:\n1. WORD: Create a 3-page business report using Copilot\n2. EXCEL: Analyze a dataset and create visualizations with Copilot\n3. POWERPOINT: Generate a 10-slide presentation with Copilot\n4. OUTLOOK: Draft 3 professional emails using Copilot\n\nFor each task:\n- Screenshot your Copilot prompts\n- Show the generated output\n- Describe any edits/refinements you made\n- Estimate time saved vs. manual work\n\nSubmit as ZIP file containing:\n- All created documents\n- Screenshots of prompts\n- 1000-word reflection on Copilot''s strengths/limitations',
    100,
    1,
    NOW(),
    NOW()
),

-- Module 6: AI Impact
(
    6,
    'AI Future Scenario Planning',
    'Develop a detailed scenario exploring AI''s potential impact on society in the next 10 years.',
    'Create a comprehensive scenario narrative exploring how AI might transform a specific sector (healthcare, education, transportation, etc.) by 2035. Include both opportunities and challenges.',
    '- Choose a specific sector to focus on\n- Research current AI trends in that sector\n- Develop a detailed 10-year scenario including:\n  * Timeline of key developments (2025-2035)\n  * Major AI technologies deployed\n  * Changes to jobs and skills needed\n  * Societal benefits and risks\n  * Ethical considerations\n  * Regulatory needs\n- Include data visualizations (timeline, charts, infographics)\n- Provide 10 credible sources\n- Submit as PDF (2500-3000 words) with visuals',
    100,
    1,
    NOW(),
    NOW()
);

-- Verify results
SELECT
    p.id as project_id,
    p.title as project_title,
    m.title as module_title,
    p.max_score,
    p.is_published
FROM projects p
JOIN modules m ON p.module_id = m.id
ORDER BY p.id;
