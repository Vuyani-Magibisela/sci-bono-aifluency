/**
 * Student Projects Page
 * Loads projects from enrolled courses, gates by module completion, shows submission status.
 */

class StudentProjects {
    constructor() {
        this.enrolledCourses = [];       // [{id, title, modules: [{id, completion_percentage, ...}]}]
        this.projectsByCourse = {};      // { courseId: [projectObj, ...] }
        this.currentFilter = 'all';      // 'all' | courseId (string)
        this.currentUser = null;
    }

    async init() {
        try {
            // Require auth — redirect to login if not authenticated
            if (typeof Auth === 'undefined' || !Auth.isAuthenticated || !Auth.isAuthenticated()) {
                this.showLoginPrompt();
                return;
            }

            this.currentUser = Auth.getUser();
            this.showLoadingState();

            await this.loadData();
            this.renderFilterButtons();
            this.renderProjects();
        } catch (error) {
            console.error('StudentProjects init error:', error);
            this.showError('Failed to load projects. Please try again later.');
        }
    }

    // -------------------------------------------------------------------------
    // Data loading
    // -------------------------------------------------------------------------

    async loadData() {
        // 1. Get enrolled courses list
        const enrolledResponse = await API.get('/courses/enrolled');
        if (!enrolledResponse || !enrolledResponse.success) {
            throw new Error('Failed to load enrolled courses');
        }

        const rawCourses = enrolledResponse.data.items || enrolledResponse.data || [];

        // 2. For each enrolled course, load full course detail (modules + completion)
        //    and load projects in parallel
        const courseDetailPromises = rawCourses.map(c => API.get(`/courses/${c.id}`).catch(() => null));
        const projectPromises = rawCourses.map(c =>
            API.get('/projects', { course_id: c.id, published: true }).catch(() => null)
        );

        const [courseDetails, projectResponses] = await Promise.all([
            Promise.all(courseDetailPromises),
            Promise.all(projectPromises)
        ]);

        // 3. Merge data
        this.enrolledCourses = [];
        this.projectsByCourse = {};

        rawCourses.forEach((course, idx) => {
            // Extract full course object with modules
            const detailResp = courseDetails[idx];
            const fullCourse = (detailResp && detailResp.success && detailResp.data && detailResp.data.course)
                ? detailResp.data.course
                : course;

            const modules = fullCourse.modules || [];

            // Build a lookup: moduleId -> completion_percentage
            const moduleCompletion = {};
            modules.forEach(m => {
                moduleCompletion[m.id] = m.completion_percentage || 0;
            });

            this.enrolledCourses.push({
                id: course.id,
                title: fullCourse.title || course.title,
                modules,
                moduleCompletion
            });

            // Extract projects for this course
            const projResp = projectResponses[idx];
            const projects = (projResp && projResp.success && projResp.data)
                ? (projResp.data.items || projResp.data || [])
                : [];

            this.projectsByCourse[course.id] = projects;
        });
    }

    // -------------------------------------------------------------------------
    // Rendering
    // -------------------------------------------------------------------------

    renderFilterButtons() {
        const container = document.getElementById('filters-container');
        if (!container) return;

        let html = `<button class="filter-btn-custom active" data-filter="all">All Courses</button>`;
        this.enrolledCourses.forEach(course => {
            html += `<button class="filter-btn-custom" data-filter="${course.id}">${this.escHtml(course.title)}</button>`;
        });

        container.innerHTML = html;

        container.querySelectorAll('.filter-btn-custom').forEach(btn => {
            btn.addEventListener('click', () => {
                this.currentFilter = btn.dataset.filter;
                container.querySelectorAll('.filter-btn-custom').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                this.renderProjects();
            });
        });
    }

