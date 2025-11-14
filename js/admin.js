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
    async init() {
        console.log('AdminDashboard: Initializing...');

        // Ensure user is authenticated and has admin role
        const user = Auth.getUser();
        if (!user) {
            console.error('AdminDashboard: No authenticated user found');
            window.location.href = '/login.html';
            return;
        }

        if (user.role !== 'admin') {
            console.error('AdminDashboard: User is not an admin');
            window.location.href = '/403.html';
            return;
        }

        // Update welcome message
        this.updateWelcomeMessage(user);

        // Load dashboard data
        await this.loadDashboardData();

        // Set up event listeners
        this.setupEventListeners();

        console.log('AdminDashboard: Initialization complete');
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
            const [users, stats, recentActivity] = await Promise.all([
                this.loadUsers(this.currentPage),
                this.loadSystemStats(),
                this.loadRecentActivity()
            ]);

            // Update UI with loaded data
            this.renderUsers(users);
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
            const response = await API.get(`/users?page=${page}&limit=${this.usersPerPage}`);
            return response.data || { users: [], total: 0, page: 1, pages: 1 };
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
            total_instructors: 0,
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
        this.updateStatCard('total-instructors', stats.total_instructors || 0);
        this.updateStatCard('total-courses', stats.total_courses || 0);
        this.updateStatCard('total-enrollments', stats.total_enrollments || 0);
        this.updateStatCard('total-certificates', stats.total_certificates || 0);
        this.updateStatCard('active-users-today', stats.active_users_today || 0);

        // Render user distribution chart if element exists
        if (stats.total_students || stats.total_instructors) {
            this.renderUserDistributionChart(stats.total_students, stats.total_instructors);
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
    renderUserDistributionChart(students, instructors) {
        const chartContainer = document.getElementById('user-distribution-chart');
        if (!chartContainer) return;

        const total = students + instructors;
        if (total === 0) {
            chartContainer.innerHTML = '<p class="no-data">No users to display</p>';
            return;
        }

        const studentPercentage = Math.round((students / total) * 100);
        const instructorPercentage = Math.round((instructors / total) * 100);

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
                    <div class="bar-label">Instructors (${instructors})</div>
                    <div class="bar-container">
                        <div class="bar-fill instructors" style="width: ${instructorPercentage}%"></div>
                    </div>
                    <div class="bar-value">${instructorPercentage}%</div>
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

        if (activities.length === 0) {
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

        // Listen for auth state changes
        document.addEventListener('authStateChanged', (e) => {
            if (!e.detail.isAuthenticated) {
                window.location.href = '/login.html';
            }
        });
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
