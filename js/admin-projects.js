/**
 * Admin Project Management
 * Handles CRUD operations for projects
 */

const AdminProjects = {
    projects: [],
    modules: [],
    courses: [],
    currentProjectId: null,
    selectedCourseId: null,
    selectedModuleId: null,
    searchTerm: '',

    /**
     * Initialize the project management interface
     */
    async init() {
        console.log('AdminProjects: Initializing...');

        const user = Auth.getUser();
        if (!user || !Auth.canManageContent()) {
            console.error('AdminProjects: Unauthorized access');
            window.location.href = '/public/403.html';
            return;
        }

        await this.loadCourses();
        await this.loadModules();
        await this.loadProjects();
        this.setupEventListeners();

        console.log('AdminProjects: Initialization complete');
    },

    /**
     * Load courses
     */
    async loadCourses() {
        try {
            const response = await API.get('/courses?published=false');
            this.courses = response.data?.items || [];
            this.populateCourseDropdowns();
        } catch (error) {
            console.error('AdminProjects: Error loading courses:', error);
        }
    },

    /**
     * Load modules
     */
    async loadModules() {
        try {
            const response = await API.get('/modules');
            this.modules = response.data?.items || [];
            this.populateModuleDropdowns();
        } catch (error) {
            console.error('AdminProjects: Error loading modules:', error);
            this.showError('Failed to load modules: ' + error.message);
        }
    },

    /**
     * Populate course dropdown selects
     */
    populateCourseDropdowns() {
        const filterDropdown = document.getElementById('filter-course');
        const formDropdown = document.getElementById('project-course');

        let filterOptions = '<option value="">All Courses</option>';
        let formOptions = '<option value="">Select a course...</option>';

        this.courses.forEach(course => {
            filterOptions += `<option value="${course.id}">${this.escapeHtml(course.title)}</option>`;
            formOptions += `<option value="${course.id}">${this.escapeHtml(course.title)}</option>`;
        });

        filterDropdown.innerHTML = filterOptions;
        formDropdown.innerHTML = formOptions;
    },

    /**
     * Populate module dropdown selects
     */
    populateModuleDropdowns() {
        const filterDropdown = document.getElementById('filter-module');
        const formDropdown = document.getElementById('project-module');

        let filterOptions = '<option value="">All Modules</option>';
        let formOptions = '<option value="">No specific module</option>';

        this.modules.forEach(module => {
            const course = this.courses.find(c => c.id === module.course_id);
            const label = course
                ? `${this.escapeHtml(course.title)} - ${this.escapeHtml(module.title)}`
                : this.escapeHtml(module.title);

            filterOptions += `<option value="${module.id}">${label}</option>`;
            formOptions += `<option value="${module.id}">${label}</option>`;
        });

        filterDropdown.innerHTML = filterOptions;
        formDropdown.innerHTML = formOptions;
    },

    /**
     * Update form module dropdown based on selected course
     */
    updateFormModules() {
        const courseId = parseInt(document.getElementById('project-course').value);
        const formDropdown = document.getElementById('project-module');

        let formOptions = '<option value="">No specific module</option>';

        const filtered = courseId
            ? this.modules.filter(m => m.course_id === courseId)
            : this.modules;

        filtered.forEach(module => {
            const course = this.courses.find(c => c.id === module.course_id);
            const label = course
                ? `${this.escapeHtml(course.title)} - ${this.escapeHtml(module.title)}`
                : this.escapeHtml(module.title);
            formOptions += `<option value="${module.id}">${label}</option>`;
        });

        formDropdown.innerHTML = formOptions;
    },

    /**
     * Load projects from API
     */
    async loadProjects() {
        try {
            let params = '?published=false';
            if (this.selectedCourseId) {
                params += `&course_id=${this.selectedCourseId}`;
            }
            if (this.selectedModuleId) {
                params += `&module_id=${this.selectedModuleId}`;
            }

            const response = await API.get(`/projects${params}`);
            this.projects = response.data?.items || [];

            this.renderProjects();
        } catch (error) {
            console.error('AdminProjects: Error loading projects:', error);
            this.showError('Failed to load projects: ' + error.message);
        }
    },

    /**
     * Render projects list
     */
    renderProjects() {
        const container = document.getElementById('projects-list');
        if (!container) return;

        let filtered = this.projects;

        // Apply client-side search filter
        if (this.searchTerm) {
            const term = this.searchTerm.toLowerCase();
            filtered = filtered.filter(p =>
                (p.title && p.title.toLowerCase().includes(term)) ||
                (p.description && p.description.toLowerCase().includes(term))
            );
        }

        if (filtered.length === 0) {
            container.innerHTML = this.getEmptyState();
            return;
        }

        let html = '<div class="quiz-questions-list">';

        filtered.forEach(project => {
            const module = this.modules.find(m => m.id === project.module_id);
            const course = this.courses.find(c => c.id === project.course_id);

            const statusBadge = project.is_published
                ? '<span class="status-badge published">Published</span>'
                : '<span class="status-badge draft">Draft</span>';

            const isOverdue = project.due_date && new Date(project.due_date) < new Date();
            const dueBadge = project.due_date
                ? `<span class="status-badge ${isOverdue ? 'draft' : 'published'}">${isOverdue ? 'Overdue' : 'Due'}: ${this.formatDate(project.due_date)}</span>`
                : '';

            html += `
                <div class="question-item">
                    <div class="question-header">
                        <div class="question-text">
                            <h3 style="margin: 0 0 0.5rem 0;">${this.escapeHtml(project.title)}</h3>
                            ${project.description ? `<p style="color: #666; margin: 0; max-width: 600px;">${this.escapeHtml(project.description).substring(0, 150)}${project.description.length > 150 ? '...' : ''}</p>` : ''}
                        </div>
                        <div>
                            ${statusBadge}
                            ${dueBadge}
                        </div>
                    </div>
                    <div class="module-meta" style="margin: 1rem 0;">
                        ${course ? `<span><i class="fas fa-book"></i> ${this.escapeHtml(course.title)}</span>` : ''}
                        ${module ? `<span><i class="fas fa-layer-group"></i> ${this.escapeHtml(module.title)}</span>` : '<span><i class="fas fa-layer-group"></i> No module</span>'}
                        <span><i class="fas fa-star"></i> Max Score: ${project.max_score || 100}</span>
                        ${project.statistics ? `<span><i class="fas fa-users"></i> ${project.statistics.total_submissions || 0} submissions</span>` : ''}
                    </div>
                    ${project.requirements ? `
                    <div style="margin: 0.5rem 0; padding: 0.5rem; background: #f8f9fa; border-radius: 4px; font-size: 0.85rem;">
                        <strong><i class="fas fa-clipboard-check"></i> Requirements:</strong> ${this.escapeHtml(project.requirements).substring(0, 200)}${project.requirements.length > 200 ? '...' : ''}
                    </div>` : ''}
                    <div class="lesson-actions" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e0e0e0;">
                        <button class="action-btn view" onclick="AdminProjects.viewProject(${project.id})" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="action-btn edit" onclick="AdminProjects.editProject(${project.id})" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="action-btn ${project.is_published ? 'unpublish' : 'publish'}"
                                onclick="AdminProjects.togglePublish(${project.id})"
                                title="${project.is_published ? 'Unpublish' : 'Publish'}">
                            <i class="fas fa-${project.is_published ? 'eye-slash' : 'check'}"></i>
                        </button>
                        <button class="action-btn delete" onclick="AdminProjects.deleteProject(${project.id})" title="Delete">
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
                <div class="empty-icon"><i class="fas fa-project-diagram"></i></div>
                <h3>No Projects Found</h3>
                <p>Create your first project to give students hands-on assignments.</p>
                <button class="btn-primary" onclick="AdminProjects.showCreateModal()">
                    <i class="fas fa-plus"></i> Create Project
                </button>
            </div>
        `;
    },

    /**
     * Show create project modal
     */
    showCreateModal() {
        this.currentProjectId = null;
        document.getElementById('modal-title').textContent = 'Create Project';
        document.getElementById('project-form').reset();
        document.getElementById('project-id').value = '';
        document.getElementById('project-published').checked = true;

        this.showModal('project-modal');
    },

    /**
     * View project details
     */
    async viewProject(projectId) {
        try {
            const response = await API.get(`/projects/${projectId}`);
            const project = response.data?.project || response.data;

            const module = this.modules.find(m => m.id === project.module_id);
            const course = this.courses.find(c => c.id === project.course_id);

            const details = `
Project Details:

Title: ${project.title}
Course: ${course?.title || 'Unknown'}
Module: ${module?.title || 'None'}
Description: ${project.description || 'N/A'}
Requirements: ${project.requirements || 'None'}
Max Score: ${project.max_score || 100}
Due Date: ${project.due_date ? this.formatDate(project.due_date) : 'No deadline'}
Status: ${project.is_published ? 'Published' : 'Draft'}
Created: ${this.formatDate(project.created_at)}
            `.trim();

            alert(details);
        } catch (error) {
            this.showError('Failed to load project details: ' + error.message);
        }
    },

    /**
     * Edit project
     */
    async editProject(projectId) {
        try {
            const response = await API.get(`/projects/${projectId}`);
            const project = response.data?.project || response.data;

            this.currentProjectId = projectId;
            document.getElementById('modal-title').textContent = 'Edit Project';

            // Populate form
            document.getElementById('project-id').value = project.id;
            document.getElementById('project-course').value = project.course_id || '';
            this.updateFormModules();
            document.getElementById('project-module').value = project.module_id || '';
            document.getElementById('project-title').value = project.title || '';
            document.getElementById('project-description').value = project.description || '';
            document.getElementById('project-requirements').value = project.requirements || '';
            document.getElementById('project-max-score').value = project.max_score || 100;
            document.getElementById('project-due-date').value = project.due_date ? project.due_date.split('T')[0] : '';
            document.getElementById('project-published').checked = !!project.is_published;

            this.showModal('project-modal');
        } catch (error) {
            this.showError('Failed to load project: ' + error.message);
        }
    },

    /**
     * Delete project
     */
    async deleteProject(projectId) {
        const project = this.projects.find(p => p.id === projectId);
        if (!project) return;

        const confirmed = confirm(
            `Are you sure you want to delete "${project.title}"?\n\n` +
            `This will also delete all project submissions.\n` +
            `This action cannot be undone.`
        );

        if (!confirmed) return;

        try {
            await API.delete(`/projects/${projectId}`);
            alert('Project deleted successfully!');
            await this.loadProjects();
        } catch (error) {
            this.showError('Failed to delete project: ' + error.message);
        }
    },

    /**
     * Toggle publish status
     */
    async togglePublish(projectId) {
        const project = this.projects.find(p => p.id === projectId);
        if (!project) return;

        try {
            const newStatus = !project.is_published;
            await API.put(`/projects/${projectId}`, {
                is_published: newStatus
            });

            alert(`Project ${newStatus ? 'published' : 'unpublished'} successfully!`);
            await this.loadProjects();
        } catch (error) {
            this.showError('Failed to update project status: ' + error.message);
        }
    },

    /**
     * Save project (create or update)
     */
    async saveProject(event) {
        event.preventDefault();

        const form = document.getElementById('project-form');
        const formData = new FormData(form);

        const title = formData.get('title');
        const slug = title.toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .trim();

        const projectData = {
            course_id: parseInt(formData.get('course_id')),
            module_id: formData.get('module_id') ? parseInt(formData.get('module_id')) : null,
            title: title,
            slug: slug,
            description: formData.get('description') || null,
            requirements: formData.get('requirements') || null,
            max_score: parseInt(formData.get('max_score')) || 100,
            due_date: formData.get('due_date') || null,
            is_published: document.getElementById('project-published').checked
        };

        if (!projectData.course_id) {
            this.showError('Please select a course');
            return;
        }

        try {
            if (this.currentProjectId) {
                await API.put(`/projects/${this.currentProjectId}`, projectData);
                alert('Project updated successfully!');
            } else {
                await API.post('/projects', projectData);
                alert('Project created successfully!');
            }

            this.hideModal('project-modal');
            await this.loadProjects();
        } catch (error) {
            this.showError('Failed to save project: ' + error.message);
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
        if (modalId === 'project-modal') {
            document.getElementById('project-form').reset();
            this.currentProjectId = null;
        }
    },

    /**
     * Apply filters
     */
    applyFilters() {
        this.selectedCourseId = document.getElementById('filter-course').value
            ? parseInt(document.getElementById('filter-course').value)
            : null;
        this.selectedModuleId = document.getElementById('filter-module').value
            ? parseInt(document.getElementById('filter-module').value)
            : null;
        this.loadProjects();
    },

    /**
     * Set up event listeners
     */
    setupEventListeners() {
        // Create project button
        document.getElementById('create-project-btn').addEventListener('click', () => this.showCreateModal());

        // Close modal
        document.getElementById('close-modal').addEventListener('click', () => this.hideModal('project-modal'));
        document.getElementById('cancel-btn').addEventListener('click', () => this.hideModal('project-modal'));

        // Close modal on outside click
        window.addEventListener('click', (event) => {
            if (event.target.id === 'project-modal') {
                this.hideModal('project-modal');
            }
        });

        // Form submission
        document.getElementById('project-form').addEventListener('submit', (e) => this.saveProject(e));

        // Course filter
        document.getElementById('filter-course').addEventListener('change', () => this.applyFilters());

        // Module filter
        document.getElementById('filter-module').addEventListener('change', () => this.applyFilters());

        // Search filter (debounced)
        let searchTimeout;
        document.getElementById('filter-search').addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.searchTerm = e.target.value.trim();
                this.renderProjects();
            }, 300);
        });

        // Update modules dropdown when course changes in the form
        document.getElementById('project-course').addEventListener('change', () => this.updateFormModules());
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
    document.addEventListener('DOMContentLoaded', () => AdminProjects.init());
} else {
    AdminProjects.init();
}
