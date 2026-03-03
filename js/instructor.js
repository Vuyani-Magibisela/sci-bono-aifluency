/**
 * Instructor Dashboard Module
 * Handles instructor dashboard functionality including:
 * - Courses taught management
 * - Student enrollment statistics
 * - Grading queue (quizzes and projects)
 * - Course analytics
 */

const InstructorDashboard = {
    /**
     * Initialize the instructor dashboard
     */
    async init() {
        console.log('InstructorDashboard: Initializing...');

        // Ensure user is authenticated and has instructor role
        const user = Auth.getUser();
        if (!user) {
            console.error('InstructorDashboard: No authenticated user found');
            window.location.href = '/public/login.html';
            return;
        }

        if (!Auth.canManageContent()) {
            console.error('InstructorDashboard: User cannot manage content');
            window.location.href = '/public/403.html';
            return;
        }

        // Update welcome message and sidebar profile
        this.updateWelcomeMessage(user);
        this.updateSidebarProfile(user);

        // Load dashboard data
        await this.loadDashboardData();

        // Set up event listeners
        this.setupEventListeners();

        console.log('InstructorDashboard: Initialization complete');
    },

    /**
     * Update the welcome message with user's name
     */
    updateWelcomeMessage(user) {
        const welcomeElement = document.getElementById('welcome-message');
        if (welcomeElement) {
            const firstName = user.name ? user.name.split(' ')[0] : 'Instructor';
            welcomeElement.textContent = `Welcome, ${firstName}!`;
        }
    },

    /**
     * Update sidebar profile with actual user info
     */
    updateSidebarProfile(user) {
        const profileName = document.querySelector('.profile-name');
        if (profileName) {
            profileName.textContent = user.name || 'Instructor';
        }
        const profileRole = document.querySelector('.profile-role');
        if (profileRole) {
            profileRole.textContent = user.role === 'teacher' ? 'Instructor' : (user.role || 'Instructor');
        }
    },

    /**
     * Load all dashboard data
     */
    async loadDashboardData() {
        // Show loading state
        this.showLoadingState();

        try {
            // Load data in parallel
            const [courses, gradingQueue] = await Promise.all([
                this.loadMyCourses(),
                this.loadGradingQueue()
            ]);

            // Compute stats from loaded data
            const stats = this.computeStats(courses, gradingQueue);

            // Update UI with loaded data
            this.renderMyCourses(courses);
            this.renderGradingQueue(gradingQueue);
            this.renderInstructorStats(stats);

            // Hide loading state
            this.hideLoadingState();

        } catch (error) {
            console.error('InstructorDashboard: Error loading data:', error);
            this.showErrorState(error.message);
        }
    },

    /**
     * Load courses taught by this instructor
     */
    async loadMyCourses() {
        try {
            const user = Auth.getUser();
            // Use instructor_id filter if available, and include unpublished
            const endpoint = user
                ? `/courses?instructor_id=${user.id}&published=false`
                : '/courses';
            const response = await API.get(endpoint);
            const raw = response.data || {};
            // Response::paginated returns { items: [...], pagination: {...} }
            return Array.isArray(raw) ? raw : (raw.items || raw.data || []);
        } catch (error) {
            console.warn('InstructorDashboard: Could not load courses:', error);
            return [];
        }
    },

    /**
     * Load grading queue (pending quizzes and projects)
     */
    async loadGradingQueue() {
        let projects = [];
        let quizzes = [];

        // Load pending project submissions (endpoint may not exist yet)
        try {
            const projectsResponse = await API.get('/projects/submissions/pending');
            const raw = projectsResponse.data || [];
            projects = Array.isArray(raw) ? raw : (raw.items || []);
        } catch (error) {
            console.warn('InstructorDashboard: Could not load project submissions:', error);
        }

        // Load quiz attempts that need review (endpoint may not exist yet)
        try {
            const quizzesResponse = await API.get('/quizzes/attempts/pending-review');
            const raw = quizzesResponse.data || [];
            quizzes = Array.isArray(raw) ? raw : (raw.items || []);
        } catch (error) {
            console.warn('InstructorDashboard: Could not load quiz reviews:', error);
        }

        return {
            projects: projects,
            quizzes: quizzes,
            total: projects.length + quizzes.length
        };
    },

    /**
     * Compute instructor stats from loaded courses and grading data
     */
    computeStats(courses, gradingQueue) {
        const courseList = Array.isArray(courses) ? courses : [];
        const totalCourses = courseList.length;

        // Sum enrollment counts from courses
        let totalStudents = 0;
        let totalCompletion = 0;
        let coursesWithCompletion = 0;

        courseList.forEach(course => {
            totalStudents += (course.enrollment_count || 0);
            if (course.completion_rate !== undefined && course.completion_rate !== null) {
                totalCompletion += parseFloat(course.completion_rate) || 0;
                coursesWithCompletion++;
            }
        });

        const avgCompletion = coursesWithCompletion > 0
            ? Math.round(totalCompletion / coursesWithCompletion)
            : 0;

        return {
            total_courses: totalCourses,
            total_students: totalStudents,
            pending_grading: gradingQueue ? gradingQueue.total : 0,
            average_completion_rate: avgCompletion
        };
    },

    /**
     * Render courses taught by this instructor
     */
    renderMyCourses(courses) {
        const container = document.getElementById('my-courses');
        if (!container) return;

        if (!Array.isArray(courses)) courses = [];

        if (courses.length === 0) {
            container.innerHTML = this.getEmptyState(
                'No Courses Yet',
                'You haven\'t created any courses yet. Contact an administrator to create your first course.',
                null,
                null
            );
            return;
        }

        let html = '<div class="course-grid">';
        courses.forEach(course => {
            const enrollmentCount = course.enrollment_count || 0;
            const completionRate = course.completion_rate || 0;

            html += `
                <div class="course-card instructor-course" data-course-id="${course.id}">
                    <div class="course-header">
                        <h3>${this.escapeHtml(course.title || 'Untitled Course')}</h3>
                        <span class="course-status ${course.is_published ? 'published' : 'draft'}">
                            ${course.is_published ? 'Published' : 'Draft'}
                        </span>
                    </div>
                    <p class="course-description">${this.escapeHtml(course.description || 'No description available')}</p>
                    <div class="course-stats">
                        <div class="stat-item">
                            <span class="stat-icon">👥</span>
                            <span class="stat-value">${enrollmentCount}</span>
                            <span class="stat-label">Students</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-icon">✅</span>
                            <span class="stat-value">${completionRate}%</span>
                            <span class="stat-label">Completion</span>
                        </div>
                    </div>
                    <div class="course-footer">
                        <button class="btn-secondary btn-sm" onclick="InstructorDashboard.viewCourse(${course.id})">
                            View Details
                        </button>
                        <button class="btn-primary btn-sm" onclick="InstructorDashboard.manageCourse(${course.id})">
                            Manage
                        </button>
                    </div>
                </div>
            `;
        });
        html += '</div>';

        container.innerHTML = html;
    },

    /**
     * Render grading queue
     */
    renderGradingQueue(queue) {
        const container = document.getElementById('grading-queue');
        if (!container) return;

        if (queue.total === 0) {
            container.innerHTML = this.getEmptyState(
                'No Pending Grading',
                'All caught up! No submissions waiting for review.',
                null,
                null
            );
            return;
        }

        let html = '<div class="grading-queue-list">';

        // Render project submissions
        if (queue.projects.length > 0) {
            html += '<h4 class="queue-section-title">Project Submissions</h4>';
            queue.projects.forEach(submission => {
                html += `
                    <div class="grading-item" data-type="project" data-id="${submission.id}">
                        <div class="item-info">
                            <h4>${this.escapeHtml(submission.project_title || 'Project')}</h4>
                            <p class="item-meta">
                                Student: ${this.escapeHtml(submission.student_name || 'Unknown')} •
                                Submitted: ${this.formatDate(submission.submitted_at)}
                            </p>
                        </div>
                        <div class="item-actions">
                            <button class="btn-primary btn-sm" onclick="InstructorDashboard.gradeProject(${submission.id})">
                                Grade
                            </button>
                        </div>
                    </div>
                `;
            });
        }

        // Render quiz attempts (if manual review needed)
        if (queue.quizzes.length > 0) {
            html += '<h4 class="queue-section-title">Quiz Reviews</h4>';
            queue.quizzes.forEach(attempt => {
                html += `
                    <div class="grading-item" data-type="quiz" data-id="${attempt.id}">
                        <div class="item-info">
                            <h4>${this.escapeHtml(attempt.quiz_title || 'Quiz')}</h4>
                            <p class="item-meta">
                                Student: ${this.escapeHtml(attempt.student_name || 'Unknown')} •
                                Completed: ${this.formatDate(attempt.completed_at)}
                            </p>
                        </div>
                        <div class="item-actions">
                            <button class="btn-primary btn-sm" onclick="InstructorDashboard.reviewQuiz(${attempt.id})">
                                Review
                            </button>
                        </div>
                    </div>
                `;
            });
        }

        html += '</div>';
        container.innerHTML = html;
    },

    /**
     * Render instructor statistics with animations
     */
    renderInstructorStats(stats) {
        this.updateStatCard('total-courses', stats.total_courses || 0);
        this.updateStatCard('total-students', stats.total_students || 0);
        this.updateStatCard('pending-grading', stats.pending_grading || 0);
        this.updateStatCard('completion-rate', `${stats.average_completion_rate || 0}%`);
    },

    /**
     * Update individual stat card
     */
    updateStatCard(id, value) {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = value;
        }
    },

    /**
     * Show loading state
     */
    showLoadingState() {
        const mainContent = document.getElementById('dashboard-content');
        if (mainContent) {
            mainContent.classList.add('loading');
        }
    },

    /**
     * Hide loading state
     */
    hideLoadingState() {
        const mainContent = document.getElementById('dashboard-content');
        if (mainContent) {
            mainContent.classList.remove('loading');
        }
    },

    /**
     * Show error state
     */
    showErrorState(message) {
        const container = document.getElementById('dashboard-content');
        if (!container) return;

        // Remove loading state before showing error
        container.classList.remove('loading');

        container.innerHTML = `
            <div class="error-state">
                <div class="error-icon">⚠️</div>
                <h2>Oops! Something went wrong</h2>
                <p>${this.escapeHtml(message)}</p>
                <button class="btn-primary" onclick="location.reload()">Try Again</button>
            </div>
        `;
    },

    /**
     * Get empty state HTML
     */
    getEmptyState(title, message, linkUrl, linkText) {
        let html = `
            <div class="empty-state">
                <div class="empty-icon">📋</div>
                <h3>${title}</h3>
                <p>${message}</p>
        `;

        if (linkUrl && linkText) {
            html += `<a href="${linkUrl}" class="btn-primary">${linkText}</a>`;
        }

        html += '</div>';
        return html;
    },

    /**
     * View course details
     */
    viewCourse(courseId) {
        console.log(`InstructorDashboard: View course ${courseId}`);
        alert(`Course viewing will be implemented in Phase 5.\nCourse ID: ${courseId}`);
    },

    /**
     * Manage course (edit content, settings)
     */
    manageCourse(courseId) {
        console.log(`InstructorDashboard: Manage course ${courseId}`);
        alert(`Course management will be implemented in Phase 5.\nCourse ID: ${courseId}`);
    },

    /**
     * Grade a project submission
     */
    gradeProject(submissionId) {
        console.log(`InstructorDashboard: Grade project ${submissionId}`);
        alert(`Project grading will be implemented in Phase 7.\nSubmission ID: ${submissionId}`);
    },

    /**
     * Review a quiz attempt
     */
    reviewQuiz(attemptId) {
        console.log(`InstructorDashboard: Review quiz ${attemptId}`);
        alert(`Quiz review will be implemented in Phase 6.\nAttempt ID: ${attemptId}`);
    },

    /**
     * Set up event listeners
     */
    setupEventListeners() {
        // Refresh button
        const refreshBtn = document.getElementById('refresh-dashboard');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => this.loadDashboardData());
        }

        // Listen for auth state changes
        document.addEventListener('authStateChanged', (e) => {
            if (!e.detail.isAuthenticated) {
                window.location.href = '/public/login.html';
            }
        });
    },

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
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
    document.addEventListener('DOMContentLoaded', () => InstructorDashboard.init());
} else {
    InstructorDashboard.init();
}
