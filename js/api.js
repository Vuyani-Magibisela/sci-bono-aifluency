/**
 * API Communication Module
 *
 * Centralized API wrapper for all backend communication
 * Handles authentication headers, token refresh, and error handling
 *
 * @version 1.0.0
 * @requires storage.js
 */

const API = {
    baseURL: '/api',
    refreshing: false,
    requestQueue: [],

    /**
     * Make an authenticated API request
     *
     * @param {string} endpoint - API endpoint (e.g., '/auth/login')
     * @param {Object} options - Fetch options
     * @returns {Promise<Object>} API response data
     */
    async request(endpoint, options = {}) {
        const token = Storage.get('access_token');

        // Build request configuration
        const config = {
            ...options,
            headers: {
                'Content-Type': 'application/json',
                ...(token && { 'Authorization': `Bearer ${token}` }),
                ...options.headers
            }
        };

        try {
            const response = await fetch(`${this.baseURL}${endpoint}`, config);

            // Handle token expiration
            if (response.status === 401 && !endpoint.includes('/auth/')) {
                console.log('Token expired, attempting refresh...');

                // If not already refreshing, attempt refresh
                if (!this.refreshing) {
                    const refreshed = await this.refreshToken();

                    if (refreshed) {
                        // Retry original request with new token
                        return this.request(endpoint, options);
                    } else {
                        // Refresh failed, redirect to login
                        this.handleAuthFailure();
                        throw new Error('Authentication failed. Please login again.');
                    }
                } else {
                    // Already refreshing, queue this request
                    return new Promise((resolve, reject) => {
                        this.requestQueue.push({ endpoint, options, resolve, reject });
                    });
                }
            }

            // Parse JSON response
            const data = await response.json();

            // Handle non-2xx responses
            if (!response.ok) {
                throw new Error(data.message || `HTTP ${response.status}: ${response.statusText}`);
            }

            return data;

        } catch (error) {
            console.error('API request error:', error);

            // Check if network error (offline)
            if (error.message === 'Failed to fetch') {
                throw new Error('Network error. Please check your internet connection.');
            }

            throw error;
        }
    },

    /**
     * Refresh access token using refresh token
     *
     * @returns {Promise<boolean>} Success status
     */
    async refreshToken() {
        if (this.refreshing) return false;

        this.refreshing = true;

        try {
            const refreshToken = Storage.get('refresh_token');

            if (!refreshToken) {
                this.refreshing = false;
                return false;
            }

            // Use fetch directly to avoid recursion
            const response = await fetch(`${this.baseURL}/auth/refresh`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ refresh_token: refreshToken })
            });

            const data = await response.json();

            if (response.ok && data.success) {
                const { access_token } = data.data;

                // Update stored token
                Storage.set('access_token', access_token);

                // Calculate new expiry (1 hour from now)
                const expiry = Date.now() + (60 * 60 * 1000);
                Storage.set('token_expiry', expiry);

                console.log('Token refreshed successfully');

                // Process queued requests
                this.processRequestQueue();

                this.refreshing = false;
                return true;
            }

            this.refreshing = false;
            return false;

        } catch (error) {
            console.error('Token refresh error:', error);
            this.refreshing = false;
            return false;
        }
    },

    /**
     * Process queued requests after token refresh
     */
    async processRequestQueue() {
        const queue = [...this.requestQueue];
        this.requestQueue = [];

        for (const item of queue) {
            try {
                const result = await this.request(item.endpoint, item.options);
                item.resolve(result);
            } catch (error) {
                item.reject(error);
            }
        }
    },

    /**
     * Handle authentication failure (redirect to login)
     */
    handleAuthFailure() {
        // Save current URL to return after login
        const currentUrl = window.location.pathname + window.location.search;
        if (!currentUrl.includes('login.html') && !currentUrl.includes('signup.html')) {
            Storage.set('return_url', currentUrl);
        }

        // Clear auth data
        Storage.remove('access_token');
        Storage.remove('refresh_token');
        Storage.remove('token_expiry');
        Storage.remove('user');

        // Redirect to login
        window.location.href = '/login.html';
    },

    /**
     * GET request
     *
     * @param {string} endpoint - API endpoint
     * @param {Object} params - URL query parameters
     * @returns {Promise<Object>} API response
     */
    async get(endpoint, params = {}) {
        // Build query string
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `${endpoint}?${queryString}` : endpoint;

        return this.request(url, { method: 'GET' });
    },

    /**
     * POST request
     *
     * @param {string} endpoint - API endpoint
     * @param {Object} body - Request body
     * @returns {Promise<Object>} API response
     */
    async post(endpoint, body = {}) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(body)
        });
    },

    /**
     * PUT request
     *
     * @param {string} endpoint - API endpoint
     * @param {Object} body - Request body
     * @returns {Promise<Object>} API response
     */
    async put(endpoint, body = {}) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(body)
        });
    },

    /**
     * DELETE request
     *
     * @param {string} endpoint - API endpoint
     * @returns {Promise<Object>} API response
     */
    async delete(endpoint) {
        return this.request(endpoint, { method: 'DELETE' });
    },

    /**
     * Upload file with multipart/form-data
     *
     * @param {string} endpoint - API endpoint
     * @param {FormData} formData - Form data with files
     * @returns {Promise<Object>} API response
     */
    async upload(endpoint, formData) {
        const token = Storage.get('access_token');

        const config = {
            method: 'POST',
            headers: {
                ...(token && { 'Authorization': `Bearer ${token}` })
                // Don't set Content-Type, browser will set it with boundary
            },
            body: formData
        };

        try {
            const response = await fetch(`${this.baseURL}${endpoint}`, config);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Upload failed');
            }

            return data;

        } catch (error) {
            console.error('Upload error:', error);
            throw error;
        }
    },

    /**
     * Check API health/connectivity
     *
     * @returns {Promise<boolean>} True if API is reachable
     */
    async healthCheck() {
        try {
            const response = await fetch(`${this.baseURL}/health`, {
                method: 'GET',
                cache: 'no-cache'
            });
            return response.ok;
        } catch (error) {
            return false;
        }
    },

    /**
     * Get API error message in user-friendly format
     *
     * @param {Error} error - Error object
     * @returns {string} User-friendly error message
     */
    getErrorMessage(error) {
        if (error.message.includes('Network error')) {
            return 'Unable to connect. Please check your internet connection.';
        }

        if (error.message.includes('401')) {
            return 'Your session has expired. Please login again.';
        }

        if (error.message.includes('403')) {
            return 'You don\'t have permission to perform this action.';
        }

        if (error.message.includes('404')) {
            return 'The requested resource was not found.';
        }

        if (error.message.includes('422')) {
            return 'Please check your input and try again.';
        }

        if (error.message.includes('500')) {
            return 'Server error. Please try again later.';
        }

        return error.message || 'An unexpected error occurred.';
    }
};

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = API;
}
