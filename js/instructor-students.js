/**
 * Instructor Students Module
 * Track students enrolled in instructor's courses, view progress, filter
 */

const InstructorStudents = {
    students: [],
    allStudents: [],
    courses: [],

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

        // Check for course_id filter from URL
        const params = new URLSearchParams(window.location.search);
        this.courseFilter = params.get('course_id') || '';

        await this.loadCourses();
        await this.loadStudents();
        this.setupEventListeners();
    },

    async loadCourses() {
        try {
            const user = Auth.getUser();
            const response = await API.get(`/courses?instructor_id=${user.id}&published=false`);
            const raw = response.data || {};
            this.courses = Array.isArray(raw) ? raw : (raw.items || raw.data || []);
            this.renderCourseFilter();
        } catch (error) {
            console.warn('InstructorStudents: Could not load courses:', error);
            this.courses = [];
        }
    },

    renderCourseFilter() {
        const select = document.getElementById('course-filter');
        if (!select) return;

        this.courses.forEach(course => {
            const option = document.createElement('option');
            option.value = course.id;
            option.textContent = course.title || 'Untitled';
            select.appendChild(option);
        });

        // Pre-select course if from URL
        if (this.courseFilter) {
            select.value = this.courseFilter;
        }
    },

    async loadStudents() {
        const container = document.getElementById('students-container');
        if (container) container.innerHTML = '<div class="loading-spinner">Loading students...</div>';

        this.students = [];

        try {
            // Get enrollments across all instructor courses (or filtered course)
            const coursesToQuery = this.courseFilter
                ? this.courses.filter(c => String(c.id) === String(this.courseFilter))
                : this.courses;

            for (const course of coursesToQuery) {
                try {
                    const response = await API.get(`/enrollments?course_id=${course.id}`);
                    const raw = response.data || {};
                    const enrollments = Array.isArray(raw) ? raw : (raw.items || raw.data || []);

                    for (const enrollment of enrollments) {
                        // Try to get user details
                        let studentInfo = {
                            id: enrollment.user_id,
                            name: enrollment.user_name || enrollment.student_name || 'Unknown',
                            email: enrollment.user_email || enrollment.student_email || '',
                            course_title: course.title,
                            course_id: course.id,
                            progress: enrollment.progress_percentage || enrollment.progress || 0,
                            status: enrollment.status || 'active',
                            enrolled_at: enrollment.enrolled_at || enrollment.created_at
                        };
                        this.students.push(studentInfo);
                    }
                } catch (err) {
                    console.warn(`Could not load enrollments for course ${course.id}:`, err);
                }
            }

            this.allStudents = [...this.students];
            this.applyFilters();
            this.updateStats();
        } catch (error) {
            console.error('InstructorStudents: Error loading students:', error);
            this.renderStudents();
        }
    },

    updateStats() {
        const totalEl = document.getElementById('stat-total');
        const activeEl = document.getElementById('stat-active');
        const avgEl = document.getElementById('stat-avg-progress');

        // Unique students
        const uniqueIds = new Set(this.allStudents.map(s => s.id));
        if (totalEl) totalEl.textContent = uniqueIds.size;

        // Active this week (students with progress > 0 as proxy)
        const activeCount = this.allStudents.filter(s => s.progress > 0).length;
        if (activeEl) activeEl.textContent = activeCount;

        // Average progress
        if (this.allStudents.length > 0) {
            const avg = Math.round(this.allStudents.reduce((sum, s) => sum + (s.progress || 0), 0) / this.allStudents.length);
            if (avgEl) avgEl.textContent = `${avg}%`;
        } else {
            if (avgEl) avgEl.textContent = '0%';
        }
    },

    applyFilters() {
        let filtered = [...this.allStudents];

        // Course filter
        const courseId = document.getElementById('course-filter')?.value;
        if (courseId) {
            filtered = filtered.filter(s => String(s.course_id) === String(courseId));
        }

        // Progress filter
        const progressFilter = document.getElementById('progress-filter')?.value;
        if (progressFilter === 'not-started') {
            filtered = filtered.filter(s => !s.progress || s.progress === 0);
        } else if (progressFilter === 'in-progress') {
            filtered = filtered.filter(s => s.progress > 0 && s.progress < 100);
        } else if (progressFilter === 'completed') {
            filtered = filtered.filter(s => s.progress >= 100);
        }

        // Search filter
        const searchQuery = document.getElementById('student-search')?.value?.toLowerCase().trim();
        if (searchQuery) {
            filtered = filtered.filter(s =>
                (s.name || '').toLowerCase().includes(searchQuery) ||
                (s.email || '').toLowerCase().includes(searchQuery) ||
                (s.course_title || '').toLowerCase().includes(searchQuery)
            );
        }

        this.students = filtered;
        this.renderStudents();
    },

    renderStudents() {
        const container = document.getElementById('students-container');
        if (!container) return;

        if (this.students.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-users"></i></div>
                    <h3>No Students Found</h3>
                    <p>No students match the current filters, or no students are enrolled in your courses yet.</p>
                </div>
            `;
            return;
        }

        let html = `
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Course</th>
                        <th>Progress</th>
                        <th>Status</th>
                        <th>Enrolled</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
        `;

        this.students.forEach(student => {
            const progressClass = student.progress >= 100 ? 'completed' :
                                  student.progress > 0 ? 'in-progress' : 'not-started';
            const statusClass = student.status === 'active' ? 'green' :
                                student.status === 'completed' ? 'blue' : 'orange';

            html += `
                <tr>
                    <td>
                        <div>
                            <strong>${this.escapeHtml(student.name)}</strong>
                            ${student.email ? `<br><small style="opacity:0.7;">${this.escapeHtml(student.email)}</small>` : ''}
                        </div>
                    </td>
                    <td>${this.escapeHtml(student.course_title || 'N/A')}</td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <div style="flex: 1; background: var(--bg-secondary, #eee); border-radius: 4px; height: 8px; min-width: 60px;">
                                <div style="width: ${Math.min(student.progress, 100)}%; height: 100%; background: var(--primary-color, #4CAF50); border-radius: 4px;"></div>
                            </div>
                            <span>${Math.round(student.progress || 0)}%</span>
                        </div>
                    </td>
                    <td><span class="role-badge ${statusClass}">${this.escapeHtml(student.status || 'active')}</span></td>
                    <td>${this.formatDate(student.enrolled_at)}</td>
                    <td>
                        <button class="btn-icon" onclick="InstructorStudents.viewStudent(${student.id})" title="View Profile">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        html += '</tbody></table>';
        container.innerHTML = html;
    },

    viewStudent(userId) {
        window.location.href = `/profile/index.html?id=${userId}`;
    },

    setupEventListeners() {
        document.getElementById('course-filter')?.addEventListener('change', () => this.applyFilters());
        document.getElementById('progress-filter')?.addEventListener('change', () => this.applyFilters());
        document.getElementById('student-search')?.addEventListener('input', () => this.applyFilters());
        document.getElementById('refresh-btn')?.addEventListener('click', () => this.loadStudents());
    },

    formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return 'N/A';
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    },

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Auto-initialize
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => InstructorStudents.init());
} else {
    InstructorStudents.init();
}
