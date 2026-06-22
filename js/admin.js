/**
 * Admin Dashboard Module
 * Handles admin dashboard functionality including:
 * - User management (list, create, update, delete)
 * - System-wide statistics
 * - Recent activity monitoring
 * - Course and content management
 */

const AdminDashboard = {
    currentPage: 1,
    usersPerPage: 10,

    /**
     * Initialize the admin dashboard
     */
    currentUser: null,
    userRole: null,
    schoolId: null,

    async init() {
        console.log('AdminDashboard: Initializing...');

        // Ensure user is authenticated and has admin role
        const user = Auth.getUser();
        if (!user) {
            console.error('AdminDashboard: No authenticated user found');
            window.location.href = '/public/login.html';
            return;
        }

        // Phase 11+: Updated for hierarchical roles
        const adminRoles = ['superadmin', 'orgadmin', 'schooladmin'];
        if (!adminRoles.includes(user.role)) {
            console.error('AdminDashboard: User does not have admin permissions');
            window.location.href = '/public/403.html';
            return;
        }

        // Store user info for role-based restrictions
        this.currentUser = user;
        this.userRole = user.role;
        this.schoolId = user.primary_school_id || null;

        // Update welcome message
        this.updateWelcomeMessage(user);

        // Apply role-based UI restrictions
        this.applyRoleRestrictions(user);

        // Load dashboard data
        await this.loadDashboardData();

        // Set up event listeners
        this.setupEventListeners();

        console.log('AdminDashboard: Initialization complete');
    },

    /**
     * Apply role-based UI restrictions
     */
    applyRoleRestrictions(user) {
        // Update sidebar profile with actual user info
        const profileName = document.querySelector('.profile-name');
        if (profileName) {
            profileName.textContent = user.name || 'Admin User';
        }

        const profileRole = document.querySelector('.profile-role');
        const roleLabels = {
            superadmin: 'System Administrator',
            orgadmin: 'Organization Administrator',
            schooladmin: 'School Administrator'
        };

        if (profileRole) {
            profileRole.textContent = roleLabels[user.role] || 'Administrator';
        }

        // School admin restrictions
        if (user.role === 'schooladmin') {
            // Hide "Create Course" button (school admins can't create courses)
            const createCourseBtn = document.getElementById('btn-create-course');
            if (createCourseBtn) {
                createCourseBtn.style.display = 'none';
            }

            // Update card descriptions to school-specific labels
            const descriptions = document.querySelectorAll('.card-description');
            const schoolLabels = {
                'Enrolled platform users': 'School users',
                'Published courses': 'Available courses',
                'Active teachers': 'School teachers',
                'Enrolled students': 'School students'
            };
            descriptions.forEach(desc => {
                const replacement = schoolLabels[desc.textContent];
                if (replacement) {
                    desc.textContent = replacement;
                }
            });
        }
    },

    /**
     * Update the welcome message with user's name
     */
    updateWelcomeMessage(user) {
        const welcomeElement = document.getElementById('welcome-message');
        if (welcomeElement) {
            const firstName = user.name ? user.name.split(' ')[0] : 'Admin';
            welcomeElement.textContent = `Admin Panel - Welcome, ${firstName}!`;
        }
    },

    /**
     * Load all dashboard data
     */
    async loadDashboardData() {
        // Show loading state
        this.showLoadingState();

        try {
            // Load data in parallel
            const [userStats, stats, recentActivity] = await Promise.all([
                this.loadUserStats(),
                this.loadSystemStats(),
                this.loadRecentActivity()
            ]);

            // Update UI with loaded data
            this.renderUserStats(userStats);
            this.renderSystemStats(stats);
            this.renderRecentActivity(recentActivity);

            // Hide loading state
            this.hideLoadingState();

        } catch (error) {
            console.error('AdminDashboard: Error loading data:', error);
            this.showErrorState(error.message);
        }
    },

    /**
     * Load users from API
     */
    async loadUsers(page = 1) {
        try {
            const response = await API.get(`/users?page=${page}&pageSize=${this.usersPerPage}`);
            const raw = response.data || {};
            return {
                users: raw.data || raw.users || [],
                total: raw.total || 0,
                page: raw.page || 1,
                pages: raw.totalPages || raw.pages || 1
            };
        } catch (error) {
            console.warn('AdminDashboard: Could not load users:', error);
            return { users: [], total: 0, page: 1, pages: 1 };
        }
    },

    /**
     * Load system statistics
     */
    async loadSystemStats() {
        try {
            const response = await API.get('/admin/stats');
            return response.data || this.getDefaultStats();
        } catch (error) {
            console.warn('AdminDashboard: Could not load stats:', error);
            return this.getDefaultStats();
        }
    },

    /**
     * Load recent activity
     */
    async loadRecentActivity() {
        try {
            const response = await API.get('/admin/activity?limit=10');
            return response.data || [];
        } catch (error) {
            console.warn('AdminDashboard: Could not load activity:', error);
            return [];
        }
    },

    /**
     * Get default stats when API fails
     */
    getDefaultStats() {
        return {
            total_users: 0,
            total_students: 0,
            total_teachers: 0,
            total_courses: 0,
            total_enrollments: 0,
            total_certificates: 0,
            active_users_today: 0
        };
    },

    /**
     * Render users table
     */
    renderUsers(data) {
        const container = document.getElementById('users-table');
        if (!container) return;

        if (!data.users || data.users.length === 0) {
            container.innerHTML = this.getEmptyState(
                'No Users Found',
                'No users in the system yet.',
                null,
                null
            );
            return;
        }

        let html = `
            <div class="table-header">
                <h3>User Management</h3>
                <button class="btn-primary btn-sm" onclick="AdminDashboard.createUser()">
                    + Add User
                </button>
            </div>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
        `;

        data.users.forEach(user => {
            const roleClass = user.role ? user.role.toLowerCase() : 'student';
            html += `
                <tr data-user-id="${user.id}">
                    <td>${user.id}</td>
                    <td>${this.escapeHtml(user.name || 'N/A')}</td>
                    <td>${this.escapeHtml(user.email || 'N/A')}</td>
                    <td><span class="role-badge ${roleClass}">${this.escapeHtml(user.role || 'student')}</span></td>
                    <td>${this.formatDate(user.created_at)}</td>
                    <td class="actions">
                        <button class="btn-icon" onclick="AdminDashboard.viewUser(${user.id})" title="View">👁️</button>
                        <button class="btn-icon" onclick="AdminDashboard.editUser(${user.id})" title="Edit">✏️</button>
                        <button class="btn-icon delete" onclick="AdminDashboard.deleteUser(${user.id})" title="Delete">🗑️</button>
                    </td>
                </tr>
            `;
        });

        html += `
                </tbody>
            </table>
        `;

        // Add pagination if needed
        if (data.pages > 1) {
            html += this.renderPagination(data.page, data.pages);
        }

        container.innerHTML = html;
    },

    /**
     * Render pagination controls
     */
    renderPagination(currentPage, totalPages) {
        let html = '<div class="pagination">';

        // Previous button
        if (currentPage > 1) {
            html += `<button class="btn-secondary btn-sm" onclick="AdminDashboard.changePage(${currentPage - 1})">Previous</button>`;
        }

        // Page numbers
        html += `<span class="page-info">Page ${currentPage} of ${totalPages}</span>`;

        // Next button
        if (currentPage < totalPages) {
            html += `<button class="btn-secondary btn-sm" onclick="AdminDashboard.changePage(${currentPage + 1})">Next</button>`;
        }

        html += '</div>';
        return html;
    },

    /**
     * Change page for users table
     */
    async changePage(page) {
        this.currentPage = page;
        const users = await this.loadUsers(page);
        this.renderUsers(users);
    },

    /**
     * Render system statistics
     */
    renderSystemStats(stats) {
        // Update stat cards
        this.updateStatCard('total-users', stats.total_users || 0);
        this.updateStatCard('total-students', stats.total_students || 0);
        this.updateStatCard('total-teachers', stats.total_teachers || 0);
        this.updateStatCard('total-courses', stats.total_courses || 0);
        this.updateStatCard('total-enrollments', stats.total_enrollments || 0);
        this.updateStatCard('total-certificates', stats.total_certificates || 0);
        this.updateStatCard('active-users-today', stats.active_users_today || 0);

        // Render user distribution chart if element exists
        if (stats.total_students || stats.total_teachers) {
            this.renderUserDistributionChart(stats.total_students, stats.total_teachers);
        }
    },

    /**
     * Update individual stat card
     */
    updateStatCard(id, value) {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = value;
        }
    },

    /**
     * Render user distribution chart
     */
    renderUserDistributionChart(students, teachers) {
        const chartContainer = document.getElementById('user-distribution-chart');
        if (!chartContainer) return;

        const total = students + teachers;
        if (total === 0) {
            chartContainer.innerHTML = '<p class="no-data">No users to display</p>';
            return;
        }

        const studentPercentage = Math.round((students / total) * 100);
        const teacherPercentage = Math.round((teachers / total) * 100);

        chartContainer.innerHTML = `
            <div class="bar-chart">
                <div class="bar-item">
                    <div class="bar-label">Students (${students})</div>
                    <div class="bar-container">
                        <div class="bar-fill students" style="width: ${studentPercentage}%"></div>
                    </div>
                    <div class="bar-value">${studentPercentage}%</div>
                </div>
                <div class="bar-item">
                    <div class="bar-label">Teachers (${teachers})</div>
                    <div class="bar-container">
                        <div class="bar-fill teachers" style="width: ${teacherPercentage}%"></div>
                    </div>
                    <div class="bar-value">${teacherPercentage}%</div>
                </div>
            </div>
        `;
    },

    /**
     * Render recent activity
     */
    renderRecentActivity(activities) {
        const container = document.getElementById('recent-activity');
        if (!container) return;

        if (!Array.isArray(activities) || activities.length === 0) {
            container.innerHTML = this.getEmptyState(
                'No Recent Activity',
                'System activity will appear here.',
                null,
                null
            );
            return;
        }

        let html = '<div class="activity-list">';
        activities.forEach(activity => {
            const iconMap = {
                'user_registered': '👤',
                'course_created': '📚',
                'enrollment': '✅',
                'certificate_issued': '🏆',
                'quiz_completed': '📝',
                'project_submitted': '📁'
            };

            const icon = iconMap[activity.type] || '📌';

            html += `
                <div class="activity-item" data-type="${activity.type}">
                    <div class="activity-icon">${icon}</div>
                    <div class="activity-details">
                        <p class="activity-description">${this.escapeHtml(activity.description || 'Activity occurred')}</p>
                        <p class="activity-time">${this.formatTimeAgo(activity.created_at)}</p>
                    </div>
                </div>
            `;
        });
        html += '</div>';

        container.innerHTML = html;
    },

    /**
     * Load user stats from API
     */
    async loadUserStats() {
        try {
            const response = await API.get('/admin/user-stats');
            console.log('AdminDashboard: user-stats raw response:', JSON.stringify(response));
            return response.data || null;
        } catch (error) {
            console.error('AdminDashboard: Could not load user stats:', error);
            this._userStatsError = error.message || 'Unknown error';
            return null;
        }
    },

    /**
     * Render a mini stat card (white theme)
     */
    renderMiniStatCard(title, value, icon, color) {
        return `
            <div style="background: #fff; border-radius: 10px; padding: 1.25rem 1rem; text-align: center; border-left: 4px solid ${color}; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                <div style="font-size: 1.3rem; margin-bottom: 0.3rem; color: ${color};">${icon}</div>
                <div style="font-size: 1.6rem; font-weight: 700; color: #333;">${value}</div>
                <div style="font-size: 0.75rem; color: #888; margin-top: 0.25rem;">${title}</div>
            </div>
        `;
    },

    /**
     * Render user stats section (white theme)
     */
    renderUserStats(data) {
        const container = document.getElementById('user-stats-section');
        if (!container) return;

        if (!data) {
            const errMsg = this._userStatsError || 'Could not load user statistics.';
            container.innerHTML = this.getEmptyState('No Data', errMsg, null, null);
            return;
        }

        console.log('AdminDashboard: renderUserStats data:', JSON.stringify(data));

        const cp = data.course_progress || {};
        const m = data.metrics || {};
        let html = '';

        // Summary row: 4 mini stat cards
        html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">';
        html += this.renderMiniStatCard('Enrolled Users', data.enrolled_users || 0, '<i class="fas fa-user-check"></i>', '#004C9A');
        html += this.renderMiniStatCard('Schools', data.total_schools || 0, '<i class="fas fa-school"></i>', '#00A86B');
        html += this.renderMiniStatCard('Organizations', data.total_organizations || 0, '<i class="fas fa-building"></i>', '#6E4BFB');
        html += this.renderMiniStatCard('Certificates', m.total_certificates || 0, '<i class="fas fa-certificate"></i>', '#E6A817');
        html += '</div>';

        // Activity metrics row
        html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">';
        html += this.renderMiniStatCard('Active Today', m.active_users_today || 0, '<i class="fas fa-bolt"></i>', '#FB8C00');
        html += this.renderMiniStatCard('Active (7d)', m.active_users_7days || 0, '<i class="fas fa-chart-line"></i>', '#0288D1');
        html += this.renderMiniStatCard('Signups (30d)', m.recent_signups_30days || 0, '<i class="fas fa-user-plus"></i>', '#00A86B');
        html += this.renderMiniStatCard('Avg Quiz Score', m.avg_quiz_score ? m.avg_quiz_score + '%' : '0%', '<i class="fas fa-star"></i>', '#D81B60');
        html += '</div>';

        // Course progress section
        html += '<div style="background: #f8f9ff; border-radius: 10px; padding: 1.25rem; margin-bottom: 1.5rem; border: 1px solid #e8eaf6;">';
        html += '<h4 style="margin-bottom: 1rem; font-size: 0.95rem; color: #333;"><i class="fas fa-graduation-cap" style="color: #004C9A;"></i> Course Progress</h4>';
        html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 1rem; margin-bottom: 1rem;">';
        html += `<div><div style="font-size: 1.3rem; font-weight: 700; color: #333;">${cp.total_enrollments || 0}</div><div style="font-size: 0.75rem; color: #888;">Total Enrollments</div></div>`;
        html += `<div><div style="font-size: 1.3rem; font-weight: 700; color: #004C9A;">${cp.active_enrollments || 0}</div><div style="font-size: 0.75rem; color: #888;">Active</div></div>`;
        html += `<div><div style="font-size: 1.3rem; font-weight: 700; color: #00A86B;">${cp.completed_enrollments || 0}</div><div style="font-size: 0.75rem; color: #888;">Completed</div></div>`;
        html += `<div><div style="font-size: 1.3rem; font-weight: 700; color: #E6A817;">${cp.completion_rate || 0}%</div><div style="font-size: 0.75rem; color: #888;">Completion Rate</div></div>`;
        html += '</div>';

        // Avg progress bar
        const avgProgress = cp.avg_progress || 0;
        html += '<div style="margin-top: 0.5rem;">';
        html += `<div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: #666; margin-bottom: 0.3rem;"><span>Avg Progress</span><span>${avgProgress}%</span></div>`;
        html += '<div style="background: #e0e0e0; border-radius: 4px; height: 8px; overflow: hidden;">';
        html += `<div style="background: linear-gradient(90deg, #004C9A, #6E4BFB); width: ${Math.min(avgProgress, 100)}%; height: 100%; border-radius: 4px; transition: width 0.5s ease;"></div>`;
        html += '</div></div>';
        html += '</div>';

        // Schools table
        if (data.schools && data.schools.length > 0) {
            html += '<div style="margin-bottom: 1.5rem;">';
            html += '<h4 style="margin-bottom: 0.75rem; font-size: 0.95rem; color: #333;"><i class="fas fa-school" style="color: #00A86B;"></i> Schools Breakdown</h4>';
            html += '<div style="overflow-x: auto;">';
            html += '<table class="admin-table" style="font-size: 0.85rem;">';
            html += '<thead><tr><th>School</th><th>Organization</th><th>Students</th><th>Teachers</th><th>Total</th></tr></thead>';
            html += '<tbody>';
            data.schools.forEach(s => {
                html += `<tr>
                    <td>${this.escapeHtml(s.name)}</td>
                    <td>${this.escapeHtml(s.organization_name)}</td>
                    <td>${s.student_count}</td>
                    <td>${s.teacher_count}</td>
                    <td><strong>${s.user_count}</strong></td>
                </tr>`;
            });
            html += '</tbody></table></div></div>';
        }

        // Organizations table
        if (data.organizations && data.organizations.length > 0) {
            html += '<div style="margin-bottom: 1rem;">';
            html += '<h4 style="margin-bottom: 0.75rem; font-size: 0.95rem; color: #333;"><i class="fas fa-building" style="color: #6E4BFB;"></i> Organizations Breakdown</h4>';
            html += '<div style="overflow-x: auto;">';
            html += '<table class="admin-table" style="font-size: 0.85rem;">';
            html += '<thead><tr><th>Organization</th><th>Schools</th><th>Users</th></tr></thead>';
            html += '<tbody>';
            data.organizations.forEach(o => {
                html += `<tr>
                    <td>${this.escapeHtml(o.name)}</td>
                    <td>${o.school_count}</td>
                    <td><strong>${o.user_count}</strong></td>
                </tr>`;
            });
            html += '</tbody></table></div></div>';
        }

        container.innerHTML = html;
    },

    /**
     * Show loading state
     */
    showLoadingState() {
        const mainContent = document.getElementById('dashboard-content');
        if (mainContent) {
            mainContent.classList.add('loading');
        }
    },

    /**
     * Hide loading state
     */
    hideLoadingState() {
        const mainContent = document.getElementById('dashboard-content');
        if (mainContent) {
            mainContent.classList.remove('loading');
        }
    },

    /**
     * Show error state
     */
    showErrorState(message) {
        const container = document.getElementById('dashboard-content');
        if (!container) return;

        // Remove loading state before showing error
        container.classList.remove('loading');

        container.innerHTML = `
            <div class="error-state">
                <div class="error-icon">⚠️</div>
                <h2>Oops! Something went wrong</h2>
                <p>${this.escapeHtml(message)}</p>
                <button class="btn-primary" onclick="location.reload()">Try Again</button>
            </div>
        `;
    },

    /**
     * Get empty state HTML
     */
    getEmptyState(title, message, linkUrl, linkText) {
        let html = `
            <div class="empty-state">
                <div class="empty-icon">📊</div>
                <h3>${title}</h3>
                <p>${message}</p>
        `;

        if (linkUrl && linkText) {
            html += `<a href="${linkUrl}" class="btn-primary">${linkText}</a>`;
        }

        html += '</div>';
        return html;
    },

    /**
     * Create a new user
     */
    createUser() {
        console.log('AdminDashboard: Create user');
        alert('User creation interface will be implemented in Phase 8.\n\nFor now, users can self-register via /signup.html');
    },

    /**
     * View user details
     */
    async viewUser(userId) {
        console.log(`AdminDashboard: View user ${userId}`);
        try {
            const response = await API.get(`/users/${userId}`);
            const user = response.data;

            alert(`User Details:\n\nID: ${user.id}\nName: ${user.name}\nEmail: ${user.email}\nRole: ${user.role}\nJoined: ${this.formatDate(user.created_at)}`);
        } catch (error) {
            alert(`Error loading user: ${error.message}`);
        }
    },

    /**
     * Edit a user
     */
    editUser(userId) {
        console.log(`AdminDashboard: Edit user ${userId}`);
        alert(`User editing interface will be implemented in Phase 8.\nUser ID: ${userId}`);
    },

    /**
     * Delete a user
     */
    async deleteUser(userId) {
        if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
            return;
        }

        try {
            await API.delete(`/users/${userId}`);
            alert('User deleted successfully!');
            await this.loadDashboardData(); // Reload data
        } catch (error) {
            alert(`Error deleting user: ${error.message}`);
        }
    },

    /**
     * Set up event listeners
     */
    setupEventListeners() {
        // Refresh button
        const refreshBtn = document.getElementById('refresh-dashboard');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => this.loadDashboardData());
        }

        // Quick action buttons
        const addUserBtn = document.getElementById('btn-add-user');
        if (addUserBtn) {
            addUserBtn.addEventListener('click', () => {
                window.location.href = '/admin/users.html';
            });
        }

        const createCourseBtn = document.getElementById('btn-create-course');
        if (createCourseBtn) {
            createCourseBtn.addEventListener('click', () => {
                window.location.href = '/admin/courses.html';
            });
        }

        const exportReportBtn = document.getElementById('btn-export-report');
        if (exportReportBtn) {
            exportReportBtn.addEventListener('click', () => this.exportDashboardReport());
        }

        const sendAnnouncementBtn = document.getElementById('btn-send-announcement');
        if (sendAnnouncementBtn) {
            sendAnnouncementBtn.addEventListener('click', () => {
                alert('Announcements feature coming soon.');
            });
        }

        // Listen for auth state changes
        document.addEventListener('authStateChanged', (e) => {
            if (!e.detail.isAuthenticated) {
                window.location.href = '/public/login.html';
            }
        });
    },

    /**
     * Export dashboard stats as CSV
     */
    exportDashboardReport() {
        const getValue = (id) => {
            const el = document.getElementById(id);
            return el ? el.textContent.trim() : '0';
        };

        const rows = [
            ['Metric', 'Value'],
            ['Total Users', getValue('total-users')],
            ['Active Courses', getValue('total-courses')],
            ['Teachers', getValue('total-teachers')],
            ['Students', getValue('total-students')]
        ];

        const csvContent = rows.map(r => r.join(',')).join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `dashboard-report-${new Date().toISOString().slice(0, 10)}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    },

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    /**
     * Format date for display
     */
    formatDate(dateString) {
        if (!dateString) return 'N/A';

        const date = new Date(dateString);
        if (isNaN(date.getTime())) return 'Invalid Date';

        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    },

    /**
     * Format time ago (e.g., "2 hours ago")
     */
    formatTimeAgo(dateString) {
        if (!dateString) return 'N/A';

        const date = new Date(dateString);
        if (isNaN(date.getTime())) return 'Invalid Date';

        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);

        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`;
        if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
        if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;

        return this.formatDate(dateString);
    }
};

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => AdminDashboard.init());
} else {
    AdminDashboard.init();
}
