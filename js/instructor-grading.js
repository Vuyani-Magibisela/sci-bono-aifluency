/**
 * Instructor Grading Management
 * Phase B: Complete Core Features
 */

const InstructorGrading = {
    currentAttempt: null,
    pendingQueue: [],
    quizzes: [],

    /**
     * Initialize grading page
     */
    async init() {
        // Load quizzes for filter
        await this.loadQuizzes();

        // Load pending grading queue
        await this.loadPendingQueue();

        // Setup event listeners
        this.setupEventListeners();
    },

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Filter changes
        document.getElementById('quiz-filter').addEventListener('change', () => {
            this.loadPendingQueue();
        });

        document.getElementById('status-filter').addEventListener('change', () => {
            this.loadPendingQueue();
        });

        // Refresh button
        document.getElementById('refresh-btn').addEventListener('click', () => {
            this.loadPendingQueue();
        });

        // Submit grade button
        document.getElementById('submit-grade-btn').addEventListener('click', () => {
            this.submitGrade();
        });

        // Feedback character count
        document.getElementById('instructor-feedback').addEventListener('input', (e) => {
            document.getElementById('feedback-char-count').textContent = e.target.value.length;
        });
    },

    /**
     * Load quizzes for filter dropdown
     */
    async loadQuizzes() {
        try {
            const response = await apiRequest('/api/quizzes');

            if (response.success) {
                this.quizzes = response.quizzes || [];
                this.renderQuizFilter();
            }
        } catch (error) {
            console.error('Failed to load quizzes:', error);
        }
    },

    /**
     * Render quiz filter dropdown
     */
    renderQuizFilter() {
        const select = document.getElementById('quiz-filter');

        // Clear existing options (except "All Quizzes")
        while (select.options.length > 1) {
            select.remove(1);
        }

        // Add quiz options
        this.quizzes.forEach(quiz => {
            const option = document.createElement('option');
            option.value = quiz.id;
            option.textContent = quiz.title;
            select.appendChild(option);
        });
    },

    /**
     * Load pending grading queue
     */
    async loadPendingQueue() {
        const loadingSpinner = document.getElementById('loading-spinner');
        const emptyState = document.getElementById('empty-state');
        const queueList = document.getElementById('grading-queue-list');

        loadingSpinner.style.display = 'block';
        emptyState.style.display = 'none';
        queueList.innerHTML = '';

        try {
            const quizId = document.getElementById('quiz-filter').value;
            const status = document.getElementById('status-filter').value;

            let url = '/api/grading/pending?limit=100';
            if (quizId) {
                url += `&quiz_id=${quizId}`;
            }

            const response = await apiRequest(url);

            if (response.success) {
                this.pendingQueue = response.attempts || [];

                // Filter by status if needed (frontend filter for now)
                if (status) {
                    this.pendingQueue = this.pendingQueue.filter(a => a.status === status);
                }

                loadingSpinner.style.display = 'none';

                if (this.pendingQueue.length === 0) {
                    emptyState.style.display = 'block';
                } else {
                    this.renderQueue();
                }

                // Update pending count
                const pendingCount = this.pendingQueue.filter(a => a.status === 'submitted').length;
                document.getElementById('pending-number').textContent = pendingCount;
            }
        } catch (error) {
            loadingSpinner.style.display = 'none';
            showToast('Failed to load grading queue: ' + error.message, 'error');
        }
    },

    /**
     * Render grading queue
     */
    renderQueue() {
        const queueList = document.getElementById('grading-queue-list');
        queueList.innerHTML = '';

        if (this.pendingQueue.length === 0) {
            document.getElementById('empty-state').style.display = 'block';
            return;
        }

        const table = document.createElement('table');
        table.className = 'grading-table';
        table.innerHTML = `
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Quiz</th>
                    <th>Score</th>
                    <th>Time Spent</th>
                    <th>Submitted</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="queue-tbody"></tbody>
        `;

        queueList.appendChild(table);

        const tbody = document.getElementById('queue-tbody');

        this.pendingQueue.forEach(attempt => {
            const row = document.createElement('tr');
            row.className = attempt.status === 'submitted' ? 'pending-row' : '';

            const statusClass = this.getStatusClass(attempt.status);
            const scoreClass = attempt.score >= 70 ? 'pass' : 'fail';

            row.innerHTML = `
                <td>
                    <div class="student-cell">
                        <strong>${this.escapeHtml(attempt.user.name)}</strong>
                        <small>${this.escapeHtml(attempt.user.email)}</small>
                    </div>
                </td>
                <td>${this.escapeHtml(attempt.quiz.title)}</td>
                <td>
                    <span class="score-badge ${scoreClass}">
                        ${attempt.score}%
                    </span>
                </td>
                <td>${attempt.time_spent_formatted || 'N/A'}</td>
                <td>${this.formatDate(attempt.time_completed || attempt.created_at)}</td>
                <td>
                    <span class="status-badge ${statusClass}">
                        ${this.formatStatus(attempt.status)}
                    </span>
                </td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-primary" onclick="InstructorGrading.openGradingModal(${attempt.id})">
                            <i class="fas fa-edit"></i> Grade
                        </button>
                        <button class="btn btn-sm btn-secondary" onclick="InstructorGrading.viewAnalytics(${attempt.quiz_id})">
                            <i class="fas fa-chart-bar"></i>
                        </button>
                    </div>
                </td>
            `;

            tbody.appendChild(row);
        });
    },

    /**
     * Open grading modal
     */
    async openGradingModal(attemptId) {
        const attempt = this.pendingQueue.find(a => a.id === attemptId);
        if (!attempt) {
            showToast('Attempt not found', 'error');
            return;
        }

        this.currentAttempt = attempt;

        // Populate modal
        document.getElementById('modal-student-name').textContent = attempt.user.name;
        document.getElementById('modal-quiz-title').textContent = attempt.quiz.title;
        document.getElementById('modal-submitted-date').textContent = this.formatDate(attempt.time_completed);

        const scoreClass = attempt.score >= 70 ? 'pass' : 'fail';
        document.getElementById('modal-auto-score').textContent = `${attempt.score}%`;
        document.getElementById('modal-auto-score').className = `score-badge ${scoreClass}`;

        document.getElementById('modal-time-spent').textContent = attempt.time_spent_formatted || 'N/A';
        document.getElementById('modal-attempt-number').textContent = attempt.attempt_number || 1;

        // Pre-fill score with auto score
        document.getElementById('instructor-score').value = attempt.instructor_score || attempt.score;
        document.getElementById('instructor-feedback').value = attempt.instructor_feedback || '';
        document.getElementById('feedback-char-count').textContent = (attempt.instructor_feedback || '').length;

        // Load and display answers
        await this.loadAttemptDetails(attemptId);

        // Show modal
        document.getElementById('grading-modal').style.display = 'block';
    },

    /**
     * Load attempt details (questions and answers)
     */
    async loadAttemptDetails(attemptId) {
        const answersList = document.getElementById('modal-answers-list');
        answersList.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading answers...</div>';

        try {
            // Get attempt details with answers
            const response = await apiRequest(`/api/quiz-attempts/${attemptId}`);

            if (response.success && response.attempt) {
                const attempt = response.attempt;

                // If we have detailed answers, show them
                if (attempt.answers && typeof attempt.answers === 'object') {
                    this.renderAnswers(attempt.answers);
                } else {
                    answersList.innerHTML = '<p class="text-muted">Detailed answers not available for this attempt.</p>';
                }
            }
        } catch (error) {
            console.error('Failed to load attempt details:', error);
            answersList.innerHTML = '<p class="text-error">Failed to load answers.</p>';
        }
    },

    /**
     * Render student answers
     */
    renderAnswers(answers) {
        const answersList = document.getElementById('modal-answers-list');
        answersList.innerHTML = '';

        if (!answers || Object.keys(answers).length === 0) {
            answersList.innerHTML = '<p class="text-muted">No detailed answers available.</p>';
            return;
        }

        Object.entries(answers).forEach(([questionId, answer], index) => {
            const answerCard = document.createElement('div');
            answerCard.className = 'answer-card';

            const isCorrect = answer.correct || false;
            const correctClass = isCorrect ? 'correct' : 'incorrect';

            answerCard.innerHTML = `
                <div class="answer-header">
                    <h4>Question ${index + 1}</h4>
                    <span class="answer-status ${correctClass}">
                        <i class="fas fa-${isCorrect ? 'check-circle' : 'times-circle'}"></i>
                        ${isCorrect ? 'Correct' : 'Incorrect'}
                    </span>
                </div>
                <div class="answer-body">
                    <p class="question-text">${this.escapeHtml(answer.question || 'Question text not available')}</p>
                    <div class="answer-details">
                        <div class="answer-item">
                            <strong>Student Answer:</strong>
                            <p>${this.escapeHtml(answer.user_answer || answer.answer || 'N/A')}</p>
                        </div>
                        ${!isCorrect ? `
                            <div class="answer-item correct-answer">
                                <strong>Correct Answer:</strong>
                                <p>${this.escapeHtml(answer.correct_answer || 'N/A')}</p>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;

            answersList.appendChild(answerCard);
        });
    },

    /**
     * Submit grade
     */
    async submitGrade() {
        if (!this.currentAttempt) {
            showToast('No attempt selected', 'error');
            return;
        }

        const score = parseFloat(document.getElementById('instructor-score').value);
        const feedback = document.getElementById('instructor-feedback').value.trim();

        // Validation
        if (isNaN(score) || score < 0 || score > 100) {
            showToast('Please enter a valid score between 0 and 100', 'error');
            return;
        }

        const submitBtn = document.getElementById('submit-grade-btn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

        try {
            const response = await apiRequest(`/api/grading/${this.currentAttempt.id}`, {
                method: 'POST',
                body: JSON.stringify({
                    score: score,
                    feedback: feedback || null
                })
            });

            if (response.success) {
                showToast('Grade submitted successfully!', 'success');
                closeGradingModal();

                // Refresh queue
                await this.loadPendingQueue();
            } else {
                showToast('Failed to submit grade: ' + (response.message || 'Unknown error'), 'error');
            }
        } catch (error) {
            showToast('Failed to submit grade: ' + error.message, 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-check"></i> Submit Grade';
        }
    },

    /**
     * View quiz analytics
     */
    async viewAnalytics(quizId) {
        const modal = document.getElementById('analytics-modal');
        const content = document.getElementById('analytics-content');

        content.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading analytics...</div>';
        modal.style.display = 'block';

        try {
            const response = await apiRequest(`/api/grading/analytics/${quizId}`);

            if (response.success) {
                this.renderAnalytics(response);
            } else {
                content.innerHTML = '<p class="text-error">Failed to load analytics.</p>';
            }
        } catch (error) {
            content.innerHTML = '<p class="text-error">Failed to load analytics: ' + this.escapeHtml(error.message) + '</p>';
        }
    },

    /**
     * Render quiz analytics
     */
    renderAnalytics(data) {
        const content = document.getElementById('analytics-content');

        const stats = data.statistics;
        const dist = data.score_distribution;

        content.innerHTML = `
            <div class="analytics-header">
                <h3>${this.escapeHtml(data.quiz.title)}</h3>
                <p class="text-muted">Passing Score: ${data.quiz.passing_score}%</p>
            </div>

            <div class="analytics-stats">
                <div class="stat-card">
                    <div class="stat-value">${stats.total_attempts}</div>
                    <div class="stat-label">Total Attempts</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${stats.unique_students}</div>
                    <div class="stat-label">Unique Students</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${stats.average_score}%</div>
                    <div class="stat-label">Average Score</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${stats.pass_rate}%</div>
                    <div class="stat-label">Pass Rate</div>
                </div>
            </div>

            <div class="analytics-section">
                <h4>Score Distribution</h4>
                <div class="score-distribution">
                    ${Object.entries(dist).map(([range, count]) => `
                        <div class="distribution-bar">
                            <div class="bar-label">${range}%</div>
                            <div class="bar-container">
                                <div class="bar-fill" style="width: ${(count / stats.total_attempts * 100)}%">
                                    ${count}
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>

            <div class="analytics-section">
                <h4>Performance Metrics</h4>
                <table class="analytics-table">
                    <tr>
                        <td>Highest Score:</td>
                        <td><strong>${stats.highest_score}%</strong></td>
                    </tr>
                    <tr>
                        <td>Lowest Score:</td>
                        <td><strong>${stats.lowest_score}%</strong></td>
                    </tr>
                    <tr>
                        <td>Average Time:</td>
                        <td><strong>${stats.avg_time_formatted}</strong></td>
                    </tr>
                    <tr>
                        <td>Total Passed:</td>
                        <td><strong>${stats.total_passed}</strong></td>
                    </tr>
                    <tr>
                        <td>Total Failed:</td>
                        <td><strong>${stats.total_failed}</strong></td>
                    </tr>
                </table>
            </div>
        `;
    },

    /**
     * Helper: Get status class
     */
    getStatusClass(status) {
        const classes = {
            'submitted': 'status-pending',
            'graded': 'status-graded',
            'reviewed': 'status-reviewed',
            'in_progress': 'status-progress'
        };
        return classes[status] || 'status-default';
    },

    /**
     * Helper: Format status
     */
    formatStatus(status) {
        const labels = {
            'submitted': 'Pending',
            'graded': 'Graded',
            'reviewed': 'Reviewed',
            'in_progress': 'In Progress'
        };
        return labels[status] || status;
    },

    /**
     * Helper: Format date
     */
    formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    },

    /**
     * Helper: Escape HTML
     */
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};
