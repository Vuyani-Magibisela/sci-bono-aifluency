/**
 * Instructor — Certificates view (read-only, school-scoped).
 *
 * Reuses the admin /certificates/admin/stats and /certificates/admin/incomplete
 * endpoints. The backend resolves the scope from the caller's role
 * (primary_school_id for teacher/instructor), so no client-side scoping needed.
 *
 * Differences vs admin/certificates.html:
 *   - No revoke / reinstate / backfill controls
 *   - No "Issue now" button in the incomplete modal (read-only)
 *   - Sidebar + page chrome match the instructor section
 */

const InstructorCertificates = {
    async init() {
        const incompleteClose = document.getElementById('incomplete-close');
        if (incompleteClose) incompleteClose.addEventListener('click', () => this.closeIncompleteModal());
        await this.load();
    },

    async load() {
        try {
            const res = await API.get('/certificates/admin/stats');
            const data = res.data || res;
            this.renderStats(data.stats || {});
            this.renderPerCourse(data.per_course || []);
            this.renderRecent(data.recent || []);
        } catch (err) {
            console.error('Failed to load certificate stats:', err);
            this.showLoadError();
        }
    },

    renderStats(stats) {
        document.getElementById('stat-total').textContent   = stats.total ?? 0;
        document.getElementById('stat-active').textContent  = stats.active ?? 0;
        document.getElementById('stat-revoked').textContent = stats.revoked ?? 0;
        document.getElementById('stat-month').textContent   = stats.this_month ?? 0;
        document.getElementById('stat-30').textContent      = stats.last_30_days ?? 0;
    },

    renderPerCourse(rows) {
        const tbody = document.querySelector('#per-course-table tbody');
        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:1.5rem;color:#6b7280;">No data for your school yet</td></tr>`;
            return;
        }
        tbody.innerHTML = rows.map(r => {
            const enrollments = Number(r.enrollments || 0);
            const completed = Number(r.completed || 0);
            const issued = Number(r.certificates_issued || 0);
            const rate = enrollments > 0 ? Math.round((completed / enrollments) * 100) : 0;
            const tplCell = r.template_id
                ? this.esc(r.template_name || `Template #${r.template_id}`)
                : '<span style="color:#94a3b8;">— Default —</span>';
            const completedCell = (enrollments > 0)
                ? `<button class="completed-link" data-course-id="${r.course_id}" data-course-title="${this.esc(r.course_title)}">${completed}${(completed > issued) ? ` <span style="color:#b45309;">(+${completed - issued} not issued)</span>` : ''}</button>`
                : completed;
            return `
                <tr>
                    <td>${this.esc(r.course_title)}</td>
                    <td>${tplCell}</td>
                    <td>${enrollments}</td>
                    <td>${completedCell}</td>
                    <td>${issued}</td>
                    <td>${rate}%</td>
                </tr>`;
        }).join('');

        tbody.querySelectorAll('.completed-link').forEach(btn => {
            btn.addEventListener('click', () => {
                const courseId = parseInt(btn.dataset.courseId, 10);
                const courseTitle = btn.dataset.courseTitle || 'this course';
                this.openIncompleteModal(courseId, courseTitle);
            });
        });
    },

    renderRecent(rows) {
        const tbody = document.querySelector('#recent-table tbody');
        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:1.5rem;color:#6b7280;">No certificates yet</td></tr>`;
            return;
        }
        tbody.innerHTML = rows.map(c => {
            const dateValue = c.issue_date || c.effective_issue_date || c.issued_date;
            const dateText = dateValue ? new Date(dateValue).toLocaleDateString() : '—';
            const statusPill = Number(c.is_revoked) === 1
                ? `<span class="pill pill-bad">Revoked</span>`
                : `<span class="pill pill-ok">Active</span>`;
            return `
                <tr>
                    <td style="font-family:monospace;font-size:.8rem;">${this.esc(c.certificate_number)}</td>
                    <td>${this.esc(c.student_name)}</td>
                    <td>${this.esc(c.course_title)}</td>
                    <td>${this.esc(dateText)}</td>
                    <td>${statusPill}</td>
                </tr>`;
        }).join('');
    },

    async openIncompleteModal(courseId, courseTitle) {
        const modal = document.getElementById('incomplete-modal');
        if (!modal) return;
        document.getElementById('incomplete-title').textContent = `${courseTitle} — students not yet certified`;
        document.getElementById('incomplete-summary').textContent = 'Loading…';
        document.querySelector('#incomplete-table tbody').innerHTML =
            `<tr><td colspan="7" style="text-align:center;padding:1.5rem;">Loading…</td></tr>`;
        modal.classList.add('active');

        try {
            const res = await API.get(`/certificates/admin/incomplete?course_id=${courseId}`);
            const data = res.data || res;
            this.renderIncomplete(data.incomplete || []);
        } catch (err) {
            document.querySelector('#incomplete-table tbody').innerHTML =
                `<tr><td colspan="7" style="text-align:center;padding:1.5rem;color:#dc2626;">${this.esc(err.message || 'Failed to load')}</td></tr>`;
            document.getElementById('incomplete-summary').textContent = '';
        }
    },

    closeIncompleteModal() {
        document.getElementById('incomplete-modal').classList.remove('active');
    },

    renderIncomplete(rows) {
        const tbody = document.querySelector('#incomplete-table tbody');
        const summary = document.getElementById('incomplete-summary');

        if (!rows.length) {
            summary.textContent = 'Every enrolled student in this course has a certificate. 🎉';
            tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:1.5rem;color:#6b7280;">No outstanding students</td></tr>`;
            return;
        }

        const eligibleNow = rows.filter(r => r.eligible_now).length;
        summary.textContent = `${rows.length} student${rows.length === 1 ? '' : 's'} enrolled but not yet certified — ${eligibleNow} ready for the admin to issue.`;

        tbody.innerHTML = rows.map(r => {
            const lessonsOk = r.lessons_total === 0 || r.lessons_completed === r.lessons_total;
            const quizzesOk = r.quizzes_total === 0 || r.quizzes_passed === r.quizzes_total;
            const projectsOk = r.projects_total === 0 || r.projects_submitted === r.projects_total;

            const missingPills = (r.missing && r.missing.length)
                ? r.missing.map(t => `<span class="gap-pill">${this.esc(t)}</span>`).join('')
                : '<span style="color:#16a34a;">—</span>';

            const statusCell = r.eligible_now
                ? '<span class="pill pill-warn">Pending issue</span>'
                : '<span class="pill" style="background:#e0e7ff;color:#3730a3;">Awaiting student</span>';

            return `
                <tr>
                    <td>
                        <div style="font-weight:600;">${this.esc(r.name)}</div>
                        <div style="font-size:.75rem;color:#6b7280;">${this.esc(r.email)}</div>
                    </td>
                    <td>${this.esc(r.school_name || '—')}</td>
                    <td><span class="progress-mini ${lessonsOk ? 'ok' : ''}">${r.lessons_completed}/${r.lessons_total}</span></td>
                    <td><span class="progress-mini ${quizzesOk ? 'ok' : ''}">${r.quizzes_passed}/${r.quizzes_total}</span></td>
                    <td><span class="progress-mini ${projectsOk ? 'ok' : ''}">${r.projects_submitted}/${r.projects_total}</span></td>
                    <td>${missingPills}</td>
                    <td>${statusCell}</td>
                </tr>`;
        }).join('');
    },

    showLoadError() {
        document.querySelector('#per-course-table tbody').innerHTML =
            `<tr><td colspan="6" style="text-align:center;padding:1.5rem;color:#dc2626;">Failed to load data</td></tr>`;
        document.querySelector('#recent-table tbody').innerHTML =
            `<tr><td colspan="5" style="text-align:center;padding:1.5rem;color:#dc2626;">Failed to load data</td></tr>`;
    },

    esc(s) {
        const div = document.createElement('div');
        div.textContent = s == null ? '' : String(s);
        return div.innerHTML;
    }
};

document.addEventListener('DOMContentLoaded', () => InstructorCertificates.init());
