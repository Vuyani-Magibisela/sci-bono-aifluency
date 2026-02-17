/**
 * Project Submission Manager
 * Handles project detail display and submission (URL or text)
 */

const ProjectUpload = {
    projectId: null,
    project: null,

    /**
     * Initialize project submission page
     */
    async init(projectId) {
        this.projectId = projectId;
        await this.loadProject();
    },

    /**
     * Load project details from API
     */
    async loadProject() {
        const spinner = document.getElementById('loading-spinner');
        const content = document.getElementById('project-content');

        spinner.style.display = 'block';

        try {
            const response = await API.get(`/projects/${this.projectId}`);

            if (response.success && response.data && response.data.project) {
                this.project = response.data.project;
                this.renderProject();
                await this.loadSubmissions();
                this.setupEventListeners();
                content.style.display = 'block';
            } else {
                showToast('Project not found', 'error');
            }
        } catch (error) {
            console.error('Failed to load project:', error);
            showToast('Failed to load project. Please try again.', 'error');
        } finally {
            spinner.style.display = 'none';
        }
    },

    /**
     * Render project details into the page
     */
    renderProject() {
        const p = this.project;

        document.getElementById('project-title').textContent = p.title;
        document.getElementById('breadcrumb-project').textContent = p.title;
        document.getElementById('module-title').textContent = p.module_title || 'Module ' + (p.module_id || '');
        document.getElementById('max-score').textContent = p.max_score != null ? p.max_score : 100;

        document.getElementById('project-description').innerHTML = this.formatText(p.description);
        document.getElementById('project-instructions').innerHTML = this.formatText(p.instructions);
        document.getElementById('project-requirements').innerHTML = this.formatText(p.requirements);

        // Show due date if set
        if (p.due_date) {
            const due = new Date(p.due_date).toLocaleDateString('en-ZA', {
                weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
            });
            const dueDateEl = document.getElementById('due-date-display');
            if (dueDateEl) {
                dueDateEl.textContent = 'Due: ' + due;
                dueDateEl.style.display = 'inline-block';
            }
        }
    },

    /**
     * Load and render previous submissions
     */
    async loadSubmissions() {
        const user = Auth.getUser();
        if (!user) return;

        try {
            const response = await API.get(`/projects/${this.projectId}/submissions`);
            const submissions = response.data?.submissions || [];

            if (submissions.length > 0) {
                this.renderSubmissionHistory(submissions);

                // Show previous submission alert
                const latest = submissions[0];
                const prevSection = document.getElementById('previous-submissions');
                const prevInfo = document.getElementById('previous-submission-info');
                if (prevSection && prevInfo) {
                    const submittedDate = latest.submitted_at
                        ? new Date(latest.submitted_at).toLocaleDateString('en-ZA')
                        : 'N/A';
                    prevSection.style.display = 'block';
                    prevInfo.textContent = `Last submitted on ${submittedDate}. Status: ${this.formatStatus(latest.status)}. You can submit a new version below.`;
                }
            }
        } catch (error) {
            console.warn('Could not load submission history:', error);
        }
    },

    /**
     * Set up form event listeners
     */
    setupEventListeners() {
        const submitBtn = document.getElementById('submit-btn');
        const submissionUrl = document.getElementById('submission-url');
        const submissionText = document.getElementById('submission-notes');
        const tabUrl = document.getElementById('tab-url');
        const tabText = document.getElementById('tab-text');
        const panelUrl = document.getElementById('panel-url');
        const panelText = document.getElementById('panel-text');

        // Tab switching
        if (tabUrl && tabText) {
            tabUrl.addEventListener('click', () => {
                tabUrl.classList.add('active');
                tabText.classList.remove('active');
                panelUrl.style.display = 'block';
                panelText.style.display = 'none';
                this.updateSubmitButton();
            });

            tabText.addEventListener('click', () => {
                tabText.classList.add('active');
                tabUrl.classList.remove('active');
                panelText.style.display = 'block';
                panelUrl.style.display = 'none';
                this.updateSubmitButton();
            });
        }

        // Validate on input
        if (submissionUrl) {
            submissionUrl.addEventListener('input', () => this.updateSubmitButton());
        }
        if (submissionText) {
            submissionText.addEventListener('input', (e) => {
                const counter = document.getElementById('notes-char-count');
                if (counter) counter.textContent = e.target.value.length;
                this.updateSubmitButton();
            });
        }

        // Submit button
        if (submitBtn) {
            submitBtn.addEventListener('click', () => this.submitProject());
        }
    },

    /**
     * Enable/disable submit button based on input
     */
    updateSubmitButton() {
        const submitBtn = document.getElementById('submit-btn');
        if (!submitBtn) return;

        const tabUrl = document.getElementById('tab-url');
        const isUrlMode = tabUrl && tabUrl.classList.contains('active');

        if (isUrlMode) {
            const url = (document.getElementById('submission-url')?.value || '').trim();
            submitBtn.disabled = url.length === 0;
        } else {
            const text = (document.getElementById('submission-notes')?.value || '').trim();
            submitBtn.disabled = text.length === 0;
        }
    },

    /**
     * Submit the project to the API
     */
    async submitProject() {
        const submitBtn = document.getElementById('submit-btn');
        const tabUrl = document.getElementById('tab-url');
        const isUrlMode = tabUrl && tabUrl.classList.contains('active');

        const submissionUrl = (document.getElementById('submission-url')?.value || '').trim();
        const submissionText = (document.getElementById('submission-notes')?.value || '').trim();

        if (isUrlMode && !submissionUrl) {
            showToast('Please enter a URL for your submission', 'error');
            return;
        }
        if (!isUrlMode && !submissionText) {
            showToast('Please enter your submission text', 'error');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

        try {
            const body = {};
            if (isUrlMode) {
                body.submission_url = submissionUrl;
            } else {
                body.submission_text = submissionText;
            }

            const response = await API.post(`/projects/${this.projectId}/submit`, body);

            if (response.success) {
                showToast('Project submitted successfully!', 'success');

                // Reset form
                if (document.getElementById('submission-url')) document.getElementById('submission-url').value = '';
                if (document.getElementById('submission-notes')) {
                    document.getElementById('submission-notes').value = '';
                    const counter = document.getElementById('notes-char-count');
                    if (counter) counter.textContent = '0';
                }

                // Reload submissions
                await this.loadSubmissions();
            } else {
                throw new Error(response.message || 'Submission failed');
            }
        } catch (error) {
            console.error('Submission error:', error);
            showToast('Submission failed: ' + error.message, 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Project';
            this.updateSubmitButton();
        }
    },

    /**
     * Render submission history
     */
    renderSubmissionHistory(submissions) {
        const section = document.getElementById('submission-history-section');
        const list = document.getElementById('submission-history-list');
        if (!section || !list) return;

        section.style.display = 'block';
        list.innerHTML = '';

        submissions.forEach(submission => {
            const card = document.createElement('div');
            card.className = 'submission-card';

            const submittedDate = submission.submitted_at
                ? new Date(submission.submitted_at).toLocaleDateString('en-ZA', {
                    day: 'numeric', month: 'short', year: 'numeric',
                    hour: '2-digit', minute: '2-digit'
                  })
                : 'N/A';

            const statusClass = this.getStatusClass(submission.status);
            const scoreHtml = submission.score != null
                ? `<div class="submission-score"><i class="fas fa-star"></i> Score: <strong>${submission.score} / ${this.project.max_score || 100}</strong></div>`
                : '';
            const feedbackHtml = submission.feedback
                ? `<div class="submission-feedback"><strong>Feedback:</strong><p>${this.escapeHtml(submission.feedback)}</p></div>`
                : '';

            let contentHtml = '';
            const fileUrl = submission.submission_file_url || submission.submission_url;
            if (fileUrl) {
                contentHtml = `<div class="submission-content"><strong>Submitted URL:</strong> <a href="${this.escapeHtml(fileUrl)}" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i> View Submission</a></div>`;
            } else if (submission.submission_text) {
                contentHtml = `<div class="submission-content"><strong>Submitted Text:</strong><p class="submission-text-preview">${this.escapeHtml(submission.submission_text.substring(0, 300))}${submission.submission_text.length > 300 ? '...' : ''}</p></div>`;
            }

            card.innerHTML = `
                <div class="submission-header">
                    <div><i class="fas fa-clock"></i> <strong>${submittedDate}</strong></div>
                    <span class="status-badge ${statusClass}">${this.formatStatus(submission.status)}</span>
                </div>
                ${contentHtml}
                ${scoreHtml}
                ${feedbackHtml}
            `;

            list.appendChild(card);
        });
    },

    // ---- Helpers ----

    formatText(text) {
        if (!text) return '<p class="text-muted">No information provided.</p>';
        return '<p>' + this.escapeHtml(text).replace(/\n/g, '<br>') + '</p>';
    },

    getStatusClass(status) {
        const map = { submitted: 'status-pending', graded: 'status-graded', pending: 'status-pending', returned: 'status-returned' };
        return map[status] || 'status-default';
    },

    formatStatus(status) {
        const map = { submitted: 'Submitted', graded: 'Graded', pending: 'Pending Review', returned: 'Returned for Revision' };
        return map[status] || (status || 'Unknown');
    },

    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};
