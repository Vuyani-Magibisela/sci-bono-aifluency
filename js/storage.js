/**
 * Storage Utility Module
 *
 * Provides localStorage abstraction with JSON serialization
 * and error handling for the Sci-Bono AI Fluency LMS
 *
 * @version 1.0.0
 */

const Storage = {
    /**
     * Store a value in localStorage with JSON serialization
     *
     * @param {string} key - Storage key
     * @param {*} value - Value to store (will be JSON stringified)
     * @returns {boolean} Success status
     */
    set(key, value) {
        try {
            const serialized = JSON.stringify(value);
            localStorage.setItem(key, serialized);
            return true;
        } catch (error) {
            console.error('Storage set error:', error);
            return false;
        }
    },

    /**
     * Retrieve a value from localStorage with JSON parsing
     *
     * @param {string} key - Storage key
     * @param {*} defaultValue - Default value if key not found
     * @returns {*} Parsed value or default
     */
    get(key, defaultValue = null) {
        try {
            const item = localStorage.getItem(key);
            if (item === null) return defaultValue;
            return JSON.parse(item);
        } catch (error) {
            console.error('Storage get error:', error);
            return defaultValue;
        }
    },

    /**
     * Remove a specific key from localStorage
     *
     * @param {string} key - Storage key to remove
     * @returns {boolean} Success status
     */
    remove(key) {
        try {
            localStorage.removeItem(key);
            return true;
        } catch (error) {
            console.error('Storage remove error:', error);
            return false;
        }
    },

    /**
     * Clear all data from localStorage
     *
     * @returns {boolean} Success status
     */
    clear() {
        try {
            localStorage.clear();
            return true;
        } catch (error) {
            console.error('Storage clear error:', error);
            return false;
        }
    },

    /**
     * Check if a key exists in localStorage
     *
     * @param {string} key - Storage key to check
     * @returns {boolean} True if key exists
     */
    has(key) {
        return localStorage.getItem(key) !== null;
    },

    /**
     * Get all keys in localStorage with optional prefix filter
     *
     * @param {string} prefix - Optional prefix to filter keys
     * @returns {Array<string>} Array of matching keys
     */
    keys(prefix = '') {
        const allKeys = Object.keys(localStorage);
        if (prefix) {
            return allKeys.filter(key => key.startsWith(prefix));
        }
        return allKeys;
    },

    /**
     * Store data with expiration time
     *
     * @param {string} key - Storage key
     * @param {*} value - Value to store
     * @param {number} ttl - Time to live in milliseconds
     * @returns {boolean} Success status
     */
    setWithExpiry(key, value, ttl) {
        const item = {
            value: value,
            expiry: Date.now() + ttl
        };
        return this.set(key, item);
    },

    /**
     * Get data with expiration check
     *
     * @param {string} key - Storage key
     * @param {*} defaultValue - Default value if expired or not found
     * @returns {*} Value if not expired, otherwise default
     */
    getWithExpiry(key, defaultValue = null) {
        const item = this.get(key);

        if (!item) return defaultValue;

        // Check if item has expiry
        if (item.expiry && Date.now() > item.expiry) {
            this.remove(key);
            return defaultValue;
        }

        return item.value;
    },

    /**
     * Get storage size in bytes (approximate)
     *
     * @returns {number} Total storage size in bytes
     */
    getSize() {
        let total = 0;
        for (let key in localStorage) {
            if (localStorage.hasOwnProperty(key)) {
                total += localStorage[key].length + key.length;
            }
        }
        return total;
    },

    /**
     * Get available storage space (rough estimate)
     *
     * @returns {Object} Object with used and available bytes
     */
    getAvailableSpace() {
        const used = this.getSize();
        const maxSize = 5 * 1024 * 1024; // 5MB typical limit

        return {
            used: used,
            available: maxSize - used,
            percentUsed: (used / maxSize * 100).toFixed(2)
        };
    }
};

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = Storage;
}
