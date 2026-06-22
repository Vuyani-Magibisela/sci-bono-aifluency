/**
 * Admin — Certificate Templates
 *
 * CRUD for certificate templates: list, create, edit, delete.
 * Uploads optional background (image/PDF) via the existing /api/upload pipeline.
 * Live-previews the certificate as the form is edited.
 */

const AdminCertTemplates = {
    templates: [],
    editing: null, // current template being edited (or null for create)

    async init() {
        document.getElementById('new-template-btn').addEventListener('click', () => this.openModal());
        document.getElementById('template-modal-close').addEventListener('click', () => this.closeModal());
        document.getElementById('tpl-cancel').addEventListener('click', () => this.closeModal());
        document.getElementById('template-form').addEventListener('submit', (e) => this.save(e));

        document.getElementById('tpl-bg-pick').addEventListener('click', () => document.getElementById('tpl-bg-file').click());
        document.getElementById('tpl-bg-file').addEventListener('change', (e) => this.uploadBackground(e));
        document.getElementById('tpl-bg-clear').addEventListener('click', () => this.clearBackground());

        ['tpl-name', 'd-title', 'd-border', 'd-text', 'd-layout', 'd-signatures'].forEach(id => {
            document.getElementById(id).addEventListener('input', () => this.refreshPreview());
        });

        document.getElementById('cert-preview').addEventListener('click', (e) => e.stopPropagation());

        await this.load();
    },

    async load() {
        const grid = document.getElementById('templates-grid');
        try {
            const res = await API.get('/certificate-templates');
            this.templates = res.data?.templates || res.templates || [];
            this.render();
        } catch (err) {
            grid.innerHTML = `<div style="padding:2rem;text-align:center;color:#dc2626;">Failed to load templates: ${err.message}</div>`;
        }
    },

    render() {
        const grid = document.getElementById('templates-grid');
        if (!this.templates.length) {
            grid.innerHTML = `
                <div style="grid-column:1/-1;padding:3rem;text-align:center;color:#6b7280;">
                    <i class="fas fa-paint-brush" style="font-size:2.5rem;color:#cfd3df;"></i>
                    <p>No templates yet. Create one to get started.</p>
                </div>`;
            return;
        }
        grid.innerHTML = this.templates.map(t => {
            const bg = t.background_url ? `style="background-image:url('${t.background_url}');"` : '';
            const scopeBadge = t.scope === 'global'
                ? `<span class="badge badge-global">Global</span>`
                : `<span class="badge badge-course">Course</span>`;
            const activeBadge = Number(t.is_active) === 1
                ? `<span class="badge badge-active">Active</span>`
                : `<span class="badge badge-inactive">Inactive</span>`;
            return `
                <div class="template-card" data-id="${t.id}">
                    <div class="template-thumb" ${bg}>
                        ${t.background_url ? '' : '<i class="fas fa-certificate"></i>'}
                    </div>
                    <div class="template-body">
                        <div class="template-name">${this.esc(t.name)}</div>
                        <div class="template-meta">${this.esc(t.template_type)} · ${scopeBadge} ${activeBadge}</div>
                        <div class="template-meta">
                            <i class="fas fa-book"></i> ${t.courses_using || 0} courses ·
                            <i class="fas fa-certificate"></i> ${t.certificates_issued || 0} issued
                        </div>
                    </div>
                    <div class="template-actions">
                        <button class="btn-secondary btn-sm" data-action="edit" data-id="${t.id}">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn-secondary btn-sm" data-action="delete" data-id="${t.id}" style="color:#dc2626;">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>`;
        }).join('');

        grid.querySelectorAll('[data-action="edit"]').forEach(btn => {
            btn.addEventListener('click', () => this.openModal(parseInt(btn.dataset.id, 10)));
        });
        grid.querySelectorAll('[data-action="delete"]').forEach(btn => {
            btn.addEventListener('click', () => this.delete(parseInt(btn.dataset.id, 10)));
        });
    },

    openModal(id = null) {
        this.editing = id ? this.templates.find(t => t.id == id) : null;
        document.getElementById('template-modal-title').textContent = this.editing ? 'Edit Certificate Template' : 'New Certificate Template';

        document.getElementById('tpl-id').value = this.editing?.id || '';
        document.getElementById('tpl-name').value = this.editing?.name || '';
        document.getElementById('tpl-description').value = this.editing?.description || '';
        document.getElementById('tpl-type').value = this.editing?.template_type || 'course_completion';
        document.getElementById('tpl-scope').value = this.editing?.scope || 'course';
        document.getElementById('tpl-active').checked = this.editing ? Number(this.editing.is_active) === 1 : true;

        const td = this.parseJson(this.editing?.template_data, {});
        document.getElementById('d-border').value = td.border_color || '#4B6EFB';
        document.getElementById('d-title').value = td.title_color || '#333333';
        document.getElementById('d-text').value = td.text_color || '#666666';
        document.getElementById('d-layout').value = td.layout || 'classic';
        document.getElementById('d-signatures').value = (td.signature_lines || []).join(', ');

        const reqs = this.parseJson(this.editing?.requirements, {});
        document.getElementById('d-min-completion').value = reqs.min_course_completion ?? 100;

        document.getElementById('tpl-background-file-id').value = this.editing?.background_file_id || '';
        this.setBackgroundUI(this.editing?.background_url || null);

        document.getElementById('template-modal').classList.add('active');
        this.refreshPreview();
    },

    closeModal() {
        document.getElementById('template-modal').classList.remove('active');
        this.editing = null;
    },

    setBackgroundUI(url) {
        const thumb = document.getElementById('tpl-bg-thumb');
        const clear = document.getElementById('tpl-bg-clear');
        if (url) {
            thumb.style.backgroundImage = `url('${url}')`;
            thumb.innerHTML = '';
            clear.style.display = '';
        } else {
            thumb.style.backgroundImage = '';
            thumb.innerHTML = '<i class="fas fa-image"></i>';
            clear.style.display = 'none';
        }
        this.refreshPreview();
    },

    async uploadBackground(event) {
        const file = event.target.files?.[0];
        if (!file) return;
        try {
            const fd = new FormData();
            fd.append('file', file);
            fd.append('type', 'certificate_template');
            const res = await API.upload('/upload', fd);
            const data = res.data || res;
            document.getElementById('tpl-background-file-id').value = data.file_id;
            this.setBackgroundUI(data.file_url);
        } catch (err) {
            alert('Upload failed: ' + err.message);
        } finally {
            event.target.value = '';
        }
    },

    clearBackground() {
        document.getElementById('tpl-background-file-id').value = '';
        this.setBackgroundUI(null);
    },

    async save(event) {
        event.preventDefault();

        const sigs = document.getElementById('d-signatures').value
            .split(',').map(s => s.trim()).filter(Boolean);

        const templateData = {
            border_color: document.getElementById('d-border').value,
            title_color:  document.getElementById('d-title').value,
            text_color:   document.getElementById('d-text').value,
            layout:       document.getElementById('d-layout').value,
            signature_lines: sigs.length ? sigs : ['Instructor']
        };
        const requirements = {
            min_course_completion: parseInt(document.getElementById('d-min-completion').value, 10) || 100
        };

        const payload = {
            name:          document.getElementById('tpl-name').value.trim(),
            description:   document.getElementById('tpl-description').value.trim(),
            template_type: document.getElementById('tpl-type').value,
            scope:         document.getElementById('tpl-scope').value,
            is_active:     document.getElementById('tpl-active').checked ? 1 : 0,
            template_data: JSON.stringify(templateData),
            requirements:  JSON.stringify(requirements),
            background_file_id: document.getElementById('tpl-background-file-id').value || null
        };

        const id = document.getElementById('tpl-id').value;
        try {
            if (id) {
                await API.put(`/certificate-templates/${id}`, payload);
            } else {
                await API.post('/certificate-templates', payload);
            }
            this.closeModal();
            await this.load();
        } catch (err) {
            alert('Save failed: ' + err.message);
        }
    },

    async delete(id) {
        const t = this.templates.find(x => x.id == id);
        if (!t) return;
        const usedBy = (t.courses_using || 0) + (t.certificates_issued || 0);
        const message = usedBy > 0
            ? `"${t.name}" is in use (${t.courses_using || 0} courses, ${t.certificates_issued || 0} certificates issued). It will be deactivated rather than deleted. Continue?`
            : `Delete "${t.name}"? This cannot be undone.`;
        if (!confirm(message)) return;
        try {
            await API.delete(`/certificate-templates/${id}`);
            await this.load();
        } catch (err) {
            alert('Delete failed: ' + err.message);
        }
    },

    refreshPreview() {
        const preview = document.getElementById('cert-preview');
        const bgId = document.getElementById('tpl-background-file-id').value;
        const titleColor  = document.getElementById('d-title').value;
        const borderColor = document.getElementById('d-border').value;
        const textColor   = document.getElementById('d-text').value;

        preview.style.setProperty('--cert-title-color', titleColor);
        preview.style.setProperty('--cert-text-color',  textColor);
        preview.style.setProperty('--cert-border',      borderColor);

        if (bgId) {
            preview.style.backgroundImage = `url('${API.baseURL}/files/${bgId}/public')`;
            preview.classList.add('has-bg');
        } else {
            preview.style.backgroundImage = '';
            preview.classList.remove('has-bg');
        }

        document.getElementById('prev-title').textContent =
            (document.getElementById('tpl-name').value || 'Certificate of Completion');
        document.getElementById('prev-date').textContent = new Date().toLocaleDateString();

        const sigs = document.getElementById('d-signatures').value
            .split(',').map(s => s.trim()).filter(Boolean);
        document.getElementById('prev-sigs').innerHTML = (sigs.length ? sigs : ['Instructor'])
            .map(s => `<div class="cert-sig">${this.esc(s)}</div>`).join('');
    },

    parseJson(value, fallback) {
        if (!value) return fallback;
        if (typeof value === 'object') return value;
        try { return JSON.parse(value); } catch { return fallback; }
    },

    esc(s) {
        const div = document.createElement('div');
        div.textContent = s == null ? '' : String(s);
        return div.innerHTML;
    }
};

document.addEventListener('DOMContentLoaded', () => AdminCertTemplates.init());
