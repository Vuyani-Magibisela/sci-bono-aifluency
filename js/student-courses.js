/**
 * Student Courses Page
 * Handles dynamic course loading with enrollment status and actions
 */

class StudentCourses {
    constructor() {
        this.courses = [];
        this.currentUser = null;
    }

    /**
     * Initialize the courses page
     */
    async init() {
        try {
            // Check authentication
            if (typeof Auth !== 'undefined' && Auth.isAuthenticated && Auth.isAuthenticated()) {
                this.currentUser = Auth.getUser();
                console.log('Authenticated user:', this.currentUser);
            } else {
                console.log('User not authenticated, showing public courses');
                this.currentUser = null;
            }

            // Load courses
            await this.loadCourses();
        } catch (error) {
            console.error('Error initializing courses page:', error);
            console.error('Stack trace:', error.stack);
            this.showError('Failed to load courses. Please try again later.');
        }
    }

    /**
     * Load courses from API with enrollment status
     */
    async loadCourses() {
        try {
            console.log('=== Loading courses from API ===');
            console.log('Current user:', this.currentUser);
            console.log('API object type:', typeof API);

            if (typeof API === 'undefined' || typeof API.get !== 'function') {
                throw new Error('API module not loaded correctly');
            }

            // Try direct fetch first to see what we're getting
            console.log('Testing direct fetch...');
            try {
                const testResponse = await fetch('/api/courses?published=true');
                const testText = await testResponse.text();
                console.log('Direct fetch status:', testResponse.status);
                console.log('Direct fetch response (first 500 chars):', testText.substring(0, 500));
            } catch (e) {
                console.error('Direct fetch test failed:', e);
            }

            console.log('Calling API.get...');
            const response = await API.get('/courses', { published: true });
            console.log('API Response:', response);
            console.log('Response type:', typeof response);

            if (response && response.success && response.data) {
                // Handle paginated response
                this.courses = response.data.items || response.data;
                console.log('Loaded courses:', this.courses);
                console.log('Courses array length:', this.courses.length);

                if (!Array.isArray(this.courses)) {
                    console.error('Courses is not an array:', this.courses);
                    this.courses = [];
                }

                this.renderCourses();
            } else {
                console.error('API response not successful:', response);
                throw new Error((response && response.message) || 'Failed to load courses');
            }
        } catch (error) {
            console.error('=== Error loading courses ===');
            console.error('Error:', error);
            console.error('Error message:', error.message);
            console.error('Error stack:', error.stack);
            console.error('Error type:', error.name);

            // Show more helpful error message
            let errorMsg = error.message;
            if (errorMsg.includes('Unexpected token')) {
                errorMsg += ' (Server returned HTML instead of JSON - check browser console for details)';
            }

            this.showError(`Failed to load courses: ${errorMsg}. Please check the browser console (F12) and refresh the page.`);
        }
    }

    /**
     * Render courses grid
     */
    renderCourses() {
        const container = document.getElementById('courses-grid');

        if (!container) {
            console.error('Courses grid container not found');
            return;
        }

        if (this.courses.length === 0) {
            container.innerHTML = '<p class="no-courses">No courses available at this time.</p>';
            return;
        }

        container.innerHTML = this.courses.map(course => this.renderCourseCard(course)).join('');

        // Attach event listeners
        this.attachEventListeners();
    }

