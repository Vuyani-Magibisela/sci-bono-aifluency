/**
 * Notification Manager
 * Lightweight in-app notification system with polling
 */

const NotificationManager = {
    pollInterval: null,
    isDropdownOpen: false,
    notifications: [],
    unreadCount: 0,

    /**
     * Initialize the notification system (call after header renders)
     */
    init() {
        if (!Auth.isAuthenticated()) return;

        this.fetchUnreadCount();
        this.startPolling();

        // Close dropdown on outside click
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.notification-bell-wrapper')) {
                this.closeDropdown();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') this.closeDropdown();
        });
    },

    /**
     * Start polling for unread count
     */
    startPolling() {
        this.pollInterval = setInterval(() => {
            this.fetchUnreadCount();
        }, 60000);
    },

    /**
     * Stop polling
     */
    stopPolling() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
            this.pollInterval = null;
        }
    },

    /**
     * Fetch unread notification count
     */
    async fetchUnreadCount() {
        try {
            const response = await API.get('/notifications/unread-count');
            const count = response.data?.unread_count ?? 0;
            this.unreadCount = count;
            this.updateBadge(count);
        } catch (e) {
            // Silently fail — notification count is non-critical
        }
    },

    /**
     * Fetch full notification list
     */
    async fetchNotifications() {
        try {
            const response = await API.get('/notifications?limit=20');
            this.notifications = response.data?.notifications ?? [];
            this.unreadCount = response.data?.unread_count ?? 0;
            this.updateBadge(this.unreadCount);
            this.renderDropdown();
        } catch (e) {
            this.renderDropdownError();
        }
    },

    /**
     * Update the bell badge count
     */
    updateBadge(count) {
        const badge = document.getElementById('notification-badge');
        if (!badge) return;

        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    },

    /**
     * Toggle dropdown
     */
    toggleDropdown() {
        if (this.isDropdownOpen) {
            this.closeDropdown();
        } else {
            this.openDropdown();
        }
    },

    /**
     * Open dropdown and fetch notifications
     */
    openDropdown() {
        const dropdown = document.getElementById('notification-dropdown');
        if (!dropdown) return;

        this.isDropdownOpen = true;
        dropdown.classList.add('active');
        dropdown.innerHTML = '<div class="notif-loading">Loading...</div>';
        this.fetchNotifications();
    },

    /**
     * Close dropdown
     */
    closeDropdown() {
        const dropdown = document.getElementById('notification-dropdown');
        if (!dropdown) return;

        this.isDropdownOpen = false;
        dropdown.classList.remove('active');
    },

    /**
     * Render notification list in dropdown
     */
    renderDropdown() {
        const dropdown = document.getElementById('notification-dropdown');
        if (!dropdown) return;

        if (this.notifications.length === 0) {
            dropdown.innerHTML = `
                <div class="notif-header">
                    <span class="notif-header-title">Notifications</span>
                </div>
                <div class="notif-empty">
                    <i class="fas fa-bell-slash"></i>
                    <p>No notifications yet</p>
                </div>
            `;
            return;
        }

        const items = this.notifications.map(n => {
            const isUnread = !parseInt(n.is_read);
            const icon = this.getTypeIcon(n.type);
            const timeAgo = this.timeAgo(n.created_at);

            return `
                <div class="notif-item ${isUnread ? 'notif-unread' : ''}"
                     data-id="${n.id}"
                     data-link="${n.link || ''}"
                     onclick="NotificationManager.handleClick(${n.id}, '${(n.link || '').replace(/'/g, "\\'")}')">
                    <div class="notif-item-icon">${icon}</div>
                    <div class="notif-item-content">
                        <div class="notif-item-title">${this.escapeHtml(n.title)}</div>
                        <div class="notif-item-message">${this.escapeHtml(n.message)}</div>
                        <div class="notif-item-time">${timeAgo}</div>
                    </div>
                    ${isUnread ? '<div class="notif-unread-dot"></div>' : ''}
                </div>
            `;
        }).join('');

        dropdown.innerHTML = `
            <div class="notif-header">
                <span class="notif-header-title">Notifications</span>
                ${this.unreadCount > 0 ? `<button class="notif-mark-all" onclick="NotificationManager.markAllRead(event)">Mark all read</button>` : ''}
            </div>
            <div class="notif-list">${items}</div>
        `;
    },

    /**
     * Render error state
     */
    renderDropdownError() {
        const dropdown = document.getElementById('notification-dropdown');
        if (!dropdown) return;

        dropdown.innerHTML = `
            <div class="notif-header">
                <span class="notif-header-title">Notifications</span>
            </div>
            <div class="notif-empty">
                <p>Failed to load notifications</p>
            </div>
        `;
    },

    /**
     * Handle notification click
     */
    async handleClick(id, link) {
        // Mark as read
        try {
            await API.put(`/notifications/${id}/read`);
        } catch (e) {
            // Continue even if marking fails
        }

        // Update local state
        const notif = this.notifications.find(n => n.id == id);
        if (notif) notif.is_read = '1';
        this.unreadCount = Math.max(0, this.unreadCount - 1);
        this.updateBadge(this.unreadCount);
        this.renderDropdown();

        // Navigate if link provided
        if (link) {
            this.closeDropdown();
            window.location.href = link;
        }
    },

    /**
     * Mark all notifications as read
     */
    async markAllRead(event) {
        event.stopPropagation();
        try {
            await API.put('/notifications/read-all');
            this.notifications.forEach(n => n.is_read = '1');
            this.unreadCount = 0;
            this.updateBadge(0);
            this.renderDropdown();
        } catch (e) {
            console.error('Failed to mark all read:', e);
        }
    },

    /**
     * Get icon for notification type
     */
    getTypeIcon(type) {
        const icons = {
            'quiz_unlocked': '<i class="fas fa-unlock" style="color: #4B6EFB;"></i>',
            'project_unlocked': '<i class="fas fa-project-diagram" style="color: #6E4BFB;"></i>',
            'certificate_ready': '<i class="fas fa-certificate" style="color: #4BFB9D;"></i>',
            'profile_reminder': '<i class="fas fa-user-edit" style="color: #FFA500;"></i>',
            'welcome': '<i class="fas fa-hand-wave" style="color: #4B6EFB;"></i>',
            'achievement': '<i class="fas fa-trophy" style="color: #FFD700;"></i>'
        };
        return icons[type] || '<i class="fas fa-bell" style="color: #4B6EFB;"></i>';
    },

    /**
     * Simple relative time formatting
     */
    timeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const seconds = Math.floor((now - date) / 1000);

        if (seconds < 60) return 'Just now';
        const minutes = Math.floor(seconds / 60);
        if (minutes < 60) return `${minutes}m ago`;
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return `${hours}h ago`;
        const days = Math.floor(hours / 24);
        if (days < 7) return `${days}d ago`;
        return date.toLocaleDateString();
    },

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
};

// Clean up on auth events
if (typeof Auth !== 'undefined') {
    Auth.onAuthEvent('logout', () => NotificationManager.stopPolling());
}
