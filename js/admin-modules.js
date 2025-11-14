/**
 * Admin Module Management
 * Handles CRUD operations for modules with drag-and-drop reordering
 */

const AdminModules = {
    modules: [],
    courses: [],
    currentModuleId: null,
    selectedCourseId: null,
    draggedElement: null,

    /**
     * Initialize the module management interface
     */
    async init() {
        console.log('AdminModules: Initializing...');

        // Ensure user is authenticated and has admin role
        const user = Auth.getUser();
        if (!user || user.role !== 'admin') {
            console.error('AdminModules: Unauthorized access');
            window.location.href = '/403.html';
            return;
        }

        // Load courses first
        await this.loadCourses();

        // Load modules
        await this.loadModules();

        // Set up event listeners
        this.setupEventListeners();

        console.log('AdminModules: Initialization complete');
    },

    /**
     * Load courses for filter dropdown
     */
    async loadCourses() {
        try {
            const response = await API.get('/courses?published=false');
            this.courses = response.data?.items || [];

            // Populate course dropdowns
            this.populateCourseDropdowns();

            // Set first course as default filter
            if (this.courses.length > 0) {
                this.selectedCourseId = this.courses[0].id;
                document.getElementById('filter-course').value = this.selectedCourseId;
            }
        } catch (error) {
            console.error('AdminModules: Error loading courses:', error);
            this.showError('Failed to load courses: ' + error.message);
        }
    },

    /**
     * Populate course dropdown selects
     */
    populateCourseDropdowns() {
        const filterDropdown = document.getElementById('filter-course');
        const formDropdown = document.getElementById('module-course');

        let options = '<option value="">All Courses</option>';
        let formOptions = '<option value="">Select a course...</option>';

        this.courses.forEach(course => {
            options += `<option value="${course.id}">${this.escapeHtml(course.title)}</option>`;
            formOptions += `<option value="${course.id}">${this.escapeHtml(course.title)}</option>`;
        });

        filterDropdown.innerHTML = options;
        formDropdown.innerHTML = formOptions;
    },

    /**
     * Load modules from API
     */
    async loadModules() {
        try {
            const courseParam = this.selectedCourseId ? `?course_id=${this.selectedCourseId}` : '';
            const response = await API.get(`/modules${courseParam}`);
            this.modules = response.data?.items || [];

            // Sort by order_index
            this.modules.sort((a, b) => a.order_index - b.order_index);

            this.renderModules();
        } catch (error) {
            console.error('AdminModules: Error loading modules:', error);
            this.showError('Failed to load modules: ' + error.message);
        }
    },

    /**
     * Render modules list with drag-and-drop
     */
    renderModules() {
        const container = document.getElementById('modules-list');
        if (!container) return;

        if (this.modules.length === 0) {
            container.innerHTML = this.getEmptyState();
            return;
        }

        let html = '<div class="modules-list">';

        this.modules.forEach((module, index) => {
            const course = this.courses.find(c => c.id === module.course_id);
            const statusBadge = module.is_published
                ? '<span class="status-badge published">Published</span>'
                : '<span class="status-badge draft">Draft</span>';

            html += `
                <div class="module-item"
                     data-module-id="${module.id}"
                     data-order-index="${module.order_index}"
                     draggable="true">
                    <div class="drag-handle">
                        <i class="fas fa-grip-vertical"></i>
                    </div>
                    <div class="module-info">
                        <h3>${this.escapeHtml(module.title)}</h3>
                        <div class="module-meta">
                            ${statusBadge}
                            <span><i class="fas fa-book"></i> ${this.escapeHtml(course?.title || 'Unknown Course')}</span>
                            <span><i class="fas fa-sort"></i> Order: ${module.order_index}</span>
                            <span><i class="fas fa-file-alt"></i> ${module.lesson_count || 0} lessons</span>
                        </div>
                    </div>
                    <div class="module-actions">
                        <button class="action-btn view" onclick="AdminModules.viewModule(${module.id})" title="View">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="action-btn edit" onclick="AdminModules.editModule(${module.id})" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="action-btn ${module.is_published ? 'unpublish' : 'publish'}"
                                onclick="AdminModules.togglePublish(${module.id})"
                                title="${module.is_published ? 'Unpublish' : 'Publish'}">
                            <i class="fas fa-${module.is_published ? 'eye-slash' : 'check'}"></i>
                        </button>
                        <button class="action-btn delete" onclick="AdminModules.deleteModule(${module.id})" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        });

        html += '</div>';
        container.innerHTML = html;

        // Set up drag-and-drop after rendering
        this.setupDragAndDrop();
    },

    /**
     * Setup drag-and-drop functionality
     */
    setupDragAndDrop() {
        const items = document.querySelectorAll('.module-item');

        items.forEach(item => {
            item.addEventListener('dragstart', (e) => this.handleDragStart(e));
            item.addEventListener('dragover', (e) => this.handleDragOver(e));
            item.addEventListener('drop', (e) => this.handleDrop(e));
            item.addEventListener('dragend', (e) => this.handleDragEnd(e));
        });
    },

    /**
     * Handle drag start
     */
    handleDragStart(e) {
        this.draggedElement = e.target;
        e.target.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/html', e.target.innerHTML);
    },

    /**
     * Handle drag over
     */
    handleDragOver(e) {
        if (e.preventDefault) {
            e.preventDefault();
        }
        e.dataTransfer.dropEffect = 'move';

        const target = e.target.closest('.module-item');
        if (target && target !== this.draggedElement) {
            const bounding = target.getBoundingClientRect();
            const offset = bounding.y + (bounding.height / 2);

            if (e.clientY - offset > 0) {
                target.style.borderBottom = '3px solid #00a8e8';
                target.style.borderTop = '';
            } else {
                target.style.borderTop = '3px solid #00a8e8';
                target.style.borderBottom = '';
            }
        }

        return false;
    },

    /**
     * Handle drop
     */
    async handleDrop(e) {
        if (e.stopPropagation) {
            e.stopPropagation();
        }

        const target = e.target.closest('.module-item');
        if (target && target !== this.draggedElement) {
            const draggedId = parseInt(this.draggedElement.getAttribute('data-module-id'));
            const targetId = parseInt(target.getAttribute('data-module-id'));

            await this.reorderModules(draggedId, targetId);
        }

        return false;
    },

    /**
     * Handle drag end
     */
    handleDragEnd(e) {
        e.target.classList.remove('dragging');

        // Remove all border styles
        document.querySelectorAll('.module-item').forEach(item => {
            item.style.borderTop = '';
            item.style.borderBottom = '';
        });
    },

    /**
     * Reorder modules
     */
    async reorderModules(draggedId, targetId) {
        const draggedModule = this.modules.find(m => m.id === draggedId);
        const targetModule = this.modules.find(m => m.id === targetId);

        if (!draggedModule || !targetModule) return;

        try {
            // Swap order indices
            const draggedOrder = draggedModule.order_index;
            const targetOrder = targetModule.order_index;

            // Update dragged module
            await API.put(`/modules/${draggedId}`, {
                order_index: targetOrder
            });

            // Update target module
            await API.put(`/modules/${targetId}`, {
                order_index: draggedOrder
            });

            // Reload modules
            await this.loadModules();

        } catch (error) {
            this.showError('Failed to reorder modules: ' + error.message);
        }
    },

    /**
     * Show empty state
     */
    getEmptyState() {
        return `
            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-layer-group"></i></div>
                <h3>No Modules Found</h3>
                <p>Create your first module to organize course content.</p>
                <button class="btn-primary" onclick="AdminModules.showCreateModal()">
                    <i class="fas fa-plus"></i> Create Module
                </button>
            </div>
        `;
    },

    /**
     * Show create module modal
     */
    showCreateModal() {
        this.currentModuleId = null;
        document.getElementById('modal-title').textContent = 'Create Module';
        document.getElementById('module-form').reset();
        document.getElementById('module-id').value = '';

        // Set default course if filtered
        if (this.selectedCourseId) {
            document.getElementById('module-course').value = this.selectedCourseId;
        }

        // Calculate next order index
        const maxOrder = this.modules.length > 0
            ? Math.max(...this.modules.map(m => m.order_index))
            : 0;
        document.getElementById('module-order').value = maxOrder + 1;

        // Auto-generate slug from title
        const titleInput = document.getElementById('module-title');
        const slugInput = document.getElementById('module-slug');

        titleInput.addEventListener('input', function() {
            if (!AdminModules.currentModuleId) {
                slugInput.value = AdminModules.generateSlug(this.value);
            }
        });

        this.showModal();
    },

    /**
     * View module details
     */
    async viewModule(moduleId) {
        try {
            const response = await API.get(`/modules/${moduleId}`);
            const module = response.data;

            const course = this.courses.find(c => c.id === module.course_id);

            const details = `
                Module Details:

                Title: ${module.title}
                Course: ${course?.title || 'Unknown'}
                Slug: ${module.slug}
                Description: ${module.description || 'N/A'}
                Order: ${module.order_index}
                Status: ${module.is_published ? 'Published' : 'Draft'}
                Lessons: ${module.lessons?.length || 0}
                Created: ${this.formatDate(module.created_at)}
            `;

            alert(details);
        } catch (error) {
            this.showError('Failed to load module details: ' + error.message);
        }
    },

    /**
     * Edit module
     */
    async editModule(moduleId) {
        try {
            const response = await API.get(`/modules/${moduleId}`);
            const module = response.data;

            this.currentModuleId = moduleId;
            document.getElementById('modal-title').textContent = 'Edit Module';

            // Populate form
            document.getElementById('module-id').value = module.id;
            document.getElementById('module-course').value = module.course_id;
            document.getElementById('module-title').value = module.title;
            document.getElementById('module-slug').value = module.slug;
            document.getElementById('module-description').value = module.description || '';
            document.getElementById('module-order').value = module.order_index;
            document.getElementById('module-published').checked = module.is_published;

            this.showModal();
        } catch (error) {
            this.showError('Failed to load module: ' + error.message);
        }
    },

    /**
     * Delete module
     */
    async deleteModule(moduleId) {
        const module = this.modules.find(m => m.id === moduleId);
        if (!module) return;

        const confirmed = confirm(
            `Are you sure you want to delete "${module.title}"?\n\n` +
            `This will also delete all associated lessons.\n` +
            `This action cannot be undone.`
        );

        if (!confirmed) return;

        try {
            await API.delete(`/modules/${moduleId}`);
            alert('Module deleted successfully!');
            await this.loadModules();
        } catch (error) {
            this.showError('Failed to delete module: ' + error.message);
        }
    },

    /**
     * Toggle publish status
     */
    async togglePublish(moduleId) {
        const module = this.modules.find(m => m.id === moduleId);
        if (!module) return;

        try {
            const newStatus = !module.is_published;
            await API.put(`/modules/${moduleId}`, {
                is_published: newStatus
            });

            alert(`Module ${newStatus ? 'published' : 'unpublished'} successfully!`);
            await this.loadModules();
        } catch (error) {
            this.showError('Failed to update module status: ' + error.message);
        }
    },

    /**
     * Save module (create or update)
     */
    async saveModule(event) {
        event.preventDefault();

        const form = document.getElementById('module-form');
        const formData = new FormData(form);

        const moduleData = {
            course_id: parseInt(formData.get('course_id')),
            title: formData.get('title'),
            slug: formData.get('slug'),
            description: formData.get('description') || null,
            order_index: parseInt(formData.get('order_index')),
            is_published: document.getElementById('module-published').checked
        };

        try {
            if (this.currentModuleId) {
                // Update existing module
                await API.put(`/modules/${this.currentModuleId}`, moduleData);
                alert('Module updated successfully!');
            } else {
                // Create new module
                await API.post('/modules', moduleData);
                alert('Module created successfully!');
            }

            this.hideModal();
            await this.loadModules();
        } catch (error) {
            this.showError('Failed to save module: ' + error.message);
        }
    },

    /**
     * Generate slug from title
     */
    generateSlug(title) {
        return title
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    },

    /**
     * Show modal
     */
    showModal() {
        document.getElementById('module-modal').style.display = 'flex';
    },

    /**
     * Hide modal
     */
    hideModal() {
        document.getElementById('module-modal').style.display = 'none';
        document.getElementById('module-form').reset();
        this.currentModuleId = null;
    },

    /**
     * Apply filters
     */
    applyFilters() {
        this.selectedCourseId = document.getElementById('filter-course').value
            ? parseInt(document.getElementById('filter-course').value)
            : null;
        this.loadModules();
    },

    /**
     * Set up event listeners
     */
    setupEventListeners() {
        // Create module button
        const createBtn = document.getElementById('create-module-btn');
        if (createBtn) {
            createBtn.addEventListener('click', () => this.showCreateModal());
        }

        // Close modal
        document.getElementById('close-modal').addEventListener('click', () => this.hideModal());
        document.getElementById('cancel-btn').addEventListener('click', () => this.hideModal());

        // Close modal on outside click
        window.addEventListener('click', (event) => {
            const modal = document.getElementById('module-modal');
            if (event.target === modal) {
                this.hideModal();
            }
        });

        // Form submission
        document.getElementById('module-form').addEventListener('submit', (e) => this.saveModule(e));

        // Course filter
        document.getElementById('filter-course').addEventListener('change', () => this.applyFilters());
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
    document.addEventListener('DOMContentLoaded', () => AdminModules.init());
} else {
    AdminModules.init();
}
