-- Migration 018: Populate Quiz Questions for Modules 2-6
-- Generated: 2025-12-30 12:15:09
-- Phase 5 Cleanup: Migrate questions from static HTML to database
-- Prerequisites: Quizzes for modules 2-6 must exist (created by migration 014)

INSERT INTO quiz_questions (quiz_id, question_text, options, correct_option, explanation, points, order_index)
VALUES
-- Module 2: 10 questions
-- Module 3: 10 questions
-- Module 4: 10 questions
-- Module 5: 11 questions
-- Module 6: 10 questions
(
    (SELECT id FROM quizzes WHERE module_id = 2 LIMIT 1),
    'What is generative AI?',
    JSON_ARRAY('A type of AI focused solely on analyzing existing data', 'A category of AI algorithms that generate new outputs based on the data they have been trained on', 'AI systems that can only produce text-based responses', 'Technology designed exclusively for graphic designers'),
    1,
    'Generative AI refers to AI algorithms that can create new content (like text, images, audio) based on patterns learned from training data, not just analyze existing data.',
    1,
    1
),
(
    (SELECT id FROM quizzes WHERE module_id = 2 LIMIT 1),
    'What does GPT stand for in the context of AI?',
    JSON_ARRAY('Global Processing Technology', 'General Purpose Transformer', 'Generative Pre-trained Transformer', 'Graphical Processing Tool'),
    2,
    'GPT stands for Generative Pre-trained Transformer. It\'s a neural network model that uses transformer architecture and is pre-trained on large datasets to generate text.',
    1,
    2
),
(
    (SELECT id FROM quizzes WHERE module_id = 2 LIMIT 1),
    'Which of the following is NOT a type of generative AI?',
    JSON_ARRAY('Text-based generative AI', 'Audio generative AI', 'Diagnostic generative AI', 'Video generative AI'),
    2,
    'While text, audio, and video are all common types of generative AI, \"Diagnostic generative AI\" is not a standard classification. Diagnostic systems typically fall under predictive or analytical AI categories.',
    1,
    3
),
(
    (SELECT id FROM quizzes WHERE module_id = 2 LIMIT 1),
    'How can Microsoft Copilot assist with content creation?',
    JSON_ARRAY('It can only check spelling and grammar', 'It can help draft emails, provide writing assistance, and generate creative content based on prompts', 'It can only translate between languages', 'It can only create spreadsheets and databases'),
    1,
    'Microsoft Copilot is versatile and can help create various types of content including drafting emails, writing documents, suggesting ideas, and generating creative content based on user prompts.',
    1,
    4
),
(
    (SELECT id FROM quizzes WHERE module_id = 2 LIMIT 1),
    'DALL·E is an example of which type of generative AI?',
    JSON_ARRAY('Text-to-image generation', 'Text-to-speech generation', 'Video generation', 'Music composition'),
    0,
    'DALL·E is a text-to-image generative AI model that creates images from text descriptions. It can generate detailed and creative visual content based on textual prompts.',
    1,
    5
),
(
    (SELECT id FROM quizzes WHERE module_id = 2 LIMIT 1),
    'How is generative AI helping transform the workforce?',
    JSON_ARRAY('By completely replacing human workers with machines', 'By creating new job categories and opportunities while enhancing existing roles', 'By eliminating the need for creativity in the workplace', 'By making all work remote and digital'),
    1,
    'Rather than just replacing jobs, generative AI is creating entirely new job categories and enhancing existing roles. It\'s transforming how people work by automating routine tasks and allowing humans to focus on more creative and strategic work.',
    1,
    6
),
(
    (SELECT id FROM quizzes WHERE module_id = 2 LIMIT 1),
    'Which of the following is a characteristic of a large language model (LLM)?',
    JSON_ARRAY('It can only process small amounts of data', 'It works solely with numerical calculations', 'It can generate text that appears to be written by a human', 'It requires a physical keyboard to function'),
    2,
    'Large Language Models (LLMs) can generate human-like text based on patterns learned from large datasets. They understand context and can produce coherent, contextually appropriate responses that mimic human writing.',
    1,
    7
),
(
    (SELECT id FROM quizzes WHERE module_id = 2 LIMIT 1),
    'How can generative AI assist in education and learning?',
    JSON_ARRAY('By replacing teachers entirely', 'By creating personalized learning experiences and identifying knowledge gaps', 'By eliminating the need for textbooks only', 'By making students memorize more information'),
    1,
    'Generative AI can analyze a learner\'s progress, create personalized learning paths, identify knowledge gaps, and provide tailored content and exercises. It helps make learning more adaptive to individual needs rather than replacing teachers.',
    1,
    8
),
(
    (SELECT id FROM quizzes WHERE module_id = 2 LIMIT 1),
    'What distinguishes NLG (Natural Language Generation) from LLMs (Large Language Models)?',
    JSON_ARRAY('NLG focuses on generating new text from structured data, while LLMs are designed to produce text that is meaningful and contextually fitting', 'NLG can only translate languages, while LLMs can only summarize text', 'NLG is always more accurate than LLMs', 'NLG is used for entertainment, while LLMs are used for business purposes only'),
    0,
    'NLG systems traditionally convert structured data into natural language text following predefined templates, while LLMs generate text based on patterns learned from vast amounts of training data, allowing them to create more contextually varied and nuanced responses.',
    1,
    9
),
(
    (SELECT id FROM quizzes WHERE module_id = 2 LIMIT 1),
    'How can generative AI help in career development?',
    JSON_ARRAY('By automatically applying for jobs on behalf of users', 'By guaranteeing employment in the technology sector', 'By analyzing available jobs, suggesting relevant skills to learn, and helping identify career opportunities that match one\'s abilities', 'By eliminating the need for interviews'),
    2,
    'Generative AI can analyze job market trends, identify skills gaps, suggest learning resources, and help match individuals with career opportunities that align with their abilities and interests. It serves as a career compass rather than replacing the job search process.',
    1,
    10
),
(
    (SELECT id FROM quizzes WHERE module_id = 3 LIMIT 1),
    'What are the three main tasks performed by search engines?',
    JSON_ARRAY('Analyzing, processing, and reporting', 'Crawling, indexing, and ranking', 'Searching, finding, and displaying', 'Querying, filtering, and sorting'),
    1,
    'Search engines operate through three fundamental tasks: crawling (discovering web content), indexing (categorizing and storing content), and ranking (determining which content best answers a query).',
    1,
    1
),
(
    (SELECT id FROM quizzes WHERE module_id = 3 LIMIT 1),
    'What is the main difference between a search engine and a reasoning engine?',
    JSON_ARRAY('Search engines are faster while reasoning engines are more thorough', 'Search engines find information while reasoning engines apply logic to draw conclusions', 'Search engines are text-based while reasoning engines are image-based', 'Search engines require internet access while reasoning engines work offline'),
    1,
    'While search engines primarily locate and present existing information, reasoning engines go a step further by applying logic and inference to analyze, interpret, and draw conclusions from that information.',
    1,
    2
),
(
    (SELECT id FROM quizzes WHERE module_id = 3 LIMIT 1),
    'What is prompt engineering?',
    JSON_ARRAY('The process of designing hardware prompts for digital assistants', 'The art of crafting prompts that guide an LLM-based generative AI to produce a desired response', 'The technical process of programming AI to respond to basic commands', 'The engineering process behind creating visual prompts for search engines'),
    1,
    'Prompt engineering is the skill of designing effective instructions (prompts) for AI systems to generate desired outputs. It involves understanding how to communicate clearly with AI to achieve specific results.',
    1,
    3
),
(
    (SELECT id FROM quizzes WHERE module_id = 3 LIMIT 1),
    'What are the two main components of an effective prompt?',
    JSON_ARRAY('Question and keywords', 'Instruction and context', 'Subject and predicate', 'Format and length'),
    1,
    'Effective prompts consist of clear instructions (what you want the AI to do) and relevant context (additional information that helps the AI understand your specific needs, such as audience, tone, or purpose).',
    1,
    4
),
(
    (SELECT id FROM quizzes WHERE module_id = 3 LIMIT 1),
    'Which of the following is NOT one of the conversation styles available in Microsoft Copilot?',
    JSON_ARRAY('More Creative', 'More Balanced', 'More Technical', 'More Precise'),
    2,
    'Microsoft Copilot offers three conversation styles: More Creative (for imaginative content), More Balanced (for everyday tasks), and More Precise (for factual information). \"More Technical\" is not an available style.',
    1,
    5
),
(
    (SELECT id FROM quizzes WHERE module_id = 3 LIMIT 1),
    'When Microsoft Copilot processes text, it breaks it down into what?',
    JSON_ARRAY('Bytes', 'Characters', 'Tokens', 'Strings'),
    2,
    'Copilot processes text by breaking it into tokens, which can be as short as a single character or as long as a word. Understanding tokenization helps users craft more efficient prompts, as there are often limits to how many tokens can be processed at once.',
    1,
    6
),
(
    (SELECT id FROM quizzes WHERE module_id = 3 LIMIT 1),
    'What methodology does Microsoft Copilot use to combine search and AI capabilities?',
    JSON_ARRAY('Reinforcement learning with human feedback', 'Retrieval-augmented generation', 'Neural network optimization', 'Multi-modal integration'),
    1,
    'Microsoft Copilot uses retrieval-augmented generation (RAG), combining the knowledge from its large language model with real-time information retrieved from the web, ensuring responses are both intelligent and up-to-date.',
    1,
    7
),
(
    (SELECT id FROM quizzes WHERE module_id = 3 LIMIT 1),
    'Which of the following is a best practice for crafting effective prompts?',
    JSON_ARRAY('Keep prompts vague to allow AI more creative freedom', 'Always use the same conversation style regardless of task', 'Be specific and provide relevant context', 'Limit prompts to single-word commands'),
    2,
    'Specificity in prompts leads to more accurate and relevant responses. By providing clear instructions with detailed context about what you want, the AI can better understand your needs and deliver more useful results.',
    1,
    8
),
(
    (SELECT id FROM quizzes WHERE module_id = 3 LIMIT 1),
    'What is the purpose of the \"More Creative\" conversation style in Microsoft Copilot?',
    JSON_ARRAY('To provide strictly factual information with references', 'To assist with mathematical calculations and data analysis', 'To generate novel ideas, help with brainstorming, and assist with creative content', 'To translate text between different languages'),
    2,
    'The \"More Creative\" style is designed for ideation, storytelling, and content creation. It encourages more imaginative and novel responses, making it ideal for brainstorming sessions and creative writing tasks.',
    1,
    9
),
(
    (SELECT id FROM quizzes WHERE module_id = 3 LIMIT 1),
    'How can critical thinking be applied when using AI search tools?',
    JSON_ARRAY('By accepting all AI-generated information as factual', 'By avoiding AI tools completely for research purposes', 'By verifying facts, questioning sources, and considering a range of viewpoints', 'By using only the most complex prompts possible'),
    2,
    'Critical thinking involves evaluating AI-generated information by verifying facts from multiple sources, questioning the reliability of information, and considering different perspectives rather than accepting AI outputs at face value.',
    1,
    10
),
(
    (SELECT id FROM quizzes WHERE module_id = 4 LIMIT 1),
    'What are the six core principles of responsible AI according to Microsoft\'s framework?',
    JSON_ARRAY('Innovation, creativity, efficiency, speed, accuracy, and automation', 'Accountability, inclusiveness, reliability and safety, fairness, transparency, and privacy and security', 'Profitability, legality, accessibility, sustainability, scalability, and compatibility', 'Personalization, optimization, customization, adaptation, learning, and development'),
    1,
    'Microsoft\'s Responsible AI framework is based on six core principles: accountability (taking responsibility for AI systems), inclusiveness (designing for all users), reliability and safety (ensuring robust, secure operation), fairness (providing equitable treatment), transparency (making AI understandable), and privacy and security (protecting user data).',
    1,
    1
),
(
    (SELECT id FROM quizzes WHERE module_id = 4 LIMIT 1),
    'What is a deepfake?',
    JSON_ARRAY('A security feature that verifies digital content authenticity', 'An AI tool that detects fraudulent online activities', 'A fraudulent piece of content—typically audio or video—created or manipulated using AI', 'A type of encryption used to protect sensitive data in AI systems'),
    2,
    'A deepfake is a fraudulent piece of content, typically audio or video, that has been manipulated or created using artificial intelligence. These technologies can replace a person\'s likeness, voice, or both with eerily realistic artificial versions, making it difficult to distinguish real from fake content.',
    1,
    2
),
(
    (SELECT id FROM quizzes WHERE module_id = 4 LIMIT 1),
    'Which of the following is a best practice for using AI responsibly?',
    JSON_ARRAY('Accept all AI-generated information as factual without verification', 'Share personal data widely to help improve AI systems', 'Use critical thinking to evaluate AI outputs and verify information from multiple sources', 'Avoid using AI tools entirely due to inherent risks'),
    2,
    'Responsible AI use involves applying critical thinking to evaluate AI outputs and verifying information from multiple sources. This practice helps identify potential biases, inaccuracies, or limitations in AI-generated content, ensuring more reliable and ethical use of the technology.',
    1,
    3
),
(
    (SELECT id FROM quizzes WHERE module_id = 4 LIMIT 1),
    'What is algorithmic bias in AI systems?',
    JSON_ARRAY('The tendency of AI to prefer certain programming languages over others', 'An intentional feature designed to improve AI performance', 'When AI systems unintentionally reflect and amplify existing societal biases', 'A security protocol that protects AI from external tampering'),
    2,
    'Algorithmic bias occurs when AI systems unintentionally reflect and amplify existing societal biases present in their training data. This can lead to unfair or discriminatory outcomes, such as facial recognition systems performing less accurately for certain demographic groups or loan approval algorithms unfairly denying applications based on factors correlated with protected characteristics.',
    1,
    4
),
(
    (SELECT id FROM quizzes WHERE module_id = 4 LIMIT 1),
    'Which of the following is NOT a strategy for combating deepfakes?',
    JSON_ARRAY('Digital watermarking for AI-generated content', 'AI-powered detection technologies', 'Blockchain verification of original content', 'Increasing the production of AI-generated content'),
    3,
    'While digital watermarking, AI-powered detection, and blockchain verification are all strategies used to combat deepfakes, simply increasing the production of AI-generated content would likely exacerbate the problem rather than solve it. Effective anti-deepfake strategies focus on authentication, detection, and education rather than proliferation.',
    1,
    5
),
(
    (SELECT id FROM quizzes WHERE module_id = 4 LIMIT 1),
    'What is the purpose of Microsoft\'s Copilot Copyright Commitment?',
    JSON_ARRAY('To prevent users from saving or sharing AI-generated content', 'To protect customers from potential copyright infringement claims related to AI outputs', 'To restrict the type of prompts users can input into Copilot', 'To automatically copyright all content generated by Microsoft Copilot'),
    1,
    'Microsoft\'s Copilot Copyright Commitment is designed to protect customers from potential legal risks related to copyright claims that might arise from using Microsoft\'s AI services. The commitment means Microsoft assumes responsibility if customers face copyright infringement claims based on Copilot outputs, providing users with greater peace of mind when using the technology.',
    1,
    6
),
(
    (SELECT id FROM quizzes WHERE module_id = 4 LIMIT 1),
    'Why is data quality important for responsible AI development?',
    JSON_ARRAY('Higher quality data requires less storage space', 'Quality data is easier to encrypt for security purposes', 'Representative, diverse data helps ensure AI systems are fair and accurate for all users', 'Higher quality data allows AI systems to run faster'),
    2,
    'Data quality is crucial for responsible AI because representative, diverse training data helps ensure AI systems make fair and accurate decisions for all users. When training data lacks diversity or contains historical biases, AI systems can perpetuate or amplify these biases, leading to discriminatory outcomes. High-quality, representative data helps mitigate these risks.',
    1,
    7
),
(
    (SELECT id FROM quizzes WHERE module_id = 4 LIMIT 1),
    'What is content credentialing in AI-generated content?',
    JSON_ARRAY('A system that rates AI content based on its quality', 'A requirement that all AI content must be approved by experts before distribution', 'A method using cryptographic techniques to add digital watermarks that verify content origin and authenticity', 'A process to remove all AI-generated content from search engine results'),
    2,
    'Content credentialing uses cryptographic techniques to add digital watermarks or other verification markers to AI-generated content. These credentials help verify the content\'s origin and authenticity, allowing viewers to distinguish between authentic content and potential deepfakes or manipulated media, enhancing trust in digital content.',
    1,
    8
),
(
    (SELECT id FROM quizzes WHERE module_id = 4 LIMIT 1),
    'Which of the following describes the principle of transparency in responsible AI?',
    JSON_ARRAY('Ensuring AI systems can be physically seen by users', 'Making AI decision-making processes understandable to users and explaining how conclusions are reached', 'Requiring AI systems to display warnings before performing any action', 'Using see-through materials in the hardware components of AI systems'),
    1,
    'The principle of transparency in responsible AI involves making AI decision-making processes understandable to users and explaining how conclusions are reached. This includes providing clear documentation, making AI decisions interpretable, disclosing when users are interacting with AI, and offering explanations for outputs. Transparency builds trust by helping users understand how AI systems operate.',
    1,
    9
),
(
    (SELECT id FROM quizzes WHERE module_id = 4 LIMIT 1),
    'What is the relationship between human-AI interaction and global outcomes?',
    JSON_ARRAY('They are completely separate concerns with no significant connection', 'Human-AI interaction only affects local issues while global outcomes are determined by governments', 'The decisions made in human-AI interaction are intertwined with global outcomes, affecting society, economics, and ethics worldwide', 'Global outcomes drive human-AI interaction but not vice versa'),
    2,
    'The decisions made in human-AI interaction are deeply intertwined with global outcomes, affecting society, economics, and ethics worldwide. How we design AI systems, interact with them, and regulate their use has far-reaching implications for issues like economic inequality, environmental sustainability, privacy rights, and social cohesion across the globe.',
    1,
    10
),
(
    (SELECT id FROM quizzes WHERE module_id = 5 LIMIT 1),
    'Which of these features can Microsoft Copilot perform?',
    JSON_ARRAY('Provide context and condense the writing result', 'Provide relevant information and make specific suggestions', 'Provide context and correct writing style', 'Condense the writing result and correct writing style'),
    1,
    'Microsoft Copilot can understand context, provide relevant information, make specific suggestions, and help create and edit documents, emails, and presentations.',
    1,
    1
),
(
    (SELECT id FROM quizzes WHERE module_id = 5 LIMIT 1),
    'What applications can users use to access Copilot to maximize their organization\'s Copilot privileges?',
    JSON_ARRAY('Microsoft 365', 'Microsoft Outlook', 'Microsoft Teams', 'Microsoft Azure'),
    0,
    'If a user has Copilot privileges from their organization, they can use that account to access Copilot in Microsoft 365 applications, which includes the suite of productivity apps.',
    1,
    2
),
(
    (SELECT id FROM quizzes WHERE module_id = 5 LIMIT 1),
    'Microsoft Copilot supports a comprehensive experience in several languages, including which of the following?',
    JSON_ARRAY('Portuguese (Brazil) and Italian', 'Japanese and Arabic', 'Tagalog and Isan', 'English and Kintaq'),
    0,
    'Microsoft Copilot supports a comprehensive experience in several languages, including Simplified Chinese, English, French, German, Italian, Japanese, Portuguese (Brazilian) and Spanish.',
    1,
    3
),
(
    (SELECT id FROM quizzes WHERE module_id = 5 LIMIT 1),
    'Which conversation style in Copilot achieves harmony between informative content and creative expression?',
    JSON_ARRAY('Balanced', 'Precise', 'Creative', 'Imaginative'),
    0,
    'There are three different styles that can be selected in Microsoft Copilot: creative, precise, and balanced. The balanced style produces harmony between informative content and creative expression.',
    1,
    4
),
(
    (SELECT id FROM quizzes WHERE module_id = 5 LIMIT 1),
    'What do plugins do for Copilot?',
    JSON_ARRAY('Assist Copilot to connect with third-party services', 'Assist Copilot to cut off access with third-party services', 'Assist Copilot to develop third-party service features', 'Help Copilot to simplify third-party service features'),
    0,
    'Plugins allow Copilot to connect with third-party services by performing a wider range of actions and retrieving information directly from third-party sources.',
    1,
    5
),
(
    (SELECT id FROM quizzes WHERE module_id = 5 LIMIT 1),
    'How should users approach achieving their ideas with Copilot?',
    JSON_ARRAY('Determine advice and provide context', 'Limiting context and limiting suggestions', 'Set expectations for help and limit other users\' suggestions', 'Determine suggestions and develop expectations'),
    0,
    'To achieve ideas in Copilot, users need to define goals, provide context, and set expectations for the assistance they want from Copilot.',
    1,
    6
),
(
    (SELECT id FROM quizzes WHERE module_id = 5 LIMIT 1),
    'Which of these methods represent Copilot\'s way of managing time effectively?',
    JSON_ARRAY('Optimizing work and personal schedules, and project planning', 'Prioritizing work and optimizing work and personal schedules', 'Prioritizing work and project planning', 'Eliminating non-urgent to-do lists and optimizing work and personal schedules'),
    0,
    'There are several ways Copilot manages time effectively, namely optimizing work and personal schedules and doing project planning.',
    1,
    7
),
(
    (SELECT id FROM quizzes WHERE module_id = 5 LIMIT 1),
    'How can Copilot help users expand their professional network?',
    JSON_ARRAY('Create a networking email and suggest a free CV template from Microsoft Create to attach to the user\'s email', 'Guide users to find jobs with the role of several job search websites such as LinkedIn and Indeed', 'Provide feedback or help improve the user\'s documents to suit the job they are applying for', 'Provide advice on answering common questions and provide feedback on user answers'),
    0,
    'To assist users in expanding professional networks, users can create commands to Copilot and Copilot creates networking emails and sends them to contacts, then suggests free CV templates from Microsoft Create to attach to user emails.',
    1,
    8
),
(
    (SELECT id FROM quizzes WHERE module_id = 5 LIMIT 1),
    'The prompt \"Help me improve this Python script for data analysis and provide suggestions on how to optimize its performance\" would be used for:',
    JSON_ARRAY('Working on a project when the user needs help', 'Connecting effectively with networks in any field', 'Assisting users in preparing for presentations or large meetings', 'Assisting users in providing new pathways that match their skills and interests'),
    0,
    'To work on a project when the user needs help, the prompt \"Help me improve this Python script for data analysis and provide suggestions on how to optimize its performance\" is appropriate for getting assistance with technical work.',
    1,
    9
),
(
    (SELECT id FROM quizzes WHERE module_id = 5 LIMIT 1),
    'How can Copilot help users understand data in spreadsheets?',
    JSON_ARRAY('Analyze the structure and relationships between cells', 'Provide a summary of the product being analyzed in the spreadsheet', 'Identify and list the elements in the spreadsheet', 'Outline the structure and relationships between cells'),
    0,
    'Copilot can help users to understand the data in the spreadsheet by analyzing the structure and relationship between cells.',
    1,
    10
),
(
    (SELECT id FROM quizzes WHERE module_id = 5 LIMIT 1),
    'The prompt \"Create a storyboard for the dragon scene in my script\" is used for which Copilot workplace support scenario?',
    JSON_ARRAY('Copilot becomes a companion for creativity', 'Copilot becomes a product discussion tool', 'Copilot generates ideas', 'Copilot draws up a business proposal'),
    0,
    'Copilot can assist users at work as a superior AI companion. The prompt \"Create a storyboard for the dragon scene in my script\" is used for the scenario where Copilot acts as a creativity companion.',
    1,
    11
),
(
    (SELECT id FROM quizzes WHERE module_id = 6 LIMIT 1),
    'How is AI redefining the digital landscape according to universal design principles?',
    JSON_ARRAY('By only focusing on improving user interfaces for technical experts', 'By replacing human workers with automated systems', 'By making technology more inclusive and empowering for everyone through adaptive interfaces', 'By focusing exclusively on physical accessibility features'),
    2,
    'Universal design principles aim to create products and environments that are inherently accessible to all people. AI is helping achieve this vision by creating new user interfaces and platforms that are more intuitive and adaptable to different user needs.',
    1,
    1
),
(
    (SELECT id FROM quizzes WHERE module_id = 6 LIMIT 1),
    'What is the main impact of AI on job roles across industries?',
    JSON_ARRAY('Complete replacement of human workers with AI systems', 'Augmentation of human capabilities and evolution of roles toward more creative and strategic work', 'Elimination of the need for skilled workers in most industries', 'Reduction of job satisfaction as work becomes more routine'),
    1,
    'AI is handling routine, repetitive tasks, freeing humans to focus on creative, strategic, and interpersonal aspects of work that require uniquely human skills. This represents an evolution of roles rather than wholesale replacement.',
    1,
    2
),
(
    (SELECT id FROM quizzes WHERE module_id = 6 LIMIT 1),
    'How is the Rijksmuseum using AI to enhance accessibility?',
    JSON_ARRAY('By creating virtual reality museum tours', 'By using Azure AI to generate detailed text descriptions of artwork for people with visual impairments', 'By developing an AI-powered robotic guide', 'By creating AI-generated reproductions of artworks'),
    1,
    'The Rijksmuseum uses Azure AI Computer Vision and Azure OpenAI to analyze artwork and generate detailed text descriptions. These descriptions can then be read aloud or translated into braille, making art more accessible to people with visual impairments.',
    1,
    3
),
(
    (SELECT id FROM quizzes WHERE module_id = 6 LIMIT 1),
    'How is AI transforming the financial sector?',
    JSON_ARRAY('By completely automating all financial decision-making', 'By replacing human financial advisors with chatbots', 'By assisting with fraud detection, risk management, and improving client experiences', 'By eliminating the need for financial institutions'),
    2,
    'In finance, AI assists with fraud detection, risk management, and improving client experiences. Tools like Microsoft\'s Copilot for Finance automate tasks and uncover insights, allowing finance teams to focus on strategic work that drives growth.',
    1,
    4
),
(
    (SELECT id FROM quizzes WHERE module_id = 6 LIMIT 1),
    'What is one way AI is being used to make educational content more accessible?',
    JSON_ARRAY('By replacing teachers with AI instructors', 'By creating devices that convert content into braille on demand, like the Hexis-Antara project', 'By limiting education to online platforms only', 'By standardizing all educational content to be identical'),
    1,
    'The Hexis-Antara project enhances braille literacy through a device that converts content into braille on demand, making educational content more accessible to learners who are blind or have low vision.',
    1,
    5
),
(
    (SELECT id FROM quizzes WHERE module_id = 6 LIMIT 1),
    'How is AI changing manufacturing processes?',
    JSON_ARRAY('By eliminating all human workers from factories', 'By analyzing machinery data to predict maintenance needs and optimize inventory management', 'By making manufacturing more expensive', 'By slowing down production to ensure quality'),
    1,
    'AI systems analyze data from machinery to predict when parts might fail, allowing maintenance to be scheduled before breakdowns occur. AI can also forecast product demand, helping maintain optimal inventory levels while reducing storage costs.',
    1,
    6
),
(
    (SELECT id FROM quizzes WHERE module_id = 6 LIMIT 1),
    'What key skills are becoming increasingly important in the AI era?',
    JSON_ARRAY('Adaptability, critical thinking, digital literacy, emotional intelligence, and ethical judgment', 'Memorization, repetitive task execution, and data entry', 'Coding skills exclusively, as all jobs will require programming', 'Resistance to technology adoption and manual process expertise'),
    0,
    'As AI handles routine tasks, human skills like adaptability, critical thinking, digital literacy, emotional intelligence, and ethical judgment become more valuable. These are qualities that complement AI capabilities rather than compete with them.',
    1,
    7
),
(
    (SELECT id FROM quizzes WHERE module_id = 6 LIMIT 1),
    'How is AI contributing to more sustainable practices in the energy sector?',
    JSON_ARRAY('By increasing energy consumption for data processing', 'By predicting wind patterns and optimizing turbine angles in wind farms to increase efficiency', 'By encouraging more energy usage during peak hours', 'By replacing renewable energy with more efficient fossil fuels'),
    1,
    'In the energy sector, AI drives us toward a greener future. Wind farms equipped with AI sensors can predict wind patterns and adjust turbine angles to capture maximum energy, increasing efficiency and extending turbine lifespan.',
    1,
    8
),
(
    (SELECT id FROM quizzes WHERE module_id = 6 LIMIT 1),
    'What is the purpose of the DAISY Consortium\'s AI initiative?',
    JSON_ARRAY('To create AI-written books', 'To develop AI critics for literature review', 'To convert books for use on various devices used by people with disabilities, including braille displays', 'To replace traditional publishers with AI publishing platforms'),
    2,
    'The DAISY Consortium is developing an AI app to convert books for use on various devices typically used by people with disabilities, including basic phones, braille displays, and solar-powered audio players, making books more accessible worldwide.',
    1,
    9
),
(
    (SELECT id FROM quizzes WHERE module_id = 6 LIMIT 1),
    'What is the most promising future of work according to the module?',
    JSON_ARRAY('Complete automation with minimal human involvement', 'A return to pre-AI work methods', 'Human-AI partnerships that combine the strengths of both', 'Humans working exclusively on maintaining AI systems'),
    2,
    'The most promising future of work is one where humans and AI form powerful partnerships that combine the strengths of both. Human creativity, ethical judgment, and interpersonal skills paired with AI\'s data processing capabilities create outcomes neither could achieve alone.',
    1,
    10
);

-- Verification query:
-- SELECT m.id, m.title, q.title as quiz, COUNT(qq.id) as questions
-- FROM modules m
-- LEFT JOIN quizzes q ON q.module_id = m.id
-- LEFT JOIN quiz_questions qq ON qq.quiz_id = q.id
-- WHERE m.id BETWEEN 2 AND 6
-- GROUP BY m.id, q.id;
