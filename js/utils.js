/**
 * Utility Functions for Sci-Bono AI Fluency LMS
 * Centralized utility functions to prevent duplication across 10+ files
 *
 * Phase 11 Refactoring: Consolidates escapeHtml, animateCounter, formatDate
 * functions that were duplicated in admin-analytics.js, instructor-analytics.js,
 * student-analytics.js, profile-view.js, profiles-directory.js, breadcrumb.js,
 * and quiz-history.js.
 *
 * Usage: Include this file before other scripts:
 * <script src="js/utils.js"></script>
 */

const Utils = {
    /**
     * Escape HTML to prevent XSS attacks
     *
     * Converts special characters to HTML entities to prevent
     * malicious scripts from executing.
     *
     * @param {string} text - Text to escape
     * @returns {string} HTML-escaped text
     *
     * @example
     * const userInput = "<script>alert('XSS')</script>";
     * const safe = Utils.escapeHtml(userInput);
     * // Returns: "&lt;script&gt;alert('XSS')&lt;/script&gt;"
     */
    escapeHtml(text) {
        if (text === null || text === undefined) return '';
        if (typeof text !== 'string') text = String(text);

        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    /**
     * Animate counter from 0 to target value using GSAP
     *
     * Creates smooth animated counter effect for statistics and metrics.
     * Requires GSAP library to be loaded.
     *
     * @param {string} elementId - Element ID to animate
     * @param {number} targetValue - Final number to reach
     * @param {number} decimals - Decimal places to display (default: 0)
     * @param {string} suffix - Suffix to append (e.g., '%', 'pts') (default: '')
     * @param {number} duration - Animation duration in seconds (default: 1.5)
     *
     * @example
     * Utils.animateCounter('score-display', 95, 1, '%');
     * // Animates from 0.0% to 95.0%
     */
    animateCounter(elementId, targetValue, decimals = 0, suffix = '', duration = 1.5) {
        const element = document.getElementById(elementId);
        if (!element) {
            console.warn(`Element with ID '${elementId}' not found`);
            return;
        }

        // Check if GSAP is available
        if (typeof gsap === 'undefined') {
            console.warn('GSAP library not found. Counter will update instantly.');
            element.textContent = targetValue.toFixed(decimals) + suffix;
            return;
        }

        gsap.to({ value: 0 }, {
            value: targetValue,
            duration: duration,
            ease: 'power2.out',
            onUpdate: function() {
                element.textContent = this.targets()[0].value.toFixed(decimals) + suffix;
            }
        });
    },

    /**
     * Format date as relative time (e.g., "2 days ago")
     *
     * Converts ISO date strings to human-readable relative time.
     *
     * @param {string} dateString - ISO date string or Date object
     * @returns {string} Formatted relative time
     *
     * @example
     * Utils.formatDate('2024-01-20T10:30:00Z');
     * // Returns: "2 days ago" (if today is Jan 22)
     */
    formatDate(dateString) {
        if (!dateString) return 'N/A';

        const date = new Date(dateString);
        if (isNaN(date.getTime())) return 'Invalid date';

        const now = new Date();
        const diffMs = now - date;
        const diffSeconds = Math.floor(diffMs / 1000);
        const diffMinutes = Math.floor(diffSeconds / 60);
        const diffHours = Math.floor(diffMinutes / 60);
        const diffDays = Math.floor(diffHours / 24);
        const diffWeeks = Math.floor(diffDays / 7);
        const diffMonths = Math.floor(diffDays / 30);
        const diffYears = Math.floor(diffDays / 365);

        // Handle future dates
        if (diffSeconds < 0) return 'In the future';

        // Recent times
        if (diffSeconds < 60) return 'Just now';
        if (diffMinutes < 60) return `${diffMinutes} minute${diffMinutes !== 1 ? 's' : ''} ago`;
        if (diffHours < 24) return `${diffHours} hour${diffHours !== 1 ? 's' : ''} ago`;

        // Days
        if (diffDays === 0) return 'Today';
        if (diffDays === 1) return 'Yesterday';
        if (diffDays < 7) return `${diffDays} days ago`;

        // Weeks
        if (diffWeeks < 4) return `${diffWeeks} week${diffWeeks !== 1 ? 's' : ''} ago`;

        // Months
        if (diffMonths < 12) return `${diffMonths} month${diffMonths !== 1 ? 's' : ''} ago`;

        // Years
        return `${diffYears} year${diffYears !== 1 ? 's' : ''} ago`;
    },

    /**
     * Format date label for charts (e.g., "Jan 15")
     *
     * Creates short, readable date labels for chart axes.
     *
     * @param {string} dateString - ISO date string
     * @returns {string} Formatted date label
     *
     * @example
     * Utils.formatDateLabel('2024-01-15');
     * // Returns: "Jan 15"
     */
    formatDateLabel(dateString) {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return 'Invalid';

        const month = date.toLocaleDateString('en-US', { month: 'short' });
        const day = date.getDate();
        return `${month} ${day}`;
    },

    /**
     * Format duration in seconds to human-readable string
     *
     * Converts seconds to minutes/hours format.
     *
     * @param {number} seconds - Duration in seconds
     * @returns {string} Formatted duration (e.g., "2h 15m", "45m")
     *
     * @example
     * Utils.formatDuration(3720);
     * // Returns: "1h 2m"
     */
    formatDuration(seconds) {
        if (!seconds || seconds < 0) return 'N/A';
        if (isNaN(seconds)) return 'Invalid';

        const minutes = Math.floor(seconds / 60);
        if (minutes < 60) return `${minutes}m`;

        const hours = Math.floor(minutes / 60);
        const remainingMinutes = minutes % 60;

        if (remainingMinutes === 0) return `${hours}h`;
        return `${hours}h ${remainingMinutes}m`;
    },

    /**
     * Debounce function to limit rate of execution
     *
     * Prevents function from being called too frequently.
     * Useful for search inputs, window resize handlers, etc.
     *
     * @param {Function} func - Function to debounce
     * @param {number} wait - Wait time in milliseconds (default: 300)
     * @returns {Function} Debounced function
     *
     * @example
     * const debouncedSearch = Utils.debounce((query) => {
     *     console.log('Searching for:', query);
     * }, 500);
     *
     * // User types: "hello"
     * // Function only called once after 500ms of no typing
     * debouncedSearch('h');
     * debouncedSearch('he');
     * debouncedSearch('hel');
     * debouncedSearch('hell');
     * debouncedSearch('hello'); // This will execute after 500ms
     */
    debounce(func, wait = 300) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    /**
     * Throttle function to limit execution rate
     *
     * Ensures function is called at most once per time period.
     * Different from debounce - executes immediately then blocks.
     *
     * @param {Function} func - Function to throttle
     * @param {number} limit - Time limit in milliseconds (default: 300)
     * @returns {Function} Throttled function
     *
     * @example
     * const throttledScroll = Utils.throttle(() => {
     *     console.log('Scroll position:', window.scrollY);
     * }, 1000);
     *
     * window.addEventListener('scroll', throttledScroll);
     * // Will only log once per second, no matter how fast user scrolls
     */
    throttle(func, limit = 300) {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    },

    /**
     * Format file size to human-readable string
     *
     * @param {number} bytes - File size in bytes
     * @param {number} decimals - Decimal places (default: 2)
     * @returns {string} Formatted file size (e.g., "1.5 MB")
     *
     * @example
     * Utils.formatFileSize(1536000);
     * // Returns: "1.46 MB"
     */
    formatFileSize(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        if (!bytes || bytes < 0) return 'N/A';

        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));

        return parseFloat((bytes / Math.pow(k, i)).toFixed(decimals)) + ' ' + sizes[i];
    },

    /**
     * Generate random ID string
     *
     * Creates random alphanumeric ID for temporary elements.
     *
     * @param {number} length - Length of ID (default: 10)
     * @returns {string} Random ID
     *
     * @example
     * const tempId = Utils.generateId();
     * // Returns: "a7k9m2p5q1"
     */
    generateId(length = 10) {
        const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        let id = '';
        for (let i = 0; i < length; i++) {
            id += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return id;
    },

    /**
     * Deep clone an object
     *
     * Creates deep copy of object (not just reference).
     *
     * @param {any} obj - Object to clone
     * @returns {any} Cloned object
     *
     * @example
     * const original = { name: 'John', scores: [90, 85, 88] };
     * const copy = Utils.deepClone(original);
     * copy.scores.push(92); // Original is unchanged
     */
    deepClone(obj) {
        if (obj === null || typeof obj !== 'object') return obj;
        if (obj instanceof Date) return new Date(obj);
        if (obj instanceof Array) return obj.map(item => this.deepClone(item));

        const cloned = {};
        for (const key in obj) {
            if (obj.hasOwnProperty(key)) {
                cloned[key] = this.deepClone(obj[key]);
            }
        }
        return cloned;
    }
};

// Export for Node.js/CommonJS (if running in Node environment)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = Utils;
}

// Freeze object to prevent modification
if (typeof Object.freeze === 'function') {
    Object.freeze(Utils);
}
