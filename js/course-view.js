/**
 * Course View Page
 * Displays course modules and tracks progress
 */

class CourseView {
    constructor() {
        this.courseId = null;
        this.course = null;
        this.modules = [];
        this.currentUser = null;
        this.enrollment = null;
    }

    /**
     * Initialize the course view
     */
    async init() {
        try {
            // Get course ID from URL parameter
            const urlParams = new URLSearchParams(window.location.search);
            this.courseId = urlParams.get('course_id');

            if (!this.courseId) {
                this.showError('No course specified');
                return;
            }

            // Check authentication
            if (typeof Auth !== 'undefined' && Auth.isAuthenticated && Auth.isAuthenticated()) {
                this.currentUser = Auth.getUser();
            }

            // Load course data
            await this.loadCourse();
        } catch (error) {
            console.error('Error initializing course view:', error);
            this.showError('Failed to load course. Please try again later.');
        }
    }

    /**
     * Load course with modules
     */
    async loadCourse() {
        try {
            const response = await API.get(`/courses/${this.courseId}`);

            if (response && response.success && response.data && response.data.course) {
                this.course = response.data.course;
                this.modules = this.course.modules || [];

                // Update page title
                document.title = `${this.course.title} - AI Discovery Hub`;
                document.getElementById('page-title').textContent = document.title;

                // Render course header
                this.renderCourseHeader();

                // Render progress section if enrolled
                if (this.course.is_enrolled) {
                    this.renderProgressSection();
                }

                // Render modules
                this.renderModules();
            } else {
                throw new Error('Failed to load course data');
            }
        } catch (error) {
            console.error('Error loading course:', error);
            this.showError('Failed to load course. Please try again later.');
        }
    }

    /**
     * Render course header
     */
    renderCourseHeader() {
        const headerContainer = document.getElementById('course-header');

        const enrollmentBadge = this.course.is_enrolled
            ? `<span class="module-status completed"><i class="fas fa-check-circle"></i> Enrolled</span>`
            : `<span class="module-status not-started"><i class="fas fa-exclamation-circle"></i> Not Enrolled</span>`;

        headerContainer.innerHTML = `
            <h1>${this.course.title}</h1>
            <p>${this.course.description || 'No description available.'}</p>
            <p class="course-gating-info"><i class="fas fa-info-circle"></i> After completing each module, you must pass a quiz and submit a project to unlock the next module.</p>
            ${enrollmentBadge}
            <div class="course-meta">
                <div class="meta-item">
                    <i class="fas fa-signal"></i>
                    <span>${this.formatLevel(this.course.difficulty_level || this.course.level)}</span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-clock"></i>
                    <span>${this.course.duration_hours || 0} Hours</span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-book"></i>
                    <span>${this.modules.length} Modules</span>
                </div>
            </div>
        `;

        headerContainer.className = 'course-header';
    }

    /**
     * Render progress section
     */
    renderProgressSection() {
        const progressContainer = document.getElementById('progress-section');

        // Count modules where both quiz and project are done
        const completedModules = this.modules.filter(m => m.quiz_passed && m.project_submitted).length;
        const totalModules = this.modules.length;
        const completionPercentage = totalModules > 0 ? Math.round((completedModules / totalModules) * 100) : 0;

        progressContainer.innerHTML = `
            <h2>Your Progress</h2>
            <div class="progress-bar-large">
                <div class="progress-fill" style="width: ${completionPercentage}%"></div>
            </div>
            <div class="progress-stats">
                <span>${completionPercentage}% Complete</span>
                <span>${completedModules} of ${totalModules} Modules Completed</span>
            </div>
        `;

        progressContainer.style.display = 'block';
    }

    /**
     * Render modules grid
     */
    renderModules() {
        const modulesContainer = document.getElementById('modules-grid');

        if (this.modules.length === 0) {
            modulesContainer.innerHTML = `
                <div class="error-message">
                    <i class="fas fa-inbox"></i>
                    <p>No modules available for this course yet.</p>
                </div>
            `;
            return;
        }

        modulesContainer.innerHTML = this.modules.map((module, index) => this.renderModuleCard(module, index)).join('');

        // Attach click listeners
        this.attachModuleListeners();
    }

