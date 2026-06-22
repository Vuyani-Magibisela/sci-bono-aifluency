/**
 * Admin Learners Management JavaScript
 * Handles student/learner-specific user management with role hardcoded to 'student'
 */

const LearnerManagement = {
    currentUser: null,
    currentPage: 1,
    pageSize: 20,
    totalPages: 0,
    filters: {
        school_id: '',
        search: '',
        is_active: ''
    },
    editingLearnerId: null,
    organizations: [],
    schools: [],

    async init() {
        console.log('Initializing Learner Management...');

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
                this.loadLearners()
            ]);

            this.setupEventListeners();
        } catch (error) {
            console.error('Initialization error:', error);
            if (error.message.includes('401') || error.message.includes('Authentication')) {
                window.location.href = '/public/login.html';
            } else {
                this.showError('Failed to initialize learner management: ' + error.message);
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
        // Teachers get read-only view — hide create/edit/delete buttons
        if (!['superadmin', 'orgadmin', 'schooladmin'].includes(role)) {
            const createBtn = document.getElementById('create-learner-btn');
            if (createBtn) createBtn.style.display = 'none';
        }
    },

    setupEventListeners() {
        // Create learner button
        document.getElementById('create-learner-btn')?.addEventListener('click', () => this.showLearnerModal());

        // Modal form
        document.getElementById('learner-form').addEventListener('submit', (e) => this.handleLearnerSubmit(e));
        document.getElementById('cancel-learner-btn').addEventListener('click', () => this.closeModal('learner-modal'));

        // Organization select change in modal
        document.getElementById('learner-organization')?.addEventListener('change', (e) => {
            this.loadSchoolsForOrganization(e.target.value, 'learner-school');
        });

        // Filter changes
        document.getElementById('school-filter')?.addEventListener('change', () => this.applyFilters());
        document.getElementById('status-filter')?.addEventListener('change', () => this.applyFilters());
        document.getElementById('grade-filter')?.addEventListener('change', () => this.applyFilters());

        // Search with debounce
        let searchTimeout;
        document.getElementById('search-learners')?.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => this.applyFilters(), 500);
        });

        // Clear filters
        document.getElementById('clear-filters')?.addEventListener('click', () => this.clearFilters());
    },

    async loadLearners() {
        try {
            const params = new URLSearchParams({
                page: this.currentPage,
                pageSize: this.pageSize,
                role: 'student'
            });

            if (this.filters.school_id) params.set('school_id', this.filters.school_id);
            if (this.filters.search) params.set('search', this.filters.search);
            if (this.filters.is_active) params.set('is_active', this.filters.is_active);

            const response = await API.get(`/users?${params}`);

            if (response.success) {
                this.displayLearners(response.data.data);
                this.updatePagination(response.data.total, response.data.page, response.data.pageSize);
            } else {
                throw new Error(response.message || 'Failed to load learners');
            }
        } catch (error) {
            console.error('Error loading learners:', error);
            this.showError('Failed to load learners: ' + error.message);
        }
    },

    displayLearners(learners) {
        const container = document.getElementById('learners-table-container');

        if (!learners || learners.length === 0) {
            container.innerHTML = '<p>No learners found.</p>';
            return;
        }

        const canEdit = ['superadmin', 'orgadmin', 'schooladmin'].includes(this.currentUser.role);

        const table = `
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>School</th>
                        <th>Grade</th>
                        <th>Status</th>
                        ${canEdit ? '<th>Actions</th>' : ''}
                    </tr>
                </thead>
                <tbody>
                    ${learners.map(l => this.renderLearnerRow(l, canEdit)).join('')}
                </tbody>
            </table>
        `;

        container.innerHTML = table;

        if (canEdit) {
            learners.forEach(learner => {
                document.getElementById(`edit-learner-${learner.id}`)?.addEventListener('click', () => this.editLearner(learner));
                document.getElementById(`delete-learner-${learner.id}`)?.addEventListener('click', () => this.deleteLearner(learner));
            });
        }
    },

    renderLearnerRow(learner, canEdit) {
        const schoolName = this.schools.find(s => s.id == learner.primary_school_id)?.name || 'N/A';
        const statusBadge = learner.is_active ?
            '<span class="status-badge status-active">Active</span>' :
            '<span class="status-badge status-inactive">Inactive</span>';
        const grade = learner.grade || learner.organizational_title || 'N/A';

        return `
            <tr>
                <td><strong>${this.escapeHtml(learner.name)}</strong></td>
                <td>${this.escapeHtml(learner.email)}</td>
                <td>${this.escapeHtml(schoolName)}</td>
                <td>${this.escapeHtml(grade)}</td>
                <td>${statusBadge}</td>
                ${canEdit ? `
                    <td>
                        <button class="action-btn edit" id="edit-learner-${learner.id}" title="Edit Learner">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="action-btn delete" id="delete-learner-${learner.id}" title="Delete Learner">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                ` : ''}
            </tr>
        `;
    },

    showLearnerModal(learner = null) {
        this.editingLearnerId = learner ? learner.id : null;

        document.getElementById('learner-modal-title').textContent = learner ? 'Edit Learner' : 'Add Learner';
        document.getElementById('submit-learner-btn').textContent = learner ? 'Update Learner' : 'Add Learner';

        // Configure password field
        const passwordInput = document.getElementById('learner-password');
        const passwordLabel = document.getElementById('password-label');
        const passwordHint = document.getElementById('password-hint');

        if (learner) {
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

        if (learner) {
            document.getElementById('learner-name').value = learner.name;
            document.getElementById('learner-email').value = learner.email;
            document.getElementById('learner-organization').value = learner.primary_organization_id || '';
            this.loadSchoolsForOrganization(learner.primary_organization_id, 'learner-school', learner.primary_school_id);
            document.getElementById('learner-grade').value = learner.grade || learner.organizational_title || '';
            document.getElementById('learner-active').checked = !!learner.is_active;
        } else {
            document.getElementById('learner-form').reset();
            document.getElementById('learner-active').checked = true;

            // Auto-populate org/school for schooladmin
            if (this.currentUser.role === 'schooladmin') {
                const orgSelect = document.getElementById('learner-organization');
                if (this.currentUser.primary_organization_id) {
                    orgSelect.value = this.currentUser.primary_organization_id;
                    orgSelect.setAttribute('disabled', 'disabled');
                    this.loadSchoolsForOrganization(
                        this.currentUser.primary_organization_id, 'learner-school',
                        this.currentUser.primary_school_id
                    ).then(() => {
                        const schoolSelect = document.getElementById('learner-school');
                        if (this.currentUser.primary_school_id) {
                            schoolSelect.value = this.currentUser.primary_school_id;
                            schoolSelect.setAttribute('disabled', 'disabled');
                        }
                    });
                }
            }
        }

        document.getElementById('learner-modal').classList.add('active');
    },

    populateModalOrganizations() {
        const select = document.getElementById('learner-organization');
        if (!select) return;
        select.innerHTML = '<option value="">Select Organization</option>';
        this.organizations.forEach(org => {
            select.innerHTML += `<option value="${org.id}">${this.escapeHtml(org.name)}</option>`;
        });
    },

    async handleLearnerSubmit(e) {
        e.preventDefault();

        const learnerData = {
            name: document.getElementById('learner-name').value,
            email: document.getElementById('learner-email').value,
            role: 'student',
            primary_organization_id: parseInt(document.getElementById('learner-organization').value) || null,
            primary_school_id: parseInt(document.getElementById('learner-school').value) || null,
            organizational_title: document.getElementById('learner-grade').value,
            is_active: document.getElementById('learner-active').checked
        };

        const password = document.getElementById('learner-password').value;
        if (password && password.trim()) {
            learnerData.password = password;
        }

        try {
            let response;
            if (this.editingLearnerId) {
                response = await API.put(`/users/${this.editingLearnerId}`, learnerData);
            } else {
                if (!learnerData.password) {
                    this.showError('Password is required for new learners');
                    return;
                }
                response = await API.post('/users', learnerData);
            }

            if (!response.success) {
                throw new Error(response.message || 'Failed to save learner');
            }

            this.showSuccess(this.editingLearnerId ? 'Learner updated successfully' : 'Learner added successfully');
            this.closeModal('learner-modal');
            await this.loadLearners();
        } catch (error) {
            console.error('Error saving learner:', error);
            this.showError(error.message);
        }
    },

    editLearner(learner) {
        this.showLearnerModal(learner);
    },

    async deleteLearner(learner) {
        if (!confirm(`Are you sure you want to delete ${learner.name}? This action cannot be undone.`)) {
            return;
        }

        try {
            const response = await API.delete(`/users/${learner.id}`);
            if (!response.success) {
                throw new Error(response.message || 'Failed to delete learner');
            }
            this.showSuccess('Learner deleted successfully');
            await this.loadLearners();
        } catch (error) {
            console.error('Error deleting learner:', error);
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
        this.filters.search = document.getElementById('search-learners')?.value || '';
        this.filters.is_active = document.getElementById('status-filter')?.value || '';
        this.currentPage = 1;
        this.loadLearners();
    },

    clearFilters() {
        const schoolFilter = document.getElementById('school-filter');
        const statusFilter = document.getElementById('status-filter');
        const gradeFilter = document.getElementById('grade-filter');
        const searchInput = document.getElementById('search-learners');
        if (schoolFilter) schoolFilter.value = '';
        if (statusFilter) statusFilter.value = '';
        if (gradeFilter) gradeFilter.value = '';
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
        info.textContent = `Showing ${start}-${end} of ${total} learners`;

        let buttons = '';
        buttons += `<button class="btn-secondary btn-sm" ${page === 1 ? 'disabled' : ''} onclick="LearnerManagement.goToPage(${page - 1})">Previous</button>`;

        const maxButtons = 5;
        let startPage = Math.max(1, page - Math.floor(maxButtons / 2));
        let endPage = Math.min(totalPages, startPage + maxButtons - 1);
        if (endPage - startPage < maxButtons - 1) {
            startPage = Math.max(1, endPage - maxButtons + 1);
        }

        for (let i = startPage; i <= endPage; i++) {
            buttons += `<button class="btn-secondary btn-sm ${i === page ? 'active' : ''}" onclick="LearnerManagement.goToPage(${i})">${i}</button>`;
        }

        buttons += `<button class="btn-secondary btn-sm" ${page === totalPages ? 'disabled' : ''} onclick="LearnerManagement.goToPage(${page + 1})">Next</button>`;

        controls.innerHTML = buttons;
    },

    goToPage(page) {
        if (page < 1 || page > this.totalPages) return;
        this.currentPage = page;
        this.loadLearners();
    },

    closeModal(modalId) {
        document.getElementById(modalId).classList.remove('active');
        document.getElementById('learner-organization')?.removeAttribute('disabled');
        document.getElementById('learner-school')?.removeAttribute('disabled');
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
    document.addEventListener('DOMContentLoaded', () => LearnerManagement.init());
} else {
    LearnerManagement.init();
}
