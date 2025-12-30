/**
 * Student Dashboard Module
 * Handles student dashboard functionality including:
 * - Enrolled courses display
 * - Learning progress tracking
 * - Quiz attempts history
 * - Earned certificates
 * - Learning statistics
 */

const StudentDashboard = {
    /**
     * Initialize the student dashboard
     */
    async init() {
        console.log('StudentDashboard: Initializing...');

        // Ensure user is authenticated
        const user = Auth.getUser();
        if (!user) {
            console.error('StudentDashboard: No authenticated user found');
            window.location.href = 'login.html';
            return;
        }

        // Update welcome message
        this.updateWelcomeMessage(user);

        // Load dashboard data
        await this.loadDashboardData();

        // Set up event listeners
        this.setupEventListeners();

        console.log('StudentDashboard: Initialization complete');
    },

    /**
     * Update the welcome message with user's name
     */
    updateWelcomeMessage(user) {
        const welcomeElement = document.getElementById('welcome-message');
        if (welcomeElement) {
            const firstName = user.name ? user.name.split(' ')[0] : 'Student';
            welcomeElement.textContent = `Welcome back, ${firstName}!`;
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
            const [courses, quizAttempts, certificates, stats] = await Promise.all([
                this.loadEnrolledCourses(),
                this.loadRecentQuizAttempts(),
                this.loadCertificates(),
                this.loadLearningStats()
            ]);

            // Update UI with loaded data
            this.renderEnrolledCourses(courses);
            this.renderQuizAttempts(quizAttempts);
            this.renderCertificates(certificates);
            this.renderLearningStats(stats);

            // Hide loading state
            this.hideLoadingState();

        } catch (error) {
            console.error('StudentDashboard: Error loading data:', error);
            this.showErrorState(error.message);
        }
    },

    /**
     * Load enrolled courses from API
     */
    async loadEnrolledCourses() {
        try {
            const response = await API.get('/courses/enrolled');
            return response.data || [];
        } catch (error) {
            console.warn('StudentDashboard: Could not load enrolled courses:', error);
            return [];
        }
    },

    /**
     * Load recent quiz attempts from API
     */
    async loadRecentQuizAttempts() {
        try {
            const response = await API.get('/quizzes/attempts/recent?limit=5');
            return response.data || [];
        } catch (error) {
            console.warn('StudentDashboard: Could not load quiz attempts:', error);
            return [];
        }
    },

    /**
     * Load earned certificates from API
     */
    async loadCertificates() {
        try {
            const response = await API.get('/certificates/my-certificates');
            return response.data || [];
        } catch (error) {
            console.warn('StudentDashboard: Could not load certificates:', error);
            return [];
        }
    },

    /**
     * Load learning statistics from API
     */
    async loadLearningStats() {
        try {
            const response = await API.get('/users/me/stats');
            return response.data || this.getDefaultStats();
        } catch (error) {
            console.warn('StudentDashboard: Could not load stats:', error);
            return this.getDefaultStats();
        }
    },

    /**
     * Get default stats when API fails
     */
    getDefaultStats() {
        return {
            total_courses: 0,
            completed_lessons: 0,
            total_lessons: 0,
            quiz_average: 0,
            certificates_earned: 0,
            current_streak: 0
        };
    },

    /**
     * Render enrolled courses
     */
    renderEnrolledCourses(courses) {
        const container = document.getElementById('enrolled-courses');
        if (!container) return;

        if (courses.length === 0) {
            container.innerHTML = this.getEmptyState(
                'No Enrolled Courses',
                'You haven\'t enrolled in any courses yet. Browse available courses to get started!',
                '/courses.html',
                'Browse Courses'
            );
            return;
        }

        let html = '<div class="course-grid">';
        courses.forEach(course => {
            const progress = course.progress || 0;
            html += `
                <div class="course-card" data-course-id="${course.id}">
                    <div class="course-header">
                        <h3>${this.escapeHtml(course.title || 'Untitled Course')}</h3>
                        <span class="course-badge">${progress}% Complete</span>
                    </div>
                    <p class="course-description">${this.escapeHtml(course.description || 'No description available')}</p>
                    <div class="progress-bar">
                        <div class="progress-fill" data-progress="${progress}" style="width: 0%"></div>
                    </div>
                    <div class="course-footer">
                        <button class="btn-primary btn-sm" onclick="StudentDashboard.continueCourse(${course.id})">
                            ${progress > 0 ? 'Continue Learning' : 'Start Course'}
                        </button>
                    </div>
                </div>
            `;
        });
        html += '</div>';

        container.innerHTML = html;

        // Animate progress bars after rendering
        const progressBars = container.querySelectorAll('.progress-fill');
        progressBars.forEach((bar, index) => {
            const targetProgress = parseFloat(bar.getAttribute('data-progress')) || 0;
            setTimeout(() => {
                Animations.animateProgressBar(bar, targetProgress, {
                    duration: 1.2,
                    delay: 0,
                    pulse: targetProgress === 100
                });
            }, index * 100);
        });

        // Animate course cards
        Animations.fadeInStagger('.course-card', {
            duration: 0.8,
            stagger: 0.15
        });
    },

    /**
     * Render recent quiz attempts
     */
    renderQuizAttempts(attempts) {
        const container = document.getElementById('recent-quiz-attempts');
        if (!container) return;

        if (attempts.length === 0) {
            container.innerHTML = this.getEmptyState(
                'No Quiz Attempts',
                'Complete quizzes to see your results here.',
                null,
                null
            );
            return;
        }

        let html = '<div class="quiz-attempts-list">';
        attempts.forEach(attempt => {
            const score = attempt.score || 0;
            const maxScore = attempt.max_score || 100;
            const percentage = maxScore > 0 ? Math.round((score / maxScore) * 100) : 0;
            const passed = percentage >= 70;

            html += `
                <div class="quiz-attempt-item ${passed ? 'passed' : 'failed'}">
                    <div class="quiz-info">
                        <h4>${this.escapeHtml(attempt.quiz_title || 'Quiz')}</h4>
                        <p class="quiz-date">${this.formatDate(attempt.created_at)}</p>
                    </div>
                    <div class="quiz-score">
                        <span class="score-value">${percentage}%</span>
                        <span class="score-badge ${passed ? 'badge-success' : 'badge-danger'}">
                            ${passed ? 'Passed' : 'Failed'}
                        </span>
                    </div>
                </div>
            `;
        });
        html += '</div>';

        container.innerHTML = html;

        // Animate quiz attempt items with slide-in effect
        Animations.slideIn('.quiz-attempt-item', 'up', {
            duration: 0.6,
            stagger: 0.1,
            distance: 30
        });
    },

    /**
     * Render certificates
     */
    renderCertificates(certificates) {
        const container = document.getElementById('certificates');
        if (!container) return;

        if (certificates.length === 0) {
            container.innerHTML = this.getEmptyState(
                'No Certificates Yet',
                'Complete courses to earn certificates!',
                null,
                null
            );
            return;
        }

        let html = '<div class="certificates-grid">';
        certificates.forEach(cert => {
            html += `
                <div class="certificate-card" data-certificate-id="${cert.id}">
                    <div class="certificate-icon">🏆</div>
                    <h4>${this.escapeHtml(cert.course_title || 'Certificate')}</h4>
                    <p class="certificate-date">Earned on ${this.formatDate(cert.issued_date)}</p>
                    <button class="btn-secondary btn-sm" onclick="StudentDashboard.viewCertificate(${cert.id})">
                        View Certificate
                    </button>
                </div>
            `;
        });
        html += '</div>';

        container.innerHTML = html;

        // Animate certificates with fade-in and slight scale effect
        Animations.fadeInStagger('.certificate-card', {
            duration: 0.8,
            stagger: 0.12,
            y: 20
        });
    },

    /**
     * Render learning statistics with animations
     */
    renderLearningStats(stats) {
        // Animate stat cards in sequence
        setTimeout(() => {
            this.updateStatCard('total-courses', stats.total_courses || 0);
        }, 100);

        setTimeout(() => {
            this.updateStatCard('completed-lessons', `${stats.completed_lessons || 0}/${stats.total_lessons || 0}`);
        }, 200);

        setTimeout(() => {
            this.updateStatCard('quiz-average', `${stats.quiz_average || 0}%`);
        }, 300);

        setTimeout(() => {
            this.updateStatCard('certificates-earned', stats.certificates_earned || 0);
        }, 400);

        // Render progress chart if element exists
        if (stats.completed_lessons && stats.total_lessons) {
            this.renderProgressChart(stats.completed_lessons, stats.total_lessons);
        }

        // Animate dashboard cards with stagger effect
        Animations.fadeInStagger('.dashboard-card', {
            duration: 0.8,
            stagger: 0.1,
            y: 30
        });
    },

    /**
     * Update individual stat card with animation
     */
    updateStatCard(id, value) {
        const element = document.getElementById(id);
        if (!element) return;

        // Check if value is a number for counter animation
        if (typeof value === 'number') {
            Animations.animateCounter(element, value, {
                duration: 1.5,
                decimals: 0
            });
        } else if (typeof value === 'string' && value.includes('%')) {
            // Animate percentage
            const percentage = parseFloat(value.replace('%', ''));
            if (!isNaN(percentage)) {
                Animations.animatePercentage(element, percentage, {
                    duration: 1.5
                });
            } else {
                element.textContent = value;
            }
        } else if (typeof value === 'string' && value.includes('/')) {
            // For fraction values like "10/20", animate the first number
            const parts = value.split('/');
            if (parts.length === 2) {
                const current = parseInt(parts[0]);
                const total = parseInt(parts[1]);
                if (!isNaN(current) && !isNaN(total)) {
                    const counter = { value: 0 };
                    gsap.to(counter, {
                        value: current,
                        duration: 1.5,
                        ease: 'power2.out',
                        onUpdate: function() {
                            element.textContent = Math.round(counter.value) + '/' + total;
                        }
                    });
                } else {
                    element.textContent = value;
                }
            } else {
                element.textContent = value;
            }
        } else {
            // Non-numeric value, just set text
            element.textContent = value;
        }
    },

    /**
     * Render progress chart with animation
     */
    renderProgressChart(completed, total) {
        const chartContainer = document.getElementById('progress-chart');
        if (!chartContainer) return;

        const percentage = total > 0 ? Math.round((completed / total) * 100) : 0;

        chartContainer.innerHTML = `
            <div class="circular-progress">
                <div class="progress-value" id="circular-progress-value">0%</div>
                <svg width="200" height="200">
                    <circle cx="100" cy="100" r="90" fill="none" stroke="#e0e0e0" stroke-width="12"/>
                    <circle id="progress-circle" cx="100" cy="100" r="90" fill="none" stroke="#4CAF50" stroke-width="12"
                        stroke-dasharray="565.48"
                        stroke-dashoffset="565.48"
                        transform="rotate(-90 100 100)"/>
                </svg>
            </div>
        `;

        // Animate the circular progress
        const circle = chartContainer.querySelector('#progress-circle');
        if (circle) {
            setTimeout(() => {
                Animations.animateCircularProgress(circle, percentage, {
                    duration: 1.5,
                    ease: 'power2.out'
                });

                // Animate the percentage text
                Animations.animatePercentage('#circular-progress-value', percentage, {
                    duration: 1.5
                });
            }, 500);
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
                <div class="empty-icon">📚</div>
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
     * Continue a course
     */
    continueCourse(courseId) {
        // Navigate to course page (Phase 5 will implement this)
        console.log(`StudentDashboard: Continue course ${courseId}`);
        alert(`Course navigation will be implemented in Phase 5.\nCourse ID: ${courseId}`);
    },

    /**
     * View certificate
     */
    viewCertificate(certificateId) {
        // Open certificate view (Phase 7 will implement this)
        console.log(`StudentDashboard: View certificate ${certificateId}`);
        alert(`Certificate viewing will be implemented in Phase 7.\nCertificate ID: ${certificateId}`);
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
                window.location.href = 'login.html';
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
            month: 'long',
            day: 'numeric'
        });
    }
};

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => StudentDashboard.init());
} else {
    StudentDashboard.init();
}
