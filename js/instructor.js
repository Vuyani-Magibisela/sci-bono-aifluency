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

        // Update sidebar profile immediately; welcome is set after school loads
        this.updateSidebarProfile(user);
        this.updateWelcomeMessage(user);

        // Load dashboard data
        await this.loadDashboardData();

        // Set up event listeners
        this.setupEventListeners();

        console.log('InstructorDashboard: Initialization complete');
    },

    /**
     * Update the welcome message with user's name
     */
    updateWelcomeMessage(user, schoolName) {
        const welcomeElement = document.getElementById('welcome-message');
        if (welcomeElement) {
            const firstName = user.name ? user.name.split(' ')[0] : 'Instructor';
            welcomeElement.textContent = `Welcome, ${firstName}!`;
        }
        const bannerSubtitle = document.querySelector('.welcome-banner p');
        if (bannerSubtitle && schoolName) {
            bannerSubtitle.innerHTML = `Instructor at <strong>${this.escapeHtml(schoolName)}</strong> &mdash; manage your courses and engage with your students`;
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
            const user = Auth.getUser();

            // Load data in parallel (school-scoped data is only fetched when assigned)
            const [courses, gradingQueue, schoolStats, school] = await Promise.all([
                this.loadMyCourses(),
                this.loadGradingQueue(),
                this.loadSchoolStats(user),
                this.loadSchoolInfo(user)
            ]);

            // Refresh welcome banner with school name if available
            if (school && school.name) {
                this.updateWelcomeMessage(user, school.name);
            }

            // Show a gentle banner if the teacher has no school AND no courses
            if (!user.primary_school_id && (!courses || courses.length === 0)) {
                this.renderUnassignedBanner();
            }

            // Compute stats from loaded data
            const stats = this.computeStats(courses, gradingQueue, schoolStats);

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
     * Fetch the student count for the teacher's assigned school.
     * Returns null if the teacher has no school assignment.
     */
    async loadSchoolStats(user) {
        if (!user || !user.primary_school_id) {
            return null;
        }
        try {
            const response = await API.get(`/users?school_id=${user.primary_school_id}&role=student&pageSize=1`);
            const payload = response.data || {};
            return { total_students: Number(payload.total) || 0 };
        } catch (error) {
            console.warn('InstructorDashboard: Could not load school stats:', error);
            return null;
        }
    },

    /**
     * Fetch school details (name, etc.) for the teacher's assigned school.
     */
    async loadSchoolInfo(user) {
        if (!user || !user.primary_school_id) {
            return null;
        }
        try {
            const response = await API.get(`/schools/${user.primary_school_id}`);
            return response.data || null;
        } catch (error) {
            console.warn('InstructorDashboard: Could not load school info:', error);
            return null;
        }
    },

    /**
     * Render a banner prompting admin to assign the teacher to a school.
     */
    renderUnassignedBanner() {
        const host = document.getElementById('dashboard-content');
        if (!host || document.getElementById('unassigned-banner')) return;
        const banner = document.createElement('div');
        banner.id = 'unassigned-banner';
        banner.className = 'welcome-banner';
        banner.style.background = 'linear-gradient(135deg, #FB4B4B 0%, #FFA500 100%)';
        banner.style.marginBottom = '1.5rem';
        banner.innerHTML = `
            <h2>You are not yet assigned to a school or course</h2>
            <p>Ask a SuperAdmin to assign you under <strong>Admin &rarr; Users</strong> so students from your school appear here.</p>
        `;
        const welcome = host.querySelector('.welcome-banner');
        if (welcome && welcome.parentNode === host) {
            host.insertBefore(banner, welcome.nextSibling);
        } else {
            host.prepend(banner);
        }
    },

    /**
     * Load courses taught by this instructor
     */
    async loadMyCourses() {
        try {
            const user = Auth.getUser();
            // Teachers see published courses with at least one enrolled student from
            // their assigned school. Superadmins without a school still fall back to
            // ?instructor_id= for their own courses.
            let endpoint;
            if (user && user.primary_school_id) {
                endpoint = `/courses?school_id=${user.primary_school_id}&published=true`;
            } else if (user) {
                endpoint = `/courses?instructor_id=${user.id}&published=false`;
            } else {
                endpoint = '/courses';
            }
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
        const user = Auth.getUser();
        const schoolQuery = user && user.primary_school_id
            ? `&school_id=${user.primary_school_id}`
            : '';
        let projects = [];
        let quizzes = [];

        // Pending project submissions (school-scoped).
        try {
            const projectsResponse = await API.get(`/projects/submissions/pending?limit=5${schoolQuery}`);
            const raw = projectsResponse.data || {};
            projects = Array.isArray(raw) ? raw : (raw.submissions || raw.items || []);
        } catch (error) {
            console.warn('InstructorDashboard: Could not load project submissions:', error);
        }

        // Pending quiz attempts needing manual grading (short-answer/essay).
        try {
            const quizzesResponse = await API.get(`/grading/pending?limit=5${schoolQuery}`);
            const raw = quizzesResponse.data || {};
            quizzes = Array.isArray(raw) ? raw : (raw.attempts || raw.items || []);
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
     * Compute instructor stats from loaded courses, grading data, and school stats.
     * When school-level stats are available (teacher assigned to a school), the
     * school's student count takes precedence over the per-course enrollment sum.
     */
    computeStats(courses, gradingQueue, schoolStats) {
        const courseList = Array.isArray(courses) ? courses : [];
        const totalCourses = courseList.length;

        let enrollmentSum = 0;
        let totalCompletion = 0;
        let coursesWithCompletion = 0;

        courseList.forEach(course => {
            enrollmentSum += (course.enrollment_count || 0);
            if (course.completion_rate !== undefined && course.completion_rate !== null) {
                totalCompletion += parseFloat(course.completion_rate) || 0;
                coursesWithCompletion++;
            }
        });

        const totalStudents = (schoolStats && schoolStats.total_students != null)
            ? schoolStats.total_students
            : enrollmentSum;

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
            const user = Auth.getUser();
            const message = user && user.primary_school_id
                ? 'You are not yet listed as the instructor on any course. An administrator can assign you in Course Management.'
                : 'No courses yet. Ask an administrator to assign you to a school and course.';
            container.innerHTML = this.getEmptyState('No Courses Yet', message, null, null);
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
                'Nothing needs your review right now. Quizzes in this course are auto-graded; items requiring instructor review will appear here.',
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
