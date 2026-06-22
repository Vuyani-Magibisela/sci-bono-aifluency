/**
 * Admin — Certificates Management
 *
 * KPI cards, per-course breakdown, recent certificate list with revoke action.
 * Reads from GET /api/certificates/admin/stats.
 */

const AdminCertificates = {
    pendingRevokeId: null,
    isReadOnly: false,    // when true (instructors), hide revoke + issue actions

    async init(options = {}) {
        this.isReadOnly = !!options.readOnly;

        const revokeClose = document.getElementById('revoke-close');
        if (revokeClose) revokeClose.addEventListener('click', () => this.closeRevokeModal());
        const revokeCancel = document.getElementById('revoke-cancel');
        if (revokeCancel) revokeCancel.addEventListener('click', () => this.closeRevokeModal());
        const revokeConfirm = document.getElementById('revoke-confirm');
        if (revokeConfirm) revokeConfirm.addEventListener('click', () => this.confirmRevoke());

        const incompleteClose = document.getElementById('incomplete-close');
        if (incompleteClose) incompleteClose.addEventListener('click', () => this.closeIncompleteModal());

        const backfillBtn = document.getElementById('backfill-missing-btn');
        if (backfillBtn) {
            backfillBtn.addEventListener('click', () => this.backfillMissing());
        }

        await this.load();
    },

    async backfillMissing() {
        if (!confirm('Issue certificates for all completed enrollments that are missing one?\n\nThis is safe to run multiple times — students who already have a certificate will be skipped.')) {
            return;
        }
        const btn = document.getElementById('backfill-missing-btn');
        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Working…';
        try {
            const res = await API.post('/certificates/backfill-missing', {});
            const data = res.data || res;
            alert(`Backfill complete.\n\nCandidates: ${data.candidates ?? 0}\nIssued: ${data.issued ?? 0}\nFailed: ${data.failed ?? 0}`);
            if (Array.isArray(data.failures) && data.failures.length) {
                console.warn('Backfill failures:', data.failures);
            }
            await this.load();
        } catch (err) {
            alert('Backfill failed: ' + err.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = original;
        }
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
            tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:1.5rem;color:#6b7280;">No courses yet</td></tr>`;
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
            // Make Enrolled cell drillable too — admins can investigate any course,
            // not just ones with a completed/issued gap.
            const completedCell = (enrollments > 0)
                ? `<button class="completed-link" data-course-id="${r.course_id}" data-course-title="${this.esc(r.course_title)}" title="See who hasn't been certified yet">${completed}${(completed > issued) ? ` <span style="color:#b45309;">(+${completed - issued} not issued)</span>` : ''}</button>`
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
            this.renderIncomplete(courseId, data.incomplete || []);
        } catch (err) {
            document.querySelector('#incomplete-table tbody').innerHTML =
                `<tr><td colspan="7" style="text-align:center;padding:1.5rem;color:#dc2626;">${this.esc(err.message || 'Failed to load')}</td></tr>`;
            document.getElementById('incomplete-summary').textContent = '';
        }
    },

    closeIncompleteModal() {
        const modal = document.getElementById('incomplete-modal');
        if (modal) modal.classList.remove('active');
    },

    renderIncomplete(courseId, rows) {
        const tbody = document.querySelector('#incomplete-table tbody');
        const summary = document.getElementById('incomplete-summary');

        if (!rows.length) {
            summary.textContent = 'Every enrolled student in this course has a certificate. 🎉';
            tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:1.5rem;color:#6b7280;">No outstanding students</td></tr>`;
            return;
        }

        const eligibleNow = rows.filter(r => r.eligible_now).length;
        summary.textContent = `${rows.length} student${rows.length === 1 ? '' : 's'} enrolled but not yet certified — ${eligibleNow} eligible to issue now.`;

        tbody.innerHTML = rows.map(r => {
            const lessonsOk = r.lessons_total === 0 || r.lessons_completed === r.lessons_total;
            const quizzesOk = r.quizzes_total === 0 || r.quizzes_passed === r.quizzes_total;
            const projectsOk = r.projects_total === 0 || r.projects_submitted === r.projects_total;

            const missingPills = (r.missing && r.missing.length)
                ? r.missing.map(t => `<span class="gap-pill">${this.esc(t)}</span>`).join('')
                : '<span style="color:#16a34a;">—</span>';

            let actionCell;
            if (this.isReadOnly) {
                actionCell = r.eligible_now
                    ? '<span class="pill pill-warn">Pending issue</span>'
                    : '<span class="pill" style="background:#e0e7ff;color:#3730a3;">Awaiting student</span>';
            } else if (r.eligible_now) {
                actionCell = `<button class="btn-secondary" data-action="issue-now" data-user-id="${r.user_id}" data-course-id="${courseId}">Issue now</button>`;
            } else {
                actionCell = '<span style="color:#94a3b8;font-size:.8rem;">Awaiting student</span>';
            }

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
                    <td>${actionCell}</td>
                </tr>`;
        }).join('');

        tbody.querySelectorAll('[data-action="issue-now"]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const userId = parseInt(btn.dataset.userId, 10);
                const cid = parseInt(btn.dataset.courseId, 10);
                btn.disabled = true;
                btn.textContent = 'Issuing…';
                try {
                    await API.post('/certificates', { user_id: userId, course_id: cid });
                    btn.textContent = '✓ Issued';
                    await this.load();
                    setTimeout(() => this.openIncompleteModal(cid, document.getElementById('incomplete-title').textContent.split(' — ')[0]), 250);
                } catch (err) {
                    btn.disabled = false;
                    btn.textContent = 'Issue now';
                    alert('Failed to issue: ' + (err.message || 'unknown error'));
                }
            });
        });
    },

    renderRecent(rows) {
        const tbody = document.querySelector('#recent-table tbody');
        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:1.5rem;color:#6b7280;">No certificates yet</td></tr>`;
            return;
        }
        tbody.innerHTML = rows.map(c => {
            const dateValue = c.issue_date || c.effective_issue_date || c.issued_date;
            const dateText = dateValue ? new Date(dateValue).toLocaleDateString() : '—';
            const statusPill = Number(c.is_revoked) === 1
                ? `<span class="pill pill-bad">Revoked</span>`
                : `<span class="pill pill-ok">Active</span>`;
            const actionBtns = Number(c.is_revoked) === 1
                ? `<button class="btn-secondary" data-action="reinstate" data-id="${c.id}">Reinstate</button>`
                : `<button class="btn-secondary" data-action="revoke" data-id="${c.id}" style="color:#b91c1c;">Revoke</button>`;
            return `
                <tr>
                    <td style="font-family:monospace;font-size:.8rem;">${this.esc(c.certificate_number)}</td>
                    <td>${this.esc(c.student_name)}</td>
                    <td>${this.esc(c.course_title)}</td>
                    <td>${this.esc(dateText)}</td>
                    <td>${statusPill}</td>
                    <td><div class="row-actions">${actionBtns}</div></td>
                </tr>`;
        }).join('');

        tbody.querySelectorAll('[data-action="revoke"]').forEach(b => {
            b.addEventListener('click', () => this.openRevokeModal(parseInt(b.dataset.id, 10)));
        });
        tbody.querySelectorAll('[data-action="reinstate"]').forEach(b => {
            b.addEventListener('click', () => this.reinstate(parseInt(b.dataset.id, 10)));
        });
    },

    openRevokeModal(certId) {
        this.pendingRevokeId = certId;
        document.getElementById('revoke-reason').value = '';
        document.getElementById('revoke-modal').classList.add('active');
    },

    closeRevokeModal() {
        this.pendingRevokeId = null;
        document.getElementById('revoke-modal').classList.remove('active');
    },

    async confirmRevoke() {
        const id = this.pendingRevokeId;
        if (!id) return;
        const reason = document.getElementById('revoke-reason').value.trim();
        try {
            await API.put(`/certificates/${id}`, {
                is_revoked: 1,
                revocation_reason: reason || null
            });
            this.closeRevokeModal();
            await this.load();
        } catch (err) {
            alert('Failed to revoke: ' + err.message);
        }
    },

    async reinstate(id) {
        if (!confirm('Reinstate this certificate? It will become valid again.')) return;
        try {
            await API.put(`/certificates/${id}`, { is_revoked: 0 });
            await this.load();
        } catch (err) {
            alert('Failed to reinstate: ' + err.message);
        }
    },

    showLoadError() {
        document.querySelector('#per-course-table tbody').innerHTML =
            `<tr><td colspan="6" style="text-align:center;padding:1.5rem;color:#dc2626;">Failed to load data</td></tr>`;
        document.querySelector('#recent-table tbody').innerHTML =
            `<tr><td colspan="6" style="text-align:center;padding:1.5rem;color:#dc2626;">Failed to load data</td></tr>`;
    },

    esc(s) {
        const div = document.createElement('div');
        div.textContent = s == null ? '' : String(s);
        return div.innerHTML;
    }
};

document.addEventListener('DOMContentLoaded', () => AdminCertificates.init());
