/**
 * Admin Feedback Management Module
 * Handles listing, filtering, and status updates for user feedback
 */

const AdminFeedback = {
    currentStatus: '',
    currentPage: 1,
    pageSize: 20,

    async init() {
        this.bindTabs();
        this.bindDetailOverlay();
        await this.loadFeedback();
    },

    bindTabs() {
        document.getElementById('feedback-tabs').addEventListener('click', (e) => {
            const tab = e.target.closest('.feedback-tab');
            if (!tab) return;

            document.querySelectorAll('.feedback-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            this.currentStatus = tab.dataset.status;
            this.currentPage = 1;
            this.loadFeedback();
        });
    },

    bindDetailOverlay() {
        const overlay = document.getElementById('feedback-detail-overlay');
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) this.closeDetail();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') this.closeDetail();
        });
    },

    async loadFeedback() {
        const list = document.getElementById('feedback-list');
        list.innerHTML = '<div class="loading-spinner">Loading feedback...</div>';

        try {
            let params = `?page=${this.currentPage}&page_size=${this.pageSize}`;
            if (this.currentStatus) params += `&status=${this.currentStatus}`;

            const response = await API.get('/feedback' + params);

            if (!response.success) {
                list.innerHTML = '<p>Failed to load feedback.</p>';
                return;
            }

            const items = response.data?.items || response.data || [];
            const total = response.data?.total || 0;
            const totalPages = response.data?.total_pages || 1;

            this.updateCounts(items, total);

            if (items.length === 0) {
                list.innerHTML = '<div class="dashboard-card" style="text-align:center;padding:3rem;"><p style="color:#999;">No feedback found.</p></div>';
                document.getElementById('feedback-pagination').innerHTML = '';
                return;
            }

            list.innerHTML = items.map(item => this.renderCard(item)).join('');

            // Bind card clicks
            list.querySelectorAll('.feedback-card').forEach(card => {
                card.addEventListener('click', () => {
                    this.openDetail(card.dataset.id);
                });
            });

            this.renderPagination(totalPages);
        } catch (err) {
            list.innerHTML = '<p>Error loading feedback. Please try again.</p>';
        }
    },

    updateCounts(items, total) {
        // Update the "All" count with the total from current query
        const allCount = document.getElementById('count-all');
        if (!this.currentStatus) {
            allCount.textContent = total;
        }

        // Load counts for each status in background
        this.loadStatusCounts();
    },

    async loadStatusCounts() {
        const statuses = ['new', 'in_review', 'resolved', 'dismissed'];
        const countIds = {
            'new': 'count-new',
            'in_review': 'count-review',
            'resolved': 'count-resolved',
            'dismissed': 'count-dismissed'
        };

        let allTotal = 0;

        for (const status of statuses) {
            try {
                const res = await API.get(`/feedback?status=${status}&page=1&page_size=1`);
                const count = res.data?.total || 0;
                allTotal += count;
                const el = document.getElementById(countIds[status]);
                if (el) el.textContent = count;
            } catch (e) {
                // Silently ignore count errors
            }
        }

        document.getElementById('count-all').textContent = allTotal;
    },

    renderCard(item) {
        const date = new Date(item.created_at).toLocaleDateString('en-ZA', {
            year: 'numeric', month: 'short', day: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });

        const userName = item.user_name || 'Anonymous';
        const statusClass = (item.status || 'new').replace(' ', '_');

        return `
            <div class="feedback-card" data-id="${item.id}">
                <div class="feedback-card-header">
                    <span class="feedback-type-badge ${item.feedback_type}">${item.feedback_type}</span>
                    <span class="feedback-status-badge ${statusClass}">${this.formatStatus(item.status)}</span>
                </div>
                <div class="feedback-card-message">${this.escapeHtml(item.message)}</div>
                <div class="feedback-card-meta">
                    <span><i class="fas fa-user"></i> ${this.escapeHtml(userName)}</span>
                    <span><i class="fas fa-clock"></i> ${date}</span>
                    ${item.page_url ? `<span><i class="fas fa-link"></i> ${this.escapeHtml(this.shortenUrl(item.page_url))}</span>` : ''}
                </div>
            </div>
        `;
    },

    formatStatus(status) {
        const map = { 'new': 'New', 'in_review': 'In Review', 'resolved': 'Resolved', 'dismissed': 'Dismissed' };
        return map[status] || status;
    },

    shortenUrl(url) {
        try {
            const u = new URL(url);
            return u.pathname + u.search;
        } catch {
            return url.length > 60 ? url.substring(0, 60) + '...' : url;
        }
    },

    escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    },

    renderPagination(totalPages) {
        const container = document.getElementById('feedback-pagination');
        if (totalPages <= 1) {
            container.innerHTML = '';
            return;
        }

        let html = '';
        for (let i = 1; i <= totalPages; i++) {
            const active = i === this.currentPage ? 'background:var(--primary-color);color:#fff;' : '';
            html += `<button onclick="AdminFeedback.goToPage(${i})" style="padding:0.4rem 0.85rem;border:1px solid #ddd;border-radius:6px;cursor:pointer;${active}">${i}</button>`;
        }
        container.innerHTML = html;
    },

    goToPage(page) {
        this.currentPage = page;
        this.loadFeedback();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    async openDetail(id) {
        const overlay = document.getElementById('feedback-detail-overlay');
        const panel = document.getElementById('feedback-detail-panel');
        panel.innerHTML = '<div class="loading-spinner">Loading...</div>';
        overlay.classList.add('active');

        try {
            const response = await API.get(`/feedback/${id}`);
            if (!response.success) {
                panel.innerHTML = '<p>Failed to load feedback details.</p>';
                return;
            }

            const item = response.data;
            const date = new Date(item.created_at).toLocaleString('en-ZA');
            const browserInfo = item.browser_info
                ? Object.entries(item.browser_info).map(([k, v]) => `<li><strong>${this.escapeHtml(k)}:</strong> ${this.escapeHtml(String(v))}</li>`).join('')
                : '<li>Not available</li>';

            panel.innerHTML = `
                <h3>
                    Feedback #${item.id}
                    <button onclick="AdminFeedback.closeDetail()" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:#999;">&times;</button>
                </h3>

                <div class="feedback-detail-section">
                    <label>Type</label>
                    <p><span class="feedback-type-badge ${item.feedback_type}">${item.feedback_type}</span></p>
                </div>

                <div class="feedback-detail-section">
                    <label>Status</label>
                    <p><span class="feedback-status-badge ${(item.status || '').replace(' ', '_')}">${this.formatStatus(item.status)}</span></p>
                </div>

                <div class="feedback-detail-section">
                    <label>Message</label>
                    <p>${this.escapeHtml(item.message)}</p>
                </div>

                <div class="feedback-detail-section">
                    <label>Submitted By</label>
                    <p>${item.user_name ? this.escapeHtml(item.user_name) + ' (' + this.escapeHtml(item.user_email) + ')' : 'Anonymous'}
                    ${item.contact_email ? '<br>Contact: ' + this.escapeHtml(item.contact_email) : ''}</p>
                </div>

                <div class="feedback-detail-section">
                    <label>Date</label>
                    <p>${date}</p>
                </div>

                <div class="feedback-detail-section">
                    <label>Page URL</label>
                    <p>${item.page_url ? '<a href="' + this.escapeHtml(item.page_url) + '" target="_blank">' + this.escapeHtml(item.page_url) + '</a>' : 'N/A'}</p>
                </div>

                <div class="feedback-detail-section">
                    <label>Screen Resolution</label>
                    <p>${this.escapeHtml(item.screen_resolution) || 'N/A'}</p>
                </div>

                <div class="feedback-detail-section">
                    <label>User Agent</label>
                    <p style="font-size:0.82rem;word-break:break-all;">${this.escapeHtml(item.user_agent) || 'N/A'}</p>
                </div>

                <div class="feedback-detail-section">
                    <label>Browser Info</label>
                    <ul style="margin:0;padding-left:1.25rem;font-size:0.88rem;">${browserInfo}</ul>
                </div>

                ${item.admin_response ? `
                <div class="feedback-detail-section">
                    <label style="color:#6366f1;">Reply Sent to User</label>
                    <p>${this.escapeHtml(item.admin_response)}</p>
                </div>` : ''}

                ${item.admin_notes ? `
                <div class="feedback-detail-section">
                    <label>Internal Admin Notes</label>
                    <p>${this.escapeHtml(item.admin_notes)}</p>
                </div>` : ''}

                ${item.resolver_name ? `
                <div class="feedback-detail-section">
                    <label>Resolved By</label>
                    <p>${this.escapeHtml(item.resolver_name)} on ${new Date(item.resolved_at).toLocaleString('en-ZA')}</p>
                </div>` : ''}

                <hr style="margin:1.5rem 0;border:none;border-top:1px solid #eee;">

                <div class="feedback-detail-actions">
                    <label style="font-size:0.85rem;font-weight:600;color:#999;width:100%;">Update Status</label>
                    <select id="detail-status">
                        <option value="new" ${item.status === 'new' ? 'selected' : ''}>New</option>
                        <option value="in_review" ${item.status === 'in_review' ? 'selected' : ''}>In Review</option>
                        <option value="resolved" ${item.status === 'resolved' ? 'selected' : ''}>Resolved</option>
                        <option value="dismissed" ${item.status === 'dismissed' ? 'selected' : ''}>Dismissed</option>
                    </select>

                    <label style="font-size:0.8rem;font-weight:600;color:#6366f1;margin-top:12px;display:block;">
                        Reply to User <span style="font-weight:400;color:#94a3b8;">(sent as notification &amp; email)</span>
                    </label>
                    <textarea id="detail-response" placeholder="Write a reply visible to the user (optional)…" style="min-height:90px;">${this.escapeHtml(item.admin_response || '')}</textarea>

                    <label style="font-size:0.8rem;font-weight:600;color:#94a3b8;margin-top:10px;display:block;">
                        Internal Notes <span style="font-weight:400;">(admin only, not sent to user)</span>
                    </label>
                    <textarea id="detail-notes" placeholder="Add internal admin notes (optional)…">${this.escapeHtml(item.admin_notes || '')}</textarea>
                    <button class="btn-update" onclick="AdminFeedback.updateStatus(${item.id})">Update</button>
                </div>
            `;
        } catch (err) {
            panel.innerHTML = '<p>Error loading feedback details.</p>';
        }
    },

    closeDetail() {
        document.getElementById('feedback-detail-overlay').classList.remove('active');
    },

    async updateStatus(id) {
        const status = document.getElementById('detail-status').value;
        const adminNotes = document.getElementById('detail-notes').value.trim();
        const adminResponse = document.getElementById('detail-response').value.trim();

        try {
            const response = await API.put(`/feedback/${id}/status`, {
                status: status,
                admin_notes: adminNotes || null,
                admin_response: adminResponse || null
            });

            if (response.success) {
                this.closeDetail();
                this.loadFeedback();
            } else {
                alert(response.message || 'Failed to update status.');
            }
        } catch (err) {
            alert('Error updating status. Please try again.');
        }
    }
};

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => AdminFeedback.init());
} else {
    AdminFeedback.init();
}
