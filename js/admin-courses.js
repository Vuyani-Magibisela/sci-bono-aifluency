/**
 * Admin Course Management Module
 * Handles CRUD operations for courses
 */

const AdminCourses = {
    courses: [],
    currentCourseId: null,
    filters: {
        status: 'all',
        search: ''
    },

    /**
     * Initialize the course management interface
     */
    async init() {
        console.log('AdminCourses: Initializing...');

        // Ensure user is authenticated and has admin role
        const user = Auth.getUser();
        if (!user || !Auth.canManageContent()) {
            console.error('AdminCourses: Unauthorized access');
            window.location.href = '/public/403.html';
            return;
        }

        // Load courses
        await this.loadCourses();

        // Set up event listeners
        this.setupEventListeners();

        console.log('AdminCourses: Initialization complete');
    },

    /**
     * Load courses from API
     */
    async loadCourses() {
        try {
            const queryParams = new URLSearchParams({
                published: this.filters.status === 'all' ? '' : (this.filters.status === 'published' ? 'true' : 'false'),
                search: this.filters.search
            });

            // Remove empty params
            if (!this.filters.search) queryParams.delete('search');
            if (this.filters.status === 'all') queryParams.delete('published');

            const response = await API.get(`/courses?${queryParams.toString()}`);
            this.courses = response.data?.items || [];

            this.renderCourses();
        } catch (error) {
            console.error('AdminCourses: Error loading courses:', error);
            this.showError('Failed to load courses: ' + error.message);
        }
    },

    /**
     * Render courses list
     */
    renderCourses() {
        const container = document.getElementById('courses-list');
        if (!container) return;

        if (this.courses.length === 0) {
            container.innerHTML = this.getEmptyState();
            return;
        }

        let html = `
            <div class="courses-grid">
        `;

        this.courses.forEach(course => {
            const statusBadge = course.is_published
                ? '<span class="status-badge published">Published</span>'
                : '<span class="status-badge draft">Draft</span>';

            const featuredBadge = course.is_featured
                ? '<span class="status-badge featured">Featured</span>'
                : '';

            html += `
                <div class="course-card" data-course-id="${course.id}">
                    <div class="course-card-header">
                        ${course.thumbnail_url
                            ? `<img src="${this.escapeHtml(course.thumbnail_url)}" alt="${this.escapeHtml(course.title)}" class="course-thumbnail">`
                            : '<div class="course-thumbnail-placeholder"><i class="fas fa-book"></i></div>'}
                    </div>
                    <div class="course-card-body">
                        <div class="course-badges">
                            ${statusBadge}
                            ${featuredBadge}
                        </div>
                        <h3>${this.escapeHtml(course.title)}</h3>
                        <p class="course-description">${this.escapeHtml(course.description || '')}</p>
                        <div class="course-meta">
                            <span><i class="fas fa-signal"></i> ${this.escapeHtml(course.difficulty_level || 'N/A')}</span>
                            <span><i class="fas fa-clock"></i> ${course.duration_hours || 0}h</span>
                            <span><i class="fas fa-layer-group"></i> ${course.module_count || 0} modules</span>
                        </div>
                    </div>
                    <div class="course-card-actions">
                        <button class="action-btn view" onclick="AdminCourses.viewCourse(${course.id})" title="View Details">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <button class="action-btn edit" onclick="AdminCourses.editCourse(${course.id})" title="Edit Course">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="action-btn ${course.is_published ? 'unpublish' : 'publish'}"
                                onclick="AdminCourses.togglePublish(${course.id})"
                                title="${course.is_published ? 'Unpublish' : 'Publish'}">
                            <i class="fas fa-${course.is_published ? 'eye-slash' : 'check'}"></i>
                            ${course.is_published ? 'Unpublish' : 'Publish'}
                        </button>
                        <button class="action-btn delete" onclick="AdminCourses.deleteCourse(${course.id})" title="Delete Course">
                            <i class="fas fa-trash"></i> Delete
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
                <div class="empty-icon"><i class="fas fa-book"></i></div>
                <h3>No Courses Found</h3>
                <p>Create your first course to get started.</p>
                <button class="btn-primary" onclick="AdminCourses.showCreateModal()">
                    <i class="fas fa-plus"></i> Create Course
                </button>
            </div>
        `;
    },

    /**
     * Show create course modal
     */
    showCreateModal() {
        this.currentCourseId = null;
        document.getElementById('modal-title').textContent = 'Create Course';
        document.getElementById('course-form').reset();
        document.getElementById('course-id').value = '';

        // Auto-generate slug from title
        const titleInput = document.getElementById('course-title');
        const slugInput = document.getElementById('course-slug');

        titleInput.addEventListener('input', function() {
            if (!AdminCourses.currentCourseId) {
                slugInput.value = AdminCourses.generateSlug(this.value);
            }
        });

        this.showModal();
    },

    /**
     * View course details
     */
    async viewCourse(courseId) {
        console.log('AdminCourses.viewCourse called with courseId:', courseId);
        try {
            console.log('Fetching course data...');
            const response = await API.get(`/courses/${courseId}`);
            console.log('API response:', response);
            const course = response.data?.course || response.data;
            console.log('Course data:', course);

            this.currentCourseId = courseId;

            // Build details HTML
            const detailsHTML = `
                <div class="course-details">
                    <div class="detail-group">
                        <h3><i class="fas fa-book"></i> Course Information</h3>
                        <div class="detail-item">
                            <strong>Title:</strong>
                            <span>${course.title}</span>
                        </div>
                        <div class="detail-item">
                            <strong>Slug:</strong>
                            <span>${course.slug || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <strong>Description:</strong>
                            <p>${course.description || 'No description'}</p>
                        </div>
                    </div>

                    <div class="detail-group">
                        <h3><i class="fas fa-info-circle"></i> Details</h3>
                        <div class="detail-item">
                            <strong>Difficulty Level:</strong>
                            <span class="badge badge-${course.difficulty_level}">${this.capitalize(course.difficulty_level)}</span>
                        </div>
                        <div class="detail-item">
                            <strong>Duration:</strong>
                            <span>${course.duration_hours} hours</span>
                        </div>
                        <div class="detail-item">
                            <strong>Status:</strong>
                            <span class="badge ${course.is_published ? 'badge-success' : 'badge-warning'}">
                                ${course.is_published ? 'Published' : 'Draft'}
                            </span>
                        </div>
                        <div class="detail-item">
                            <strong>Featured:</strong>
                            <span>${course.is_featured ? '⭐ Yes' : 'No'}</span>
                        </div>
                    </div>

                    <div class="detail-group">
                        <h3><i class="fas fa-layer-group"></i> Modules</h3>
                        <div class="detail-item">
                            <strong>Total Modules:</strong>
                            <span>${course.modules?.length || 0}</span>
                        </div>
                        ${course.modules && course.modules.length > 0 ? `
                            <div class="modules-list">
                                ${course.modules.map(module => `
                                    <div class="module-item">
                                        <i class="fas fa-layer-group"></i>
                                        ${module.title}
                                    </div>
                                `).join('')}
                            </div>
                        ` : '<p class="text-muted">No modules yet</p>'}
                    </div>

                    <div class="detail-group">
                        <h3><i class="fas fa-calendar"></i> Timestamps</h3>
                        <div class="detail-item">
                            <strong>Created:</strong>
                            <span>${this.formatDate(course.created_at)}</span>
                        </div>
                        <div class="detail-item">
                            <strong>Last Updated:</strong>
                            <span>${this.formatDate(course.updated_at)}</span>
                        </div>
                    </div>
                </div>
            `;

            const detailsElement = document.getElementById('course-details-content');
            const modalElement = document.getElementById('course-details-modal');

            console.log('Details element:', detailsElement);
            console.log('Modal element:', modalElement);

            if (!detailsElement || !modalElement) {
                console.error('Modal elements not found!');
                alert('Error: Modal elements not found in the page. Please refresh.');
                return;
            }

            detailsElement.innerHTML = detailsHTML;
            modalElement.classList.add('show');
            console.log('Modal should now be visible');
        } catch (error) {
            console.error('ViewCourse error:', error);
            this.showError('Failed to load course details: ' + error.message);
        }
    },

    /**
     * Close details modal
     */
    closeDetailsModal() {
        document.getElementById('course-details-modal').classList.remove('show');
        // Don't clear currentCourseId here - it's needed for Edit button
        // this.currentCourseId = null;
    },

    /**
     * Edit course from details modal
     */
    async editCourseFromDetails() {
        console.log('editCourseFromDetails called, currentCourseId:', this.currentCourseId);
        if (this.currentCourseId) {
            const courseIdToEdit = this.currentCourseId; // Store it before closing
            this.closeDetailsModal();
            // Small delay to ensure modal is closed before opening edit modal
            setTimeout(() => {
                this.editCourse(courseIdToEdit);
            }, 100);
        } else {
            console.error('No currentCourseId set!');
            alert('Error: Course ID not found. Please try clicking View again.');
        }
    },

    /**
     * Edit course
     */
    async editCourse(courseId) {
        console.log('editCourse called with courseId:', courseId);
        try {
            console.log('Fetching course for edit:', courseId);
            const response = await API.get(`/courses/${courseId}`);
            console.log('Edit course API response:', response);

            const course = response.data?.course || response.data;
            console.log('Parsed course data:', course);

            if (!course || !course.id) {
                throw new Error('Invalid course data received from API');
            }

            this.currentCourseId = courseId;
            document.getElementById('modal-title').textContent = 'Edit Course';

            // Populate form
            document.getElementById('course-id').value = course.id;
            document.getElementById('course-title').value = course.title || '';
            document.getElementById('course-slug').value = course.slug || '';
            document.getElementById('course-description').value = course.description || '';
            document.getElementById('course-difficulty').value = course.difficulty_level || '';
            document.getElementById('course-duration').value = course.duration_hours || '';
            document.getElementById('course-thumbnail').value = course.thumbnail_url || '';
            document.getElementById('course-featured').checked = course.is_featured ? true : false;
            document.getElementById('course-published').checked = course.is_published ? true : false;

            this.showModal();
            console.log('Edit modal opened successfully');
        } catch (error) {
            console.error('EditCourse error:', error);
            console.error('Error details:', {
                message: error.message,
                response: error.response,
                stack: error.stack
            });
            this.showError('Failed to load course: ' + (error.response?.data?.message || error.message));
        }
    },

    /**
     * Delete course
     */
    async deleteCourse(courseId) {
        const course = this.courses.find(c => c.id === courseId);
        if (!course) return;

        const confirmed = confirm(
            `Are you sure you want to delete "${course.title}"?\n\n` +
            `This will also delete all associated modules, lessons, and quizzes.\n` +
            `This action cannot be undone.`
        );

        if (!confirmed) return;

        try {
            await API.delete(`/courses/${courseId}`);
            alert('Course deleted successfully!');
            await this.loadCourses();
        } catch (error) {
            this.showError('Failed to delete course: ' + error.message);
        }
    },

    /**
     * Toggle publish status
     */
    async togglePublish(courseId) {
        const course = this.courses.find(c => c.id === courseId);
        if (!course) return;

        try {
            const newStatus = !course.is_published;
            await API.put(`/courses/${courseId}`, {
                is_published: newStatus
            });

            alert(`Course ${newStatus ? 'published' : 'unpublished'} successfully!`);
            await this.loadCourses();
        } catch (error) {
            this.showError('Failed to update course status: ' + error.message);
        }
    },

    /**
     * Save course (create or update)
     */
    async saveCourse(event) {
        event.preventDefault();

        const form = document.getElementById('course-form');
        const formData = new FormData(form);

        const courseData = {
            title: formData.get('title'),
            slug: formData.get('slug'),
            description: formData.get('description'),
            difficulty_level: formData.get('difficulty_level'),
            duration_hours: parseFloat(formData.get('duration_hours')),
            thumbnail_url: formData.get('thumbnail_url') || null,
            is_featured: document.getElementById('course-featured').checked,
            is_published: document.getElementById('course-published').checked
        };

        try {
            if (this.currentCourseId) {
                // Update existing course
                await API.put(`/courses/${this.currentCourseId}`, courseData);
                alert('Course updated successfully!');
            } else {
                // Create new course
                await API.post('/courses', courseData);
                alert('Course created successfully!');
            }

            this.hideModal();
            await this.loadCourses();
        } catch (error) {
            this.showError('Failed to save course: ' + error.message);
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
        document.getElementById('course-modal').style.display = 'flex';
    },

    /**
     * Hide modal
     */
    hideModal() {
        document.getElementById('course-modal').style.display = 'none';
        document.getElementById('course-form').reset();
        this.currentCourseId = null;
    },

    /**
     * Apply filters
     */
    applyFilters() {
        this.filters.status = document.getElementById('filter-status').value;
        this.filters.search = document.getElementById('filter-search').value.trim();
        this.loadCourses();
    },

    /**
     * Clear filters
     */
    clearFilters() {
        document.getElementById('filter-status').value = 'all';
        document.getElementById('filter-search').value = '';
        this.filters = { status: 'all', search: '' };
        this.loadCourses();
    },

    /**
     * Set up event listeners
     */
    setupEventListeners() {
        // Create course button
        const createBtn = document.getElementById('create-course-btn');
        if (createBtn) {
            createBtn.addEventListener('click', () => this.showCreateModal());
        }

        // Close modal
        document.getElementById('close-modal').addEventListener('click', () => this.hideModal());
        document.getElementById('cancel-btn').addEventListener('click', () => this.hideModal());

        // Close modal on outside click (both modals)
        window.addEventListener('click', (event) => {
            const modal = document.getElementById('course-modal');
            const detailsModal = document.getElementById('course-details-modal');

            if (event.target === modal) {
                this.hideModal();
            }
            if (event.target === detailsModal) {
                this.closeDetailsModal();
            }
        });

        // Form submission
        document.getElementById('course-form').addEventListener('submit', (e) => this.saveCourse(e));

        // Filters
        document.getElementById('filter-status').addEventListener('change', () => this.applyFilters());
        document.getElementById('filter-search').addEventListener('input',
            this.debounce(() => this.applyFilters(), 500)
        );
        document.getElementById('clear-filters').addEventListener('click', () => this.clearFilters());
    },

    /**
     * Debounce function for search input
     */
    debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
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
    },

    /**
     * Capitalize first letter of string
     */
    capitalize(str) {
        if (!str) return '';
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
};

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => AdminCourses.init());
} else {
    AdminCourses.init();
}
