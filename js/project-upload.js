/**
 * Project Upload Manager
 * Phase B: Complete Core Features
 * Handles project submission with drag-drop file upload
 */

const ProjectUpload = {
    projectId: null,
    project: null,
    selectedFiles: [],
    uploadedFileIds: [],

    /**
     * Initialize project upload page
     */
    async init(projectId) {
        this.projectId = projectId;

        // Load project details
        await this.loadProject();

        // Load submission history
        await this.loadSubmissionHistory();

        // Setup event listeners
        this.setupEventListeners();
    },

    /**
     * Load project details
     */
    async loadProject() {
        const spinner = document.getElementById('loading-spinner');
        const content = document.getElementById('project-content');

        spinner.style.display = 'block';

        try {
            const response = await apiRequest(`/api/projects/${this.projectId}`);

            if (response.success && response.project) {
                this.project = response.project;
                this.renderProject();
                content.style.display = 'block';
            } else {
                showToast('Project not found', 'error');
                setTimeout(() => {
                    window.location.href = 'student-dashboard.html';
                }, 2000);
            }
        } catch (error) {
            showToast('Failed to load project: ' + error.message, 'error');
        } finally {
            spinner.style.display = 'none';
        }
    },

    /**
     * Render project details
     */
    renderProject() {
        document.getElementById('project-title').textContent = this.project.title;
        document.getElementById('breadcrumb-project').textContent = this.project.title;
        document.getElementById('module-title').textContent = this.project.module_title || 'Module';
        document.getElementById('max-score').textContent = this.project.max_score || 100;

        document.getElementById('project-description').innerHTML = this.formatText(this.project.description);
        document.getElementById('project-instructions').innerHTML = this.formatText(this.project.instructions);
        document.getElementById('project-requirements').innerHTML = this.formatText(this.project.requirements);
    },

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        const dropZone = document.getElementById('drop-zone');
        const fileInput = document.getElementById('file-input');
        const submitBtn = document.getElementById('submit-btn');
        const notesTextarea = document.getElementById('submission-notes');

        // Drop zone click
        dropZone.addEventListener('click', () => {
            fileInput.click();
        });

        // File input change
        fileInput.addEventListener('change', (e) => {
            this.handleFiles(e.target.files);
        });

        // Drag and drop events
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('drag-over');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('drag-over');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
            this.handleFiles(e.dataTransfer.files);
        });

        // Submit button
        submitBtn.addEventListener('click', () => {
            this.submitProject();
        });

        // Notes character count
        notesTextarea.addEventListener('input', (e) => {
            document.getElementById('notes-char-count').textContent = e.target.value.length;
        });
    },

    /**
     * Handle selected files
     */
    handleFiles(files) {
        const validFiles = [];

        for (let file of files) {
            // Validate file
            const validation = this.validateFile(file);
            if (!validation.valid) {
                showToast(validation.error, 'error');
                continue;
            }

            // Check for duplicates
            const isDuplicate = this.selectedFiles.some(f => f.name === file.name && f.size === file.size);
            if (isDuplicate) {
                showToast(`File "${file.name}" already added`, 'warning');
                continue;
            }

            validFiles.push(file);
        }

        if (validFiles.length > 0) {
            this.selectedFiles.push(...validFiles);
            this.renderFileList();
            this.updateSubmitButton();
        }
    },

    /**
     * Validate file
     */
    validateFile(file) {
        const maxSize = 10 * 1024 * 1024; // 10MB
        const allowedExtensions = ['pdf', 'doc', 'docx', 'zip', 'png', 'jpg', 'jpeg'];

        // Check size
        if (file.size > maxSize) {
            return {
                valid: false,
                error: `File "${file.name}" exceeds 10MB limit`
            };
        }

        // Check extension
        const extension = file.name.split('.').pop().toLowerCase();
        if (!allowedExtensions.includes(extension)) {
            return {
                valid: false,
                error: `File type ".${extension}" not allowed`
            };
        }

        return { valid: true };
    },

    /**
     * Render file list
     */
    renderFileList() {
        const fileList = document.getElementById('file-list');

        if (this.selectedFiles.length === 0) {
            fileList.style.display = 'none';
            return;
        }

        fileList.style.display = 'block';
        fileList.innerHTML = '<h3>Selected Files</h3>';

        const list = document.createElement('div');
        list.className = 'files-grid';

        this.selectedFiles.forEach((file, index) => {
            const fileCard = document.createElement('div');
            fileCard.className = 'file-card';

            const icon = this.getFileIcon(file.name);
            const size = this.formatFileSize(file.size);

            fileCard.innerHTML = `
                <div class="file-icon">
                    <i class="${icon}"></i>
                </div>
                <div class="file-info">
                    <div class="file-name">${this.escapeHtml(file.name)}</div>
                    <div class="file-size">${size}</div>
                </div>
                <button class="file-remove" onclick="ProjectUpload.removeFile(${index})" title="Remove file">
                    <i class="fas fa-times"></i>
                </button>
            `;

            list.appendChild(fileCard);
        });

        fileList.appendChild(list);
    },

    /**
     * Remove file from selection
     */
    removeFile(index) {
        this.selectedFiles.splice(index, 1);
        this.renderFileList();
        this.updateSubmitButton();
    },

    /**
     * Update submit button state
     */
    updateSubmitButton() {
        const submitBtn = document.getElementById('submit-btn');
        submitBtn.disabled = this.selectedFiles.length === 0;
    },

    /**
     * Submit project
     */
    async submitProject() {
        if (this.selectedFiles.length === 0) {
            showToast('Please select at least one file', 'error');
            return;
        }

        const submitBtn = document.getElementById('submit-btn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';

        try {
            // Upload each file
            this.uploadedFileIds = [];
            let uploadedCount = 0;

            for (const file of this.selectedFiles) {
                const fileId = await this.uploadFile(file);
                if (fileId) {
                    this.uploadedFileIds.push(fileId);
                    uploadedCount++;

                    // Update progress
                    submitBtn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Uploading... (${uploadedCount}/${this.selectedFiles.length})`;
                }
            }

            if (this.uploadedFileIds.length === 0) {
                throw new Error('No files uploaded successfully');
            }

            // Create submission record
            await this.createSubmission();

        } catch (error) {
            showToast('Submission failed: ' + error.message, 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Project';
        }
    },

    /**
     * Upload single file
     */
    async uploadFile(file) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('type', 'project');
        formData.append('metadata', JSON.stringify({
            project_id: this.projectId,
            project_title: this.project.title
        }));

        try {
            const token = localStorage.getItem('token');
            const response = await fetch('/api/upload', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`
                },
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                return data.file_id;
            } else {
                throw new Error(data.message || 'Upload failed');
            }
        } catch (error) {
            console.error('File upload error:', error);
            showToast(`Failed to upload "${file.name}"`, 'error');
            return null;
        }
    },

    /**
     * Create submission record
     */
    async createSubmission() {
        const notes = document.getElementById('submission-notes').value.trim();

        const response = await apiRequest('/api/project-submissions', {
            method: 'POST',
            body: JSON.stringify({
                project_id: this.projectId,
                file_ids: this.uploadedFileIds,
                notes: notes || null
            })
        });

        if (response.success) {
            showToast('Project submitted successfully!', 'success');

            // Reset form
            this.selectedFiles = [];
            this.uploadedFileIds = [];
            this.renderFileList();
            document.getElementById('submission-notes').value = '';
            document.getElementById('notes-char-count').textContent = '0';

            // Reload submission history
            await this.loadSubmissionHistory();

            // Re-enable button
            const submitBtn = document.getElementById('submit-btn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Project';
        } else {
            throw new Error(response.message || 'Submission failed');
        }
    },

    /**
     * Load submission history
     */
    async loadSubmissionHistory() {
        try {
            const response = await apiRequest(`/api/project-submissions?project_id=${this.projectId}`);

            if (response.success && response.submissions && response.submissions.length > 0) {
                this.renderSubmissionHistory(response.submissions);
            }
        } catch (error) {
            console.error('Failed to load submission history:', error);
        }
    },

    /**
     * Render submission history
     */
    renderSubmissionHistory(submissions) {
        const section = document.getElementById('submission-history-section');
        const list = document.getElementById('submission-history-list');

        section.style.display = 'block';
        list.innerHTML = '';

        // Show most recent submission alert
        if (submissions.length > 0) {
            const latest = submissions[0];
            const prevSection = document.getElementById('previous-submissions');
            const prevInfo = document.getElementById('previous-submission-info');

            prevSection.style.display = 'block';
            prevInfo.textContent = `Last submitted on ${this.formatDate(latest.submitted_at)}. Status: ${latest.status}. You can submit a new version below.`;
        }

        submissions.forEach(submission => {
            const card = document.createElement('div');
            card.className = 'submission-card';

            const statusClass = this.getStatusClass(submission.status);

            card.innerHTML = `
                <div class="submission-header">
                    <div>
                        <strong>Submitted:</strong> ${this.formatDate(submission.submitted_at)}
                    </div>
                    <span class="status-badge ${statusClass}">
                        ${this.formatStatus(submission.status)}
                    </span>
                </div>
                ${submission.score !== null ? `
                    <div class="submission-score">
                        <strong>Score:</strong> ${submission.score} / ${this.project.max_score}
                    </div>
                ` : ''}
                ${submission.feedback ? `
                    <div class="submission-feedback">
                        <strong>Feedback:</strong>
                        <p>${this.escapeHtml(submission.feedback)}</p>
                    </div>
                ` : ''}
                ${submission.notes ? `
                    <div class="submission-notes">
                        <strong>Your Notes:</strong>
                        <p>${this.escapeHtml(submission.notes)}</p>
                    </div>
                ` : ''}
                <div class="submission-files">
                    <strong>Files:</strong>
                    <div class="file-links">
                        ${this.renderSubmissionFiles(submission.files || [])}
                    </div>
                </div>
            `;

            list.appendChild(card);
        });
    },

    /**
     * Render submission files
     */
    renderSubmissionFiles(files) {
        if (!files || files.length === 0) {
            return '<p class="text-muted">No files</p>';
        }

        return files.map(file => `
            <a href="/api/files/${file.id}" target="_blank" class="file-link">
                <i class="${this.getFileIcon(file.filename)}"></i>
                ${this.escapeHtml(file.filename)}
            </a>
        `).join('');
    },

    /**
     * Helper: Get file icon
     */
    getFileIcon(filename) {
        const ext = filename.split('.').pop().toLowerCase();
        const icons = {
            'pdf': 'fas fa-file-pdf',
            'doc': 'fas fa-file-word',
            'docx': 'fas fa-file-word',
            'zip': 'fas fa-file-archive',
            'png': 'fas fa-file-image',
            'jpg': 'fas fa-file-image',
            'jpeg': 'fas fa-file-image'
        };
        return icons[ext] || 'fas fa-file';
    },

    /**
     * Helper: Format file size
     */
    formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    },

    /**
     * Helper: Format text with line breaks
     */
    formatText(text) {
        if (!text) return '<p class="text-muted">No information provided.</p>';
        return '<p>' + this.escapeHtml(text).replace(/\n/g, '<br>') + '</p>';
    },

    /**
     * Helper: Get status class
     */
    getStatusClass(status) {
        const classes = {
            'submitted': 'status-pending',
            'graded': 'status-graded',
            'pending': 'status-pending'
        };
        return classes[status] || 'status-default';
    },

    /**
     * Helper: Format status
     */
    formatStatus(status) {
        const labels = {
            'submitted': 'Submitted',
            'graded': 'Graded',
            'pending': 'Pending Review'
        };
        return labels[status] || status;
    },

    /**
     * Helper: Format date
     */
    formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    },

    /**
     * Helper: Escape HTML
     */
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};