    /**
     * Render a single course card
     */
    renderCourseCard(course) {
        const isEnrolled = course.is_enrolled || false;
        const completionPercentage = course.completion_percentage || 0;

        // Determine button state and text
        let buttonHtml;
        if (!this.currentUser) {
            buttonHtml = `<a href="/public/login.html?redirect=${encodeURIComponent(window.location.pathname)}" class="course-btn">
                Login to Enroll <i class="fas fa-sign-in-alt"></i>
            </a>`;
        } else if (isEnrolled) {
            if (completionPercentage > 0) {
                buttonHtml = `<button class="course-btn" data-action="continue" data-course-id="${course.id}">
                    Continue Learning <i class="fas fa-play"></i>
                </button>`;
            } else {
                buttonHtml = `<button class="course-btn" data-action="start" data-course-id="${course.id}">
                    Start Learning <i class="fas fa-rocket"></i>
                </button>`;
            }
        } else {
            buttonHtml = `<button class="course-btn" data-action="enroll" data-course-id="${course.id}">
                Enroll Now <i class="fas fa-plus-circle"></i>
            </button>`;
        }

        // Enrollment status badge
        let enrollmentBadgeHtml = '';
        if (isEnrolled) {
            enrollmentBadgeHtml = `
                <div class="enrollment-status-badge">
                    <i class="fas fa-check-circle"></i> Enrolled
                </div>
            `;
        }

        // Progress bar for enrolled courses
        let progressBarHtml = '';
        if (isEnrolled && completionPercentage > 0) {
            progressBarHtml = `
                <div class="course-progress">
                    <div class="progress-bar-container">
                        <div class="progress-bar" style="width: ${completionPercentage}%"></div>
                    </div>
                    <span class="progress-text">${completionPercentage}% Complete</span>
                </div>
            `;
        }

        return `
            <div class="course-card-detailed" data-course-id="${course.id}">
                <div class="course-header-image" style="${!course.thumbnail_url ? 'background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));' : ''}">
                    ${course.thumbnail_url ?
                        `<img src="${course.thumbnail_url}" alt="${course.title}">` :
                        `<i class="fas fa-graduation-cap"></i>`
                    }
                    ${course.partner_name && course.partner_name !== '' ?
                        `<span class="partner-badge">${course.partner_name}</span>` :
                        ''
                    }
                    ${enrollmentBadgeHtml}
                </div>
                <div class="course-body">
                    <h2 class="course-title">${course.title}</h2>
                    <div class="course-badges">
                        ${course.difficulty_level || course.level ?
                            `<span class="badge type"><i class="fas fa-signal"></i> ${this.formatLevel(course.difficulty_level || course.level)}</span>` :
                            ''
                        }
                        ${course.duration_hours ?
                            `<span class="badge duration"><i class="fas fa-clock"></i> ${course.duration_hours} Hours</span>` :
                            ''
                        }
                    </div>
                    <p class="course-description">
                        ${course.description || 'No description available.'}
                    </p>
                    ${course.objectives ? `
                        <div class="course-topics">
                            <h4>What You'll Learn:</h4>
                            <div class="topics-list">
                                ${this.renderObjectives(course.objectives)}
                            </div>
                        </div>
                    ` : ''}
                    ${progressBarHtml}
                    <div class="course-action">
                        <span class="modules-count">
                            <i class="fas fa-book"></i>
                            ${course.modules_count || (course.modules ? course.modules.length : 0)} Modules
                        </span>
                        ${buttonHtml}
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Format course level
     */
    formatLevel(level) {
        const levels = {
            'beginner': 'Beginner',
            'intermediate': 'Intermediate',
            'advanced': 'Advanced'
        };
        return levels[level] || level;
    }

    /**
     * Render course objectives
     */
    renderObjectives(objectives) {
        // If objectives is a string, try to parse as JSON
        let objectivesArray = [];

        if (typeof objectives === 'string') {
            try {
                objectivesArray = JSON.parse(objectives);
            } catch (e) {
                // If not JSON, split by newlines or commas
                objectivesArray = objectives.split(/[,\n]/).filter(o => o.trim());
            }
        } else if (Array.isArray(objectives)) {
            objectivesArray = objectives;
        }

        return objectivesArray.map(objective => `
            <div class="topic-item">
                <i class="fas fa-check-circle"></i>
                <span>${objective.trim()}</span>
            </div>
        `).join('');
    }

    /**
     * Attach event listeners to course buttons
     */
    attachEventListeners() {
        const buttons = document.querySelectorAll('.course-btn[data-action]');

        buttons.forEach(button => {
            button.addEventListener('click', async (e) => {
                e.preventDefault();

                const action = button.dataset.action;
                const courseId = parseInt(button.dataset.courseId);

                await this.handleCourseAction(action, courseId, button);
            });
        });
    }

    /**
     * Handle course action (enroll, start, continue)
     */
    async handleCourseAction(action, courseId, button) {
        const originalContent = button.innerHTML;

        try {
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';

            switch (action) {
                case 'enroll':
                    await this.enrollInCourse(courseId);
                    break;
                case 'start':
                case 'continue':
                    await this.navigateToCourse(courseId);
                    break;
            }
        } catch (error) {
            console.error('Error handling course action:', error);
            button.disabled = false;
            button.innerHTML = originalContent;

            // Show error message
            this.showToast('An error occurred. Please try again.', 'error');
        }
    }

    /**
     * Enroll user in a course
     */
    async enrollInCourse(courseId) {
        try {
            const response = await API.post('/enrollments', {
                course_id: courseId
            });

            if (response.success) {
                this.showToast('Successfully enrolled in course!', 'success');

                // Reload courses to update UI
                await this.loadCourses();

                // Navigate to course after a brief delay
                setTimeout(() => {
                    this.navigateToCourse(courseId);
                }, 1000);
            } else {
                throw new Error(response.message || 'Enrollment failed');
            }
        } catch (error) {
            console.error('Enrollment error:', error);

            if (error.message.includes('already enrolled')) {
                this.showToast('You are already enrolled in this course', 'info');
                await this.loadCourses(); // Refresh to show current state
            } else {
                this.showToast(error.message || 'Failed to enroll. Please try again.', 'error');
            }

            throw error;
        }
    }

    /**
     * Navigate to course view page
     */
    async navigateToCourse(courseId) {
        try {
            // Navigate to course view page showing all modules
            window.location.href = `/student/course-view.html?course_id=${courseId}`;
        } catch (error) {
            console.error('Navigation error:', error);
            this.showToast('Failed to access course. Please try again.', 'error');
            throw error;
        }
    }

    /**
     * Show error message
     */
    showError(message) {
        const container = document.getElementById('courses-grid');

        if (container) {
            container.innerHTML = `
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <p>${message}</p>
                    <button onclick="window.location.reload()" class="btn-primary">Retry</button>
                </div>
            `;
        }
    }

    /**
     * Show toast notification
     */
    showToast(message, type = 'info') {
        // Create toast element
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;

        const icon = {
            'success': 'fa-check-circle',
            'error': 'fa-exclamation-circle',
            'warning': 'fa-exclamation-triangle',
            'info': 'fa-info-circle'
        }[type] || 'fa-info-circle';

        toast.innerHTML = `
            <i class="fas ${icon}"></i>
            <span>${message}</span>
        `;

        document.body.appendChild(toast);

        // Show toast
        setTimeout(() => toast.classList.add('show'), 100);

        // Hide and remove toast after 3 seconds
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    const studentCourses = new StudentCourses();
    studentCourses.init();
});
