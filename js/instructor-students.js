/**
 * Instructor Students Module
 * School-scoped student roster with server-side pagination, real stats,
 * detail modal (progress + quizzes + account controls), and a duplicates view.
 */

const InstructorStudents = {
    students: [],
    allStudents: [],
    courses: [],
    currentPage: 1,
    pageSize: 25,
    totalCount: 0,
    totalPages: 0,
    courseFilter: '',
    progressFilter: '',
    sortBy: 'name',       // 'name' | 'progress' | 'last_active'
    sortOrder: 'asc',     // 'asc' | 'desc'
    searchQuery: '',
    showInactive: false,
    viewMode: 'roster', // 'roster' | 'duplicates'
    duplicates: [],
    searchTimer: null,
    // A student is considered "stale" if there's been no coursework activity in this many days.
    // Surfaced as the Active/Stale badge in the roster.
    staleAfterDays: 14,

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

        const profileName = document.querySelector('.profile-name');
        if (profileName) profileName.textContent = user.name || 'Instructor';

        const params = new URLSearchParams(window.location.search);
        this.courseFilter = params.get('course_id') || '';
        if (params.get('view') === 'duplicates') this.viewMode = 'duplicates';

        await this.loadCourses();
        this.loadStats();
        this.setupEventListeners();
        await this.refresh();
    },

    async refresh() {
        if (this.viewMode === 'duplicates') {
            await this.loadDuplicates();
        } else {
            await this.loadStudents();
        }
    },

    async loadCourses() {
        try {
            const user = Auth.getUser();
            const endpoint = user && user.primary_school_id
                ? `/courses?school_id=${user.primary_school_id}&published=true`
                : `/courses?instructor_id=${user.id}&published=false`;
            const response = await API.get(endpoint);
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

        if (this.courseFilter) {
            select.value = this.courseFilter;
        }
    },

    /**
     * School-wide stats — independent of pagination or filters so the cards
     * always show the true totals for the instructor's school.
     */
    async loadStats() {
        const totalEl = document.getElementById('stat-total');
        const activeEl = document.getElementById('stat-active');
        const avgEl = document.getElementById('stat-avg-progress');

        try {
            const response = await API.get('/instructor/students/stats');
            const data = response.data || {};
            if (totalEl) totalEl.textContent = Number(data.total_students) || 0;
            if (activeEl) activeEl.textContent = Number(data.active_count) || 0;
            if (avgEl) avgEl.textContent = `${Math.round(Number(data.avg_progress) || 0)}%`;
        } catch (error) {
            console.warn('InstructorStudents: Could not load stats:', error);
            if (totalEl) totalEl.textContent = '—';
            if (activeEl) activeEl.textContent = '—';
            if (avgEl) avgEl.textContent = '—';
        }
    },

    async loadStudents() {
        const container = document.getElementById('students-container');
        if (container) container.innerHTML = '<div class="loading-spinner">Loading students...</div>';

        const user = Auth.getUser();
        if (!user || !user.primary_school_id) {
            this.allStudents = [];
            this.students = [];
            this.totalCount = 0;
            this.totalPages = 0;
            this.renderUnassigned();
            this.renderPager();
            return;
        }

        // Course-filtered view: load enrollments for the course and overlay onto the
        // school roster. Pagination becomes client-side over the filtered list.
        if (this.courseFilter) {
            await this.loadCourseFilteredStudents(user.primary_school_id);
            return;
        }

        // Default view: server-side pagination over the full school roster.
        // Progress filter and sort are pushed to the backend so they apply across the whole
        // roster (not just the visible page) and `total` reflects the filtered count.
        try {
            const params = new URLSearchParams({
                school_id: String(user.primary_school_id),
                role: 'student',
                include_enrollment_summary: 'true',
                page: String(this.currentPage),
                pageSize: String(this.pageSize)
            });
            if (this.searchQuery) params.set('search', this.searchQuery);
            if (!this.showInactive) params.set('is_active', 'true');
            if (this.progressFilter) params.set('progress_filter', this.progressFilter);
            if (this.sortBy && this.sortBy !== 'name') params.set('sort', this.sortBy);
            if (this.sortOrder && this.sortOrder !== 'asc') params.set('order', this.sortOrder);

            const response = await API.get(`/users?${params.toString()}`);
            const payload = response.data || {};
            const users = Array.isArray(payload) ? payload : (payload.data || payload.items || []);
            this.totalCount = Number(payload.total) || users.length;
            this.totalPages = Number(payload.totalPages) || Math.max(1, Math.ceil(this.totalCount / this.pageSize));

            this.students = users.map(u => this.mapUserSummary(u));
            this.allStudents = [...this.students];

            this.renderStudents();
            this.renderPager();
        } catch (error) {
            console.error('InstructorStudents: Error loading students:', error);
            this.students = [];
            this.totalCount = 0;
            this.totalPages = 0;
            this.renderStudents();
            this.renderPager();
        }
    },

    async loadCourseFilteredStudents(schoolId) {
        try {
            const params = new URLSearchParams({
                school_id: String(schoolId),
                role: 'student',
                include_enrollment_summary: 'true',
                pageSize: '100'
            });
            if (!this.showInactive) params.set('is_active', 'true');
            const response = await API.get(`/users?${params.toString()}`);
            const payload = response.data || {};
            const users = Array.isArray(payload) ? payload : (payload.data || payload.items || []);
            this.allStudents = users.map(u => this.mapUserSummary(u));

            await this.overlayCourseEnrollments(this.courseFilter);

            // Client-side search + progress filter + sort + pagination over the overlaid set.
            // Data is already fully loaded for the selected course, so filtering and sorting
            // happen in memory here (the backend path is for the unscoped roster only).
            let filtered = [...this.allStudents];
            if (this.searchQuery) {
                const q = this.searchQuery.toLowerCase();
                filtered = filtered.filter(s =>
                    (s.name || '').toLowerCase().includes(q) ||
                    (s.email || '').toLowerCase().includes(q) ||
                    (s.course_title || '').toLowerCase().includes(q)
                );
            }
            if (this.progressFilter) {
                filtered = this.filterByProgress(filtered, this.progressFilter);
            }
            this.sortStudentsInPlace(filtered);

            this.totalCount = filtered.length;
            this.totalPages = Math.max(1, Math.ceil(this.totalCount / this.pageSize));
            if (this.currentPage > this.totalPages) this.currentPage = this.totalPages;

            const start = (this.currentPage - 1) * this.pageSize;
            this.students = filtered.slice(start, start + this.pageSize);

            this.renderStudents();
            this.renderPager();
        } catch (error) {
            console.error('InstructorStudents: Error loading course-filtered students:', error);
            this.students = [];
            this.renderStudents();
            this.renderPager();
        }
    },

    mapUserSummary(u) {
        const enrollmentCount = Number(u.enrollment_count) || 0;
        const courseLabel = enrollmentCount === 0
            ? 'Not enrolled'
            : enrollmentCount === 1
                ? (u.single_course_title || '1 course')
                : `${enrollmentCount} courses`;
        return {
            id: u.id,
            name: u.name || [u.first_name, u.last_name].filter(Boolean).join(' ') || 'Unknown',
            email: u.email || '',
            course_title: courseLabel,
            course_id: null,
            enrollment_count: enrollmentCount,
            completed_count: Number(u.completed_count) || 0,
            progress: Math.round(Number(u.avg_progress) || 0),
            status: u.is_active === false ? 'inactive' : 'active',
            enrollment_status: null,  // set in course-filtered view via overlayCourseEnrollments
            is_active: u.is_active !== false,
            enrolled_at: u.latest_enrolled_at || u.created_at || u.registered_at || null,
            last_active_at: u.last_active_at || null
        };
    },

    /**
     * Overlay enrollment progress from a course onto the loaded student list.
     * Students not enrolled in the course are removed.
     */
    async overlayCourseEnrollments(courseId) {
        try {
            const response = await API.get(`/enrollments?course_id=${courseId}`);
            const raw = response.data || {};
            const enrollments = Array.isArray(raw) ? raw : (raw.items || raw.data || []);
            const byUser = new Map(enrollments.map(e => [String(e.user_id), e]));
            const course = this.courses.find(c => String(c.id) === String(courseId));

            this.allStudents = this.allStudents
                .filter(s => byUser.has(String(s.id)))
                .map(s => {
                    const e = byUser.get(String(s.id));
                    return {
                        ...s,
                        course_title: course ? course.title : '',
                        course_id: courseId,
                        progress: e.progress_percentage || e.progress || 0,
                        // Track enrollment status separately so account status (active/inactive)
                        // and enrollment status (active/completed/dropped) don't collide.
                        enrollment_status: e.status || null,
                        completed_count: (e.status === 'completed') ? 1 : 0,
                        enrolled_at: e.enrolled_at || e.created_at || s.enrolled_at,
                        last_active_at: e.last_accessed_at || s.last_active_at || null
                    };
                });
        } catch (err) {
            console.warn(`Could not overlay enrollments for course ${courseId}:`, err);
        }
    },

    /**
     * Progress filter for the course-scoped view (operates on already-loaded data).
     * The unscoped roster pushes this filter to the backend instead — see loadStudents().
     *
     * "completed" means status='completed' on the enrollment (or completed_count >= 1 fallback),
     * NOT progress >= 100. Average percentages rarely hit 100 across multiple courses, which is
     * the bug the original `progress >= 100` rule produced.
     */
    filterByProgress(list, mode) {
        const isCompleted = s => (s.enrollment_status === 'completed') || (Number(s.completed_count) || 0) >= 1;
        if (mode === 'not-started') {
            return list.filter(s => !isCompleted(s) && (!s.progress || s.progress === 0));
        }
        if (mode === 'in-progress') {
            return list.filter(s => !isCompleted(s) && s.progress > 0);
        }
        if (mode === 'completed') {
            return list.filter(s => isCompleted(s));
        }
        return list;
    },

    /**
     * Sort an array of student rows in place using the current sortBy/sortOrder.
     * Used by the course-scoped view; the unscoped roster sorts on the backend.
     */
    sortStudentsInPlace(list) {
        const dir = this.sortOrder === 'desc' ? -1 : 1;
        if (this.sortBy === 'progress') {
            list.sort((a, b) => (Number(a.progress) - Number(b.progress)) * dir || a.name.localeCompare(b.name));
        } else if (this.sortBy === 'last_active') {
            // Nulls always last, regardless of direction — unknown activity shouldn't beat known activity.
            list.sort((a, b) => {
                const aT = a.last_active_at ? new Date(a.last_active_at).getTime() : null;
                const bT = b.last_active_at ? new Date(b.last_active_at).getTime() : null;
                if (aT === null && bT === null) return a.name.localeCompare(b.name);
                if (aT === null) return 1;
                if (bT === null) return -1;
                return (aT - bT) * dir || a.name.localeCompare(b.name);
            });
        } else {
            list.sort((a, b) => a.name.localeCompare(b.name) * dir);
        }
    },

    /**
     * Returns staleness display info for a last-active timestamp.
     * { label: 'Active'|'Stale', className: '...', relativeText: '2d ago'|'—' }
     */
    staleness(lastActiveAt) {
        if (!lastActiveAt) {
            return { label: 'Stale', className: 'orange', relativeText: '—' };
        }
        const last = new Date(lastActiveAt).getTime();
        if (isNaN(last)) {
            return { label: 'Stale', className: 'orange', relativeText: '—' };
        }
        const ageMs = Date.now() - last;
        const ageDays = Math.floor(ageMs / 86400000);
        const isStale = ageDays >= this.staleAfterDays;
        return {
            label: isStale ? 'Stale' : 'Active',
            className: isStale ? 'orange' : 'green',
            relativeText: this.formatRelativeTime(ageMs)
        };
    },

    formatRelativeTime(ageMs) {
        if (ageMs < 0) return 'just now';
        const minutes = Math.floor(ageMs / 60000);
        if (minutes < 60) return minutes <= 1 ? 'just now' : `${minutes}m ago`;
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return `${hours}h ago`;
        const days = Math.floor(hours / 24);
        if (days < 30) return `${days}d ago`;
        const months = Math.floor(days / 30);
        if (months < 12) return `${months}mo ago`;
        return `${Math.floor(months / 12)}y ago`;
    },

    renderUnassigned() {
        const container = document.getElementById('students-container');
        if (!container) return;
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-school"></i></div>
                <h3>Not assigned to a school</h3>
                <p>Ask a SuperAdmin to assign you to a school via <strong>Admin &rarr; Users</strong>. Once assigned, students from that school will appear here.</p>
            </div>
        `;
    },

    renderStudents() {
        const container = document.getElementById('students-container');
        if (!container) return;

        if (this.students.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-users"></i></div>
                    <h3>No Students Found</h3>
                    <p>No students match the current filters.</p>
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
                        <th>Last Active</th>
                        <th>Enrolled</th>
                    </tr>
                </thead>
                <tbody>
        `;

        this.students.forEach(student => {
            const statusClass = student.status === 'active' ? 'green' :
                                student.status === 'completed' ? 'blue' : 'orange';
            const stale = this.staleness(student.last_active_at);

            html += `
                <tr>
                    <td>
                        <button type="button"
                                class="student-name-link"
                                onclick="InstructorStudents.openStudentModal(${student.id})"
                                title="Open student details">
                            <strong>${this.escapeHtml(student.name)}</strong>
                            ${student.email ? `<br><small style="opacity:0.7;">${this.escapeHtml(student.email)}</small>` : ''}
                        </button>
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
                    <td>
                        <span class="role-badge ${stale.className}">${stale.label}</span>
                        <br><small style="opacity:0.7;">${this.escapeHtml(stale.relativeText)}</small>
                    </td>
                    <td>${this.formatDate(student.enrolled_at)}</td>
                </tr>
            `;
        });

        html += '</tbody></table>';
        container.innerHTML = html;
    },

    renderPager() {
        const pager = document.getElementById('students-pager');
        if (!pager) return;

        if (this.viewMode === 'duplicates' || this.totalPages <= 1) {
            pager.innerHTML = '';
            return;
        }

        const pages = this.computePagerPages(this.currentPage, this.totalPages);
        const startIdx = (this.currentPage - 1) * this.pageSize + 1;
        const endIdx = Math.min(this.currentPage * this.pageSize, this.totalCount);

        let html = '<div class="students-pager-inner">';
        html += `<div class="pager-summary">Showing ${startIdx}–${endIdx} of ${this.totalCount}</div>`;
        html += '<div class="pager-buttons">';
        html += `<button class="pager-btn" ${this.currentPage <= 1 ? 'disabled' : ''} onclick="InstructorStudents.goToPage(${this.currentPage - 1})">&laquo; Prev</button>`;
        pages.forEach(p => {
            if (p === '…') {
                html += '<span class="pager-ellipsis">…</span>';
            } else {
                const active = p === this.currentPage ? ' pager-active' : '';
                html += `<button class="pager-btn${active}" onclick="InstructorStudents.goToPage(${p})">${p}</button>`;
            }
        });
        html += `<button class="pager-btn" ${this.currentPage >= this.totalPages ? 'disabled' : ''} onclick="InstructorStudents.goToPage(${this.currentPage + 1})">Next &raquo;</button>`;
        html += '</div></div>';
        pager.innerHTML = html;
    },

    /**
     * Returns an array of page numbers / ellipsis tokens for the pager.
     * Always shows first, last, current, and 1 neighbour each side.
     */
    computePagerPages(current, total) {
        const pages = new Set([1, total, current, current - 1, current + 1]);
        const sorted = [...pages]
            .filter(p => p >= 1 && p <= total)
            .sort((a, b) => a - b);

        const result = [];
        let prev = 0;
        sorted.forEach(p => {
            if (p - prev > 1) result.push('…');
            result.push(p);
            prev = p;
        });
        return result;
    },

    goToPage(page) {
        if (page < 1 || page > this.totalPages || page === this.currentPage) return;
        this.currentPage = page;
        this.loadStudents();
    },

    // ---------------------------------------------------------------
    // Duplicates view
    // ---------------------------------------------------------------

    async loadDuplicates() {
        const container = document.getElementById('students-container');
        if (container) container.innerHTML = '<div class="loading-spinner">Loading duplicate accounts...</div>';

        try {
            const response = await API.get('/instructor/students/duplicates');
            const payload = response.data || {};
            this.duplicates = Array.isArray(payload) ? payload : (payload.data || payload.items || []);
            this.renderDuplicates();
        } catch (error) {
            console.error('InstructorStudents: Error loading duplicates:', error);
            this.duplicates = [];
            this.renderDuplicates();
        }
        this.renderPager();
    },

    renderDuplicates() {
        const container = document.getElementById('students-container');
        if (!container) return;

        if (!this.duplicates.length) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-clone"></i></div>
                    <h3>No duplicate accounts found</h3>
                    <p>Every student in your school has a unique email.</p>
                </div>
            `;
            return;
        }

        // Group rows by normalised email so duplicates show together.
        const groups = new Map();
        this.duplicates.forEach(row => {
            const key = (row.norm_email || (row.email || '').toLowerCase().trim());
            if (!groups.has(key)) groups.set(key, []);
            groups.get(key).push(row);
        });

        let html = `
            <p style="margin-top:0; color:#6b7280;">
                ${groups.size} email${groups.size === 1 ? '' : 's'} have more than one account in your school.
                Click a student's name to review and resolve.
            </p>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Email (shared)</th>
                        <th>Account name</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Last login</th>
                    </tr>
                </thead>
                <tbody>
        `;

        [...groups.entries()].forEach(([email, rows]) => {
            rows.forEach((row, idx) => {
                const statusClass = row.is_active == 1 ? 'green' : 'orange';
                const statusText = row.is_active == 1 ? 'active' : 'inactive';
                html += `
                    <tr style="${idx === 0 ? 'border-top: 2px solid #e5e7eb;' : ''}">
                        <td>${idx === 0 ? `<strong>${this.escapeHtml(email)}</strong><br><small style="opacity:0.6;">${rows.length} accounts</small>` : ''}</td>
                        <td>
                            <button type="button" class="student-name-link" onclick="InstructorStudents.openStudentModal(${row.id})">
                                <strong>${this.escapeHtml(row.name || 'Unknown')}</strong>
                                <br><small style="opacity:0.7;">id: ${row.id}</small>
                            </button>
                        </td>
                        <td><span class="role-badge ${statusClass}">${statusText}</span></td>
                        <td>${this.formatDate(row.created_at)}</td>
                        <td>${this.formatDate(row.last_login_at)}</td>
                    </tr>
                `;
            });
        });

        html += '</tbody></table>';
        container.innerHTML = html;
    },

    setViewMode(mode) {
        if (this.viewMode === mode) return;
        this.viewMode = mode;
        const rosterBtn = document.getElementById('view-roster');
        const dupBtn = document.getElementById('view-duplicates');
        if (rosterBtn) rosterBtn.classList.toggle('active', mode === 'roster');
        if (dupBtn) dupBtn.classList.toggle('active', mode === 'duplicates');
        this.refresh();
    },

    // ---------------------------------------------------------------
    // Student detail modal
    // ---------------------------------------------------------------

    closeStudentModal() {
        const existing = document.getElementById('student-detail-modal');
        if (existing) existing.remove();
    },

    async openStudentModal(userId) {
        this.closeStudentModal();
        this.closeResetPasswordModal();

        const overlay = document.createElement('div');
        overlay.id = 'student-detail-modal';
        overlay.className = 'modal';
        overlay.style.display = 'flex';
        overlay.style.alignItems = 'center';
        overlay.style.justifyContent = 'center';
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) this.closeStudentModal();
        });

        overlay.innerHTML = `
            <div class="modal-content" style="max-width: 760px; width: 95%;">
                <div class="modal-header">
                    <h2 id="student-modal-title">Student details</h2>
                    <button type="button" class="modal-close" onclick="InstructorStudents.closeStudentModal()">&times;</button>
                </div>
                <div class="modal-body" id="student-modal-body" style="max-height: 70vh; overflow-y: auto;">
                    <div class="loading-spinner">Loading…</div>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);

        try {
            const [userRes, enrollRes, attemptsRes] = await Promise.all([
                API.get(`/users/${userId}`),
                API.get(`/enrollments?user_id=${userId}&pageSize=50`),
                API.get(`/quizzes/attempts/recent?user_id=${userId}&limit=25`).catch(() => ({ data: [] }))
            ]);

            const user = (userRes.data && userRes.data.user) || {};
            const enrollPayload = enrollRes.data || {};
            const enrollments = Array.isArray(enrollPayload)
                ? enrollPayload
                : (enrollPayload.data || enrollPayload.items || []);
            const attempts = Array.isArray(attemptsRes.data)
                ? attemptsRes.data
                : (attemptsRes.data && attemptsRes.data.data) || [];

            this.renderStudentModal(user, enrollments, attempts);
        } catch (error) {
            console.error('openStudentModal error:', error);
            const body = document.getElementById('student-modal-body');
            if (body) {
                body.innerHTML = `
                    <div class="empty-state">
                        <h3>Could not load student</h3>
                        <p>${this.escapeHtml(error.message || 'Please try again.')}</p>
                    </div>
                `;
            }
        }
    },

    renderStudentModal(user, enrollments, attempts) {
        const titleEl = document.getElementById('student-modal-title');
        const body = document.getElementById('student-modal-body');
        if (!body) return;

        if (titleEl) titleEl.textContent = user.name || 'Student details';

        const isActive = user.is_active === 1 || user.is_active === true;
        const statusBadge = isActive
            ? '<span class="role-badge green">active</span>'
            : '<span class="role-badge orange">inactive</span>';

        // Group attempts by quiz to surface attempt counts per quiz.
        const attemptsByQuiz = new Map();
        attempts.forEach(a => {
            const k = a.quiz_id;
            if (!attemptsByQuiz.has(k)) attemptsByQuiz.set(k, []);
            attemptsByQuiz.get(k).push(a);
        });

        const quizRows = [...attemptsByQuiz.entries()].map(([quizId, list]) => {
            list.sort((a, b) => Number(b.attempt_number || 0) - Number(a.attempt_number || 0));
            const latest = list[0] || {};
            const best = list.reduce((acc, a) => {
                const s = Number(a.score || 0);
                return s > acc ? s : acc;
            }, 0);
            const passedAny = list.some(a => a.passed == 1 || a.passed === true);
            return `
                <tr>
                    <td>
                        <strong>${this.escapeHtml(latest.quiz_title || `Quiz #${quizId}`)}</strong>
                        ${latest.module_title ? `<br><small style="opacity:0.7;">${this.escapeHtml(latest.module_title)}</small>` : ''}
                    </td>
                    <td>${list.length}</td>
                    <td>${Math.round(best)}%</td>
                    <td>${latest.score != null ? Math.round(latest.score) + '%' : '—'}</td>
                    <td>${passedAny ? '<span class="role-badge green">passed</span>' : '<span class="role-badge orange">not yet</span>'}</td>
                    <td>${this.formatDate(latest.time_completed)}</td>
                </tr>
            `;
        }).join('');

        const enrollmentRows = enrollments.map(e => {
            const courseTitle = (e.course && e.course.title) || e.course_title || `Course #${e.course_id}`;
            const progress = Math.round(Number(e.progress_percentage || e.progress || 0));
            return `
                <tr>
                    <td><strong>${this.escapeHtml(courseTitle)}</strong></td>
                    <td>
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <div style="flex:1; background:#eee; border-radius:4px; height:6px; min-width:80px;">
                                <div style="width:${Math.min(progress, 100)}%; height:100%; background:var(--primary-color,#4CAF50); border-radius:4px;"></div>
                            </div>
                            <span>${progress}%</span>
                        </div>
                    </td>
                    <td>${this.escapeHtml(e.status || '—')}</td>
                    <td>${this.formatDate(e.enrolled_at || e.created_at)}</td>
                </tr>
            `;
        }).join('');

        body.innerHTML = `
            <div style="margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid #e5e7eb;">
                <div style="display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
                    <div>
                        <strong>${this.escapeHtml(user.email || '')}</strong>
                        ${statusBadge}
                    </div>
                    <small style="opacity:0.7;">Joined ${this.formatDate(user.created_at)}</small>
                </div>
            </div>

            <div class="student-modal-tabs" role="tablist" style="display:flex; gap:0.5rem; flex-wrap:wrap; margin-bottom:1rem;">
                <button type="button" class="tab-btn active" data-tab="progress" onclick="InstructorStudents.switchModalTab('progress')">Progress</button>
                <button type="button" class="tab-btn" data-tab="quizzes" onclick="InstructorStudents.switchModalTab('quizzes')">Quizzes</button>
                <button type="button" class="tab-btn" data-tab="account" onclick="InstructorStudents.switchModalTab('account')">Account</button>
            </div>

            <div class="student-modal-pane" data-pane="progress">
                ${enrollments.length === 0 ? `
                    <p style="opacity:0.7;">No course enrollments yet.</p>
                ` : `
                    <table class="admin-table">
                        <thead>
                            <tr><th>Course</th><th>Progress</th><th>Status</th><th>Enrolled</th></tr>
                        </thead>
                        <tbody>${enrollmentRows}</tbody>
                    </table>
                `}
            </div>

            <div class="student-modal-pane" data-pane="quizzes" style="display:none;">
                ${attemptsByQuiz.size === 0 ? `
                    <p style="opacity:0.7;">No quiz attempts on record.</p>
                ` : `
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Quiz</th>
                                <th>Attempts</th>
                                <th>Best</th>
                                <th>Latest</th>
                                <th>Result</th>
                                <th>Last attempt</th>
                            </tr>
                        </thead>
                        <tbody>${quizRows}</tbody>
                    </table>
                `}
            </div>

            <div class="student-modal-pane" data-pane="account" style="display:none;">
                ${this.renderAccountPane(user, isActive)}
            </div>
        `;
    },

    switchModalTab(tab) {
        const buttons = document.querySelectorAll('#student-modal-body .tab-btn');
        const panes = document.querySelectorAll('#student-modal-body .student-modal-pane');
        buttons.forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
        panes.forEach(p => p.style.display = p.dataset.pane === tab ? '' : 'none');
    },

    renderAccountPane(user, isActive) {
        const id = user.id;
        const toggleLabel = isActive ? 'Deactivate account' : 'Reactivate account';
        const toggleIcon = isActive ? 'fa-user-slash' : 'fa-user-check';
        const toggleNext = isActive ? 'false' : 'true';

        return `
            <div class="account-section" style="margin-bottom:1.5rem;">
                <h4 style="margin:0 0 0.5rem;">Reset password</h4>
                <p style="margin:0 0 0.75rem; opacity:0.75; font-size:0.9rem;">Set a new password for this student. They'll need it next time they log in.</p>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem; max-width:480px;">
                    <input type="password" id="modal-new-password" placeholder="New password" autocomplete="new-password" minlength="8" style="padding:0.5rem; border:1px solid #ccc; border-radius:4px;">
                    <input type="password" id="modal-confirm-password" placeholder="Confirm password" autocomplete="new-password" minlength="8" style="padding:0.5rem; border:1px solid #ccc; border-radius:4px;">
                </div>
                <div id="modal-reset-error" style="color:#d32f2f; font-size:0.85rem; min-height:1.2rem; margin-top:0.4rem;"></div>
                <button type="button" class="btn-primary btn-sm" onclick="InstructorStudents.submitModalPasswordReset(${id})">
                    <i class="fas fa-key"></i> Reset password
                </button>
            </div>

            <div class="account-section" style="margin-bottom:1.5rem; padding-top:1rem; border-top:1px solid #e5e7eb;">
                <h4 style="margin:0 0 0.5rem;">Account status</h4>
                <p style="margin:0 0 0.75rem; opacity:0.75; font-size:0.9rem;">Deactivating hides the account from rosters and blocks login. Reversible.</p>
                <button type="button" class="btn-secondary btn-sm" onclick="InstructorStudents.setAccountActive(${id}, ${toggleNext})">
                    <i class="fas ${toggleIcon}"></i> ${toggleLabel}
                </button>
            </div>

            <div class="account-section" style="padding-top:1rem; border-top:1px solid #e5e7eb;">
                <h4 style="margin:0 0 0.5rem; color:#b91c1c;">Mark as duplicate</h4>
                <p style="margin:0 0 0.75rem; opacity:0.75; font-size:0.9rem;">
                    If this is a duplicate of another account, soft-delete it here. The account stays in the database but is hidden from the active roster. Hard delete is admin-only.
                </p>
                <div id="duplicate-section-${id}">
                    <button type="button" class="btn-secondary btn-sm" onclick="InstructorStudents.showDuplicateForm(${id}, '${this.escapeJsString(user.email || '')}')">
                        <i class="fas fa-clone"></i> Mark as duplicate…
                    </button>
                </div>
            </div>
        `;
    },

    async submitModalPasswordReset(userId) {
        const errorEl = document.getElementById('modal-reset-error');
        const newPwd = (document.getElementById('modal-new-password') || {}).value || '';
        const confirmPwd = (document.getElementById('modal-confirm-password') || {}).value || '';

        if (errorEl) errorEl.textContent = '';

        if (!newPwd || !confirmPwd) {
            if (errorEl) errorEl.textContent = 'Both fields are required.';
            return;
        }
        if (newPwd.length < 8) {
            if (errorEl) errorEl.textContent = 'Password must be at least 8 characters.';
            return;
        }
        if (newPwd !== confirmPwd) {
            if (errorEl) errorEl.textContent = 'Passwords do not match.';
            return;
        }

        if (!confirm("Reset this student's password? They will need the new password to log in.")) return;

        try {
            await API.put(`/users/${userId}/admin-reset-password`, {
                new_password: newPwd,
                confirm_password: confirmPwd
            });
            this.showToast('Password reset successfully', 'success');
            const a = document.getElementById('modal-new-password');
            const b = document.getElementById('modal-confirm-password');
            if (a) a.value = '';
            if (b) b.value = '';
        } catch (error) {
            if (errorEl) errorEl.textContent = error.message || 'Failed to reset password.';
        }
    },

    async setAccountActive(userId, makeActive) {
        const verb = makeActive ? 'reactivate' : 'deactivate';
        if (!confirm(`Are you sure you want to ${verb} this account?`)) return;
        try {
            await API.put(`/users/${userId}/deactivate`, { is_active: !!makeActive });
            this.showToast(`Account ${makeActive ? 'reactivated' : 'deactivated'}`, 'success');
            this.closeStudentModal();
            this.refresh();
        } catch (error) {
            this.showToast(error.message || 'Failed to update account status', 'error');
        }
    },

    async showDuplicateForm(userId, email) {
        const section = document.getElementById(`duplicate-section-${userId}`);
        if (!section) return;

        section.innerHTML = '<p style="opacity:0.7;">Looking up other accounts with this email…</p>';

        let candidates = [];
        try {
            const response = await API.get('/instructor/students/duplicates');
            const payload = response.data || {};
            const rows = Array.isArray(payload) ? payload : (payload.data || payload.items || []);
            const target = (email || '').toLowerCase().trim();
            candidates = rows.filter(r =>
                String(r.id) !== String(userId) &&
                ((r.norm_email || (r.email || '').toLowerCase().trim()) === target)
            );
        } catch (err) {
            section.innerHTML = `<p style="color:#b91c1c;">Could not load duplicate list: ${this.escapeHtml(err.message || 'unknown error')}</p>`;
            return;
        }

        if (candidates.length === 0) {
            section.innerHTML = `
                <p style="color:#b91c1c;">No other accounts share this email in your school. Either the email isn't actually duplicated, or the other accounts are in a different school.</p>
                <button type="button" class="btn-secondary btn-sm" onclick="InstructorStudents.cancelDuplicateForm(${userId}, '${this.escapeJsString(email)}')">Cancel</button>
            `;
            return;
        }

        const options = candidates.map(c => `
            <option value="${c.id}">
                ${this.escapeHtml(c.name || 'Unknown')} — id ${c.id} — ${c.is_active == 1 ? 'active' : 'inactive'} — joined ${this.formatDate(c.created_at)}
            </option>
        `).join('');

        section.innerHTML = `
            <div style="background:#fef2f2; border:1px solid #fecaca; padding:0.75rem; border-radius:4px;">
                <p style="margin:0 0 0.5rem; font-weight:600;">Which account should we keep?</p>
                <p style="margin:0 0 0.5rem; font-size:0.85rem; opacity:0.8;">The account you select will remain active. This account will be marked inactive.</p>
                <select id="duplicate-keep-${userId}" style="width:100%; padding:0.5rem; border:1px solid #ccc; border-radius:4px; margin-bottom:0.5rem;">
                    ${options}
                </select>
                <label style="display:block; margin-bottom:0.4rem; font-size:0.85rem;">Reason (optional):</label>
                <input type="text" id="duplicate-reason-${userId}" maxlength="500" placeholder="e.g. confirmed with student in class" style="width:100%; padding:0.4rem; border:1px solid #ccc; border-radius:4px; margin-bottom:0.5rem;">
                <label style="display:block; font-size:0.85rem; margin-bottom:0.25rem;">Type <strong>DUPLICATE</strong> to confirm:</label>
                <input type="text" id="duplicate-confirm-${userId}" autocomplete="off" placeholder="DUPLICATE" style="width:100%; padding:0.4rem; border:1px solid #ccc; border-radius:4px; margin-bottom:0.5rem;">
                <div id="duplicate-error-${userId}" style="color:#b91c1c; font-size:0.85rem; min-height:1.2rem; margin-bottom:0.4rem;"></div>
                <div style="display:flex; gap:0.5rem;">
                    <button type="button" class="btn-secondary btn-sm" onclick="InstructorStudents.cancelDuplicateForm(${userId}, '${this.escapeJsString(email)}')">Cancel</button>
                    <button type="button" class="btn-primary btn-sm" style="background:#b91c1c;" onclick="InstructorStudents.submitDuplicate(${userId})">
                        Mark as duplicate
                    </button>
                </div>
            </div>
        `;
    },

    cancelDuplicateForm(userId, email) {
        const section = document.getElementById(`duplicate-section-${userId}`);
        if (!section) return;
        section.innerHTML = `
            <button type="button" class="btn-secondary btn-sm" onclick="InstructorStudents.showDuplicateForm(${userId}, '${this.escapeJsString(email)}')">
                <i class="fas fa-clone"></i> Mark as duplicate…
            </button>
        `;
    },

    async submitDuplicate(userId) {
        const keptEl = document.getElementById(`duplicate-keep-${userId}`);
        const reasonEl = document.getElementById(`duplicate-reason-${userId}`);
        const confirmEl = document.getElementById(`duplicate-confirm-${userId}`);
        const errorEl = document.getElementById(`duplicate-error-${userId}`);

        if (errorEl) errorEl.textContent = '';

        const keptUserId = keptEl ? Number(keptEl.value) : 0;
        const reason = reasonEl ? reasonEl.value.trim() : '';
        const typed = confirmEl ? confirmEl.value.trim() : '';

        if (!keptUserId) {
            if (errorEl) errorEl.textContent = 'Pick which account to keep.';
            return;
        }
        if (typed !== 'DUPLICATE') {
            if (errorEl) errorEl.textContent = 'Type DUPLICATE exactly to confirm.';
            return;
        }

        try {
            await API.post(`/users/${userId}/mark-duplicate`, {
                kept_user_id: keptUserId,
                reason: reason
            });
            this.showToast('Account marked as duplicate', 'success');
            this.closeStudentModal();
            this.refresh();
        } catch (error) {
            if (errorEl) errorEl.textContent = error.message || 'Failed to mark account as duplicate.';
        }
    },

    // ---------------------------------------------------------------
    // Legacy password-reset modal (kept for backward compatibility,
    // in case it's still referenced elsewhere).
    // ---------------------------------------------------------------

    closeResetPasswordModal() {
        const existing = document.getElementById('reset-password-modal');
        if (existing) existing.remove();
    },

    // ---------------------------------------------------------------

    setupEventListeners() {
        document.getElementById('course-filter')?.addEventListener('change', (e) => {
            this.courseFilter = e.target.value || '';
            this.currentPage = 1;
            this.refresh();
        });
        document.getElementById('progress-filter')?.addEventListener('change', (e) => {
            this.progressFilter = e.target.value || '';
            this.currentPage = 1;
            this.refresh();
        });
        // Sort dropdown values are "field:direction" (e.g. "progress:desc"). Default "name:asc".
        document.getElementById('sort-filter')?.addEventListener('change', (e) => {
            const raw = e.target.value || 'name:asc';
            const [field, order] = raw.split(':');
            this.sortBy = field || 'name';
            this.sortOrder = (order === 'desc') ? 'desc' : 'asc';
            this.currentPage = 1;
            this.refresh();
        });
        document.getElementById('student-search')?.addEventListener('input', (e) => {
            clearTimeout(this.searchTimer);
            const value = e.target.value || '';
            this.searchTimer = setTimeout(() => {
                this.searchQuery = value.trim();
                this.currentPage = 1;
                this.refresh();
            }, 300);
        });
        document.getElementById('show-inactive')?.addEventListener('change', (e) => {
            this.showInactive = !!e.target.checked;
            this.currentPage = 1;
            this.refresh();
        });
        document.getElementById('refresh-btn')?.addEventListener('click', () => {
            this.loadStats();
            this.refresh();
        });
        document.getElementById('clear-filters-btn')?.addEventListener('click', () => {
            this.courseFilter = '';
            this.progressFilter = '';
            this.sortBy = 'name';
            this.sortOrder = 'asc';
            this.searchQuery = '';
            this.showInactive = false;
            const cf = document.getElementById('course-filter');
            const pf = document.getElementById('progress-filter');
            const sortSel = document.getElementById('sort-filter');
            const sf = document.getElementById('student-search');
            const ia = document.getElementById('show-inactive');
            if (cf) cf.value = '';
            if (pf) pf.value = '';
            if (sortSel) sortSel.value = 'name:asc';
            if (sf) sf.value = '';
            if (ia) ia.checked = false;
            this.currentPage = 1;
            this.refresh();
        });
        document.getElementById('view-roster')?.addEventListener('click', () => this.setViewMode('roster'));
        document.getElementById('view-duplicates')?.addEventListener('click', () => this.setViewMode('duplicates'));

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeStudentModal();
                this.closeResetPasswordModal();
            }
        });
    },

    formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return 'N/A';
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    },

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    },

    escapeJsString(value) {
        return String(value == null ? '' : value)
            .replace(/\\/g, '\\\\')
            .replace(/'/g, "\\'")
            .replace(/"/g, '\\"')
            .replace(/</g, '\\x3C')
            .replace(/>/g, '\\x3E');
    },

    showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        const icon = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        }[type] || 'fa-info-circle';
        toast.innerHTML = `<i class="fas ${icon}"></i><span>${this.escapeHtml(message)}</span>`;
        document.body.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 50);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
};

// Auto-initialize
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => InstructorStudents.init());
} else {
    InstructorStudents.init();
}
