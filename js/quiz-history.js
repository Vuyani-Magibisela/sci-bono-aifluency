/**
 * Quiz History Module (Phase 5D Priority 3)
 * Displays student's quiz attempt history with filtering and review
 */

(async function() {
    'use strict';

    let allAttempts = [];
    let filteredAttempts = [];
    let modules = [];

    // Initialize page
    async function init() {
        // Check authentication
        if (!Auth.isAuthenticated()) {
            window.location.href = 'login.html?redirect=' + encodeURIComponent(window.location.pathname);
            return;
        }

        try {
            // Load modules for filter
            await loadModules();

            // Load quiz attempts
            await loadQuizAttempts();

            // Setup filter event listeners
            setupFilters();

        } catch (error) {
            console.error('Error initializing quiz history:', error);
            showError('Failed to load quiz history. Please try again later.');
        }
    }

    /**
     * Load all modules for filter dropdown
     */
    async function loadModules() {
        try {
            const response = await API.get('/courses/1/modules');
            modules = response.data || [];

            // Populate module filter
            const moduleFilter = document.getElementById('module-filter');
            modules.forEach(module => {
                const option = document.createElement('option');
                option.value = module.id;
                option.textContent = module.title;
                moduleFilter.appendChild(option);
            });
        } catch (error) {
            console.error('Error loading modules:', error);
        }
    }

    /**
     * Load quiz attempts for current user
     */
    async function loadQuizAttempts() {
        try {
            const currentUser = Auth.getCurrentUser();
            if (!currentUser) {
                throw new Error('User not authenticated');
            }

            // Get quiz attempts from API
            const response = await API.get(`/progress/quiz-attempts`);
            allAttempts = response.data?.items || [];

            // Enrich attempts with quiz and module data
            await enrichAttempts();

            // Apply filters and render
            applyFilters();

        } catch (error) {
            console.error('Error loading quiz attempts:', error);
            throw error;
        }
    }

    /**
     * Enrich attempts with quiz and module data
     */
    async function enrichAttempts() {
        for (let attempt of allAttempts) {
            try {
                // Get quiz details
                const quizResponse = await API.get(`/quizzes/${attempt.quiz_id}`);
                attempt.quizData = quizResponse.data;

                // Get module details
                if (attempt.quizData.module_id) {
                    const moduleResponse = await API.get(`/modules/${attempt.quizData.module_id}`);
                    attempt.moduleData = moduleResponse.data;
                }
            } catch (error) {
                console.error(`Error enriching attempt ${attempt.id}:`, error);
            }
        }
    }

    /**
     * Setup filter event listeners
     */
    function setupFilters() {
        document.getElementById('module-filter').addEventListener('change', applyFilters);
        document.getElementById('status-filter').addEventListener('change', applyFilters);
        document.getElementById('sort-filter').addEventListener('change', applyFilters);
    }

    /**
     * Apply filters to quiz attempts
     */
    function applyFilters() {
        const moduleFilter = document.getElementById('module-filter').value;
        const statusFilter = document.getElementById('status-filter').value;
        const sortFilter = document.getElementById('sort-filter').value;

        // Filter attempts
        filteredAttempts = allAttempts.filter(attempt => {
            // Module filter
            if (moduleFilter !== 'all' && attempt.quizData?.module_id !== parseInt(moduleFilter)) {
                return false;
            }

            // Status filter
            if (statusFilter !== 'all') {
                const passed = attempt.score >= (attempt.quizData?.passing_score || 70);
                if (statusFilter === 'passed' && !passed) return false;
                if (statusFilter === 'failed' && passed) return false;
            }

            return true;
        });

        // Sort attempts
        filteredAttempts.sort((a, b) => {
            switch (sortFilter) {
                case 'recent':
                    return new Date(b.completed_at) - new Date(a.completed_at);
                case 'oldest':
                    return new Date(a.completed_at) - new Date(b.completed_at);
                case 'highest':
                    return b.score - a.score;
                case 'lowest':
                    return a.score - b.score;
                default:
                    return 0;
            }
        });

        // Render filtered attempts
        renderAttempts();
    }

    /**
     * Render quiz attempts
     */
    function renderAttempts() {
        const container = document.getElementById('quiz-attempts-list');

        if (filteredAttempts.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-clipboard-list"></i>
                    <h2>No Quiz Attempts Found</h2>
                    <p>${allAttempts.length === 0
                        ? "You haven't taken any quizzes yet. Start learning and test your knowledge!"
                        : "No attempts match your current filters. Try adjusting your filters."}</p>
                    ${allAttempts.length === 0
                        ? '<a href="aifluencystart.html" class="btn-primary">Browse Courses</a>'
                        : ''}
                </div>
            `;
            return;
        }

        container.innerHTML = filteredAttempts.map(attempt => renderAttemptCard(attempt)).join('');
    }

    /**
     * Render single attempt card
     * @param {object} attempt - Quiz attempt data
     * @returns {string} HTML string
     */
    function renderAttemptCard(attempt) {
        const quizTitle = attempt.quizData?.title || 'Unknown Quiz';
        const moduleTitle = attempt.moduleData?.title || 'Unknown Module';
        const score = attempt.score || 0;
        const passingScore = attempt.quizData?.passing_score || 70;
        const passed = score >= passingScore;
        const completedDate = new Date(attempt.completed_at).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });

        // Calculate time spent
        const timeSpent = attempt.time_spent_seconds
            ? formatTime(attempt.time_spent_seconds)
            : 'N/A';

        // Calculate questions answered
        const totalQuestions = attempt.total_questions || 0;
        const correctAnswers = Math.round((score / 100) * totalQuestions);

        return `
            <div class="quiz-attempt-card">
                <div class="attempt-header">
                    <div>
                        <div class="attempt-title">${escapeHtml(quizTitle)}</div>
                        <div class="attempt-date">
                            <i class="fas fa-book"></i> ${escapeHtml(moduleTitle)} •
                            <i class="fas fa-calendar"></i> ${completedDate}
                        </div>
                    </div>
                    <div class="stat-value ${passed ? 'passed' : 'failed'}">
                        ${passed ? '<i class="fas fa-check-circle"></i> Passed' : '<i class="fas fa-times-circle"></i> Failed'}
                    </div>
                </div>

                <div class="attempt-stats">
                    <div class="stat-item">
                        <div class="stat-value ${passed ? 'passed' : 'failed'}">${score}%</div>
                        <div class="stat-label">Score</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">${correctAnswers}/${totalQuestions}</div>
                        <div class="stat-label">Correct Answers</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">${timeSpent}</div>
                        <div class="stat-label">Time Spent</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">${passingScore}%</div>
                        <div class="stat-label">Passing Score</div>
                    </div>
                </div>

                <div class="attempt-actions">
                    <a href="#" class="btn-review" onclick="viewAttemptDetails(${attempt.id}); return false;">
                        <i class="fas fa-eye"></i> Review Answers
                    </a>
                    ${!passed || true ? `
                        <a href="quiz-dynamic.html?module_id=${attempt.quizData?.module_id || ''}" class="btn-retake">
                            <i class="fas fa-redo"></i> Retake Quiz
                        </a>
                    ` : ''}
                </div>
            </div>
        `;
    }

    /**
     * Format time in seconds to readable format
     * @param {number} seconds - Time in seconds
     * @returns {string} Formatted time (e.g., "5m 30s")
     */
    function formatTime(seconds) {
        const minutes = Math.floor(seconds / 60);
        const secs = seconds % 60;
        if (minutes > 0) {
            return `${minutes}m ${secs}s`;
        }
        return `${secs}s`;
    }

    /**
     * Escape HTML to prevent XSS
     * @param {string} text - Text to escape
     * @returns {string} Escaped text
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Show error message
     * @param {string} message - Error message
     */
    function showError(message) {
        const container = document.getElementById('quiz-attempts-list');
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-exclamation-triangle" style="color: var(--accent-color);"></i>
                <h2>Error</h2>
                <p>${escapeHtml(message)}</p>
                <button class="btn-primary" onclick="location.reload()">Retry</button>
            </div>
        `;
    }

    /**
     * View attempt details (review mode)
     * @param {number} attemptId - Attempt ID
     */
    window.viewAttemptDetails = async function(attemptId) {
        // TODO: Implement review mode in Phase 5D
        // For now, show a coming soon message
        alert('Review mode coming soon! You will be able to see all questions and answers from this attempt.');

        // Future implementation:
        // window.location.href = `quiz-review.html?attempt_id=${attemptId}`;
    };

    // Initialize when DOM is ready
    init();
})();
