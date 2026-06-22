/**
 * Quiz History Module (Phase 5D Priority 3)
 * Displays student's quiz attempt history with filtering and review
 */

(async function() {
    'use strict';

    let allAttempts = [];
    let filteredAttempts = [];

    // Initialize page
    async function init() {
        // Check authentication
        if (!Auth.isAuthenticated()) {
            window.location.href = '/login.html?redirect=' + encodeURIComponent(window.location.pathname);
            return;
        }

        try {
            // Load quiz attempts (a single call returns everything we need:
            // quiz/module/course titles, passing_score, module_id)
            await loadQuizAttempts();

            // Populate the module filter dropdown from the loaded attempts
            populateModuleFilter();

            // Setup filter event listeners
            setupFilters();

        } catch (error) {
            console.error('Error initializing quiz history:', error);
            showError('Failed to load quiz history. Please try again later.');
        }
    }

    /**
     * Load quiz attempts for current user
     */
    async function loadQuizAttempts() {
        // /quizzes/attempts/recent reads the user from the JWT and joins quiz,
        // module, lesson, and course titles in one shot.
        const response = await API.get('/quizzes/attempts/recent?limit=100');
        allAttempts = Array.isArray(response.data) ? response.data : [];

        // Apply filters and render
        applyFilters();
    }

    /**
     * Populate the module filter dropdown from modules that appear in the attempts.
     */
    function populateModuleFilter() {
        const moduleFilter = document.getElementById('module-filter');
        if (!moduleFilter) return;

        const seen = new Map();
        allAttempts.forEach(a => {
            if (a.module_id && a.module_title && !seen.has(a.module_id)) {
                seen.set(a.module_id, a.module_title);
            }
        });

        seen.forEach((title, id) => {
            const option = document.createElement('option');
            option.value = String(id);
            option.textContent = title;
            moduleFilter.appendChild(option);
        });
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
            if (moduleFilter !== 'all' && parseInt(attempt.module_id) !== parseInt(moduleFilter)) {
                return false;
            }

            // Status filter
            if (statusFilter !== 'all') {
                const passed = parseFloat(attempt.score) >= parseFloat(attempt.passing_score || 70);
                if (statusFilter === 'passed' && !passed) return false;
                if (statusFilter === 'failed' && passed) return false;
            }

            return true;
        });

        // Sort attempts (time_completed is the canonical submission timestamp)
        filteredAttempts.sort((a, b) => {
            switch (sortFilter) {
                case 'recent':
                    return new Date(b.time_completed) - new Date(a.time_completed);
                case 'oldest':
                    return new Date(a.time_completed) - new Date(b.time_completed);
                case 'highest':
                    return parseFloat(b.score) - parseFloat(a.score);
                case 'lowest':
                    return parseFloat(a.score) - parseFloat(b.score);
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
        const quizTitle = attempt.quiz_title || 'Unknown Quiz';
        const moduleTitle = attempt.module_title || attempt.lesson_title || 'Unknown Module';
        const score = parseFloat(attempt.score) || 0;
        const passingScore = parseFloat(attempt.passing_score) || 70;
        const passed = score >= passingScore;

        const completedDate = attempt.time_completed
            ? new Date(attempt.time_completed).toLocaleString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            })
            : '—';

        // Time spent: prefer canonical seconds, fall back to historical minutes column.
        let timeSpent = 'N/A';
        if (attempt.time_spent_seconds) {
            timeSpent = formatTime(parseInt(attempt.time_spent_seconds, 10));
        } else if (attempt.time_taken_minutes) {
            timeSpent = formatTime(parseInt(attempt.time_taken_minutes, 10) * 60);
        }

        const totalQuestions = parseInt(attempt.total_questions, 10) || 0;
        const correctAnswers = parseInt(attempt.correct_answers, 10)
            || Math.round((score / 100) * totalQuestions);

        const moduleIdParam = attempt.module_id ? `?module_id=${attempt.module_id}` : '';
        const scoreDisplay = Number.isInteger(score) ? score : score.toFixed(1);

        return `
            <div class="quiz-attempt-card">
                <div class="attempt-header">
                    <div>
                        <div class="attempt-title">${Utils.escapeHtml(quizTitle)}</div>
                        <div class="attempt-date">
                            <i class="fas fa-book"></i> ${Utils.escapeHtml(moduleTitle)} &bull;
                            <i class="fas fa-calendar"></i> ${Utils.escapeHtml(completedDate)}
                        </div>
                    </div>
                    <div class="stat-value ${passed ? 'passed' : 'failed'}">
                        ${passed ? '<i class="fas fa-check-circle"></i> Passed' : '<i class="fas fa-times-circle"></i> Failed'}
                    </div>
                </div>

                <div class="attempt-stats">
                    <div class="stat-item">
                        <div class="stat-value ${passed ? 'passed' : 'failed'}">${scoreDisplay}%</div>
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
                    <a href="quiz-dynamic.html${moduleIdParam}" class="btn-retake">
                        <i class="fas fa-redo"></i> Retake Quiz
                    </a>
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

    // escapeHtml moved to Utils.js (Phase 11 refactoring)

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
                <p>${Utils.escapeHtml(message)}</p>
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
