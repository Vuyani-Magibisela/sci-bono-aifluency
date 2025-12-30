/**
 * Admin Lesson Management
 * Handles CRUD operations for lessons with Quill.js rich text editor
 */

const AdminLessons = {
    lessons: [],
    modules: [],
    courses: [],
    currentLessonId: null,
    selectedModuleId: null,
    quillEditor: null,

    /**
     * Initialize the lesson management interface
     */
    async init() {
        console.log('AdminLessons: Initializing...');

        // Ensure user is authenticated and has admin role
        const user = Auth.getUser();
        if (!user || user.role !== 'admin') {
            console.error('AdminLessons: Unauthorized access');
            window.location.href = '403.html';
            return;
        }

        // Load courses and modules first
        await this.loadCourses();
        await this.loadModules();

        // Load lessons
        await this.loadLessons();

        // Set up event listeners
        this.setupEventListeners();

        console.log('AdminLessons: Initialization complete');
    },

    /**
     * Load courses
     */
    async loadCourses() {
        try {
            const response = await API.get('/courses?published=false');
            this.courses = response.data?.items || [];
        } catch (error) {
            console.error('AdminLessons: Error loading courses:', error);
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
            console.error('AdminLessons: Error loading modules:', error);
            this.showError('Failed to load modules: ' + error.message);
        }
    },

    /**
     * Populate module dropdown selects
     */
    populateModuleDropdowns() {
        const filterDropdown = document.getElementById('filter-module');
        const formDropdown = document.getElementById('lesson-module');

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
     * Load lessons from API
     */
    async loadLessons() {
        try {
            const moduleParam = this.selectedModuleId ? `?module_id=${this.selectedModuleId}` : '';
            const response = await API.get(`/lessons${moduleParam}`);
            this.lessons = response.data?.items || [];

            // Sort by order_index
            this.lessons.sort((a, b) => a.order_index - b.order_index);

            this.renderLessons();
        } catch (error) {
            console.error('AdminLessons: Error loading lessons:', error);
            this.showError('Failed to load lessons: ' + error.message);
        }
    },

    /**
     * Render lessons list
     */
    renderLessons() {
        const container = document.getElementById('lessons-list');
        if (!container) return;

        if (this.lessons.length === 0) {
            container.innerHTML = this.getEmptyState();
            return;
        }

        let html = '<div class="lessons-list">';

        this.lessons.forEach(lesson => {
            const module = this.modules.find(m => m.id === lesson.module_id);
            const course = module ? this.courses.find(c => c.id === module.course_id) : null;

            const statusBadge = lesson.is_published
                ? '<span class="status-badge published">Published</span>'
                : '<span class="status-badge draft">Draft</span>';

            // Truncate content for preview
            const contentPreview = this.stripHtml(lesson.content || '')
                .substring(0, 100) + '...';

            html += `
                <div class="lesson-item" data-lesson-id="${lesson.id}">
                    <div class="lesson-info">
                        <h4>${this.escapeHtml(lesson.title)}</h4>
                        ${lesson.subtitle ? `<p class="lesson-subtitle">${this.escapeHtml(lesson.subtitle)}</p>` : ''}
                        <div class="module-meta" style="margin-top: 0.5rem;">
                            ${statusBadge}
                            ${course ? `<span><i class="fas fa-book"></i> ${this.escapeHtml(course.title)}</span>` : ''}
                            ${module ? `<span><i class="fas fa-layer-group"></i> ${this.escapeHtml(module.title)}</span>` : ''}
                            <span><i class="fas fa-sort"></i> Order: ${lesson.order_index}</span>
                            ${lesson.duration_minutes ? `<span><i class="fas fa-clock"></i> ${lesson.duration_minutes} min</span>` : ''}
                        </div>
                    </div>
                    <div class="lesson-actions">
                        <button class="action-btn view" onclick="AdminLessons.viewLesson(${lesson.id})" title="View">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="action-btn edit" onclick="AdminLessons.editLesson(${lesson.id})" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="action-btn ${lesson.is_published ? 'unpublish' : 'publish'}"
                                onclick="AdminLessons.togglePublish(${lesson.id})"
                                title="${lesson.is_published ? 'Unpublish' : 'Publish'}">
                            <i class="fas fa-${lesson.is_published ? 'eye-slash' : 'check'}"></i>
                        </button>
                        <button class="action-btn delete" onclick="AdminLessons.deleteLesson(${lesson.id})" title="Delete">
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
                <div class="empty-icon"><i class="fas fa-file-alt"></i></div>
                <h3>No Lessons Found</h3>
                <p>Create your first lesson to start building course content.</p>
                <button class="btn-primary" onclick="AdminLessons.showCreateModal()">
                    <i class="fas fa-plus"></i> Create Lesson
                </button>
            </div>
        `;
    },

    /**
     * Initialize Quill editor
     */
    initQuillEditor() {
        const editorContainer = document.getElementById('lesson-content-editor');
        if (!editorContainer) return;

        // Clear any existing editor
        editorContainer.innerHTML = '';

        // Create Quill editor
        this.quillEditor = new Quill('#lesson-content-editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    [{ 'align': [] }],
                    ['blockquote', 'code-block'],
                    ['link', 'image', 'video'],
                    ['clean']
                ]
            },
            placeholder: 'Write your lesson content here...'
        });
    },

    /**
     * Show create lesson modal
     */
    showCreateModal() {
        this.currentLessonId = null;
        document.getElementById('modal-title').textContent = 'Create Lesson';
        document.getElementById('lesson-form').reset();
        document.getElementById('lesson-id').value = '';

        // Set default module if filtered
        if (this.selectedModuleId) {
            document.getElementById('lesson-module').value = this.selectedModuleId;
        }

        // Calculate next order index
        const maxOrder = this.lessons.length > 0
            ? Math.max(...this.lessons.map(l => l.order_index))
            : 0;
        document.getElementById('lesson-order').value = maxOrder + 1;

        // Initialize Quill editor
        this.initQuillEditor();

        // Auto-generate slug from title
        const titleInput = document.getElementById('lesson-title');
        const slugInput = document.getElementById('lesson-slug');

        titleInput.addEventListener('input', function() {
            if (!AdminLessons.currentLessonId) {
                slugInput.value = AdminLessons.generateSlug(this.value);
            }
        });

        this.showModal();
    },

    /**
     * View lesson details
     */
    async viewLesson(lessonId) {
        try {
            const response = await API.get(`/lessons/${lessonId}`);
            const lesson = response.data;

            // Open preview in a new window or show in modal
            const previewWindow = window.open('', '_blank', 'width=800,height=600');
            previewWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>${this.escapeHtml(lesson.title)}</title>
                    <link rel="stylesheet" href="/css/styles.css">
                </head>
                <body style="padding: 2rem;">
                    <h1>${this.escapeHtml(lesson.title)}</h1>
                    ${lesson.subtitle ? `<h2>${this.escapeHtml(lesson.subtitle)}</h2>` : ''}
                    <div>${lesson.content || 'No content'}</div>
                </body>
                </html>
            `);
            previewWindow.document.close();
        } catch (error) {
            this.showError('Failed to load lesson: ' + error.message);
        }
    },

    /**
     * Edit lesson
     */
    async editLesson(lessonId) {
        try {
            const response = await API.get(`/lessons/${lessonId}`);
            const lesson = response.data;

            this.currentLessonId = lessonId;
            document.getElementById('modal-title').textContent = 'Edit Lesson';

            // Populate form
            document.getElementById('lesson-id').value = lesson.id;
            document.getElementById('lesson-module').value = lesson.module_id;
            document.getElementById('lesson-title').value = lesson.title;
            document.getElementById('lesson-subtitle').value = lesson.subtitle || '';
            document.getElementById('lesson-slug').value = lesson.slug;
            document.getElementById('lesson-order').value = lesson.order_index;
            document.getElementById('lesson-duration').value = lesson.duration_minutes || '';
            document.getElementById('lesson-published').checked = lesson.is_published;

            // Initialize Quill editor and set content
            this.initQuillEditor();
            if (lesson.content) {
                this.quillEditor.root.innerHTML = lesson.content;
            }

            this.showModal();
        } catch (error) {
            this.showError('Failed to load lesson: ' + error.message);
        }
    },

    /**
     * Delete lesson
     */
    async deleteLesson(lessonId) {
        const lesson = this.lessons.find(l => l.id === lessonId);
        if (!lesson) return;

        const confirmed = confirm(
            `Are you sure you want to delete "${lesson.title}"?\n\n` +
            `This action cannot be undone.`
        );

        if (!confirmed) return;

        try {
            await API.delete(`/lessons/${lessonId}`);
            alert('Lesson deleted successfully!');
            await this.loadLessons();
        } catch (error) {
            this.showError('Failed to delete lesson: ' + error.message);
        }
    },

    /**
     * Toggle publish status
     */
    async togglePublish(lessonId) {
        const lesson = this.lessons.find(l => l.id === lessonId);
        if (!lesson) return;

        try {
            const newStatus = !lesson.is_published;
            await API.put(`/lessons/${lessonId}`, {
                is_published: newStatus
            });

            alert(`Lesson ${newStatus ? 'published' : 'unpublished'} successfully!`);
            await this.loadLessons();
        } catch (error) {
            this.showError('Failed to update lesson status: ' + error.message);
        }
    },

    /**
     * Save lesson (create or update)
     */
    async saveLesson(event) {
        event.preventDefault();

        const form = document.getElementById('lesson-form');
        const formData = new FormData(form);

        // Get content from Quill editor
        const content = this.quillEditor ? this.quillEditor.root.innerHTML : '';

        const lessonData = {
            module_id: parseInt(formData.get('module_id')),
            title: formData.get('title'),
            subtitle: formData.get('subtitle') || null,
            slug: formData.get('slug'),
            content: content,
            order_index: parseInt(formData.get('order_index')),
            duration_minutes: formData.get('duration_minutes')
                ? parseInt(formData.get('duration_minutes'))
                : null,
            is_published: document.getElementById('lesson-published').checked
        };

        try {
            if (this.currentLessonId) {
                // Update existing lesson
                await API.put(`/lessons/${this.currentLessonId}`, lessonData);
                alert('Lesson updated successfully!');
            } else {
                // Create new lesson
                await API.post('/lessons', lessonData);
                alert('Lesson created successfully!');
            }

            this.hideModal();
            await this.loadLessons();
        } catch (error) {
            this.showError('Failed to save lesson: ' + error.message);
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
     * Strip HTML tags for preview
     */
    stripHtml(html) {
        const tmp = document.createElement('div');
        tmp.innerHTML = html;
        return tmp.textContent || tmp.innerText || '';
    },

    /**
     * Show modal
     */
    showModal() {
        document.getElementById('lesson-modal').style.display = 'flex';
    },

    /**
     * Hide modal
     */
    hideModal() {
        document.getElementById('lesson-modal').style.display = 'none';
        document.getElementById('lesson-form').reset();
        this.currentLessonId = null;
        this.quillEditor = null;
    },

    /**
     * Apply filters
     */
    applyFilters() {
        this.selectedModuleId = document.getElementById('filter-module').value
            ? parseInt(document.getElementById('filter-module').value)
            : null;
        this.loadLessons();
    },

    /**
     * Set up event listeners
     */
    setupEventListeners() {
        // Create lesson button
        const createBtn = document.getElementById('create-lesson-btn');
        if (createBtn) {
            createBtn.addEventListener('click', () => this.showCreateModal());
        }

        // Close modal
        document.getElementById('close-modal').addEventListener('click', () => this.hideModal());
        document.getElementById('cancel-btn').addEventListener('click', () => this.hideModal());

        // Close modal on outside click
        window.addEventListener('click', (event) => {
            const modal = document.getElementById('lesson-modal');
            if (event.target === modal) {
                this.hideModal();
            }
        });

        // Form submission
        document.getElementById('lesson-form').addEventListener('submit', (e) => this.saveLesson(e));

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
    }
};

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => AdminLessons.init());
} else {
    AdminLessons.init();
}
