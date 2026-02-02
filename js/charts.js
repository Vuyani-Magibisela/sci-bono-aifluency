/**
 * Charts.js - Chart.js Wrapper Library for Analytics Dashboard
 * Phase 10: Advanced Analytics Dashboard
 *
 * Provides reusable chart creation functions with GSAP animations,
 * design system integration, and responsive configurations.
 *
 * Dependencies:
 * - Chart.js 4.4.1+ (CDN: https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js)
 * - GSAP 3.12.2+ (from animations.js)
 */

const Charts = {
    // ================================================================
    // DEFAULT CONFIGURATION
    // ================================================================

    defaults: {
        // Design system colors (from styles.css)
        colors: {
            primary: '#4B6EFB',      // Blue
            secondary: '#6E4BFB',    // Purple
            accent: '#FB4B4B',       // Red
            accentGreen: '#4BFB9D',  // Green
            accentOrange: '#FFA500', // Orange
            accentYellow: '#FFD700', // Gold
            grey: '#666666',
            lightGrey: '#EEEEEE',
            white: '#FFFFFF'
        },

        // Chart gradients
        gradients: {
            blue: ['#4B6EFB', '#6E4BFB'],
            purple: ['#6E4BFB', '#9B6EFB'],
            red: ['#FB4B4B', '#FB6E6E'],
            green: ['#4BFB9D', '#6EFBBB'],
            multi: ['#4B6EFB', '#6E4BFB', '#FB4B4B', '#4BFB9D', '#FFA500', '#FFD700']
        },

        // Typography
        fontFamily: "'Poppins', sans-serif",
        fontSize: 12,

        // Chart options
        responsive: true,
        maintainAspectRatio: false,

        // Animation settings
        animation: {
            duration: 1200,
            easing: 'easeInOutQuart'
        },

        // Plugin options
        plugins: {
            legend: {
                display: true,
                position: 'bottom',
                labels: {
                    padding: 15,
                    usePointStyle: true,
                    font: {
                        family: "'Poppins', sans-serif",
                        size: 12
                    }
                }
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                titleFont: {
                    family: "'Poppins', sans-serif",
                    size: 14,
                    weight: 'bold'
                },
                bodyFont: {
                    family: "'Poppins', sans-serif",
                    size: 12
                },
                padding: 12,
                cornerRadius: 8,
                displayColors: true
            }
        }
    },

    // Store chart instances for cleanup
    instances: {},

    // ================================================================
    // LINE CHART (Time-Series Data)
    // ================================================================

    /**
     * Create a line chart for time-series data
     * @param {string} canvasId - Canvas element ID
     * @param {Object} data - Chart data {labels: [], datasets: [{label, data, ...}]}
     * @param {Object} options - Additional options
     * @returns {Chart} Chart.js instance
     */
    createLineChart(canvasId, data, options = {}) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) {
            console.error(`Canvas with ID "${canvasId}" not found`);
            return null;
        }

        // Destroy existing chart if present
        if (this.instances[canvasId]) {
            this.instances[canvasId].destroy();
        }

        const ctx = canvas.getContext('2d');

        // Apply gradient to datasets
        data.datasets.forEach((dataset, index) => {
            const gradient = ctx.createLinearGradient(0, 0, 0, canvas.height);
            const colorPair = this.defaults.gradients.multi[index % this.defaults.gradients.multi.length];
            gradient.addColorStop(0, dataset.borderColor || this.defaults.colors.primary);
            gradient.addColorStop(1, dataset.backgroundColor || 'rgba(75, 110, 251, 0.1)');

            dataset.borderColor = dataset.borderColor || this.defaults.colors.primary;
            dataset.backgroundColor = gradient;
            dataset.borderWidth = dataset.borderWidth || 2;
            dataset.fill = dataset.fill !== undefined ? dataset.fill : true;
            dataset.tension = dataset.tension || 0.4; // Smooth curves
            dataset.pointRadius = dataset.pointRadius || 4;
            dataset.pointHoverRadius = dataset.pointHoverRadius || 6;
        });

        const config = {
            type: 'line',
            data: data,
            options: {
                ...this.defaults,
                ...options,
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                family: this.defaults.fontFamily,
                                size: 11
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                            borderDash: [5, 5]
                        },
                        ticks: {
                            font: {
                                family: this.defaults.fontFamily,
                                size: 11
                            }
                        }
                    }
                },
                plugins: {
                    ...this.defaults.plugins,
                    ...options.plugins
                }
            }
        };

        const chart = new Chart(ctx, config);
        this.instances[canvasId] = chart;

        // Animate entrance with GSAP
        this.animateChartEntrance(canvas);

        return chart;
    },

    // ================================================================
    // PIE/DONUT CHART (Distributions)
    // ================================================================

    /**
     * Create a pie or donut chart for data distribution
     * @param {string} canvasId - Canvas element ID
     * @param {Object} data - Chart data {labels: [], datasets: [{data: [], ...}]}
     * @param {Object} options - Additional options (use cutout: '60%' for donut)
     * @returns {Chart} Chart.js instance
     */
    createPieChart(canvasId, data, options = {}) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) {
            console.error(`Canvas with ID "${canvasId}" not found`);
            return null;
        }

        if (this.instances[canvasId]) {
            this.instances[canvasId].destroy();
        }

        // Apply colors to dataset
        data.datasets.forEach(dataset => {
            if (!dataset.backgroundColor) {
                dataset.backgroundColor = this.defaults.gradients.multi;
            }
            dataset.borderWidth = dataset.borderWidth || 2;
            dataset.borderColor = dataset.borderColor || this.defaults.colors.white;
        });

        const config = {
            type: 'pie',
            data: data,
            options: {
                ...this.defaults,
                ...options,
                plugins: {
                    ...this.defaults.plugins,
                    ...options.plugins,
                    legend: {
                        ...this.defaults.plugins.legend,
                        position: 'right'
                    }
                }
            }
        };

        const chart = new Chart(canvas.getContext('2d'), config);
        this.instances[canvasId] = chart;
        this.animateChartEntrance(canvas);

        return chart;
    },

    // ================================================================
    // BAR CHART (Comparisons)
    // ================================================================

    /**
     * Create a bar chart for comparative data
     * @param {string} canvasId - Canvas element ID
     * @param {Object} data - Chart data {labels: [], datasets: [{label, data, ...}]}
     * @param {Object} options - Additional options (use indexAxis: 'y' for horizontal)
     * @returns {Chart} Chart.js instance
     */
    createBarChart(canvasId, data, options = {}) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) {
            console.error(`Canvas with ID "${canvasId}" not found`);
            return null;
        }

        if (this.instances[canvasId]) {
            this.instances[canvasId].destroy();
        }

        const ctx = canvas.getContext('2d');

        // Apply colors to datasets
        data.datasets.forEach((dataset, index) => {
            if (!dataset.backgroundColor) {
                const gradient = ctx.createLinearGradient(0, 0, options.indexAxis === 'y' ? canvas.width : 0, options.indexAxis === 'y' ? 0 : canvas.height);
                const colorPair = this.defaults.gradients.multi.slice(index * 2, index * 2 + 2);
                gradient.addColorStop(0, colorPair[0] || this.defaults.colors.primary);
                gradient.addColorStop(1, colorPair[1] || this.defaults.colors.secondary);
                dataset.backgroundColor = gradient;
            }
            dataset.borderRadius = dataset.borderRadius || 8;
            dataset.borderSkipped = false;
        });

        const config = {
            type: 'bar',
            data: data,
            options: {
                ...this.defaults,
                ...options,
                scales: {
                    x: {
                        grid: {
                            display: options.indexAxis === 'y'
                        },
                        ticks: {
                            font: {
                                family: this.defaults.fontFamily,
                                size: 11
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            display: options.indexAxis !== 'y',
                            color: 'rgba(0, 0, 0, 0.05)',
                            borderDash: [5, 5]
                        },
                        ticks: {
                            font: {
                                family: this.defaults.fontFamily,
                                size: 11
                            }
                        }
                    }
                },
                plugins: {
                    ...this.defaults.plugins,
                    ...options.plugins
                }
            }
        };

        const chart = new Chart(ctx, config);
        this.instances[canvasId] = chart;
        this.animateChartEntrance(canvas);

        return chart;
    },

    // ================================================================
    // AREA CHART (Cumulative Metrics)
    // ================================================================

    /**
     * Create an area chart (filled line chart) for cumulative data
     * @param {string} canvasId - Canvas element ID
     * @param {Object} data - Chart data {labels: [], datasets: [{label, data, ...}]}
     * @param {Object} options - Additional options
     * @returns {Chart} Chart.js instance
     */
    createAreaChart(canvasId, data, options = {}) {
        // Area chart is just a filled line chart
        data.datasets.forEach(dataset => {
            dataset.fill = true;
        });
        return this.createLineChart(canvasId, data, options);
    },

    // ================================================================
    // MULTI-LINE CHART (Multiple Series Comparison)
    // ================================================================

    /**
     * Create a multi-line chart for comparing multiple series
     * @param {string} canvasId - Canvas element ID
     * @param {Array} datasets - Array of dataset objects
     * @param {Array} labels - X-axis labels
     * @param {Object} options - Additional options
     * @returns {Chart} Chart.js instance
     */
    createMultiLineChart(canvasId, datasets, labels, options = {}) {
        const data = {
            labels: labels,
            datasets: datasets.map((dataset, index) => ({
                ...dataset,
                borderColor: dataset.borderColor || this.defaults.gradients.multi[index % this.defaults.gradients.multi.length],
                backgroundColor: dataset.backgroundColor || 'transparent',
                fill: false,
                tension: 0.4,
                borderWidth: 2,
                pointRadius: 3,
                pointHoverRadius: 5
            }))
        };

        return this.createLineChart(canvasId, data, options);
    },

    // ================================================================
    // DOUGHNUT CHART (Ring Chart)
    // ================================================================

    /**
     * Create a doughnut chart (pie chart with center cutout)
     * @param {string} canvasId - Canvas element ID
     * @param {Object} data - Chart data
     * @param {Object} options - Additional options
     * @returns {Chart} Chart.js instance
     */
    createDoughnutChart(canvasId, data, options = {}) {
        return this.createPieChart(canvasId, data, {
            ...options,
            cutout: options.cutout || '60%'
        });
    },

    // ================================================================
    // RADAR CHART (Skill Proficiency)
    // ================================================================

    /**
     * Create a radar chart for skill/proficiency visualization
     * @param {string} canvasId - Canvas element ID
     * @param {Object} data - Chart data
     * @param {Object} options - Additional options
     * @returns {Chart} Chart.js instance
     */
    createRadarChart(canvasId, data, options = {}) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) {
            console.error(`Canvas with ID "${canvasId}" not found`);
            return null;
        }

        if (this.instances[canvasId]) {
            this.instances[canvasId].destroy();
        }

        // Apply colors to datasets
        data.datasets.forEach((dataset, index) => {
            const color = this.defaults.gradients.multi[index % this.defaults.gradients.multi.length];
            dataset.borderColor = dataset.borderColor || color;
            dataset.backgroundColor = dataset.backgroundColor || this.hexToRGBA(color, 0.2);
            dataset.pointBackgroundColor = dataset.pointBackgroundColor || color;
            dataset.pointBorderColor = dataset.pointBorderColor || '#fff';
            dataset.pointHoverBackgroundColor = dataset.pointHoverBackgroundColor || '#fff';
            dataset.pointHoverBorderColor = dataset.pointHoverBorderColor || color;
        });

        const config = {
            type: 'radar',
            data: data,
            options: {
                ...this.defaults,
                ...options,
                scales: {
                    r: {
                        beginAtZero: true,
                        max: options.max || 100,
                        ticks: {
                            stepSize: 20,
                            font: {
                                family: this.defaults.fontFamily,
                                size: 10
                            }
                        },
                        pointLabels: {
                            font: {
                                family: this.defaults.fontFamily,
                                size: 12,
                                weight: 'bold'
                            }
                        }
                    }
                },
                plugins: {
                    ...this.defaults.plugins,
                    ...options.plugins
                }
            }
        };

        const chart = new Chart(canvas.getContext('2d'), config);
        this.instances[canvasId] = chart;
        this.animateChartEntrance(canvas);

        return chart;
    },

    // ================================================================
    // UTILITY METHODS
    // ================================================================

    /**
     * Update existing chart with new data
     * @param {string} canvasId - Canvas element ID
     * @param {Object} newData - New chart data
     */
    updateChart(canvasId, newData) {
        const chart = this.instances[canvasId];
        if (!chart) {
            console.error(`No chart found with ID "${canvasId}"`);
            return;
        }

        chart.data = newData;
        chart.update('active');
    },

    /**
     * Destroy a chart instance
     * @param {string} canvasId - Canvas element ID
     */
    destroyChart(canvasId) {
        const chart = this.instances[canvasId];
        if (chart) {
            chart.destroy();
            delete this.instances[canvasId];
        }
    },

    /**
     * Destroy all chart instances
     */
    destroyAllCharts() {
        Object.keys(this.instances).forEach(canvasId => {
            this.destroyChart(canvasId);
        });
    },

    /**
     * Animate chart entrance with GSAP
     * @param {HTMLElement} canvas - Canvas element
     */
    animateChartEntrance(canvas) {
        if (typeof gsap !== 'undefined') {
            gsap.from(canvas, {
                opacity: 0,
                scale: 0.95,
                duration: 0.8,
                ease: 'power2.out'
            });
        }
    },

    /**
     * Convert hex color to RGBA
     * @param {string} hex - Hex color code
     * @param {number} alpha - Alpha value (0-1)
     * @returns {string} RGBA color string
     */
    hexToRGBA(hex, alpha) {
        const r = parseInt(hex.slice(1, 3), 16);
        const g = parseInt(hex.slice(3, 5), 16);
        const b = parseInt(hex.slice(5, 7), 16);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    },

    /**
     * Create responsive chart options for mobile
     * @param {number} breakpoint - Breakpoint in pixels (default 768)
     * @returns {Object} Responsive options
     */
    getResponsiveOptions(breakpoint = 768) {
        const isMobile = window.innerWidth <= breakpoint;

        return {
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: !isMobile,
                    position: isMobile ? 'top' : 'bottom'
                }
            },
            scales: {
                x: {
                    ticks: {
                        maxRotation: isMobile ? 45 : 0,
                        minRotation: isMobile ? 45 : 0,
                        font: {
                            size: isMobile ? 9 : 11
                        }
                    }
                },
                y: {
                    ticks: {
                        font: {
                            size: isMobile ? 9 : 11
                        }
                    }
                }
            }
        };
    }
};

// Export for use in modules (if using ES6 modules)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = Charts;
}
