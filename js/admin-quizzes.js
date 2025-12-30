/**
 * Admin Quiz Management
 * Handles CRUD operations for quizzes and quiz questions
 */

const AdminQuizzes = {
    quizzes: [],
    modules: [],
    courses: [],
    currentQuizId: null,
    currentQuestionId: null,
    selectedModuleId: null,
    currentQuizQuestions: [],

    /**
     * Initialize the quiz management interface
     */
    async init() {
        console.log('AdminQuizzes: Initializing...');

        // Ensure user is authenticated and has admin role
        const user = Auth.getUser();
        if (!user || user.role !== 'admin') {
            console.error('AdminQuizzes: Unauthorized access');
            window.location.href = '403.html';
            return;
        }

        // Load courses and modules first
        await this.loadCourses();
        await this.loadModules();

        // Load quizzes
        await this.loadQuizzes();

        // Set up event listeners
        this.setupEventListeners();

        console.log('AdminQuizzes: Initialization complete');
    },

    /**
     * Load courses
     */
    async loadCourses() {
        try {
            const response = await API.get('/courses?published=false');
            this.courses = response.data?.items || [];
        } catch (error) {
            console.error('AdminQuizzes: Error loading courses:', error);
        }
    },

    /**
     * Load modules for filter dropdown
     */
    async loadModules() {
        try {
            const response = await API.get('/modules');
            this.modules = response.data?.items || [];

            // Populate module dropdowns
            this.populateModuleDropdowns();

            // Set first module as default filter if available
            if (this.modules.length > 0) {
                this.selectedModuleId = this.modules[0].id;
                document.getElementById('filter-module').value = this.selectedModuleId;
            }
        } catch (error) {
            console.error('AdminQuizzes: Error loading modules:', error);
            this.showError('Failed to load modules: ' + error.message);
        }
    },

    /**
     * Populate module dropdown selects
     */
    populateModuleDropdowns() {
        const filterDropdown = document.getElementById('filter-module');
        const formDropdown = document.getElementById('quiz-module');

        let options = '<option value="">All Modules</option>';
        let formOptions = '<option value="">Select a module...</option>';

        this.modules.forEach(module => {
            const course = this.courses.find(c => c.id === module.course_id);
            const label = course
                ? `${this.escapeHtml(course.title)} - ${this.escapeHtml(module.title)}`
                : this.escapeHtml(module.title);

            options += `<option value="${module.id}">${label}</option>`;
            formOptions += `<option value="${module.id}">${label}</option>`;
        });

        filterDropdown.innerHTML = options;
        formDropdown.innerHTML = formOptions;
    },

    /**
     * Load quizzes from API
     */
    async loadQuizzes() {
        try {
            const moduleParam = this.selectedModuleId ? `?module_id=${this.selectedModuleId}` : '';
            const response = await API.get(`/quizzes${moduleParam}`);
            this.quizzes = response.data?.items || [];

            this.renderQuizzes();
        } catch (error) {
            console.error('AdminQuizzes: Error loading quizzes:', error);
            this.showError('Failed to load quizzes: ' + error.message);
        }
    },

    /**
     * Render quizzes list
     */
    renderQuizzes() {
        const container = document.getElementById('quizzes-list');
        if (!container) return;

        if (this.quizzes.length === 0) {
            container.innerHTML = this.getEmptyState();
            return;
        }

        let html = '<div class="quiz-questions-list">';

        this.quizzes.forEach(quiz => {
            const module = this.modules.find(m => m.id === quiz.module_id);
            const course = module ? this.courses.find(c => c.id === module.course_id) : null;

            const statusBadge = quiz.is_published
                ? '<span class="status-badge published">Published</span>'
                : '<span class="status-badge draft">Draft</span>';

            html += `
                <div class="question-item">
                    <div class="question-header">
                        <div class="question-text">
                            <h3 style="margin: 0 0 0.5rem 0;">${this.escapeHtml(quiz.title)}</h3>
                            ${quiz.description ? `<p style="color: #666; margin: 0;">${this.escapeHtml(quiz.description)}</p>` : ''}
                        </div>
                        <div>
                            ${statusBadge}
                        </div>
                    </div>
                    <div class="module-meta" style="margin: 1rem 0;">
                        ${course ? `<span><i class="fas fa-book"></i> ${this.escapeHtml(course.title)}</span>` : ''}
                        ${module ? `<span><i class="fas fa-layer-group"></i> ${this.escapeHtml(module.title)}</span>` : ''}
                        <span><i class="fas fa-question"></i> ${quiz.question_count || 0} questions</span>
                        <span><i class="fas fa-check-circle"></i> Pass: ${quiz.passing_score}%</span>
                        ${quiz.time_limit_minutes ? `<span><i class="fas fa-clock"></i> ${quiz.time_limit_minutes} min</span>` : ''}
                        <span><i class="fas fa-redo"></i> ${quiz.max_attempts} attempts</span>
                    </div>
                    <div class="lesson-actions" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e0e0e0;">
                        <button class="action-btn edit" onclick="AdminQuizzes.manageQuestions(${quiz.id})" title="Manage Questions">
                            <i class="fas fa-list"></i> Questions
                        </button>
                        <button class="action-btn view" onclick="AdminQuizzes.viewQuiz(${quiz.id})" title="View">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="action-btn edit" onclick="AdminQuizzes.editQuiz(${quiz.id})" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="action-btn ${quiz.is_published ? 'unpublish' : 'publish'}"
                                onclick="AdminQuizzes.togglePublish(${quiz.id})"
                                title="${quiz.is_published ? 'Unpublish' : 'Publish'}">
                            <i class="fas fa-${quiz.is_published ? 'eye-slash' : 'check'}"></i>
                        </button>
                        <button class="action-btn delete" onclick="AdminQuizzes.deleteQuiz(${quiz.id})" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        });

        html += '</div>';
        container.innerHTML = html;
    },

    /**
     * Show empty state
     */
    getEmptyState() {
        return `
            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-question-circle"></i></div>
                <h3>No Quizzes Found</h3>
                <p>Create your first quiz to assess student knowledge.</p>
                <button class="btn-primary" onclick="AdminQuizzes.showCreateModal()">
                    <i class="fas fa-plus"></i> Create Quiz
                </button>
            </div>
        `;
    },

    /**
     * Show create quiz modal
     */
    showCreateModal() {
        this.currentQuizId = null;
        document.getElementById('modal-title').textContent = 'Create Quiz';
        document.getElementById('quiz-form').reset();
        document.getElementById('quiz-id').value = '';

        // Set default module if filtered
        if (this.selectedModuleId) {
            document.getElementById('quiz-module').value = this.selectedModuleId;
        }

        this.showModal('quiz-modal');
    },

    /**
     * View quiz details
     */
    async viewQuiz(quizId) {
        try {
            const response = await API.get(`/quizzes/${quizId}`);
            const quiz = response.data;

            const module = this.modules.find(m => m.id === quiz.module_id);
            const course = module ? this.courses.find(c => c.id === module.course_id) : null;

            const details = `
                Quiz Details:

                Title: ${quiz.title}
                Course: ${course?.title || 'Unknown'}
                Module: ${module?.title || 'Unknown'}
                Description: ${quiz.description || 'N/A'}
                Passing Score: ${quiz.passing_score}%
                Time Limit: ${quiz.time_limit_minutes ? quiz.time_limit_minutes + ' min' : 'No limit'}
                Max Attempts: ${quiz.max_attempts}
                Status: ${quiz.is_published ? 'Published' : 'Draft'}
                Questions: ${quiz.questions?.length || 0}
                Created: ${this.formatDate(quiz.created_at)}
            `;

            alert(details);
        } catch (error) {
            this.showError('Failed to load quiz details: ' + error.message);
        }
    },

    /**
     * Edit quiz
     */
    async editQuiz(quizId) {
        try {
            const response = await API.get(`/quizzes/${quizId}`);
            const quiz = response.data;

            this.currentQuizId = quizId;
            document.getElementById('modal-title').textContent = 'Edit Quiz';

            // Populate form
            document.getElementById('quiz-id').value = quiz.id;
            document.getElementById('quiz-module').value = quiz.module_id;
            document.getElementById('quiz-title').value = quiz.title;
            document.getElementById('quiz-description').value = quiz.description || '';
            document.getElementById('quiz-passing-score').value = quiz.passing_score;
            document.getElementById('quiz-time-limit').value = quiz.time_limit_minutes || '';
            document.getElementById('quiz-max-attempts').value = quiz.max_attempts;
            document.getElementById('quiz-published').checked = quiz.is_published;

            this.showModal('quiz-modal');
        } catch (error) {
            this.showError('Failed to load quiz: ' + error.message);
        }
    },

    /**
     * Delete quiz
     */
    async deleteQuiz(quizId) {
        const quiz = this.quizzes.find(q => q.id === quizId);
        if (!quiz) return;

        const confirmed = confirm(
            `Are you sure you want to delete "${quiz.title}"?\n\n` +
            `This will also delete all quiz questions.\n` +
            `This action cannot be undone.`
        );

        if (!confirmed) return;

        try {
            await API.delete(`/quizzes/${quizId}`);
            alert('Quiz deleted successfully!');
            await this.loadQuizzes();
        } catch (error) {
            this.showError('Failed to delete quiz: ' + error.message);
        }
    },

    /**
     * Toggle publish status
     */
    async togglePublish(quizId) {
        const quiz = this.quizzes.find(q => q.id === quizId);
        if (!quiz) return;

        try {
            const newStatus = !quiz.is_published;
            await API.put(`/quizzes/${quizId}`, {
                is_published: newStatus
            });

            alert(`Quiz ${newStatus ? 'published' : 'unpublished'} successfully!`);
            await this.loadQuizzes();
        } catch (error) {
            this.showError('Failed to update quiz status: ' + error.message);
        }
    },

    /**
     * Save quiz (create or update)
     */
    async saveQuiz(event) {
        event.preventDefault();

        const form = document.getElementById('quiz-form');
        const formData = new FormData(form);

        const quizData = {
            module_id: parseInt(formData.get('module_id')),
            title: formData.get('title'),
            description: formData.get('description') || null,
            passing_score: parseInt(formData.get('passing_score')),
            time_limit_minutes: formData.get('time_limit_minutes')
                ? parseInt(formData.get('time_limit_minutes'))
                : null,
            max_attempts: parseInt(formData.get('max_attempts')),
            is_published: document.getElementById('quiz-published').checked
        };

        try {
            if (this.currentQuizId) {
                // Update existing quiz
                await API.put(`/quizzes/${this.currentQuizId}`, quizData);
                alert('Quiz updated successfully!');
            } else {
                // Create new quiz
                const response = await API.post('/quizzes', quizData);
                const newQuiz = response.data;
                alert('Quiz created successfully! Now add questions.');

                // Open question manager for new quiz
                this.hideModal('quiz-modal');
                await this.loadQuizzes();
                this.manageQuestions(newQuiz.id);
                return;
            }

            this.hideModal('quiz-modal');
            await this.loadQuizzes();
        } catch (error) {
            this.showError('Failed to save quiz: ' + error.message);
        }
    },

    /**
     * Manage quiz questions
     */
    async manageQuestions(quizId) {
        try {
            this.currentQuizId = quizId;
            const response = await API.get(`/quizzes/${quizId}`);
            const quiz = response.data;
            this.currentQuizQuestions = quiz.questions || [];

            const questionList = this.currentQuizQuestions.map((q, idx) => {
                const options = JSON.parse(q.options);
                return `
                    ${idx + 1}. ${this.escapeHtml(q.question)}
                    ${options.map((opt, i) => `   ${i === q.correct_answer ? '✓' : '○'} ${this.escapeHtml(opt)}`).join('\n')}
                `;
            }).join('\n\n');

            const action = confirm(
                `Questions for: ${quiz.title}\n\n` +
                `Current questions (${this.currentQuizQuestions.length}):\n\n` +
                `${questionList || 'No questions yet'}\n\n` +
                `Click OK to add a new question, or Cancel to close.`
            );

            if (action) {
                this.showAddQuestionModal(quizId);
            }
        } catch (error) {
            this.showError('Failed to load quiz questions: ' + error.message);
        }
    },

    /**
     * Show add question modal
     */
    showAddQuestionModal(quizId) {
        this.currentQuestionId = null;
        document.getElementById('question-modal-title').textContent = 'Add Question';
        document.getElementById('question-form').reset();
        document.getElementById('question-id').value = '';
        document.getElementById('question-quiz-id').value = quizId;

        this.showModal('question-modal');
    },

    /**
     * Save question
     */
    async saveQuestion(event) {
        event.preventDefault();

        const form = document.getElementById('question-form');
        const formData = new FormData(form);

        // Collect options
        const optionInputs = document.querySelectorAll('.option-input');
        const options = Array.from(optionInputs).map(input => input.value.trim());

        // Get correct answer index
        const correctAnswer = parseInt(formData.get('correct_option'));

        const questionData = {
            quiz_id: parseInt(formData.get('quiz_id')),
            question: formData.get('question'),
            options: JSON.stringify(options),
            correct_answer: correctAnswer,
            explanation: formData.get('explanation') || null,
            points: parseInt(formData.get('points'))
        };

        try {
            if (this.currentQuestionId) {
                // Update existing question
                await API.put(`/quiz-questions/${this.currentQuestionId}`, questionData);
                alert('Question updated successfully!');
            } else {
                // Create new question
                await API.post('/quiz-questions', questionData);
                alert('Question added successfully!');
            }

            this.hideModal('question-modal');

            // Ask if user wants to add another question
            const addAnother = confirm('Question saved! Add another question?');
            if (addAnother) {
                this.showAddQuestionModal(questionData.quiz_id);
            } else {
                await this.loadQuizzes();
            }
        } catch (error) {
            this.showError('Failed to save question: ' + error.message);
        }
    },

    /**
     * Show modal
     */
    showModal(modalId) {
        document.getElementById(modalId).style.display = 'flex';
    },

    /**
     * Hide modal
     */
    hideModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
        if (modalId === 'quiz-modal') {
            document.getElementById('quiz-form').reset();
            this.currentQuizId = null;
        } else if (modalId === 'question-modal') {
            document.getElementById('question-form').reset();
            this.currentQuestionId = null;
        }
    },

    /**
     * Apply filters
     */
    applyFilters() {
        this.selectedModuleId = document.getElementById('filter-module').value
            ? parseInt(document.getElementById('filter-module').value)
            : null;
        this.loadQuizzes();
    },

    /**
     * Set up event listeners
     */
    setupEventListeners() {
        // Create quiz button
        document.getElementById('create-quiz-btn').addEventListener('click', () => this.showCreateModal());

        // Close modals
        document.getElementById('close-modal').addEventListener('click', () => this.hideModal('quiz-modal'));
        document.getElementById('cancel-btn').addEventListener('click', () => this.hideModal('quiz-modal'));
        document.getElementById('close-question-modal').addEventListener('click', () => this.hideModal('question-modal'));
        document.getElementById('cancel-question-btn').addEventListener('click', () => this.hideModal('question-modal'));

        // Close modals on outside click
        window.addEventListener('click', (event) => {
            if (event.target.id === 'quiz-modal') {
                this.hideModal('quiz-modal');
            } else if (event.target.id === 'question-modal') {
                this.hideModal('question-modal');
            }
        });

        // Form submissions
        document.getElementById('quiz-form').addEventListener('submit', (e) => this.saveQuiz(e));
        document.getElementById('question-form').addEventListener('submit', (e) => this.saveQuestion(e));

        // Module filter
        document.getElementById('filter-module').addEventListener('change', () => this.applyFilters());
    },

    /**
     * Show error message
     */
    showError(message) {
        alert('Error: ' + message);
    },

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    /**
     * Format date for display
     */
    formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return 'Invalid Date';
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }
};

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => AdminQuizzes.init());
} else {
    AdminQuizzes.init();
}
