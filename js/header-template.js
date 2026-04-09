/**
 * Header Template Module
 *
 * Dynamically generates page header based on authentication state
 * Replaces static header HTML across all pages
 *
 * @version 1.0.0
 * @requires auth.js
 */

const HeaderTemplate = {
    /**
     * Render header into placeholder div
     *
     * @param {string} containerId - ID of container element (default: 'header-placeholder')
     */
    render(containerId = 'header-placeholder') {
        const container = document.getElementById(containerId);

        if (!container) {
            console.error('Header container not found:', containerId);
            return;
        }

        const isAuthenticated = Auth.isAuthenticated();
        const user = Auth.getUser();

        const headerHTML = `
            <header class="main-header">
                <div class="header-container">
                    <!-- Logo and Title -->
                    <div class="header-brand">
                        <a href="${isAuthenticated ? Auth.getDashboardUrl() : '/index.html'}" class="logo-link">
                            <img src="/assets/sci-bono-logo.png" alt="Sci-Bono" class="logo-image">
                        </a>
                    </div>

                    <!-- Main Navigation -->
                    <nav class="main-nav" role="navigation" aria-label="Main navigation">
                        <ul class="nav-links">
                            <li><a href="/index.html" class="nav-link">Home</a></li>
                            <li><a href="${this.getCoursesUrl(user)}" class="nav-link">Courses</a></li>
                            ${isAuthenticated ? '<li><a href="/student/projects/index.html" class="nav-link">Projects</a></li>' : ''}
                            <li><a href="/about.html" class="nav-link">About</a></li>
                        </ul>
                    </nav>

                    <!-- Mobile Menu Toggle -->
                    <button class="hamburger" id="hamburger" aria-label="Toggle navigation menu" aria-expanded="false">
                        <span class="hamburger-line"></span>
                        <span class="hamburger-line"></span>
                        <span class="hamburger-line"></span>
                    </button>

                    <!-- Auth Controls -->
                    <div class="header-controls" id="headerControls">
                        ${this.renderAuthControls(isAuthenticated, user)}
                    </div>
                </div>

                <!-- Mobile Navigation Overlay -->
                <div class="mobile-nav-overlay" id="mobileNavOverlay">
                    <nav class="mobile-nav" role="navigation" aria-label="Mobile navigation">
                        <ul class="mobile-nav-links">
                            <li><a href="/index.html" class="mobile-nav-link">Home</a></li>
                            <li><a href="${this.getCoursesUrl(user)}" class="mobile-nav-link">Courses</a></li>
                            ${isAuthenticated ? '<li><a href="/student/projects/index.html" class="mobile-nav-link">Projects</a></li>' : ''}
                            <li><a href="/about.html" class="mobile-nav-link">About</a></li>
                            ${isAuthenticated ? `
                                <li class="mobile-nav-divider"></li>
                                <li><a href="${Auth.getDashboardUrl()}" class="mobile-nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                                <li><a href="/profile/index.html" class="mobile-nav-link"><i class="fas fa-user"></i> Profile</a></li>
                                <li><button onclick="Auth.logout()" class="mobile-nav-button"><i class="fas fa-sign-out-alt"></i> Logout</button></li>
                            ` : `
                                <li class="mobile-nav-divider"></li>
                                <li><a href="/public/login.html" class="mobile-nav-link"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                                <li><a href="/public/signup.html" class="mobile-nav-link"><i class="fas fa-user-plus"></i> Sign Up</a></li>
                            `}
                        </ul>
                    </nav>
                </div>
            </header>
        `;

        container.innerHTML = headerHTML;

        // Initialize header interactions
        this.initInteractions();

        // Set active link
        this.setActiveLink();

        // Initialize notifications (if logged in)
        if (isAuthenticated) {
            this.initNotifications();
        }

        // Load the feedback widget on every page
        this.loadFeedbackWidget();
    },

    /**
     * Render authentication controls based on state
     *
     * @param {boolean} isAuthenticated - Authentication status
     * @param {Object|null} user - User object
     * @returns {string} HTML string for auth controls
     */
    renderAuthControls(isAuthenticated, user) {
        if (isAuthenticated && user) {
            return `
                <a href="${Auth.getDashboardUrl()}" class="header-btn dashboard-btn">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="btn-text">Dashboard</span>
                </a>
                <div class="notification-bell-wrapper">
                    <button class="notification-bell-btn" aria-label="Notifications" onclick="NotificationManager.toggleDropdown()">
                        <i class="fas fa-bell"></i>
                        <span class="notif-badge" id="notification-badge" style="display:none;">0</span>
                    </button>
                    <div class="notification-dropdown" id="notification-dropdown"></div>
                </div>
                <div class="user-menu">
                    <button class="user-menu-toggle" aria-label="User menu" aria-haspopup="true" aria-expanded="false">
                        <div class="user-avatar">
                            ${this.getUserInitials(user.name)}
                        </div>
                        <span class="user-name">${this.truncateName(user.name)}</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="user-menu-dropdown" role="menu">
                        <div class="user-menu-header">
                            <div class="user-menu-avatar">${this.getUserInitials(user.name)}</div>
                            <div class="user-menu-info">
                                <div class="user-menu-name">${user.name}</div>
                                <div class="user-menu-email">${user.email}</div>
                                <div class="user-menu-role">${this.formatRole(user.role)}</div>
                            </div>
                        </div>
                        <div class="user-menu-divider"></div>
                        <a href="/profile/index.html" class="user-menu-item" role="menuitem">
                            <i class="fas fa-user"></i>
                            <span>My Profile</span>
                        </a>
                        <a href="/profile/edit.html" class="user-menu-item" role="menuitem">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </a>
                        ${Auth.isAdmin() || Auth.isTeacher() ? `
                            <div class="user-menu-divider"></div>
                            <a href="/admin/dashboard.html" class="user-menu-item" role="menuitem">
                                <i class="fas fa-shield-alt"></i>
                                <span>Admin Panel</span>
                            </a>
                        ` : ''}
                        <div class="user-menu-divider"></div>
                        <button onclick="Auth.logout()" class="user-menu-item logout-btn" role="menuitem">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </button>
                    </div>
                </div>
            `;
        }

        return `
            <a href="/public/login.html" class="header-btn login-btn">
                <i class="fas fa-sign-in-alt"></i>
                <span class="btn-text">Login</span>
            </a>
            <a href="/public/signup.html" class="header-btn signup-btn primary">
                <i class="fas fa-user-plus"></i>
                <span class="btn-text">Sign Up</span>
            </a>
        `;
    },

    /**
     * Initialize header interactions (menu toggles, dropdowns)
     */
    initInteractions() {
        // Mobile menu toggle
        const hamburger = document.getElementById('hamburger');
        const mobileOverlay = document.getElementById('mobileNavOverlay');

        if (hamburger && mobileOverlay) {
            hamburger.addEventListener('click', () => {
                const isOpen = hamburger.getAttribute('aria-expanded') === 'true';
                hamburger.setAttribute('aria-expanded', !isOpen);
                hamburger.classList.toggle('active');
                mobileOverlay.classList.toggle('active');
                document.body.classList.toggle('mobile-menu-open');
            });

            // Close on overlay click
            mobileOverlay.addEventListener('click', (e) => {
                if (e.target === mobileOverlay) {
                    hamburger.click();
                }
            });

            // Close on link click
            const mobileLinks = mobileOverlay.querySelectorAll('.mobile-nav-link, .mobile-nav-button');
            mobileLinks.forEach(link => {
                link.addEventListener('click', () => {
                    if (!link.classList.contains('mobile-nav-button')) {
                        hamburger.click();
                    }
                });
            });
        }

        // User menu dropdown
        const userMenuToggle = document.querySelector('.user-menu-toggle');
        const userMenuDropdown = document.querySelector('.user-menu-dropdown');

        if (userMenuToggle && userMenuDropdown) {
            userMenuToggle.addEventListener('click', (e) => {
                e.stopPropagation();
                const isExpanded = userMenuToggle.getAttribute('aria-expanded') === 'true';
                userMenuToggle.setAttribute('aria-expanded', !isExpanded);
                userMenuDropdown.classList.toggle('active');
            });

            // Close on outside click
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.user-menu')) {
                    userMenuToggle.setAttribute('aria-expanded', 'false');
                    userMenuDropdown.classList.remove('active');
                }
            });

            // Close on escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && userMenuDropdown.classList.contains('active')) {
                    userMenuToggle.setAttribute('aria-expanded', 'false');
                    userMenuDropdown.classList.remove('active');
                    userMenuToggle.focus();
                }
            });
        }
    },

    /**
     * Set active link based on current page
     */
    setActiveLink() {
        const currentPath = window.location.pathname;
        const currentPage = currentPath.split('/').pop() || 'index.html';

        // Don't highlight nav links on dashboard pages
        if (currentPage === 'dashboard.html') {
            return;
        }

        const navLinks = document.querySelectorAll('.nav-link, .mobile-nav-link');

        navLinks.forEach(link => {
            // Skip hash links (like #about)
            if (link.getAttribute('href').startsWith('#')) {
                return;
            }

            const linkPath = new URL(link.href, window.location.origin).pathname;

            if (linkPath === currentPath) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });
    },

    /**
     * Get user initials from name
     *
     * @param {string} name - Full name
     * @returns {string} Initials (max 2 characters)
     */
    getUserInitials(name) {
        if (!name) return '?';

        const parts = name.trim().split(' ');
        if (parts.length === 1) {
            return parts[0].charAt(0).toUpperCase();
        }

        return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
    },

    /**
     * Truncate name for display
     *
     * @param {string} name - Full name
     * @param {number} maxLength - Maximum length
     * @returns {string} Truncated name
     */
    truncateName(name, maxLength = 20) {
        if (!name) return '';

        if (name.length <= maxLength) {
            return name;
        }

        return name.substring(0, maxLength - 3) + '...';
    },

    /**
     * Get the correct courses URL based on user role
     *
     * @param {Object|null} user - User object
     * @returns {string} Courses URL
     */
    getCoursesUrl(user) {
        // If not authenticated, go to student courses (public view)
        if (!user || !user.role) {
            return '/student/courses.html';
        }

        // Check if user is admin, teacher, or organizational admin
        const adminRoles = ['superadmin', 'orgadmin', 'schooladmin', 'teacher', 'admin', 'instructor'];
        if (adminRoles.includes(user.role.toLowerCase())) {
            return '/admin/courses.html';
        }

        // Default to student courses
        return '/student/courses.html';
    },

    /**
     * Format user role for display
     *
     * @param {string} role - User role
     * @returns {string} Formatted role
     */
    formatRole(role) {
        if (!role) return '';

        const roleMap = {
            'student': 'Student',
            'teacher': 'Teacher',
            'schooladmin': 'School Admin',
            'orgadmin': 'Organization Admin',
            'superadmin': 'Super Admin',
            // Legacy role names for backwards compatibility
            'instructor': 'Teacher',
            'admin': 'Administrator'
        };

        return roleMap[role.toLowerCase()] || role;
    },

    /**
     * Load and initialize the notification system
     */
    initNotifications() {
        if (typeof NotificationManager !== 'undefined') {
            NotificationManager.init();
            return;
        }

        // Dynamically load notifications.js
        const basePath = document.querySelector('script[src*="header-template"]')?.src || '';
        const dir = basePath.substring(0, basePath.lastIndexOf('/') + 1);
        const script = document.createElement('script');
        script.src = dir + 'notifications.js';
        script.onload = () => {
            if (typeof NotificationManager !== 'undefined') {
                NotificationManager.init();
            }
        };
        document.head.appendChild(script);
    },

    /**
     * Dynamically load the feedback widget script
     */
    loadFeedbackWidget() {
        if (document.getElementById('feedback-widget-script')) return;
        const basePath = document.querySelector('script[src*="header-template"]')?.src || '';
        const dir = basePath.substring(0, basePath.lastIndexOf('/') + 1);
        const script = document.createElement('script');
        script.id = 'feedback-widget-script';
        script.src = dir + 'feedback-widget.js';
        document.body.appendChild(script);
    },

    /**
     * Update header after auth state change
     */
    update() {
        this.render();
    }
};

// Auto-render header when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        HeaderTemplate.render();
    });
} else {
    HeaderTemplate.render();
}

// Listen for auth events to update header
Auth.onAuthEvent('login', () => HeaderTemplate.update());
Auth.onAuthEvent('logout', () => HeaderTemplate.update());
Auth.onAuthEvent('userUpdated', () => HeaderTemplate.update());

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = HeaderTemplate;
}
