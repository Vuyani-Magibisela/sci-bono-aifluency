/**
 * Content Loader Module (Phase 5C)
 * Dynamically loads course content from the backend API
 * Handles modules, lessons, and quizzes
 */

const ContentLoader = {
    /**
     * Load module data and lessons
     * @param {number} moduleId - Module ID from database
     * @returns {Promise<object>} Module data with lessons
     */
    async loadModule(moduleId) {
        try {
            // Load module details
            const moduleResponse = await API.get(`/modules/${moduleId}`);
            const module = moduleResponse.data;

            // Load lessons for this module
            const lessonsResponse = await API.get(`/lessons?module_id=${moduleId}`);
            const lessons = lessonsResponse.data?.items || [];

            return {
                module,
                lessons: lessons.sort((a, b) => a.order_index - b.order_index)
            };
        } catch (error) {
            console.error('ContentLoader: Error loading module:', error);
            throw error;
        }
    },

    /**
     * Load lesson/chapter data
     * @param {number} lessonId - Lesson ID from database
     * @returns {Promise<object>} Lesson data
     */
    async loadLesson(lessonId) {
        try {
            const response = await API.get(`/lessons/${lessonId}`);
            return response.data;
        } catch (error) {
            console.error('ContentLoader: Error loading lesson:', error);
            throw error;
        }
    },

    /**
     * Load quiz data with questions
     * @param {number} moduleId - Module ID to load quiz for
     * @returns {Promise<object>} Quiz data with questions
     */
    async loadQuiz(moduleId) {
        try {
            // Get quiz for this module
            const quizResponse = await API.get(`/quizzes?module_id=${moduleId}`);
            const quizzes = quizResponse.data?.items || [];

            if (quizzes.length === 0) {
                throw new Error('No quiz found for this module');
            }

            const quiz = quizzes[0]; // Get first quiz for module

            // Get questions for this quiz
            const questionsResponse = await API.get(`/quiz-questions?quiz_id=${quiz.id}`);
            const questions = questionsResponse.data?.items || [];

            return {
                ...quiz,
                questions: questions.map(q => ({
                    id: q.id,
                    question: q.question,
                    options: JSON.parse(q.options),
                    correctAnswer: q.correct_answer,
                    explanation: q.explanation,
                    points: q.points
                }))
            };
        } catch (error) {
            console.error('ContentLoader: Error loading quiz:', error);
            throw error;
        }
    },

    /**
     * Randomize quiz questions (Phase 5D Priority 3)
     * @param {object} quizData - Quiz data with questions
     * @param {boolean} randomizeQuestions - Whether to randomize question order
     * @param {boolean} randomizeOptions - Whether to randomize answer options
     * @returns {object} Quiz data with randomized questions
     */
    randomizeQuiz(quizData, randomizeQuestions = true, randomizeOptions = true) {
        const quiz = { ...quizData };

        // Randomize question order
        if (randomizeQuestions && quiz.questions && quiz.questions.length > 0) {
            quiz.questions = this.shuffleArray([...quiz.questions]);
        }

        // Randomize answer options for each question
        if (randomizeOptions && quiz.questions) {
            quiz.questions = quiz.questions.map(question => {
                const q = { ...question };

                // Store original correct answer before shuffling
                const correctAnswerText = q.options[q.correctAnswer];

                // Shuffle options
                q.options = this.shuffleArray([...q.options]);

                // Find new index of correct answer
                q.correctAnswer = q.options.indexOf(correctAnswerText);

                return q;
            });
        }

        return quiz;
    },

    /**
     * Shuffle array using Fisher-Yates algorithm
     * @param {Array} array - Array to shuffle
     * @returns {Array} Shuffled array
     */
    shuffleArray(array) {
        const shuffled = [...array];
        for (let i = shuffled.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
        }
        return shuffled;
    },

    /**
     * Render module page with dynamic content
     * @param {object} moduleData - Module data with lessons
     * @param {object} containerSelectors - DOM selectors for content injection
     */
    renderModulePage(moduleData, containerSelectors = {}) {
        const { module, lessons } = moduleData;

        // Update module header
        if (containerSelectors.title) {
            const titleElement = document.querySelector(containerSelectors.title);
            if (titleElement) {
                titleElement.textContent = `${module.title}`;
            }
        }

        if (containerSelectors.subtitle) {
            const subtitleElement = document.querySelector(containerSelectors.subtitle);
            if (subtitleElement && module.description) {
                subtitleElement.textContent = module.description;
            }
        }

        // Render lesson cards
        if (containerSelectors.lessonsContainer) {
            const container = document.querySelector(containerSelectors.lessonsContainer);
            if (container && lessons.length > 0) {
                container.innerHTML = this.generateLessonCards(lessons);
            }
        }
    },

    /**
     * Generate HTML for lesson cards
     * @param {Array} lessons - Array of lesson objects
     * @returns {string} HTML string
     */
    generateLessonCards(lessons) {
        return lessons.map(lesson => {
            // Map lesson to chapter card format
            const icon = this.getLessonIcon(lesson.order_index);

            return `
                <div class="chapter-card">
                    <div class="chapter-card-icon">
                        <i class="fas fa-${icon}"></i>
                    </div>
                    <div class="chapter-card-content">
                        <h3>${this.escapeHtml(lesson.title)}</h3>
                        ${lesson.subtitle ? `<p>${this.escapeHtml(lesson.subtitle)}</p>` : ''}
                        <a href="?lesson_id=${lesson.id}" class="chapter-link">Begin Lesson</a>
                    </div>
                </div>
            `;
        }).join('');
    },

    /**
     * Get icon for lesson based on order
     * @param {number} orderIndex - Lesson order index
     * @returns {string} Font Awesome icon name
     */
    getLessonIcon(orderIndex) {
        const icons = [
            'history', 'robot', 'brain', 'database', 'chart-line',
            'lightbulb', 'cogs', 'search', 'network-wired', 'microchip',
            'graduation-cap'
        ];
        return icons[(orderIndex - 1) % icons.length] || 'book';
    },

    /**
     * Render lesson page with dynamic content
     * @param {object} lesson - Lesson data
     * @param {object} containerSelectors - DOM selectors for content injection
     */
    renderLessonPage(lesson, containerSelectors = {}) {
        // Update chapter header
        if (containerSelectors.moduleBadge) {
            const badgeElement = document.querySelector(containerSelectors.moduleBadge);
            if (badgeElement && lesson.module) {
                badgeElement.textContent = lesson.module.title;
            }
        }

        if (containerSelectors.title) {
            const titleElement = document.querySelector(containerSelectors.title);
            if (titleElement) {
                titleElement.textContent = lesson.title;
            }
        }

        if (containerSelectors.subtitle) {
            const subtitleElement = document.querySelector(containerSelectors.subtitle);
            if (subtitleElement && lesson.subtitle) {
                subtitleElement.textContent = lesson.subtitle;
            }
        }

        // Inject lesson content
        if (containerSelectors.content) {
            const contentElement = document.querySelector(containerSelectors.content);
            if (contentElement && lesson.content) {
                contentElement.innerHTML = lesson.content;
            }
        }
    },

    /**
     * Render quiz page with dynamic content
     * @param {object} quizData - Quiz data with questions
     * @param {object} containerSelectors - DOM selectors for content injection
     */
    renderQuizPage(quizData, containerSelectors = {}) {
        // Update quiz header
        if (containerSelectors.title) {
            const titleElement = document.querySelector(containerSelectors.title);
            if (titleElement) {
                titleElement.textContent = quizData.title;
            }
        }

        if (containerSelectors.description) {
            const descElement = document.querySelector(containerSelectors.description);
            if (descElement && quizData.description) {
                descElement.textContent = quizData.description;
            }
        }

        // Generate quiz questions HTML
        if (containerSelectors.questionsContainer) {
            const container = document.querySelector(containerSelectors.questionsContainer);
            if (container) {
                container.innerHTML = this.generateQuizQuestions(quizData.questions);
            }
        }

        // Store quiz data for submission
        window.quizData = quizData.questions;
        window.currentQuiz = {
            id: quizData.id,
            passing_score: quizData.passing_score,
            time_limit_minutes: quizData.time_limit_minutes
        };
    },

    /**
     * Generate HTML for quiz questions
     * @param {Array} questions - Array of question objects
     * @returns {string} HTML string
     */
    generateQuizQuestions(questions) {
        return questions.map((question, index) => `
            <div class="question-container" data-question-id="${question.id}">
                <div class="question">
                    <span class="question-number">Question ${index + 1}:</span>
                    ${this.escapeHtml(question.question)}
                </div>
                <div class="options">
                    ${question.options.map((option, optIndex) => `
                        <div class="option">
                            <input type="radio"
                                   id="q${index}_opt${optIndex}"
                                   name="question${index}"
                                   value="${optIndex}">
                            <label for="q${index}_opt${optIndex}">
                                ${this.escapeHtml(option)}
                            </label>
                        </div>
                    `).join('')}
                </div>
            </div>
        `).join('');
    },

    /**
     * Track lesson progress (start)
     * @param {number} lessonId - Lesson ID
     */
    async trackLessonStart(lessonId) {
        const user = Auth.getUser();
        if (!user) {
            console.log('ContentLoader: No authenticated user, skipping progress tracking');
            return;
        }

        try {
            await API.post('/progress/lesson/start', {
                lesson_id: lessonId
            });
            console.log('ContentLoader: Lesson start tracked');
        } catch (error) {
            console.warn('ContentLoader: Could not track lesson start:', error);
        }
    },

    /**
     * Track lesson progress (complete)
     * @param {number} lessonId - Lesson ID
     */
    async trackLessonComplete(lessonId) {
        const user = Auth.getUser();
        if (!user) {
            console.log('ContentLoader: No authenticated user, skipping progress tracking');
            return;
        }

        try {
            await API.post('/progress/lesson/complete', {
                lesson_id: lessonId
            });
            console.log('ContentLoader: Lesson completion tracked');
        } catch (error) {
            console.warn('ContentLoader: Could not track lesson completion:', error);
        }
    },

    /**
     * Submit quiz attempt
     * @param {number} quizId - Quiz ID
     * @param {Array} answers - Array of answer objects {question_id, selected_answer}
     * @returns {Promise<object>} Quiz results
     */
    async submitQuiz(quizId, answers) {
        const user = Auth.getUser();
        if (!user) {
            // For unauthenticated users, calculate score locally
            return this.calculateLocalScore(answers);
        }

        try {
            const response = await API.post('/progress/quiz/submit', {
                quiz_id: quizId,
                answers: answers
            });
            return response.data;
        } catch (error) {
            console.error('ContentLoader: Error submitting quiz:', error);
            // Fallback to local scoring
            return this.calculateLocalScore(answers);
        }
    },

    /**
     * Calculate quiz score locally (for unauthenticated users)
     * @param {Array} answers - Array of answer objects
     * @returns {object} Quiz results
     */
    calculateLocalScore(answers) {
        const quizData = window.quizData || [];
        let correctCount = 0;
        let totalPoints = 0;
        let earnedPoints = 0;

        const results = answers.map(answer => {
            const question = quizData.find(q => q.id === answer.question_id);
            if (!question) return null;

            totalPoints += question.points;
            const isCorrect = question.correctAnswer === answer.selected_answer;

            if (isCorrect) {
                correctCount++;
                earnedPoints += question.points;
            }

            return {
                question_id: answer.question_id,
                is_correct: isCorrect,
                explanation: question.explanation
            };
        }).filter(r => r !== null);

        const scorePercentage = totalPoints > 0 ? (earnedPoints / totalPoints) * 100 : 0;
        const passingScore = window.currentQuiz?.passing_score || 70;

        return {
            score: Math.round(scorePercentage),
            correct_count: correctCount,
            total_questions: quizData.length,
            passed: scorePercentage >= passingScore,
            results: results
        };
    },

    /**
     * Get URL parameter
     * @param {string} param - Parameter name
     * @returns {string|null} Parameter value
     */
    getUrlParam(param) {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(param);
    },

    /**
     * Show loading state
     * @param {string} selector - Container selector
     */
    showLoading(selector) {
        const container = document.querySelector(selector);
        if (container) {
            container.innerHTML = '<div class="loading-spinner">Loading content...</div>';
        }
    },

    /**
     * Show error message
     * @param {string} selector - Container selector
     * @param {string} message - Error message
     */
    showError(selector, message) {
        const container = document.querySelector(selector);
        if (container) {
            container.innerHTML = `
                <div class="error-state">
                    <div class="error-icon">⚠️</div>
                    <h2>Content Not Available</h2>
                    <p>${this.escapeHtml(message)}</p>
                    <p>This content may not have been published yet.</p>
                    <a href="index.html" class="btn-primary">Return to Home</a>
                </div>
            `;
        }
    },

    /**
     * Escape HTML to prevent XSS
     * @param {string} text - Text to escape
     * @returns {string} Escaped text
     */
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Make available globally
window.ContentLoader = ContentLoader;