    renderProjects() {
        const grid = document.getElementById('projectsGrid');
        if (!grid) return;

        // Collect visible projects
        const allProjects = [];

        this.enrolledCourses.forEach(course => {
            if (this.currentFilter !== 'all' && String(course.id) !== String(this.currentFilter)) return;
            const projects = this.projectsByCourse[course.id] || [];
            projects.forEach(p => {
                allProjects.push({ project: p, course });
            });
        });

        if (allProjects.length === 0) {
            grid.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <p>${this.enrolledCourses.length === 0
                        ? 'You are not enrolled in any courses yet. <a href="/student/courses.html">Browse courses</a> to get started.'
                        : 'No projects found for the selected course.'
                    }</p>
                </div>`;
            return;
        }

        grid.innerHTML = allProjects.map(({ project, course }) =>
            this.renderProjectCard(project, course)
        ).join('');

        // Attach card listeners
        grid.querySelectorAll('.project-card-custom[data-unlocked="true"]').forEach(card => {
            card.addEventListener('click', () => {
                const projectId = card.dataset.projectId;
                const courseObj = this.enrolledCourses.find(c => String(c.id) === card.dataset.courseId);
                const project = (this.projectsByCourse[courseObj?.id] || []).find(p => String(p.id) === projectId);
                if (project) this.openModal(project, courseObj);
            });
        });
    }

    renderProjectCard(project, course) {
        const isUnlocked = this.isProjectUnlocked(project, course);
        const status = this.getProjectStatus(project);
        const statusBadge = this.renderStatusBadge(status, project);
        const moduleBadge = project.module_title
            ? `<span class="meta-badge module-badge"><i class="fas fa-book"></i> ${this.escHtml(project.module_title)}</span>`
            : '';

        const dueDate = project.due_date
            ? `Due: ${new Date(project.due_date).toLocaleDateString('en-ZA', { day: 'numeric', month: 'short', year: 'numeric' })}`
            : 'No due date';

        const maxScore = project.max_score != null ? `Max: ${project.max_score}` : '';

        const lockOverlay = !isUnlocked ? `
            <div class="lock-overlay">
                <i class="fas fa-lock"></i>
                <span>Complete all lessons in<br><strong>${this.escHtml(project.module_title || 'the module')}</strong><br>to unlock</span>
            </div>` : '';

        const gradedScore = (status.key === 'completed' && project.user_submission && project.user_submission.score != null)
            ? `<span class="score-display"><i class="fas fa-star"></i> ${project.user_submission.score}/${project.max_score || 100}</span>`
            : '';

        let footerBtn;
        if (!isUnlocked) {
            footerBtn = `<button class="action-btn locked-btn" disabled><i class="fas fa-lock"></i> Locked</button>`;
        } else if (status.key === 'not_started') {
            footerBtn = `<a href="/student/projects/submit.html?project_id=${project.id}" class="action-btn start-btn">Start Project <i class="fas fa-arrow-right"></i></a>`;
        } else if (status.key === 'in_progress') {
            footerBtn = `<a href="/student/projects/submit.html?project_id=${project.id}" class="action-btn in-progress-btn">View Submission <i class="fas fa-eye"></i></a>`;
        } else if (status.key === 'completed') {
            footerBtn = `<a href="/student/projects/submit.html?project_id=${project.id}" class="action-btn completed-btn"><i class="fas fa-check-circle"></i> Completed</a>`;
        } else if (status.key === 'returned') {
            footerBtn = `<a href="/student/projects/submit.html?project_id=${project.id}" class="action-btn returned-btn"><i class="fas fa-redo"></i> Revise</a>`;
        }

        return `
            <div class="project-card-custom ${!isUnlocked ? 'project-locked' : ''} project-status-${status.key}"
                 data-project-id="${project.id}"
                 data-course-id="${course.id}"
                 data-unlocked="${isUnlocked}">
                <div class="project-header">
                    <div class="project-icon">📋</div>
                    ${lockOverlay}
                </div>
                <div class="project-content">
                    <h3 class="project-title">${this.escHtml(project.title)}</h3>
                    <div class="project-meta">
                        ${statusBadge}
                        ${moduleBadge}
                    </div>
                    <p class="project-description">${this.escHtml(project.description || 'No description available.')}</p>
                    <div class="project-footer">
                        <span class="project-dates">
                            <small>${dueDate}${maxScore ? ' &nbsp;|&nbsp; ' + maxScore : ''}</small>
                            ${gradedScore}
                        </span>
                        ${footerBtn}
                    </div>
                </div>
            </div>`;
    }

    // -------------------------------------------------------------------------
    // Modal
    // -------------------------------------------------------------------------

    openModal(project, course) {
        const modal = document.getElementById('projectModal');
        if (!modal) return;

        const status = this.getProjectStatus(project);
        const dueDate = project.due_date
            ? new Date(project.due_date).toLocaleDateString('en-ZA', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
            : 'No due date set';

        document.getElementById('modalTitle').textContent = project.title;
        document.getElementById('modalMeta').innerHTML = `
            ${this.renderStatusBadge(status, project)}
            ${project.module_title ? `<span class="meta-badge module-badge"><i class="fas fa-book"></i> ${this.escHtml(project.module_title)}</span>` : ''}
            ${project.max_score != null ? `<span class="meta-badge duration-badge">Max score: ${project.max_score}</span>` : ''}
        `;

        const detailedGuideBtn = document.getElementById('detailedGuideBtn');
        if (detailedGuideBtn) detailedGuideBtn.style.display = 'none';

        const requirements = project.requirements
            ? `<div class="modal-section">
                   <h3>Requirements</h3>
                   <p>${this.escHtml(project.requirements).replace(/\n/g, '<br>')}</p>
               </div>`
            : '';

        document.getElementById('modalBody').innerHTML = `
            <div class="modal-section">
                <h3>Description</h3>
                <p>${this.escHtml(project.description || 'No description available.')}</p>
            </div>
            ${requirements}
            <div class="modal-section">
                <h3>Details</h3>
                <ul>
                    <li>Due Date: ${dueDate}</li>
                    <li>Maximum Score: ${project.max_score != null ? project.max_score : 'Not specified'}</li>
                    <li>Module: ${this.escHtml(project.module_title || 'N/A')}</li>
                    <li>Course: ${this.escHtml(course.title)}</li>
                </ul>
            </div>
            <div class="modal-section" style="text-align:center;">
                <a href="/student/projects/submit.html?project_id=${project.id}" class="action-btn start-btn" style="display:inline-block;margin-top:.5rem;">
                    ${status.key === 'not_started' ? 'Start Project' : 'View Submission'}
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        `;

        modal.style.display = 'block';
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * A project is unlocked if:
     * - It has no module_id (standalone), OR
     * - The server flagged quiz_passed = true (quiz passed or no quiz exists), OR
     * - The student already has a submission (never re-lock after submission)
     */
    isProjectUnlocked(project, course) {
        if (!project.module_id) return true;
        if (project.user_submission !== null && project.user_submission !== undefined) return true;
        return project.quiz_passed === true;
    }

    /**
     * Derive project status from user_submission field.
     * Returns { key, label, icon }
     */
    getProjectStatus(project) {
        const sub = project.user_submission;
        if (!sub) return { key: 'not_started', label: 'Not Started', icon: 'fa-circle' };
        if (sub.status === 'graded') return { key: 'completed', label: 'Completed', icon: 'fa-check-circle' };
        if (sub.status === 'returned') return { key: 'returned', label: 'Returned', icon: 'fa-undo' };
        return { key: 'in_progress', label: 'In Progress', icon: 'fa-spinner' };
    }

    renderStatusBadge(status, project) {
        const colorMap = {
            not_started: 'status-not-started',
            in_progress: 'status-in-progress',
            completed: 'status-completed',
            returned: 'status-returned'
        };
        const cls = colorMap[status.key] || 'status-not-started';
        return `<span class="meta-badge ${cls}"><i class="fas ${status.icon}"></i> ${status.label}</span>`;
    }

    escHtml(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // -------------------------------------------------------------------------
    // UI states
    // -------------------------------------------------------------------------

    showLoadingState() {
        const grid = document.getElementById('projectsGrid');
        if (grid) {
            grid.innerHTML = `
                <div class="loading-state">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading your projects&hellip;</p>
                </div>`;
        }
    }

    showLoginPrompt() {
        const loginSection = document.getElementById('login-prompt');
        if (loginSection) loginSection.style.display = 'flex';

        const filtersContainer = document.getElementById('filters-container');
        if (filtersContainer) filtersContainer.style.display = 'none';

        const grid = document.getElementById('projectsGrid');
        if (grid) grid.innerHTML = '';
    }

    showError(message) {
        const grid = document.getElementById('projectsGrid');
        if (grid) {
            grid.innerHTML = `
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <p>${message}</p>
                    <button onclick="window.location.reload()" class="action-btn start-btn">Retry</button>
                </div>`;
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    const studentProjects = new StudentProjects();
    studentProjects.init();
});
