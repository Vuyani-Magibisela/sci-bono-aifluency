/**
 * Instructor Courses Module
 * Shows courses the instructor teaches, student enrollment, and progress
 */

const InstructorCourses = {
    courses: [],
    allCourses: [],

    async init() {
        const user = Auth.getUser();
        if (!user) {
            window.location.href = '/public/login.html';
            return;
        }

        if (!Auth.canManageContent()) {
            window.location.href = '/public/403.html';
            return;
        }

        // Update sidebar profile
        const profileName = document.querySelector('.profile-name');
        if (profileName) profileName.textContent = user.name || 'Instructor';

        await this.loadCourses();
        this.setupEventListeners();
    },

    async loadCourses() {
        const container = document.getElementById('courses-container');
        try {
            const user = Auth.getUser();
            const endpoint = user && user.primary_school_id
                ? `/courses?school_id=${user.primary_school_id}&published=true`
                : `/courses?instructor_id=${user.id}&published=false`;
            const response = await API.get(endpoint);
            const raw = response.data || {};
            this.courses = Array.isArray(raw) ? raw : (raw.items || raw.data || []);
            this.allCourses = [...this.courses];

            // Load enrollment counts per course (scoped to this school's students)
            await this.loadEnrollmentCounts();

            this.updateStats();
            this.renderCourses();
        } catch (error) {
            console.warn('InstructorCourses: Could not load courses:', error);
            this.courses = [];
            this.allCourses = [];
            this.renderCourses();
        }
    },

    async loadEnrollmentCounts() {
        const user = Auth.getUser();
        const schoolQuery = user && user.primary_school_id
            ? `&school_id=${user.primary_school_id}`
            : '';
        for (const course of this.courses) {
            try {
                const response = await API.get(`/enrollments?course_id=${course.id}${schoolQuery}&pageSize=100`);
                const raw = response.data || {};
                const enrollments = Array.isArray(raw) ? raw : (raw.items || raw.data || []);
                // Prefer the paginated `total` when available — it's the authoritative count.
                course.enrollment_count = (raw && typeof raw.total === 'number')
                    ? raw.total
                    : enrollments.length;
                course.enrollments = enrollments;
            } catch (error) {
                course.enrollment_count = course.enrollment_count || 0;
                course.enrollments = [];
            }
        }
    },

    updateStats() {
        const totalEl = document.getElementById('stat-total-courses');
        const studentsEl = document.getElementById('stat-total-students');
        const publishedEl = document.getElementById('stat-published');

        if (totalEl) totalEl.textContent = this.courses.length;

        const totalStudents = this.courses.reduce((sum, c) => sum + (c.enrollment_count || 0), 0);
        if (studentsEl) studentsEl.textContent = totalStudents;

        const published = this.courses.filter(c => c.is_published).length;
        if (publishedEl) publishedEl.textContent = published;
    },

    renderCourses() {
        const container = document.getElementById('courses-container');
        if (!container) return;

        if (this.courses.length === 0) {
            const user = Auth.getUser();
            const message = user && user.primary_school_id
                ? 'No published courses have students from your school enrolled yet. Once a student at your school enrols, the course will appear here.'
                : 'No courses available. Ask an administrator to assign you to a school.';
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">📚</div>
                    <h3>No Courses Yet</h3>
                    <p>${this.escapeHtml(message)}</p>
                </div>
            `;
            return;
        }

        let html = '<div class="course-grid">';
        this.courses.forEach(course => {
            const enrollmentCount = course.enrollment_count || 0;
            const completionRate = course.completion_rate || course.completion_percentage || 0;
            const statusClass = course.is_published ? 'published' : 'draft';
            const statusText = course.is_published ? 'Published' : 'Draft';

            html += `
                <div class="course-card instructor-course" data-course-id="${course.id}">
                    <div class="course-header">
                        <h3>${this.escapeHtml(course.title || 'Untitled Course')}</h3>
                        <span class="course-status ${statusClass}">${statusText}</span>
                    </div>
                    <p class="course-description">${this.escapeHtml(course.description || 'No description available')}</p>
                    <div class="course-stats">
                        <div class="stat-item">
                            <span class="stat-icon"><i class="fas fa-users"></i></span>
                            <span class="stat-value">${enrollmentCount}</span>
                            <span class="stat-label">Students</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-icon"><i class="fas fa-layer-group"></i></span>
                            <span class="stat-value">${course.modules_count || 0}</span>
                            <span class="stat-label">Modules</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-icon"><i class="fas fa-chart-line"></i></span>
                            <span class="stat-value">${completionRate}%</span>
                            <span class="stat-label">Completion</span>
                        </div>
                    </div>
                    <div class="course-footer">
                        <button class="btn-secondary btn-sm" onclick="InstructorCourses.viewStudents(${course.id}, '${this.escapeHtml(course.title)}')">
                            <i class="fas fa-users"></i> View Students
                        </button>
                        <button class="btn-primary btn-sm" onclick="InstructorCourses.viewCourse(${course.id})">
                            <i class="fas fa-eye"></i> View Course
                        </button>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        container.innerHTML = html;
    },

    viewCourse(courseId) {
        window.location.href = `/student/course-view.html?id=${courseId}`;
    },

    viewStudents(courseId, courseTitle) {
        window.location.href = `/instructor/students.html?course_id=${courseId}`;
    },

    setupEventListeners() {
        const searchInput = document.getElementById('course-search');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                const query = e.target.value.toLowerCase().trim();
                if (!query) {
                    this.courses = [...this.allCourses];
                } else {
                    this.courses = this.allCourses.filter(c =>
                        (c.title || '').toLowerCase().includes(query) ||
                        (c.description || '').toLowerCase().includes(query)
                    );
                }
                this.renderCourses();
            });
        }
    },

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Auto-initialize
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => InstructorCourses.init());
} else {
    InstructorCourses.init();
}
