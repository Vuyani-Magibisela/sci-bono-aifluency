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
                            <li><a href="/index.html" class="mobile-nav-link"><i class="fas fa-home"></i> Home</a></li>
                            <li><a href="${this.getCoursesUrl(user)}" class="mobile-nav-link"><i class="fas fa-book"></i> ${isAuthenticated ? 'My Courses' : 'Courses'}</a></li>
                            ${isAuthenticated ? '<li><a href="/student/projects/index.html" class="mobile-nav-link"><i class="fas fa-project-diagram"></i> Projects</a></li>' : ''}
                            <li><a href="/about.html" class="mobile-nav-link"><i class="fas fa-info-circle"></i> About</a></li>
                            ${isAuthenticated ? `
                                <li class="mobile-nav-divider"></li>
                                <li><a href="${Auth.getDashboardUrl()}" class="mobile-nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                                <li><a href="/student/quizzes/quiz-history.html" class="mobile-nav-link"><i class="fas fa-question-circle"></i> Quizzes</a></li>
                                <li><a href="${this.getAnalyticsUrl(user)}" class="mobile-nav-link"><i class="fas fa-chart-line"></i> Analytics</a></li>
                                <li><a href="/student/certificates.html" class="mobile-nav-link"><i class="fas fa-certificate"></i> Certificates</a></li>
                                <li><a href="/profile/index.html" class="mobile-nav-link"><i class="fas fa-user"></i> Profile</a></li>
                                <li><a href="/profile/edit.html" class="mobile-nav-link"><i class="fas fa-cog"></i> Settings</a></li>
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
            ${isAuthenticated ? `
                <nav class="bottom-nav" id="bottomNav" role="navigation" aria-label="Primary mobile navigation">
                    <a href="${Auth.getDashboardUrl()}" class="bottom-nav-item" data-nav="dashboard">
                        <i class="fas fa-tachometer-alt" aria-hidden="true"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="${this.getCoursesUrl(user)}" class="bottom-nav-item" data-nav="courses">
                        <i class="fas fa-book" aria-hidden="true"></i>
                        <span>Courses</span>
                    </a>
                    <a href="/student/quizzes/quiz-history.html" class="bottom-nav-item" data-nav="quizzes">
                        <i class="fas fa-question-circle" aria-hidden="true"></i>
                        <span>Quizzes</span>
                    </a>
                    <a href="${this.getAnalyticsUrl(user)}" class="bottom-nav-item" data-nav="analytics">
                        <i class="fas fa-chart-line" aria-hidden="true"></i>
                        <span>Analytics</span>
                    </a>
                    <button type="button" class="bottom-nav-item bottom-nav-menu" id="bottomNavMenu" aria-label="Open menu">
                        <i class="fas fa-bars" aria-hidden="true"></i>
                        <span>Menu</span>
                    </button>
                </nav>
            ` : ''}
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

        // Load Driver.js walkthrough (only when authenticated)
        if (isAuthenticated) {
            this.loadWalkthrough();
        }
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
                <div class="tour-help-wrapper" id="tour-help-wrapper">
                    <button id="tour-help-button"
                            type="button"
                            class="tour-help-btn"
                            aria-label="Platform tour and help"
                            aria-haspopup="true"
                            aria-expanded="false"
                            aria-controls="tour-help-menu"
                            title="Take the tour">
                        <i class="fas fa-compass" aria-hidden="true"></i>
                        <span id="tour-new-badge" aria-hidden="true" style="display:none;"></span>
                    </button>
                    <div id="tour-help-menu" class="tour-help-menu" role="menu" aria-labelledby="tour-help-button">
                        <div class="tour-menu-header">Need help?</div>
                        <button id="tour-action-full" type="button" role="menuitem">
                            <i class="fas fa-route" aria-hidden="true"></i>
                            <span>Take the full tour</span>
                        </button>
                        <button id="tour-action-page" type="button" role="menuitem">
                            <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                            <span>What's on this page?</span>
                        </button>
                    </div>
                </div>
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

            // Bottom-nav "Menu" button reuses the hamburger toggle
            const bottomNavMenu = document.getElementById('bottomNavMenu');
            if (bottomNavMenu) {
                bottomNavMenu.addEventListener('click', () => hamburger.click());
            }
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

        // Highlight bottom-nav items on every page (including dashboard).
        // Quizzes slot also activates for sibling pages in /student/quizzes/.
        document.querySelectorAll('.bottom-nav-item[href]').forEach(item => {
            const itemPath = new URL(item.href, window.location.origin).pathname;
            const inQuizzesSection = item.dataset.nav === 'quizzes' && currentPath.startsWith('/student/quizzes/');
            item.classList.toggle('active', itemPath === currentPath || inQuizzesSection);
        });

        // Don't highlight top nav links on dashboard pages
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
     * Get the correct analytics URL based on user role.
     * Mirrors getCoursesUrl — teachers/instructors land on /instructor/analytics.html,
     * admins on /admin/analytics.html, everyone else on /student/analytics.html.
     *
     * @param {Object|null} user - User object
     * @returns {string} Analytics URL
     */
    getAnalyticsUrl(user) {
        if (!user || !user.role) return '/student/analytics.html';
        const role = user.role.toLowerCase();
        if (role === 'teacher' || role === 'instructor') return '/instructor/analytics.html';
        if (['superadmin', 'orgadmin', 'schooladmin', 'admin'].includes(role)) return '/admin/analytics.html';
        return '/student/analytics.html';
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
     * Inject Driver.js + walkthrough assets and the floating help button.
     * Idempotent — safe to call on every render.
     */
    loadWalkthrough() {
        if (!Auth.isAuthenticated()) return;

        // 1. Inject Driver.js CSS (CDN)
        if (!document.getElementById('driverjs-css')) {
            const link = document.createElement('link');
            link.id = 'driverjs-css';
            link.rel = 'stylesheet';
            link.href = 'https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.css';
            link.onerror = () => console.error('[Walkthrough] Failed to load Driver.js CSS from CDN');
            document.head.appendChild(link);
        }

        // 2. Inject walkthrough.css
        if (!document.getElementById('walkthrough-css')) {
            const wlink = document.createElement('link');
            wlink.id = 'walkthrough-css';
            wlink.rel = 'stylesheet';
            wlink.href = '/css/walkthrough.css';
            document.head.appendChild(wlink);
        }

        // 3. Help button markup is now rendered inline inside renderAuthControls()

        // 4. Load Driver.js + walkthrough scripts in order, then init
        const basePath = document.querySelector('script[src*="header-template"]')?.src || '';
        const dir = basePath.substring(0, basePath.lastIndexOf('/') + 1);

        const startWalkthrough = () => {
            if (typeof window.Walkthrough !== 'undefined') {
                Walkthrough.init();
            }
        };

        const loadStepsAndCore = () => {
            if (!document.getElementById('walkthrough-steps-js')) {
                const stepsScript = document.createElement('script');
                stepsScript.id = 'walkthrough-steps-js';
                stepsScript.src = dir + 'walkthrough-steps.js';
                stepsScript.onerror = () => console.error('[Walkthrough] Failed to load', stepsScript.src);
                stepsScript.onload = () => {
                    console.log('[Walkthrough] walkthrough-steps.js loaded');
                    if (!document.getElementById('walkthrough-js')) {
                        const coreScript = document.createElement('script');
                        coreScript.id = 'walkthrough-js';
                        coreScript.src = dir + 'walkthrough.js';
                        coreScript.onerror = () => console.error('[Walkthrough] Failed to load', coreScript.src);
                        coreScript.onload = () => {
                            console.log('[Walkthrough] walkthrough.js loaded');
                            startWalkthrough();
                        };
                        document.body.appendChild(coreScript);
                    } else {
                        startWalkthrough();
                    }
                };
                document.body.appendChild(stepsScript);
            } else {
                startWalkthrough();
            }
        };

        if (typeof window.driver === 'undefined' && !document.getElementById('driverjs-script')) {
            const driverScript = document.createElement('script');
            driverScript.id = 'driverjs-script';
            driverScript.src = 'https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.js.iife.js';
            driverScript.onerror = () => {
                console.error('[Walkthrough] Failed to load Driver.js CDN — tour cannot run. URL:', driverScript.src);
                // Still load steps + core so the help button at least logs a clear error on click.
                loadStepsAndCore();
            };
            driverScript.onload = () => {
                console.log('[Walkthrough] Driver.js loaded, window.driver =', typeof window.driver);
                loadStepsAndCore();
            };
            document.body.appendChild(driverScript);
        } else {
            loadStepsAndCore();
        }
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
