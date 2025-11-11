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
                        <a href="/index.html" class="logo-link">
                            <img src="/images/logo.svg" alt="Sci-Bono AI Fluency" class="logo-image">
                            <div class="brand-text">
                                <h1 class="brand-title">Sci-Bono</h1>
                                <span class="brand-subtitle">AI Fluency</span>
                            </div>
                        </a>
                    </div>

                    <!-- Main Navigation -->
                    <nav class="main-nav" role="navigation" aria-label="Main navigation">
                        <ul class="nav-links">
                            <li><a href="/index.html" class="nav-link">Home</a></li>
                            <li><a href="/courses.html" class="nav-link">Courses</a></li>
                            ${isAuthenticated ? '<li><a href="/projects.html" class="nav-link">Projects</a></li>' : ''}
                            <li><a href="#about" class="nav-link">About</a></li>
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
                            <li><a href="/courses.html" class="mobile-nav-link">Courses</a></li>
                            ${isAuthenticated ? '<li><a href="/projects.html" class="mobile-nav-link">Projects</a></li>' : ''}
                            <li><a href="#about" class="mobile-nav-link">About</a></li>
                            ${isAuthenticated ? `
                                <li class="mobile-nav-divider"></li>
                                <li><a href="${Auth.getDashboardUrl()}" class="mobile-nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                                <li><a href="/profile.html" class="mobile-nav-link"><i class="fas fa-user"></i> Profile</a></li>
                                <li><button onclick="Auth.logout()" class="mobile-nav-button"><i class="fas fa-sign-out-alt"></i> Logout</button></li>
                            ` : `
                                <li class="mobile-nav-divider"></li>
                                <li><a href="/login.html" class="mobile-nav-link"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                                <li><a href="/signup.html" class="mobile-nav-link"><i class="fas fa-user-plus"></i> Sign Up</a></li>
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
                        <a href="/profile.html" class="user-menu-item" role="menuitem">
                            <i class="fas fa-user"></i>
                            <span>My Profile</span>
                        </a>
                        <a href="/settings.html" class="user-menu-item" role="menuitem">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </a>
                        ${user.role === 'admin' ? `
                            <div class="user-menu-divider"></div>
                            <a href="/admin-dashboard.html" class="user-menu-item" role="menuitem">
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
            <a href="/login.html" class="header-btn login-btn">
                <i class="fas fa-sign-in-alt"></i>
                <span class="btn-text">Login</span>
            </a>
            <a href="/signup.html" class="header-btn signup-btn primary">
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
        const navLinks = document.querySelectorAll('.nav-link, .mobile-nav-link');

        navLinks.forEach(link => {
            const linkPath = new URL(link.href).pathname;

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
     * Format user role for display
     *
     * @param {string} role - User role
     * @returns {string} Formatted role
     */
    formatRole(role) {
        if (!role) return '';

        const roleMap = {
            'student': 'Student',
            'instructor': 'Instructor',
            'admin': 'Administrator'
        };

        return roleMap[role.toLowerCase()] || role;
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
