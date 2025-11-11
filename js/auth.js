/**
 * Authentication Module
 *
 * Handles user authentication, session management, and token lifecycle
 * for the Sci-Bono AI Fluency LMS
 *
 * @version 1.0.0
 * @requires storage.js, api.js
 */

const Auth = {
    /**
     * Login user with email and password
     *
     * @param {string} email - User email
     * @param {string} password - User password
     * @returns {Promise<Object>} Result object with success status and data
     */
    async login(email, password) {
        try {
            const response = await API.post('/auth/login', { email, password });

            if (response.success) {
                const { access_token, refresh_token, user } = response.data;

                // Store authentication data
                Storage.set('access_token', access_token);
                Storage.set('refresh_token', refresh_token);
                Storage.set('user', user);

                // Calculate token expiry (1 hour from now)
                const expiry = Date.now() + (60 * 60 * 1000);
                Storage.set('token_expiry', expiry);

                // Dispatch login event
                this.dispatchAuthEvent('login', user);

                console.log('Login successful:', user.email);

                return { success: true, user };
            }

            return { success: false, message: response.message || 'Login failed' };

        } catch (error) {
            console.error('Login error:', error);
            return {
                success: false,
                message: API.getErrorMessage(error)
            };
        }
    },

    /**
     * Register new user
     *
     * @param {Object} userData - User registration data
     * @param {string} userData.name - Full name
     * @param {string} userData.email - Email address
     * @param {string} userData.password - Password
     * @param {string} userData.role - User role (default: student)
     * @returns {Promise<Object>} Result object with success status
     */
    async register(userData) {
        try {
            const response = await API.post('/auth/register', userData);

            if (response.success) {
                console.log('Registration successful:', userData.email);

                // Auto-login after registration
                if (response.data.access_token) {
                    const { access_token, refresh_token, user } = response.data;

                    Storage.set('access_token', access_token);
                    Storage.set('refresh_token', refresh_token);
                    Storage.set('user', user);

                    const expiry = Date.now() + (60 * 60 * 1000);
                    Storage.set('token_expiry', expiry);

                    this.dispatchAuthEvent('register', user);
                }

                return { success: true, data: response.data };
            }

            return { success: false, message: response.message || 'Registration failed' };

        } catch (error) {
            console.error('Registration error:', error);
            return {
                success: false,
                message: API.getErrorMessage(error)
            };
        }
    },

    /**
     * Logout current user
     *
     * @returns {Promise<void>}
     */
    async logout() {
        try {
            // Call logout endpoint to blacklist token
            await API.post('/auth/logout');
            console.log('Logout successful');
        } catch (error) {
            console.error('Logout API error:', error);
            // Continue with local logout even if API fails
        }

        // Get user before clearing
        const user = this.getUser();

        // Clear all authentication data
        Storage.remove('access_token');
        Storage.remove('refresh_token');
        Storage.remove('token_expiry');
        Storage.remove('user');
        Storage.remove('return_url');

        // Dispatch logout event
        this.dispatchAuthEvent('logout', user);

        // Redirect to home page
        window.location.href = '/index.html';
    },

    /**
     * Check if user is authenticated
     *
     * @returns {boolean} True if authenticated with valid token
     */
    isAuthenticated() {
        const token = Storage.get('access_token');
        const expiry = Storage.get('token_expiry');

        if (!token || !expiry) {
            return false;
        }

        // Check if token has expired
        if (Date.now() >= expiry) {
            console.log('Token expired');
            return false;
        }

        return true;
    },

    /**
     * Get current authenticated user
     *
     * @returns {Object|null} User object or null if not authenticated
     */
    getUser() {
        if (!this.isAuthenticated()) {
            return null;
        }

        return Storage.get('user');
    },

    /**
     * Get user role
     *
     * @returns {string|null} User role (student, instructor, admin) or null
     */
    getUserRole() {
        const user = this.getUser();
        return user ? user.role : null;
    },

    /**
     * Check if user has specific role
     *
     * @param {string|Array<string>} roles - Role(s) to check
     * @returns {boolean} True if user has one of the specified roles
     */
    hasRole(roles) {
        const userRole = this.getUserRole();

        if (!userRole) return false;

        if (Array.isArray(roles)) {
            return roles.includes(userRole);
        }

        return userRole === roles;
    },

    /**
     * Check authentication status and refresh token if needed
     * Call this on every page load
     *
     * @returns {Promise<boolean>} True if authenticated
     */
    async checkAuthOnPageLoad() {
        if (!this.isAuthenticated()) {
            return false;
        }

        // Check if token needs refresh (< 5 minutes remaining)
        const expiry = Storage.get('token_expiry');
        const timeRemaining = expiry - Date.now();
        const fiveMinutes = 5 * 60 * 1000;

        if (timeRemaining < fiveMinutes && timeRemaining > 0) {
            console.log('Token expiring soon, refreshing...');
            await API.refreshToken();
        }

        return true;
    },

    /**
     * Require authentication for current page
     * Redirects to login if not authenticated
     *
     * @param {string|Array<string>} requiredRoles - Optional role requirement
     * @returns {boolean} True if authenticated and authorized
     */
    requireAuth(requiredRoles = null) {
        if (!this.isAuthenticated()) {
            // Save current URL to return after login
            const currentUrl = window.location.pathname + window.location.search;
            if (!currentUrl.includes('login.html') && !currentUrl.includes('signup.html')) {
                Storage.set('return_url', currentUrl);
            }

            window.location.href = '/login.html';
            return false;
        }

        // Check role if required
        if (requiredRoles && !this.hasRole(requiredRoles)) {
            console.error('Insufficient permissions');
            window.location.href = '/403.html'; // Forbidden page
            return false;
        }

        return true;
    },

    /**
     * Get appropriate dashboard URL based on user role
     *
     * @returns {string} Dashboard URL
     */
    getDashboardUrl() {
        const role = this.getUserRole();

        switch (role) {
            case 'admin':
                return '/admin-dashboard.html';
            case 'instructor':
                return '/instructor-dashboard.html';
            default:
                return '/student-dashboard.html';
        }
    },

    /**
     * Handle successful login redirect
     * Redirects to return URL or role-appropriate dashboard
     */
    handleLoginRedirect() {
        // Check for return URL
        const returnUrl = Storage.get('return_url');

        if (returnUrl) {
            Storage.remove('return_url');
            window.location.href = returnUrl;
        } else {
            // Redirect to appropriate dashboard
            window.location.href = this.getDashboardUrl();
        }
    },

    /**
     * Refresh current user data from API
     *
     * @returns {Promise<Object|null>} Updated user object
     */
    async refreshUserData() {
        try {
            const response = await API.get('/auth/me');

            if (response.success) {
                const user = response.data.user;
                Storage.set('user', user);
                this.dispatchAuthEvent('userUpdated', user);
                return user;
            }

            return null;

        } catch (error) {
            console.error('Failed to refresh user data:', error);
            return null;
        }
    },

    /**
     * Update user profile
     *
     * @param {Object} userData - Updated user data
     * @returns {Promise<Object>} Result object
     */
    async updateProfile(userData) {
        try {
            const user = this.getUser();
            if (!user) {
                return { success: false, message: 'Not authenticated' };
            }

            const response = await API.put(`/users/${user.id}`, userData);

            if (response.success) {
                // Update stored user data
                const updatedUser = response.data.user;
                Storage.set('user', updatedUser);
                this.dispatchAuthEvent('userUpdated', updatedUser);

                return { success: true, user: updatedUser };
            }

            return { success: false, message: response.message };

        } catch (error) {
            console.error('Profile update error:', error);
            return {
                success: false,
                message: API.getErrorMessage(error)
            };
        }
    },

    /**
     * Dispatch custom authentication events
     *
     * @param {string} eventType - Event type (login, logout, register, userUpdated)
     * @param {Object} detail - Event detail data
     */
    dispatchAuthEvent(eventType, detail) {
        const event = new CustomEvent('auth:' + eventType, {
            detail: detail,
            bubbles: true
        });

        window.dispatchEvent(event);
    },

    /**
     * Listen for authentication events
     *
     * @param {string} eventType - Event type to listen for
     * @param {Function} callback - Callback function
     */
    onAuthEvent(eventType, callback) {
        window.addEventListener('auth:' + eventType, (e) => {
            callback(e.detail);
        });
    },

    /**
     * Initialize authentication module
     * Call once on app initialization
     */
    init() {
        console.log('Auth module initialized');

        // Check authentication status
        if (this.isAuthenticated()) {
            console.log('User is authenticated:', this.getUser().email);
        } else {
            console.log('User is not authenticated');
        }

        // Set up periodic token refresh check (every minute)
        setInterval(async () => {
            if (this.isAuthenticated()) {
                const expiry = Storage.get('token_expiry');
                const timeRemaining = expiry - Date.now();
                const fiveMinutes = 5 * 60 * 1000;

                if (timeRemaining < fiveMinutes && timeRemaining > 0) {
                    console.log('Auto-refreshing token...');
                    await API.refreshToken();
                }
            }
        }, 60000); // Check every minute
    }
};

// Auto-initialize when script loads
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => Auth.init());
} else {
    Auth.init();
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = Auth;
}
