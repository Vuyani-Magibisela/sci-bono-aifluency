/**
 * Filters.js - Interactive Analytics Filters
 * Phase 10: Advanced Analytics Dashboard
 *
 * Provides date range, course, and role filtering functionality
 * with callback support for chart updates.
 */

const AnalyticsFilters = {
    // Store active filters
    activeFilters: {
        dateRange: '30', // days
        startDate: null,
        endDate: null,
        courseId: null,
        moduleId: null,
        role: 'all'
    },

    // Store filter callbacks
    callbacks: [],

    // ================================================================
    // DATE RANGE FILTER
    // ================================================================

    /**
     * Initialize date range picker with presets
     * @param {string} containerId - Container element ID
     * @param {Function} callback - Callback function when date changes
     */
    initDateRangePicker(containerId, callback) {
        const container = document.getElementById(containerId);
        if (!container) {
            console.error(`Container "${containerId}" not found`);
            return;
        }

        // Create filter HTML
        container.innerHTML = `
            <div class="filter-group date-range-filter">
                <label for="date-range-preset">
                    <i class="fas fa-calendar-alt"></i> Date Range
                </label>
                <select id="date-range-preset" class="filter-select">
                    <option value="7">Last 7 Days</option>
                    <option value="30" selected>Last 30 Days</option>
                    <option value="90">Last 3 Months</option>
                    <option value="180">Last 6 Months</option>
                    <option value="365">Last Year</option>
                    <option value="all">All Time</option>
                    <option value="custom">Custom Range</option>
                </select>
                <div id="custom-date-inputs" style="display: none; margin-top: 10px;">
                    <input type="date" id="date-start" class="filter-input" placeholder="Start Date">
                    <input type="date" id="date-end" class="filter-input" placeholder="End Date">
                </div>
            </div>
        `;

        // Set up event listeners
        const preset = container.querySelector('#date-range-preset');
        const customInputs = container.querySelector('#custom-date-inputs');
        const startDate = container.querySelector('#date-start');
        const endDate = container.querySelector('#date-end');

        preset.addEventListener('change', (e) => {
            const value = e.target.value;

            if (value === 'custom') {
                customInputs.style.display = 'flex';
                this.activeFilters.dateRange = 'custom';
            } else {
                customInputs.style.display = 'none';
                this.activeFilters.dateRange = value;
                this.activeFilters.startDate = null;
                this.activeFilters.endDate = null;

                if (callback) callback(this.getDateRangeParams());
                this.triggerCallbacks();
            }
        });

        if (startDate && endDate) {
            startDate.addEventListener('change', () => {
                this.activeFilters.startDate = startDate.value;
                if (startDate.value && endDate.value) {
                    if (callback) callback(this.getDateRangeParams());
                    this.triggerCallbacks();
                }
            });

            endDate.addEventListener('change', () => {
                this.activeFilters.endDate = endDate.value;
                if (startDate.value && endDate.value) {
                    if (callback) callback(this.getDateRangeParams());
                    this.triggerCallbacks();
                }
            });
        }

        // Store callback
        if (callback) this.addCallback(callback);
    },

    /**
     * Get date range parameters for API calls
     * @returns {Object} Date range params
     */
    getDateRangeParams() {
        if (this.activeFilters.dateRange === 'custom') {
            return {
                start_date: this.activeFilters.startDate,
                end_date: this.activeFilters.endDate
            };
        } else if (this.activeFilters.dateRange === 'all') {
            return {};
        } else {
            return {
                range: this.activeFilters.dateRange
            };
        }
    },

    // ================================================================
    // COURSE/MODULE FILTER
    // ================================================================

    /**
     * Initialize course filter dropdown
     * @param {string} containerId - Container element ID
     * @param {Array} courses - Array of course objects {id, title}
     * @param {Function} callback - Callback function when selection changes
     */
    initCourseFilter(containerId, courses, callback) {
        const container = document.getElementById(containerId);
        if (!container) {
            console.error(`Container "${containerId}" not found`);
            return;
        }

        // Create filter HTML
        const options = courses.map(course =>
            `<option value="${course.id}">${course.title}</option>`
        ).join('');

        container.innerHTML = `
            <div class="filter-group course-filter">
                <label for="course-filter-select">
                    <i class="fas fa-book"></i> Course
                </label>
                <select id="course-filter-select" class="filter-select">
                    <option value="">All Courses</option>
                    ${options}
                </select>
            </div>
        `;

        // Set up event listener
        const select = container.querySelector('#course-filter-select');
        select.addEventListener('change', (e) => {
            this.activeFilters.courseId = e.target.value || null;
            if (callback) callback(this.activeFilters.courseId);
            this.triggerCallbacks();
        });

        // Store callback
        if (callback) this.addCallback(callback);
    },

    /**
     * Initialize module filter dropdown
     * @param {string} containerId - Container element ID
     * @param {Array} modules - Array of module objects {id, title}
     * @param {Function} callback - Callback function when selection changes
     */
    initModuleFilter(containerId, modules, callback) {
        const container = document.getElementById(containerId);
        if (!container) {
            console.error(`Container "${containerId}" not found`);
            return;
        }

        const options = modules.map(module =>
            `<option value="${module.id}">${module.title}</option>`
        ).join('');

        container.innerHTML = `
            <div class="filter-group module-filter">
                <label for="module-filter-select">
                    <i class="fas fa-layer-group"></i> Module
                </label>
                <select id="module-filter-select" class="filter-select">
                    <option value="">All Modules</option>
                    ${options}
                </select>
            </div>
        `;

        const select = container.querySelector('#module-filter-select');
        select.addEventListener('change', (e) => {
            this.activeFilters.moduleId = e.target.value || null;
            if (callback) callback(this.activeFilters.moduleId);
            this.triggerCallbacks();
        });

        if (callback) this.addCallback(callback);
    },

    // ================================================================
    // ROLE FILTER (Admin Views)
    // ================================================================

    /**
     * Initialize role filter for admin dashboards
     * @param {string} containerId - Container element ID
     * @param {Function} callback - Callback function when role changes
     */
    initRoleFilter(containerId, callback) {
        const container = document.getElementById(containerId);
        if (!container) {
            console.error(`Container "${containerId}" not found`);
            return;
        }

        container.innerHTML = `
            <div class="filter-group role-filter">
                <label for="role-filter-select">
                    <i class="fas fa-user-tag"></i> Role
                </label>
                <select id="role-filter-select" class="filter-select">
                    <option value="all" selected>All Users</option>
                    <option value="student">Students</option>
                    <option value="instructor">Instructors</option>
                    <option value="admin">Admins</option>
                </select>
            </div>
        `;

        const select = container.querySelector('#role-filter-select');
        select.addEventListener('change', (e) => {
            this.activeFilters.role = e.target.value;
            if (callback) callback(this.activeFilters.role);
            this.triggerCallbacks();
        });

        if (callback) this.addCallback(callback);
    },

    // ================================================================
    // FILTER MANAGEMENT
    // ================================================================

    /**
     * Apply all active filters and refresh charts
     * @param {Array} chartsToUpdate - Array of chart canvas IDs
     */
    applyFilters(chartsToUpdate = []) {
        chartsToUpdate.forEach(canvasId => {
            // Trigger chart update via callback
            this.triggerCallbacks();
        });
    },

    /**
     * Reset all filters to defaults
     */
    resetFilters() {
        this.activeFilters = {
            dateRange: '30',
            startDate: null,
            endDate: null,
            courseId: null,
            moduleId: null,
            role: 'all'
        };

        // Reset UI elements
        const datePreset = document.getElementById('date-range-preset');
        if (datePreset) datePreset.value = '30';

        const customInputs = document.getElementById('custom-date-inputs');
        if (customInputs) customInputs.style.display = 'none';

        const courseSelect = document.getElementById('course-filter-select');
        if (courseSelect) courseSelect.value = '';

        const moduleSelect = document.getElementById('module-filter-select');
        if (moduleSelect) moduleSelect.value = '';

        const roleSelect = document.getElementById('role-filter-select');
        if (roleSelect) roleSelect.value = 'all';

        // Trigger callbacks to refresh data
        this.triggerCallbacks();
    },

    /**
     * Get all active filters as query parameters
     * @returns {string} Query string
     */
    getFilterParams() {
        const params = new URLSearchParams();

        // Date range
        if (this.activeFilters.dateRange === 'custom') {
            if (this.activeFilters.startDate) params.append('start_date', this.activeFilters.startDate);
            if (this.activeFilters.endDate) params.append('end_date', this.activeFilters.endDate);
        } else if (this.activeFilters.dateRange !== 'all') {
            params.append('range', this.activeFilters.dateRange);
        }

        // Course/Module
        if (this.activeFilters.courseId) params.append('course_id', this.activeFilters.courseId);
        if (this.activeFilters.moduleId) params.append('module_id', this.activeFilters.moduleId);

        // Role
        if (this.activeFilters.role !== 'all') params.append('role', this.activeFilters.role);

        return params.toString();
    },

    /**
     * Add a callback function to be triggered on filter changes
     * @param {Function} callback - Callback function
     */
    addCallback(callback) {
        if (typeof callback === 'function' && !this.callbacks.includes(callback)) {
            this.callbacks.push(callback);
        }
    },

    /**
     * Remove a callback function
     * @param {Function} callback - Callback function to remove
     */
    removeCallback(callback) {
        const index = this.callbacks.indexOf(callback);
        if (index > -1) {
            this.callbacks.splice(index, 1);
        }
    },

    /**
     * Trigger all registered callbacks
     */
    triggerCallbacks() {
        this.callbacks.forEach(callback => {
            try {
                callback(this.activeFilters);
            } catch (error) {
                console.error('Error in filter callback:', error);
            }
        });
    },

    /**
     * Create a complete filter bar with all controls
     * @param {string} containerId - Container element ID
     * @param {Object} options - Configuration options
     */
    createFilterBar(containerId, options = {}) {
        const container = document.getElementById(containerId);
        if (!container) {
            console.error(`Container "${containerId}" not found`);
            return;
        }

        container.innerHTML = `
            <div class="analytics-filter-bar">
                <div id="date-filter-container"></div>
                ${options.showCourseFilter ? '<div id="course-filter-container"></div>' : ''}
                ${options.showModuleFilter ? '<div id="module-filter-container"></div>' : ''}
                ${options.showRoleFilter ? '<div id="role-filter-container"></div>' : ''}
                <div class="filter-actions">
                    <button id="apply-filters-btn" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <button id="reset-filters-btn" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                </div>
            </div>
        `;

        // Initialize filters
        this.initDateRangePicker('date-filter-container', options.onFilterChange);

        if (options.showCourseFilter && options.courses) {
            this.initCourseFilter('course-filter-container', options.courses, options.onFilterChange);
        }

        if (options.showModuleFilter && options.modules) {
            this.initModuleFilter('module-filter-container', options.modules, options.onFilterChange);
        }

        if (options.showRoleFilter) {
            this.initRoleFilter('role-filter-container', options.onFilterChange);
        }

        // Set up action buttons
        const applyBtn = container.querySelector('#apply-filters-btn');
        const resetBtn = container.querySelector('#reset-filters-btn');

        if (applyBtn) {
            applyBtn.addEventListener('click', () => {
                if (options.onApply) options.onApply(this.activeFilters);
                this.triggerCallbacks();
            });
        }

        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                this.resetFilters();
                if (options.onReset) options.onReset();
            });
        }
    }
};

// Export for use in modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AnalyticsFilters;
}