    /**
     * Render a single module card
     */
    renderModuleCard(module, index) {
        const moduleNumber = index + 1;
        // First module always unlocked; subsequent modules require previous module's quiz AND project done
        let isLocked = false;
        if (this.course.is_enrolled && index > 0) {
            const prevModule = this.modules[index - 1];
            isLocked = !(prevModule.quiz_passed && prevModule.project_submitted);
        }
        const progress = module.completion_percentage || 0;
        const lessonsCount = module.lessons_count || 0;

        // Determine status
        let status = 'not-started';
        let statusText = 'Not Started';
        if (progress === 100) {
            status = 'completed';
            statusText = 'Completed';
        } else if (progress > 0) {
            status = 'in-progress';
            statusText = 'In Progress';
        }

        // Module icons (you can customize these)
        const icons = [
            'fa-brain',
            'fa-robot',
            'fa-search',
            'fa-shield-alt',
            'fa-tools',
            'fa-globe'
        ];
        const icon = icons[index] || 'fa-book';

        return `
            <div class="module-card ${isLocked ? 'locked' : ''}" data-module-id="${module.id}">
                ${isLocked ? '<i class="fas fa-lock lock-icon"></i>' : ''}
                <div class="module-header">
                    <span class="module-number">Module ${moduleNumber}</span>
                    <i class="fas ${icon} module-icon"></i>
                    <h3 class="module-title">${module.title}</h3>
                </div>
                <div class="module-body">
                    <p class="module-description">
                        ${module.description || 'Explore the concepts and applications in this module.'}
                    </p>

                    ${this.course.is_enrolled && progress > 0 ? `
                        <div class="module-progress">
                            <div class="module-progress-bar">
                                <div class="module-progress-fill" style="width: ${progress}%"></div>
                            </div>
                            <span class="module-progress-text">${progress}% Complete</span>
                        </div>
                    ` : ''}

                    <div class="module-stats">
                        <div class="stat-item">
                            <i class="fas fa-book-open"></i>
                            <span>${lessonsCount} Lessons</span>
                        </div>
                        ${module.duration_hours ? `
                            <div class="stat-item">
                                <i class="fas fa-clock"></i>
                                <span>${module.duration_hours}h</span>
                            </div>
                        ` : ''}
                    </div>

                    ${isLocked ? `
                        <div class="module-requirements">
                            <i class="fas fa-lock"></i>
                            <span>Complete Quiz and Project in Module ${index} to unlock</span>
                        </div>
                    ` : ''}

                    ${!isLocked ? `<span class="module-status ${status}">${statusText}</span>` : `<span class="module-status locked"><i class="fas fa-lock"></i> Locked</span>`}
                </div>
            </div>
        `;
    }

    /**
     * Attach click listeners to module cards
     */
    attachModuleListeners() {
        const moduleCards = document.querySelectorAll('.module-card:not(.locked)');

        moduleCards.forEach(card => {
            card.addEventListener('click', () => {
                const moduleId = card.dataset.moduleId;
                this.navigateToModule(moduleId);
            });
        });
    }

    /**
     * Navigate to module page
     */
    navigateToModule(moduleId) {
        window.location.href = `/student/modules/module-dynamic.html?module_id=${moduleId}&course_id=${this.courseId}`;
    }

    /**
     * Format difficulty level
     */
    formatLevel(level) {
        if (!level) return 'All Levels';

        const levels = {
            'beginner': 'Beginner',
            'intermediate': 'Intermediate',
            'advanced': 'Advanced'
        };

        return levels[level.toLowerCase()] || level;
    }

    /**
     * Show error message
     */
    showError(message) {
        const headerContainer = document.getElementById('course-header');
        const modulesContainer = document.getElementById('modules-grid');

        headerContainer.innerHTML = `
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <p>${message}</p>
                <a href="/student/courses.html" class="btn-primary">Back to Courses</a>
            </div>
        `;

        modulesContainer.innerHTML = '';
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    const courseView = new CourseView();
    courseView.init();
});
