/**
 * Admin Users Management JavaScript
 * Handles hierarchical role-based user management with organizations and schools
 */

const API_BASE_URL = '/api';

const UserManagement = {
    currentUser: null,
    currentPage: 1,
    pageSize: 20,
    totalPages: 0,
    filters: {
        role: '',
        organization_id: '',
        school_id: '',
        search: ''
    },
    editingUserId: null,
    organizations: [],
    schools: [],

    /**
     * Initialize user management
     */
    async init() {
        console.log('Initializing User Management...');

        // Check authentication
        const token = localStorage.getItem('access_token');
        if (!token) {
            window.location.href = '/public/login.html';
            return;
        }

        try {
            // Get current user info
            this.currentUser = await this.getCurrentUser();
            console.log('Current user:', this.currentUser);

            // Update sidebar with user info
            this.updateSidebarUserInfo();

            // Show/hide tabs based on role
            this.setupRoleBasedUI();

            // Load initial data
            await Promise.all([
                this.loadOrganizations(),
                this.loadSchools(),
                this.loadUsers()
            ]);

            // Setup event listeners
            this.setupEventListeners();

        } catch (error) {
            console.error('Initialization error:', error);
            if (error.message.includes('401') || error.message.includes('Authentication')) {
                window.location.href = '/public/login.html';
            } else {
                this.showError('Failed to initialize user management: ' + error.message);
            }
        }
    },

    /**
     * Get current user from Auth module
     */
    async getCurrentUser() {
        // Use the Auth module that's already loaded
        if (!Auth.isAuthenticated()) {
            throw new Error('Authentication required');
        }

        const user = Auth.getUser();
        if (!user) {
            throw new Error('User data not found');
        }

        return user;
    },

    /**
     * Update sidebar with current user info
     */
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

    /**
     * Setup role-based UI visibility
     */
    setupRoleBasedUI() {
        const role = this.currentUser.role;

        // Show organizations tab only for SuperAdmin
        if (role === 'superadmin') {
            document.getElementById('organizations-tab').style.display = 'block';
        }

        // Hide create buttons for roles that can't create users
        if (!['superadmin', 'orgadmin', 'schooladmin'].includes(role)) {
            const createBtn = document.getElementById('create-user-btn');
            if (createBtn) createBtn.style.display = 'none';
        }
    },

    /**
     * Setup all event listeners
     */
    setupEventListeners() {
        // Tab switching
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => this.switchTab(tab.dataset.tab));
        });

        // Create user button
        document.getElementById('create-user-btn')?.addEventListener('click', () => this.showUserModal());

        // User modal form
        document.getElementById('user-form').addEventListener('submit', (e) => this.handleUserSubmit(e));
        document.getElementById('cancel-user-btn').addEventListener('click', () => this.closeModal('user-modal'));

        // Organization select change - load schools
        document.getElementById('user-organization').addEventListener('change', (e) => {
            this.loadSchoolsForOrganization(e.target.value, 'user-school');
        });

        // Filter changes
        document.getElementById('role-filter').addEventListener('change', () => this.applyFilters());
        document.getElementById('organization-filter').addEventListener('change', () => this.applyFilters());
        document.getElementById('school-filter').addEventListener('change', () => this.applyFilters());

        // Search with debounce
        let searchTimeout;
        document.getElementById('search-users').addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => this.applyFilters(), 500);
        });

        // Clear filters
        document.getElementById('clear-filters').addEventListener('click', () => this.clearFilters());

        // Organization management
        document.getElementById('create-org-btn')?.addEventListener('click', () => this.showOrgModal());
        document.getElementById('org-form').addEventListener('submit', (e) => this.handleOrgSubmit(e));
        document.getElementById('cancel-org-btn').addEventListener('click', () => this.closeModal('org-modal'));

        // School management
        document.getElementById('create-school-btn')?.addEventListener('click', () => this.showSchoolModal());
        document.getElementById('school-form').addEventListener('submit', (e) => this.handleSchoolSubmit(e));
        document.getElementById('cancel-school-btn').addEventListener('click', () => this.closeModal('school-modal'));
    },

    /**
     * Switch between tabs
     */
    switchTab(tabName) {
        // Update tab buttons
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');

        // Update tab content
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.getElementById(`${tabName}-content`).classList.add('active');

        // Load data for tab
        if (tabName === 'organizations' && this.currentUser.role === 'superadmin') {
            this.loadOrganizationsList();
        } else if (tabName === 'schools') {
            this.loadSchoolsList();
        }
    },

    /**
     * Load users from API
     */
    async loadUsers() {
        try {
            const params = new URLSearchParams({
                page: this.currentPage,
                pageSize: this.pageSize,
                ...this.filters
            });

            // Remove empty filters
            for (let [key, value] of [...params.entries()]) {
                if (!value) params.delete(key);
            }

            // Use API module for authenticated request
            const response = await API.get(`/users?${params}`);

            if (response.success) {
                this.displayUsers(response.data.data);
                this.updatePagination(response.data.total, response.data.page, response.data.pageSize);
            } else {
                throw new Error(response.message || 'Failed to load users');
            }

        } catch (error) {
            console.error('Error loading users:', error);
            this.showError('Failed to load users: ' + error.message);
        }
    },

    /**
     * Display users in table
     */
    displayUsers(users) {
        const container = document.getElementById('users-table-container');

        if (!users || users.length === 0) {
            container.innerHTML = '<p>No users found.</p>';
            return;
        }

        const table = `
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Organization</th>
                        <th>School</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${users.map(user => this.renderUserRow(user)).join('')}
                </tbody>
            </table>
        `;

        container.innerHTML = table;

        // Add event listeners to action buttons
        users.forEach(user => {
            document.getElementById(`edit-user-${user.id}`)?.addEventListener('click', () => this.editUser(user));
            document.getElementById(`delete-user-${user.id}`)?.addEventListener('click', () => this.deleteUser(user));
        });
    },

    /**
     * Render single user row
     */
    renderUserRow(user) {
        const orgName = this.organizations.find(o => o.id == user.primary_organization_id)?.name || 'N/A';
        const schoolName = this.schools.find(s => s.id == user.primary_school_id)?.name || 'N/A';
        const statusBadge = user.is_active ?
            '<span class="status-badge status-active">Active</span>' :
            '<span class="status-badge status-inactive">Inactive</span>';

        return `
            <tr>
                <td>
                    <strong>${this.escapeHtml(user.name)}</strong>
                    ${user.organizational_title ? `<br><small>${this.escapeHtml(user.organizational_title)}</small>` : ''}
                </td>
                <td>${this.escapeHtml(user.email)}</td>
                <td><span class="role-badge role-${user.role}">${user.role}</span></td>
                <td>${this.escapeHtml(orgName)}</td>
                <td>${this.escapeHtml(schoolName)}</td>
                <td>${statusBadge}</td>
                <td>
                    <button class="action-btn edit" id="edit-user-${user.id}" title="Edit User">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="action-btn delete" id="delete-user-${user.id}" title="Delete User">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    },

    /**
     * Show user modal (create or edit)
     */
    showUserModal(user = null) {
        this.editingUserId = user ? user.id : null;

        // Update modal title
        document.getElementById('user-modal-title').textContent = user ? 'Edit User' : 'Create User';
        document.getElementById('submit-user-btn').textContent = user ? 'Update User' : 'Create User';

        // Configure password field
        const passwordGroup = document.getElementById('password-group');
        const passwordInput = document.getElementById('user-password');
        const passwordLabel = document.getElementById('password-label');
        const passwordHint = document.getElementById('password-hint');
        passwordGroup.style.display = 'block';
        if (user) {
            // Editing: password is optional
            passwordInput.removeAttribute('required');
            passwordInput.value = '';
            passwordLabel.textContent = 'New Password (leave blank to keep current)';
            passwordHint.textContent = 'Minimum 8 characters. Leave blank to keep current password.';
        } else {
            // Creating: password is required
            passwordInput.setAttribute('required', 'required');
            passwordLabel.textContent = 'Password *';
            passwordHint.textContent = 'Minimum 8 characters';
        }

        // Populate assignable roles
        this.populateAssignableRoles();

        // Fill form if editing
        if (user) {
            document.getElementById('user-name').value = user.name;
            document.getElementById('user-email').value = user.email;
            document.getElementById('user-role').value = user.role;
            document.getElementById('user-organization').value = user.primary_organization_id || '';

            // Load schools and set value
            this.loadSchoolsForOrganization(user.primary_organization_id, 'user-school', user.primary_school_id);

            document.getElementById('user-title').value = user.organizational_title || '';
            document.getElementById('user-active').checked = !!user.is_active;
        } else {
            document.getElementById('user-form').reset();
            document.getElementById('user-active').checked = true;

            // Auto-populate org/school for schooladmin
            if (this.currentUser.role === 'schooladmin') {
                const orgSelect = document.getElementById('user-organization');
                if (this.currentUser.primary_organization_id) {
                    orgSelect.value = this.currentUser.primary_organization_id;
                    orgSelect.setAttribute('disabled', 'disabled');
                    this.loadSchoolsForOrganization(
                        this.currentUser.primary_organization_id, 'user-school',
                        this.currentUser.primary_school_id
                    ).then(() => {
                        const schoolSelect = document.getElementById('user-school');
                        if (this.currentUser.primary_school_id) {
                            schoolSelect.value = this.currentUser.primary_school_id;
                            schoolSelect.setAttribute('disabled', 'disabled');
                        }
                    });
                }
            }
        }

        // Show modal
        document.getElementById('user-modal').classList.add('active');
    },

    /**
     * Populate assignable roles based on current user's role
     */
    populateAssignableRoles() {
        const roleSelect = document.getElementById('user-role');
        const assignableRoles = {
            'superadmin': [
                { value: 'superadmin', label: 'SuperAdmin' },
                { value: 'orgadmin', label: 'Organization Admin' },
                { value: 'schooladmin', label: 'School Admin' },
                { value: 'teacher', label: 'Teacher' },
                { value: 'student', label: 'Student' }
            ],
            'orgadmin': [
                { value: 'schooladmin', label: 'School Admin' },
                { value: 'teacher', label: 'Teacher' },
                { value: 'student', label: 'Student' }
            ],
            'schooladmin': [
                { value: 'teacher', label: 'Teacher' },
                { value: 'student', label: 'Student' }
            ]
        };

        const roles = assignableRoles[this.currentUser.role] || [];
        roleSelect.innerHTML = '<option value="">Select Role</option>' +
            roles.map(r => `<option value="${r.value}">${r.label}</option>`).join('');
    },

    /**
     * Handle user form submission
     */
    async handleUserSubmit(e) {
        e.preventDefault();

        const formData = new FormData(e.target);
        const userData = {};

        for (let [key, value] of formData.entries()) {
            if (key === 'is_active') {
                userData[key] = document.getElementById('user-active').checked;
            } else if (key === 'organization_id' || key === 'school_id') {
                userData[key] = value ? parseInt(value) : null;
            } else if (value) {
                userData[key] = value;
            }
        }

        // FormData skips disabled fields — read org/school directly from DOM
        if (!userData.organization_id) {
            const orgVal = document.getElementById('user-organization')?.value;
            if (orgVal) userData.organization_id = parseInt(orgVal);
        }
        if (!userData.school_id) {
            const schoolVal = document.getElementById('user-school')?.value;
            if (schoolVal) userData.school_id = parseInt(schoolVal);
        }

        try {
            let response;
            if (this.editingUserId) {
                // Update user — remap field names to match backend's $allowedFields
                const updateData = {
                    name: userData.name,
                    email: userData.email,
                    role: userData.role,
                    primary_organization_id: userData.organization_id,
                    primary_school_id: userData.school_id,
                    organizational_title: userData.organizational_title,
                    is_active: userData.is_active !== false
                };
                // Only include password if the user typed one
                if (userData.password && userData.password.trim()) {
                    updateData.password = userData.password;
                }
                response = await API.put(`/users/${this.editingUserId}`, updateData);
            } else {
                // Create user - use proper field names
                const createData = {
                    name: userData.name,
                    email: userData.email,
                    password: userData.password,
                    role: userData.role,
                    primary_organization_id: userData.organization_id,
                    primary_school_id: userData.school_id,
                    organizational_title: userData.organizational_title,
                    is_active: userData.is_active !== false
                };
                response = await API.post('/users', createData);
            }

            if (!response.success) {
                throw new Error(response.message || 'Failed to save user');
            }

            this.showSuccess(this.editingUserId ? 'User updated successfully' : 'User created successfully');
            this.closeModal('user-modal');
            await this.loadUsers();

        } catch (error) {
            console.error('Error saving user:', error);
            this.showError(error.message);
        }
    },

    /**
     * Edit user
     */
    editUser(user) {
        this.showUserModal(user);
    },

    /**
     * Delete user
     */
    async deleteUser(user) {
        if (!confirm(`Are you sure you want to delete ${user.name}? This action cannot be undone.`)) {
            return;
        }

        try {
            const response = await API.delete(`/users/${user.id}`);

            if (!response.success) {
                throw new Error(response.message || 'Failed to delete user');
            }

            this.showSuccess('User deleted successfully');
            await this.loadUsers();

        } catch (error) {
            console.error('Error deleting user:', error);
            this.showError(error.message);
        }
    },

    /**
     * Load organizations
     */
    async loadOrganizations() {
        try {
            const response = await API.get('/organizations');

            if (response.success) {
                this.organizations = response.data.organizations || [];
                this.populateOrganizationSelects();
            }
        } catch (error) {
            console.error('Error loading organizations:', error);
            // User doesn't have access to all organizations - use their organization
            this.organizations = [];
            if (this.currentUser.primary_organization_id) {
                this.organizations = [{
                    id: this.currentUser.primary_organization_id,
                    name: 'Your Organization'
                }];
            }
            this.populateOrganizationSelects();
        }
    },

    /**
     * Populate organization select dropdowns
     */
    populateOrganizationSelects() {
        const selects = ['user-organization', 'organization-filter', 'school-organization'];

        selects.forEach(selectId => {
            const select = document.getElementById(selectId);
            if (select) {
                const currentValue = select.value;
                const isFilter = selectId.includes('filter');

                select.innerHTML = isFilter ?
                    '<option value="">All Organizations</option>' :
                    '<option value="">Select Organization</option>';

                this.organizations.forEach(org => {
                    select.innerHTML += `<option value="${org.id}">${this.escapeHtml(org.name)}</option>`;
                });

                if (currentValue) select.value = currentValue;
            }
        });
    },

    /**
     * Load schools
     */
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

    /**
     * Load schools for specific organization
     */
    async loadSchoolsForOrganization(orgId, selectId, selectedSchoolId = null) {
        const select = document.getElementById(selectId);
        if (!select) return;

        select.innerHTML = '<option value="">Select School (Optional)</option>';

        if (!orgId) return;

        const orgSchools = this.schools.filter(s => s.organization_id == orgId);
        orgSchools.forEach(school => {
            select.innerHTML += `<option value="${school.id}">${this.escapeHtml(school.name)}</option>`;
        });

        if (selectedSchoolId) {
            select.value = selectedSchoolId;
        }
    },

    /**
     * Populate school filter
     */
    populateSchoolFilter() {
        const select = document.getElementById('school-filter');
        if (!select) return;

        select.innerHTML = '<option value="">All Schools</option>';
        this.schools.forEach(school => {
            select.innerHTML += `<option value="${school.id}">${this.escapeHtml(school.name)}</option>`;
        });
    },

    /**
     * Apply filters
     */
    applyFilters() {
        this.filters.role = document.getElementById('role-filter').value;
        this.filters.organization_id = document.getElementById('organization-filter').value;
        this.filters.school_id = document.getElementById('school-filter').value;
        this.filters.search = document.getElementById('search-users').value;

        this.currentPage = 1;
        this.loadUsers();
    },

    /**
     * Clear filters
     */
    clearFilters() {
        document.getElementById('role-filter').value = '';
        document.getElementById('organization-filter').value = '';
        document.getElementById('school-filter').value = '';
        document.getElementById('search-users').value = '';
        this.applyFilters();
    },

    /**
     * Update pagination
     */
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
        info.textContent = `Showing ${start}-${end} of ${total} users`;

        // Create pagination buttons
        let buttons = '';

        // Previous button
        buttons += `<button class="btn-secondary btn-sm" ${page === 1 ? 'disabled' : ''} onclick="UserManagement.goToPage(${page - 1})">Previous</button>`;

        // Page numbers (show max 5 pages)
        const maxButtons = 5;
        let startPage = Math.max(1, page - Math.floor(maxButtons / 2));
        let endPage = Math.min(totalPages, startPage + maxButtons - 1);

        if (endPage - startPage < maxButtons - 1) {
            startPage = Math.max(1, endPage - maxButtons + 1);
        }

        for (let i = startPage; i <= endPage; i++) {
            buttons += `<button class="btn-secondary btn-sm ${i === page ? 'active' : ''}" onclick="UserManagement.goToPage(${i})">${i}</button>`;
        }

        // Next button
        buttons += `<button class="btn-secondary btn-sm" ${page === totalPages ? 'disabled' : ''} onclick="UserManagement.goToPage(${page + 1})">Next</button>`;

        controls.innerHTML = buttons;
    },

    /**
     * Go to specific page
     */
    goToPage(page) {
        if (page < 1 || page > this.totalPages) return;
        this.currentPage = page;
        this.loadUsers();
    },

    /**
     * Organization management methods
     */
    showOrgModal() {
        document.getElementById('org-form').reset();
        document.getElementById('org-modal').classList.add('active');
    },

    async handleOrgSubmit(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        const orgData = Object.fromEntries(formData);

        try {
            const response = await API.post('/organizations', orgData);

            if (!response.success) {
                throw new Error(response.message || 'Failed to create organization');
            }

            this.showSuccess('Organization created successfully');
            this.closeModal('org-modal');
            await this.loadOrganizations();
            this.loadOrganizationsList();

        } catch (error) {
            console.error('Error creating organization:', error);
            this.showError(error.message);
        }
    },

    async loadOrganizationsList() {
        // Implementation for organizations list view
        const container = document.getElementById('organizations-list');
        container.innerHTML = '<div class="loading-spinner">Loading organizations...</div>';

        try {
            const response = await API.get('/organizations');

            if (!response.success) throw new Error('Failed to load organizations');

            this.displayOrganizationsList(response.data.organizations);

        } catch (error) {
            console.error('Error loading organizations list:', error);
            container.innerHTML = '<p>Error loading organizations</p>';
        }
    },

    displayOrganizationsList(organizations) {
        const container = document.getElementById('organizations-list');

        if (!organizations || organizations.length === 0) {
            container.innerHTML = '<p>No organizations found.</p>';
            return;
        }

        const html = organizations.map(org => `
            <div class="org-section">
                <div class="org-header">
                    <div>
                        <h3>${this.escapeHtml(org.name)}</h3>
                        <p style="color: #6b7280;">${org.description || ''}</p>
                        <small>Schools: ${org.school_count || 0} | Users: ${org.user_count || 0}</small>
                    </div>
                </div>
            </div>
        `).join('');

        container.innerHTML = html;
    },

    /**
     * School management methods
     */
    showSchoolModal() {
        document.getElementById('school-form').reset();
        document.getElementById('school-modal-title').textContent = 'Create School';
        document.getElementById('submit-school-btn').textContent = 'Create School';
        document.getElementById('school-modal').classList.add('active');
    },

    async handleSchoolSubmit(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        const schoolData = {};

        for (let [key, value] of formData.entries()) {
            if (key === 'organization_id') {
                schoolData[key] = parseInt(value);
            } else {
                schoolData[key] = value;
            }
        }

        try {
            const response = await API.post('/schools', schoolData);

            if (!response.success) {
                throw new Error(response.message || 'Failed to create school');
            }

            this.showSuccess('School created successfully');
            this.closeModal('school-modal');
            await this.loadSchools();
            this.loadSchoolsList();

        } catch (error) {
            console.error('Error creating school:', error);
            this.showError(error.message);
        }
    },

    async loadSchoolsList() {
        const container = document.getElementById('schools-list');
        container.innerHTML = '<div class="loading-spinner">Loading schools...</div>';

        try {
            // Only fetch schools that have registered users (server-side filter)
            const response = await API.get('/schools?has_users=1');

            if (!response.success) throw new Error('Failed to load schools');

            this.displaySchoolsList(response.data.schools);

        } catch (error) {
            console.error('Error loading schools list:', error);
            container.innerHTML = '<p>Error loading schools</p>';
        }
    },

    displaySchoolsList(schools) {
        const container = document.getElementById('schools-list');

        if (!schools || schools.length === 0) {
            container.innerHTML = '<p>No schools with registered users found.</p>';
            return;
        }

        const html = `
            <div class="school-list">
                ${schools.map(school => `
                    <div class="school-card" onclick="UserManagement.viewSchoolDetails(${school.id})" style="cursor: pointer;" title="Click to view details">
                        <h4>${this.escapeHtml(school.name)}</h4>
                        <div class="school-stats">
                            <div><i class="fas fa-building"></i> ${school.organization_name || 'N/A'}</div>
                            <div><i class="fas fa-users"></i> ${school.user_count || 0} users</div>
                            <div><i class="fas fa-tag"></i> ${school.school_type || 'combined'}</div>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;

        container.innerHTML = html;
    },

    /**
     * View school details in modal
     */
    async viewSchoolDetails(schoolId) {
        const modal = document.getElementById('school-detail-modal');
        const body = document.getElementById('school-detail-body');

        // Show modal with loading state
        body.innerHTML = '<div class="loading-spinner">Loading school details...</div>';
        modal.classList.add('active');

        try {
            const response = await API.get(`/schools/${schoolId}`);

            if (!response.success) {
                throw new Error(response.message || 'Failed to load school details');
            }

            const school = response.data;
            const stats = school.statistics || {};

            document.getElementById('school-detail-title').textContent = school.name;

            body.innerHTML = `
                <div class="details-grid">
                    <div class="detail-item">
                        <label>Organization</label>
                        <div class="detail-value">${this.escapeHtml(school.organization_name || 'N/A')}</div>
                    </div>
                    <div class="detail-item">
                        <label>School Type</label>
                        <div class="detail-value">${this.escapeHtml(school.school_type || 'combined')}</div>
                    </div>
                    <div class="detail-item">
                        <label>Email</label>
                        <div class="detail-value">${this.escapeHtml(school.email || 'N/A')}</div>
                    </div>
                    <div class="detail-item">
                        <label>City</label>
                        <div class="detail-value">${this.escapeHtml(school.city || 'N/A')}</div>
                    </div>
                </div>

                <h3 style="margin: 1.5rem 0 1rem; color: #1f2937; border-bottom: 1px solid #e5e7eb; padding-bottom: 0.5rem;">
                    <i class="fas fa-users" style="color: #3b82f6;"></i> User Breakdown
                </h3>
                <div class="details-grid">
                    <div class="detail-item">
                        <label>Total Users</label>
                        <div class="detail-value">${stats.total_users || 0}</div>
                    </div>
                    <div class="detail-item">
                        <label>Students</label>
                        <div class="detail-value">${stats.total_students || 0}</div>
                    </div>
                    <div class="detail-item">
                        <label>Teachers</label>
                        <div class="detail-value">${stats.total_teachers || 0}</div>
                    </div>
                    <div class="detail-item">
                        <label>School Admins</label>
                        <div class="detail-value">${stats.total_school_admins || 0}</div>
                    </div>
                </div>

                <h3 style="margin: 1.5rem 0 1rem; color: #1f2937; border-bottom: 1px solid #e5e7eb; padding-bottom: 0.5rem;">
                    <i class="fas fa-chart-line" style="color: #10b981;"></i> Learning Progress
                </h3>
                <div class="details-grid">
                    <div class="detail-item">
                        <label>Total Enrollments</label>
                        <div class="detail-value">${stats.total_enrollments || 0}</div>
                    </div>
                    <div class="detail-item">
                        <label>Avg Progress</label>
                        <div class="detail-value">${stats.avg_progress || 0}%</div>
                    </div>
                    <div class="detail-item">
                        <label>Completed Courses</label>
                        <div class="detail-value">${stats.completed_courses || 0}</div>
                    </div>
                    <div class="detail-item">
                        <label>Avg Quiz Score</label>
                        <div class="detail-value">${stats.avg_quiz_score || 0}%</div>
                    </div>
                    <div class="detail-item">
                        <label>Quiz Attempts</label>
                        <div class="detail-value">${stats.total_quiz_attempts || 0}</div>
                    </div>
                    <div class="detail-item">
                        <label>Certificates Earned</label>
                        <div class="detail-value">${stats.total_certificates || 0}</div>
                    </div>
                </div>

                <h3 style="margin: 1.5rem 0 1rem; color: #1f2937; border-bottom: 1px solid #e5e7eb; padding-bottom: 0.5rem;">
                    <i class="fas fa-clock" style="color: #f59e0b;"></i> Activity
                </h3>
                <div class="details-grid">
                    <div class="detail-item">
                        <label>Recent Signups (30 days)</label>
                        <div class="detail-value">${stats.recent_signups || 0}</div>
                    </div>
                </div>

                <h3 style="margin: 1.5rem 0 1rem; color: #1f2937; border-bottom: 1px solid #e5e7eb; padding-bottom: 0.5rem;">
                    <i class="fas fa-chalkboard-teacher" style="color: #8b5cf6;"></i> Teachers at this School
                </h3>
                <div id="school-teachers-list" data-school-id="${school.id}">
                    <div class="loading-spinner">Loading teachers...</div>
                </div>

                <div id="assign-teacher-section" style="margin-top: 1rem; padding: 1rem; background: #f9fafb; border-radius: 8px;">
                    <label for="assign-teacher-select" style="display: block; font-weight: 600; margin-bottom: 0.5rem;">
                        Assign a teacher to this school
                    </label>
                    <div style="display: flex; gap: 0.5rem; align-items: stretch;">
                        <select id="assign-teacher-select" style="flex: 1; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px;">
                            <option value="">Loading teachers...</option>
                        </select>
                        <button type="button" class="btn-primary" id="assign-teacher-btn" data-school-id="${school.id}">
                            <i class="fas fa-plus"></i> Assign
                        </button>
                    </div>
                    <small style="color: #6b7280;">Picks from teachers who are not yet assigned to this school. The teacher's dashboard will immediately scope to this school.</small>
                </div>
            `;

            // Wire up assignment controls and load teacher data
            this.loadSchoolTeachers(school.id);
            document.getElementById('assign-teacher-btn').onclick = () => {
                const select = document.getElementById('assign-teacher-select');
                const teacherId = select.value;
                if (!teacherId) {
                    this.showToast('Pick a teacher first', 'error');
                    return;
                }
                this.assignTeacherToSchool(teacherId, school.id);
            };

        } catch (error) {
            console.error('Error loading school details:', error);
            body.innerHTML = `<p style="color: #ef4444;">Failed to load school details: ${this.escapeHtml(error.message)}</p>`;
        }
    },

    /**
     * Load the list of teachers currently assigned to a school,
     * plus the list of teachers available to assign.
     */
    async loadSchoolTeachers(schoolId) {
        const listEl = document.getElementById('school-teachers-list');
        const selectEl = document.getElementById('assign-teacher-select');
        if (!listEl || !selectEl) return;

        try {
            // Both calls in parallel: current teachers + all teachers
            const [assignedRes, allRes] = await Promise.all([
                API.get(`/users?school_id=${schoolId}&role=teacher&pageSize=100`),
                API.get(`/users?role=teacher&pageSize=200`)
            ]);

            const assignedPayload = assignedRes.data || {};
            const allPayload = allRes.data || {};
            const assigned = Array.isArray(assignedPayload) ? assignedPayload : (assignedPayload.data || []);
            const all = Array.isArray(allPayload) ? allPayload : (allPayload.data || []);

            // Render current teachers
            if (assigned.length === 0) {
                listEl.innerHTML = '<p style="color: #6b7280; font-style: italic;">No teachers assigned yet.</p>';
            } else {
                listEl.innerHTML = `
                    <table class="admin-table" style="margin: 0;">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th style="width: 120px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${assigned.map(t => `
                                <tr>
                                    <td><strong>${this.escapeHtml(t.name || '')}</strong></td>
                                    <td>${this.escapeHtml(t.email || '')}</td>
                                    <td>
                                        <button type="button" class="btn-secondary btn-sm"
                                                onclick="UserManagement.removeTeacherFromSchool(${t.id}, ${schoolId})">
                                            <i class="fas fa-times"></i> Remove
                                        </button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
            }

            // Populate the assign dropdown with teachers not already at this school
            const assignedIds = new Set(assigned.map(t => String(t.id)));
            const candidates = all.filter(t => !assignedIds.has(String(t.id)));

            if (candidates.length === 0) {
                selectEl.innerHTML = '<option value="">No teachers available to assign</option>';
                selectEl.disabled = true;
            } else {
                selectEl.disabled = false;
                selectEl.innerHTML = '<option value="">Select a teacher...</option>' +
                    candidates.map(t => {
                        const currentSchool = t.primary_school_id ? ` (currently at school #${t.primary_school_id})` : '';
                        return `<option value="${t.id}">${this.escapeHtml(t.name || 'Unnamed')} — ${this.escapeHtml(t.email || '')}${currentSchool}</option>`;
                    }).join('');
            }
        } catch (error) {
            console.error('Error loading teachers for school:', error);
            listEl.innerHTML = `<p style="color: #ef4444;">Failed to load teachers: ${this.escapeHtml(error.message)}</p>`;
        }
    },

    /**
     * Assign a teacher to a school by setting primary_school_id.
     */
    async assignTeacherToSchool(teacherId, schoolId) {
        const btn = document.getElementById('assign-teacher-btn');
        if (btn) btn.disabled = true;

        try {
            const response = await API.put(`/users/${teacherId}`, { primary_school_id: schoolId });
            if (response && response.success === false) {
                throw new Error(response.message || 'Failed to assign teacher');
            }
            this.showToast('Teacher assigned successfully', 'success');
            await this.loadSchoolTeachers(schoolId);
        } catch (error) {
            console.error('Error assigning teacher:', error);
            this.showToast('Failed to assign teacher: ' + error.message, 'error');
        } finally {
            if (btn) btn.disabled = false;
        }
    },

    /**
     * Unassign a teacher from a school (sets primary_school_id to null).
     */
    async removeTeacherFromSchool(teacherId, schoolId) {
        if (!confirm('Remove this teacher from the school? Their dashboard will no longer show school data until they are reassigned.')) {
            return;
        }
        try {
            const response = await API.put(`/users/${teacherId}`, { primary_school_id: null });
            if (response && response.success === false) {
                throw new Error(response.message || 'Failed to remove teacher');
            }
            this.showToast('Teacher removed from school', 'success');
            await this.loadSchoolTeachers(schoolId);
        } catch (error) {
            console.error('Error removing teacher:', error);
            this.showToast('Failed to remove teacher: ' + error.message, 'error');
        }
    },

    /**
     * Utility methods
     */
    closeModal(modalId) {
        document.getElementById(modalId).classList.remove('active');
        // Re-enable any disabled selects so they work correctly next time the modal opens
        if (modalId === 'user-modal') {
            document.getElementById('user-organization')?.removeAttribute('disabled');
            document.getElementById('user-school')?.removeAttribute('disabled');
        }
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

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => UserManagement.init());
} else {
    UserManagement.init();
}
