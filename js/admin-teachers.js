/**
 * Admin Teachers Management JavaScript
 * Handles teacher-specific user management with role hardcoded to 'teacher'
 */

const TeacherManagement = {
    currentUser: null,
    currentPage: 1,
    pageSize: 20,
    totalPages: 0,
    filters: {
        school_id: '',
        search: '',
        is_active: ''
    },
    editingTeacherId: null,
    organizations: [],
    schools: [],

    async init() {
        console.log('Initializing Teacher Management...');

        const token = localStorage.getItem('access_token');
        if (!token) {
            window.location.href = '/public/login.html';
            return;
        }

        try {
            this.currentUser = await this.getCurrentUser();
            this.updateSidebarUserInfo();
            this.setupRoleBasedUI();

            await Promise.all([
                this.loadOrganizations(),
                this.loadSchools(),
                this.loadTeachers()
            ]);

            this.setupEventListeners();
        } catch (error) {
            console.error('Initialization error:', error);
            if (error.message.includes('401') || error.message.includes('Authentication')) {
                window.location.href = '/public/login.html';
            } else {
                this.showError('Failed to initialize teacher management: ' + error.message);
            }
        }
    },

    async getCurrentUser() {
        if (!Auth.isAuthenticated()) {
            throw new Error('Authentication required');
        }
        const user = Auth.getUser();
        if (!user) {
            throw new Error('User data not found');
        }
        return user;
    },

    updateSidebarUserInfo() {
        const nameEl = document.getElementById('sidebar-user-name');
        const roleEl = document.getElementById('sidebar-user-role');

        if (nameEl) nameEl.textContent = this.currentUser.name;
        if (roleEl) {
            const roleDisplay = {
                'superadmin': 'Super Administrator',
                'orgadmin': 'Organization Administrator',
                'schooladmin': 'School Administrator',
                'teacher': 'Teacher',
                'student': 'Student'
            };
            roleEl.textContent = roleDisplay[this.currentUser.role] || this.currentUser.role;
        }
    },

    setupRoleBasedUI() {
        const role = this.currentUser.role;
        if (!['superadmin', 'orgadmin', 'schooladmin'].includes(role)) {
            const createBtn = document.getElementById('create-teacher-btn');
            if (createBtn) createBtn.style.display = 'none';
        }
    },

    setupEventListeners() {
        // Create teacher button
        document.getElementById('create-teacher-btn')?.addEventListener('click', () => this.showTeacherModal());

        // Modal form
        document.getElementById('teacher-form').addEventListener('submit', (e) => this.handleTeacherSubmit(e));
        document.getElementById('cancel-teacher-btn').addEventListener('click', () => this.closeModal('teacher-modal'));

        // Organization select change in modal
        document.getElementById('teacher-organization')?.addEventListener('change', (e) => {
            this.loadSchoolsForOrganization(e.target.value, 'teacher-school');
        });

        // Filter changes
        document.getElementById('school-filter')?.addEventListener('change', () => this.applyFilters());
        document.getElementById('status-filter')?.addEventListener('change', () => this.applyFilters());

        // Search with debounce
        let searchTimeout;
        document.getElementById('search-teachers')?.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => this.applyFilters(), 500);
        });

        // Clear filters
        document.getElementById('clear-filters')?.addEventListener('click', () => this.clearFilters());
    },

    async loadTeachers() {
        try {
            const params = new URLSearchParams({
                page: this.currentPage,
                pageSize: this.pageSize,
                role: 'teacher'
            });

            if (this.filters.school_id) params.set('school_id', this.filters.school_id);
            if (this.filters.search) params.set('search', this.filters.search);
            if (this.filters.is_active) params.set('is_active', this.filters.is_active);

            const response = await API.get(`/users?${params}`);

            if (response.success) {
                this.displayTeachers(response.data.data);
                this.updatePagination(response.data.total, response.data.page, response.data.pageSize);
            } else {
                throw new Error(response.message || 'Failed to load teachers');
            }
        } catch (error) {
            console.error('Error loading teachers:', error);
            this.showError('Failed to load teachers: ' + error.message);
        }
    },

    displayTeachers(teachers) {
        const container = document.getElementById('teachers-table-container');

        if (!teachers || teachers.length === 0) {
            container.innerHTML = '<p>No teachers found.</p>';
            return;
        }

        const table = `
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>School</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${teachers.map(t => this.renderTeacherRow(t)).join('')}
                </tbody>
            </table>
        `;

        container.innerHTML = table;

        teachers.forEach(teacher => {
            document.getElementById(`edit-teacher-${teacher.id}`)?.addEventListener('click', () => this.editTeacher(teacher));
            document.getElementById(`delete-teacher-${teacher.id}`)?.addEventListener('click', () => this.deleteTeacher(teacher));
        });
    },

    renderTeacherRow(teacher) {
        const schoolName = this.schools.find(s => s.id == teacher.primary_school_id)?.name || 'N/A';
        const statusBadge = teacher.is_active ?
            '<span class="status-badge status-active">Active</span>' :
            '<span class="status-badge status-inactive">Inactive</span>';

        const canEdit = ['superadmin', 'orgadmin', 'schooladmin'].includes(this.currentUser.role);

        return `
            <tr>
                <td><strong>${this.escapeHtml(teacher.name)}</strong></td>
                <td>${this.escapeHtml(teacher.email)}</td>
                <td>${this.escapeHtml(schoolName)}</td>
                <td>${teacher.organizational_title ? this.escapeHtml(teacher.organizational_title) : 'N/A'}</td>
                <td>${statusBadge}</td>
                <td>
                    ${canEdit ? `
                        <button class="action-btn edit" id="edit-teacher-${teacher.id}" title="Edit Teacher">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="action-btn delete" id="delete-teacher-${teacher.id}" title="Delete Teacher">
                            <i class="fas fa-trash"></i>
                        </button>
                    ` : ''}
                </td>
            </tr>
        `;
    },

    showTeacherModal(teacher = null) {
        this.editingTeacherId = teacher ? teacher.id : null;

        document.getElementById('teacher-modal-title').textContent = teacher ? 'Edit Teacher' : 'Add Teacher';
        document.getElementById('submit-teacher-btn').textContent = teacher ? 'Update Teacher' : 'Add Teacher';

        // Configure password field
        const passwordInput = document.getElementById('teacher-password');
        const passwordLabel = document.getElementById('password-label');
        const passwordHint = document.getElementById('password-hint');

        if (teacher) {
            passwordInput.removeAttribute('required');
            passwordInput.value = '';
            passwordLabel.textContent = 'New Password (leave blank to keep current)';
            passwordHint.textContent = 'Minimum 8 characters. Leave blank to keep current password.';
        } else {
            passwordInput.setAttribute('required', 'required');
            passwordLabel.textContent = 'Password *';
            passwordHint.textContent = 'Minimum 8 characters';
        }

        // Populate organization select
        this.populateModalOrganizations();

        if (teacher) {
            document.getElementById('teacher-name').value = teacher.name;
            document.getElementById('teacher-email').value = teacher.email;
            document.getElementById('teacher-organization').value = teacher.primary_organization_id || '';
            this.loadSchoolsForOrganization(teacher.primary_organization_id, 'teacher-school', teacher.primary_school_id);
            document.getElementById('teacher-title').value = teacher.organizational_title || '';
            document.getElementById('teacher-active').checked = !!teacher.is_active;
        } else {
            document.getElementById('teacher-form').reset();
            document.getElementById('teacher-active').checked = true;

            // Auto-populate org/school for schooladmin
            if (this.currentUser.role === 'schooladmin') {
                const orgSelect = document.getElementById('teacher-organization');
                if (this.currentUser.primary_organization_id) {
                    orgSelect.value = this.currentUser.primary_organization_id;
                    orgSelect.setAttribute('disabled', 'disabled');
                    this.loadSchoolsForOrganization(
                        this.currentUser.primary_organization_id, 'teacher-school',
                        this.currentUser.primary_school_id
                    ).then(() => {
                        const schoolSelect = document.getElementById('teacher-school');
                        if (this.currentUser.primary_school_id) {
                            schoolSelect.value = this.currentUser.primary_school_id;
                            schoolSelect.setAttribute('disabled', 'disabled');
                        }
                    });
                }
            }
        }

        document.getElementById('teacher-modal').classList.add('active');
    },

    populateModalOrganizations() {
        const select = document.getElementById('teacher-organization');
        if (!select) return;
        select.innerHTML = '<option value="">Select Organization</option>';
        this.organizations.forEach(org => {
            select.innerHTML += `<option value="${org.id}">${this.escapeHtml(org.name)}</option>`;
        });
    },

    async handleTeacherSubmit(e) {
        e.preventDefault();

        const teacherData = {
            name: document.getElementById('teacher-name').value,
            email: document.getElementById('teacher-email').value,
            role: 'teacher',
            primary_organization_id: parseInt(document.getElementById('teacher-organization').value) || null,
            primary_school_id: parseInt(document.getElementById('teacher-school').value) || null,
            organizational_title: document.getElementById('teacher-title').value,
            is_active: document.getElementById('teacher-active').checked
        };

        const password = document.getElementById('teacher-password').value;
        if (password && password.trim()) {
            teacherData.password = password;
        }

        try {
            let response;
            if (this.editingTeacherId) {
                response = await API.put(`/users/${this.editingTeacherId}`, teacherData);
            } else {
                if (!teacherData.password) {
                    this.showError('Password is required for new teachers');
                    return;
                }
                response = await API.post('/users', teacherData);
            }

            if (!response.success) {
                throw new Error(response.message || 'Failed to save teacher');
            }

            this.showSuccess(this.editingTeacherId ? 'Teacher updated successfully' : 'Teacher added successfully');
            this.closeModal('teacher-modal');
            await this.loadTeachers();
        } catch (error) {
            console.error('Error saving teacher:', error);
            this.showError(error.message);
        }
    },

    editTeacher(teacher) {
        this.showTeacherModal(teacher);
    },

    async deleteTeacher(teacher) {
        if (!confirm(`Are you sure you want to delete ${teacher.name}? This action cannot be undone.`)) {
            return;
        }

        try {
            const response = await API.delete(`/users/${teacher.id}`);
            if (!response.success) {
                throw new Error(response.message || 'Failed to delete teacher');
            }
            this.showSuccess('Teacher deleted successfully');
            await this.loadTeachers();
        } catch (error) {
            console.error('Error deleting teacher:', error);
            this.showError(error.message);
        }
    },

    async loadOrganizations() {
        try {
            const response = await API.get('/organizations');
            if (response.success) {
                this.organizations = response.data.organizations || [];
            }
        } catch (error) {
            console.error('Error loading organizations:', error);
            this.organizations = [];
            if (this.currentUser.primary_organization_id) {
                this.organizations = [{
                    id: this.currentUser.primary_organization_id,
                    name: 'Your Organization'
                }];
            }
        }
    },

    async loadSchools() {
        try {
            const response = await API.get('/schools');
            if (response.success) {
                this.schools = response.data.schools || [];
                this.populateSchoolFilter();
            }
        } catch (error) {
            console.error('Error loading schools:', error);
        }
    },

    async loadSchoolsForOrganization(orgId, selectId, selectedSchoolId = null) {
        const select = document.getElementById(selectId);
        if (!select) return;

        select.innerHTML = '<option value="">Select School</option>';
        if (!orgId) return;

        const orgSchools = this.schools.filter(s => s.organization_id == orgId);
        orgSchools.forEach(school => {
            select.innerHTML += `<option value="${school.id}">${this.escapeHtml(school.name)}</option>`;
        });

        if (selectedSchoolId) {
            select.value = selectedSchoolId;
        }
    },

    populateSchoolFilter() {
        const select = document.getElementById('school-filter');
        if (!select) return;

        select.innerHTML = '<option value="">All Schools</option>';
        this.schools.forEach(school => {
            select.innerHTML += `<option value="${school.id}">${this.escapeHtml(school.name)}</option>`;
        });
    },

    applyFilters() {
        this.filters.school_id = document.getElementById('school-filter')?.value || '';
        this.filters.search = document.getElementById('search-teachers')?.value || '';
        this.filters.is_active = document.getElementById('status-filter')?.value || '';
        this.currentPage = 1;
        this.loadTeachers();
    },

    clearFilters() {
        const schoolFilter = document.getElementById('school-filter');
        const statusFilter = document.getElementById('status-filter');
        const searchInput = document.getElementById('search-teachers');
        if (schoolFilter) schoolFilter.value = '';
        if (statusFilter) statusFilter.value = '';
        if (searchInput) searchInput.value = '';
        this.applyFilters();
    },

    updatePagination(total, page, pageSize) {
        const totalPages = Math.ceil(total / pageSize);
        this.totalPages = totalPages;

        const container = document.getElementById('pagination-container');
        const info = document.getElementById('pagination-info');
        const controls = document.getElementById('pagination-controls');

        if (totalPages <= 1) {
            container.style.display = 'none';
            return;
        }

        container.style.display = 'block';

        const start = (page - 1) * pageSize + 1;
        const end = Math.min(page * pageSize, total);
        info.textContent = `Showing ${start}-${end} of ${total} teachers`;

        let buttons = '';
        buttons += `<button class="btn-secondary btn-sm" ${page === 1 ? 'disabled' : ''} onclick="TeacherManagement.goToPage(${page - 1})">Previous</button>`;

        const maxButtons = 5;
        let startPage = Math.max(1, page - Math.floor(maxButtons / 2));
        let endPage = Math.min(totalPages, startPage + maxButtons - 1);
        if (endPage - startPage < maxButtons - 1) {
            startPage = Math.max(1, endPage - maxButtons + 1);
        }

        for (let i = startPage; i <= endPage; i++) {
            buttons += `<button class="btn-secondary btn-sm ${i === page ? 'active' : ''}" onclick="TeacherManagement.goToPage(${i})">${i}</button>`;
        }

        buttons += `<button class="btn-secondary btn-sm" ${page === totalPages ? 'disabled' : ''} onclick="TeacherManagement.goToPage(${page + 1})">Next</button>`;

        controls.innerHTML = buttons;
    },

    goToPage(page) {
        if (page < 1 || page > this.totalPages) return;
        this.currentPage = page;
        this.loadTeachers();
    },

    closeModal(modalId) {
        document.getElementById(modalId).classList.remove('active');
        document.getElementById('teacher-organization')?.removeAttribute('disabled');
        document.getElementById('teacher-school')?.removeAttribute('disabled');
    },

    showError(message) {
        alert('Error: ' + message);
    },

    showSuccess(message) {
        alert(message);
    },

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => TeacherManagement.init());
} else {
    TeacherManagement.init();
}
