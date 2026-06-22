/**
 * Admin Analytics Dashboard
 * Platform-wide metrics, enrollment trends, course popularity, user acquisition, and certificates
 */

// State management
let chartInstances = {};
const SchoolFilter = {
    selectedId: '',
    schools: [],
    isSuperadmin: false
};
const SchoolDetail = {
    schoolId: null,
    page: 1,
    perPage: 25,
    sort: 'last_login_at',
    search: '',
    searchDebounce: null
};
const SchoolsList = {
    loaded: false,
    schools: []
};

// Initialize dashboard
document.addEventListener('DOMContentLoaded', async () => {
    // Check authentication and role
    if (!Auth.isAuthenticated()) {
        window.location.href = 'login.html';
        return;
    }

    const user = Auth.getUser();
    if (!Auth.isAdmin()) {
        alert('Access denied. This page is for administrators only.');
        window.location.href = 'student-dashboard.html';
        return;
    }

    // Superadmin-only school filter
    SchoolFilter.isSuperadmin = user && user.role === 'superadmin';
    if (SchoolFilter.isSuperadmin) {
        await initializeSchoolFilter();
    }

    // Initialize filters (date range only for admin)
    await initializeFilters();

    // Animate page entrance
    gsap.from('.analytics-header', {
        opacity: 0,
        y: -30,
        duration: 0.8,
        ease: 'power3.out'
    });

    gsap.from('.platform-stats-grid .platform-stat-card', {
        opacity: 0,
        scale: 0.9,
        duration: 0.6,
        stagger: 0.08,
        delay: 0.2,
        ease: 'back.out(1.7)'
    });

    gsap.from('.analytics-charts-grid .chart-card', {
        opacity: 0,
        y: 30,
        duration: 0.6,
        stagger: 0.15,
        delay: 0.5,
        ease: 'power3.out'
    });
});

/**
 * Initialize filters (date range only)
 */
async function initializeFilters() {
    try {
        // Create filter bar with date range only
        AnalyticsFilters.createFilterBar('filters-container', {
            dateRange: true,
            course: false
        });

        // Add filter change callback
        AnalyticsFilters.addCallback(async (filters) => {
            await loadAdminAnalytics();
        });

        // Load initial data
        await loadAdminAnalytics();

    } catch (error) {
        console.error('Failed to initialize filters:', error);
        document.getElementById('filters-container').innerHTML = `
            <div class="error-message">
                Failed to initialize dashboard: ${Utils.escapeHtml(error.message)}
            </div>
        `;
    }
}

/**
 * Load all admin analytics data
 */
async function loadAdminAnalytics() {
    const baseParams = AnalyticsFilters.getFilterParams();
    const schoolId = SchoolFilter.selectedId;
    const filterParams = schoolId
        ? `${baseParams}${baseParams ? '&' : ''}school_id=${encodeURIComponent(schoolId)}`
        : baseParams;
    const loadingMessage = '<div class="loading-spinner">Loading analytics data...</div>';

    try {
        // Reset chart canvases — they may have been replaced by "no data" messages on previous loads.
        // Use data-chart attribute on .chart-container divs for reliable lookup.
        document.querySelectorAll('.chart-container[data-chart]').forEach(container => {
            container.innerHTML = `<canvas id="${container.dataset.chart}"></canvas>`;
        });

        const courseContainer = document.getElementById('course-popularity-container');
        const achievementContainer = document.getElementById('achievement-distribution-container');
        if (courseContainer) courseContainer.innerHTML = loadingMessage;
        if (achievementContainer) achievementContainer.innerHTML = loadingMessage;

        // Fetch all data in parallel (using allSettled so one failure doesn't kill the whole dashboard)
        const results = await Promise.allSettled([
            API.get(`/analytics/admin/enrollment-trends?${filterParams}`),
            API.get(`/analytics/admin/course-popularity?${filterParams}`),
            API.get(`/analytics/admin/user-acquisition?${filterParams}`),
            API.get(`/analytics/admin/achievement-distribution?${filterParams}`),
            API.get(`/analytics/admin/platform-usage?${filterParams}`),
            API.get(`/analytics/admin/certificate-trends?${filterParams}`)
        ]);

        // Toggle the school detail section. Load in parallel but don't block the main dashboard.
        if (schoolId) {
            SchoolDetail.schoolId = schoolId;
            SchoolDetail.page = 1;
            loadSchoolDetail();

            const listSection = document.getElementById('schools-list-section');
            if (listSection) listSection.style.display = 'none';
        } else {
            SchoolDetail.schoolId = null;
            const section = document.getElementById('school-detail-section');
            if (section) section.style.display = 'none';

            // Superadmin All-Schools state: show schools overview grid
            if (SchoolFilter.isSuperadmin) {
                const listSection = document.getElementById('schools-list-section');
                if (listSection) listSection.style.display = '';
                if (!SchoolsList.loaded) {
                    loadSchoolsOverview();
                }
            }
        }
        const [
            enrollmentData,
            coursePopularityData,
            userAcquisitionData,
            achievementData,
            platformUsageData,
            certificateData
        ] = results.map(r => r.status === 'fulfilled' ? (r.value.data || {}) : {});

        // Update platform stats
        updatePlatformStats(
            enrollmentData,
            coursePopularityData,
            userAcquisitionData,
            certificateData
        );

        // Render visualizations
        renderEnrollmentTrends(enrollmentData);
        renderCoursePopularity(coursePopularityData);
        renderUserAcquisition(userAcquisitionData);
        renderAchievementDistribution(achievementData);
        renderPlatformUsage(platformUsageData);
        renderCertificateTrends(certificateData);

        // Generate insights
        generateAdminInsights(
            enrollmentData,
            coursePopularityData,
            userAcquisitionData,
            certificateData
        );

    } catch (error) {
        console.error('Failed to load admin analytics:', error);
        showError('Failed to load analytics data. Please try again.');
    }
}

/**
 * Update platform overview statistics
 */
function updatePlatformStats(enrollmentData, courseData, userData, certificateData) {
    const totalEnrollments = enrollmentData.total_enrollments || 0;
    const completionRate = enrollmentData.completion_rate || 0;
    const totalCourses = courseData.total_courses || courseData.courses?.length || 0;
    const totalUsers = userData.total_new_users || userData.total_users || 0;
    const totalCertificates = certificateData.total_certificates || 0;
    const avgScore = courseData.platform_avg_score || 0;

    Utils.animateCounter('total-users', totalUsers, 0);
    Utils.animateCounter('total-courses', totalCourses, 0);
    Utils.animateCounter('total-enrollments', totalEnrollments, 0);
    Utils.animateCounter('total-certificates', totalCertificates, 0);
    Utils.animateCounter('completion-rate', completionRate, 1, '%');
    Utils.animateCounter('avg-platform-score', avgScore, 1, '%');
}

/**
 * Get the chart container element for a given canvas ID.
 * Uses data-chart attribute for reliable lookup even after the canvas has been replaced.
 */
function getChartContainer(canvasId) {
    return document.querySelector(`.chart-container[data-chart="${canvasId}"]`);
}

/**
 * Render enrollment trends line chart
 */
function renderEnrollmentTrends(data) {
    const trends = data.trends || [];
    const container = getChartContainer('enrollmentTrendsChart');

    if (trends.length === 0) {
        if (container) container.innerHTML =
            '<div class="no-data-message">No enrollment data available for this period.</div>';
        return;
    }

    const labels = trends.map(t => Utils.formatDateLabel(t.period));
    const enrollmentCounts = trends.map(t => t.enrollments_count || t.enrollment_count || 0);
    const completionRates = trends.map(t => {
        if (t.completion_rate !== undefined) return t.completion_rate;
        if (t.avg_progress !== undefined) return parseFloat(t.avg_progress);
        const total = parseInt(t.enrollments_count || t.enrollment_count || 0);
        const completed = parseInt(t.completed_count || 0);
        return total > 0 ? (completed / total) * 100 : 0;
    });

    const chartData = {
        labels: labels,
        datasets: [
            {
                label: 'New Enrollments',
                data: enrollmentCounts,
                borderColor: '#4B6EFB',
                backgroundColor: 'rgba(75, 110, 251, 0.1)',
                tension: 0.4,
                fill: true,
                yAxisID: 'y'
            },
            {
                label: 'Completion Rate (%)',
                data: completionRates,
                borderColor: '#4BFB9D',
                backgroundColor: 'rgba(75, 251, 157, 0.1)',
                tension: 0.4,
                fill: true,
                yAxisID: 'y1'
            }
        ]
    };

    const options = {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: 'index',
            intersect: false
        },
        plugins: {
            legend: {
                position: 'top'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.dataset.label || '';
                        if (label) {
                            label += ': ';
                        }
                        if (context.parsed.y !== null) {
                            if (context.datasetIndex === 1) {
                                label += context.parsed.y.toFixed(1) + '%';
                            } else {
                                label += context.parsed.y;
                            }
                        }
                        return label;
                    }
                }
            }
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Enrollments'
                },
                beginAtZero: true
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Completion Rate (%)'
                },
                grid: {
                    drawOnChartArea: false
                },
                beginAtZero: true,
                max: 100
            }
        }
    };

    Charts.createLineChart('enrollmentTrendsChart', chartData, options);
}

/**
 * Render course popularity ranking list
 */
function renderCoursePopularity(data) {
    const courses = data.courses || [];
    const container = document.getElementById('course-popularity-container');

    if (courses.length === 0) {
        container.innerHTML = '<div class="no-data-message">No course data available.</div>';
        return;
    }

    // Sort by enrollment count (handle both column names from view)
    const getEnrollCount = (c) => parseInt(c.total_enrollments || c.enrollment_count || 0);
    const sortedCourses = courses.sort((a, b) => getEnrollCount(b) - getEnrollCount(a));
    const topCourses = sortedCourses.slice(0, 10);

    container.innerHTML = `
        <ul class="course-ranking-list">
            ${topCourses.map((course, index) => {
                const rank = index + 1;
                const rankClass = rank === 1 ? 'top-1' : rank === 2 ? 'top-2' : rank === 3 ? 'top-3' : '';
                const enrollCount = getEnrollCount(course);
                const completionRate = parseFloat(course.completion_rate || course.avg_progress_percentage || 0);
                const avgScore = parseFloat(course.avg_quiz_score || 0);

                return `
                    <li class="course-ranking-item">
                        <div class="course-rank ${rankClass}">
                            ${rank === 1 ? '🥇' : rank === 2 ? '🥈' : rank === 3 ? '🥉' : rank}
                        </div>
                        <div class="course-info">
                            <h4 class="course-title">${Utils.escapeHtml(course.course_title)}</h4>
                            <div class="course-stats">
                                <span>👥 ${enrollCount} enrolled</span>
                                <span>✅ ${completionRate.toFixed(1)}% complete</span>
                                <span>📊 ${avgScore.toFixed(1)}% avg score</span>
                            </div>
                        </div>
                        <div class="course-metric">
                            ${enrollCount}
                        </div>
                    </li>
                `;
            }).join('')}
        </ul>
    `;

    // Animate ranking items
    gsap.from('.course-ranking-item', {
        opacity: 0,
        x: -30,
        duration: 0.5,
        stagger: 0.08,
        ease: 'power2.out'
    });
}

/**
 * Render user acquisition trends
 */
function renderUserAcquisition(data) {
    const trends = data.trends || [];

    if (trends.length === 0) {
        const container = getChartContainer('userAcquisitionChart');
        if (container) container.innerHTML =
            '<div class="no-data-message">No user acquisition data available.</div>';
        return;
    }

    const labels = trends.map(t => Utils.formatDateLabel(t.period));

    // Model returns columns: students_count, instructors_count, admins_count per period
    const roleConfig = [
        { key: 'students_count', label: 'Students', border: '#4B6EFB', bg: 'rgba(75, 110, 251, 0.5)' },
        { key: 'instructors_count', label: 'Instructors', border: '#6E4BFB', bg: 'rgba(110, 75, 251, 0.5)' },
        { key: 'admins_count', label: 'Admins', border: '#FB4B4B', bg: 'rgba(251, 75, 75, 0.5)' }
    ];

    const datasets = roleConfig.map(role => ({
        label: role.label,
        data: trends.map(t => parseInt(t[role.key] || 0)),
        borderColor: role.border,
        backgroundColor: role.bg,
        tension: 0.4,
        fill: false
    }));

    const chartData = {
        labels: labels,
        datasets: datasets
    };

    const options = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'New Users'
                }
            }
        }
    };

    Charts.createMultiLineChart('userAcquisitionChart', datasets, labels, options);
}

/**
 * Render achievement distribution
 */
function renderAchievementDistribution(data) {
    const achievements = data.achievements || [];
    const container = document.getElementById('achievement-distribution-container');

    if (achievements.length === 0) {
        container.innerHTML = '<div class="no-data-message">No achievements have been earned yet.</div>';
        return;
    }

    // Sort by count and take top 12
    const topAchievements = achievements
        .sort((a, b) => (parseInt(b.unlock_count || b.earned_count || 0)) - (parseInt(a.unlock_count || a.earned_count || 0)))
        .slice(0, 12);

    container.innerHTML = `
        <div class="achievement-grid">
            ${topAchievements.map(achievement => {
                const name = achievement.achievement_title || achievement.achievement_name || 'Achievement';
                const count = parseInt(achievement.unlock_count || achievement.earned_count || 0);
                return `
                <div class="achievement-item">
                    <div class="achievement-icon">${getAchievementIcon(name)}</div>
                    <div class="achievement-name">${Utils.escapeHtml(name)}</div>
                    <div class="achievement-count">${count}</div>
                </div>
                `;
            }).join('')}
        </div>
    `;

    // Animate achievement items
    gsap.from('.achievement-item', {
        opacity: 0,
        scale: 0.8,
        duration: 0.5,
        stagger: 0.05,
        ease: 'back.out(1.7)'
    });
}

/**
 * Render platform usage heatmap
 */
function renderPlatformUsage(data) {
    const usageData = data.usage_by_hour || data.heatmap_data || [];

    if (usageData.length === 0) {
        const container = getChartContainer('platformUsageChart');
        if (container) container.innerHTML =
            '<div class="no-data-message">No usage data available.</div>';
        return;
    }

    // Group by hour of day
    const hourlyActivity = new Array(24).fill(0);
    usageData.forEach(item => {
        const hour = parseInt(item.hour_of_day);
        hourlyActivity[hour] = item.activity_count;
    });

    const labels = hourlyActivity.map((_, index) => {
        const hour = index % 12 === 0 ? 12 : index % 12;
        const period = index < 12 ? 'AM' : 'PM';
        return `${hour}${period}`;
    });

    const chartData = {
        labels: labels,
        datasets: [{
            label: 'Active Users',
            data: hourlyActivity,
            backgroundColor: hourlyActivity.map(value => {
                const maxValue = Math.max(...hourlyActivity);
                const intensity = maxValue > 0 ? value / maxValue : 0;
                return `rgba(75, 110, 251, ${0.3 + intensity * 0.7})`;
            }),
            borderColor: '#4B6EFB',
            borderWidth: 2,
            borderRadius: 6
        }]
    };

    const options = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return `${context.parsed.y} active users`;
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Activity Count'
                }
            },
            x: {
                title: {
                    display: true,
                    text: 'Time of Day'
                }
            }
        }
    };

    Charts.createBarChart('platformUsageChart', chartData, options);
}

/**
 * Render certificate issuance trends
 */
function renderCertificateTrends(data) {
    const trends = data.trends || [];

    if (trends.length === 0) {
        const container = getChartContainer('certificateTrendsChart');
        if (container) container.innerHTML =
            '<div class="no-data-message">No certificates issued in this period.</div>';
        return;
    }

    const labels = trends.map(t => Utils.formatDateLabel(t.period || t.issue_date_day || t.issue_month));
    const counts = trends.map(t => parseInt(t.certificate_count || t.certificates_issued || 0));

    const chartData = {
        labels: labels,
        datasets: [{
            label: 'Certificates Issued',
            data: counts,
            borderColor: '#6E4BFB',
            backgroundColor: 'rgba(110, 75, 251, 0.2)',
            tension: 0.4,
            fill: true
        }]
    };

    const options = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Certificates'
                }
            }
        }
    };

    Charts.createAreaChart('certificateTrendsChart', chartData, options);
}

/**
 * Generate admin insights
 */
function generateAdminInsights(enrollmentData, courseData, userData, certificateData) {
    const insights = [];

    // Enrollment growth insights
    const trends = enrollmentData.trends || [];
    if (trends.length >= 2) {
        const recentEnrollments = trends[trends.length - 1].enrollments_count || trends[trends.length - 1].enrollment_count || 0;
        const previousEnrollments = trends[trends.length - 2].enrollments_count || trends[trends.length - 2].enrollment_count || 0;
        const growthRate = previousEnrollments > 0
            ? ((recentEnrollments - previousEnrollments) / previousEnrollments) * 100
            : 0;

        if (growthRate > 10) {
            insights.push({
                type: 'success',
                icon: '📈',
                title: 'Strong Enrollment Growth',
                message: `Enrollments increased by ${growthRate.toFixed(1)}% compared to the previous period. Platform is gaining traction!`
            });
        } else if (growthRate < -10) {
            insights.push({
                type: 'warning',
                icon: '📉',
                title: 'Enrollment Decline Detected',
                message: `Enrollments decreased by ${Math.abs(growthRate).toFixed(1)}%. Consider launching marketing campaigns or new courses.`
            });
        }
    }

    // Completion rate insights
    const completionRate = enrollmentData.completion_rate || 0;
    if (completionRate >= 70) {
        insights.push({
            type: 'success',
            icon: '🎯',
            title: 'Excellent Completion Rate',
            message: `${completionRate.toFixed(1)}% of enrolled students complete courses. Your content is highly engaging!`
        });
    } else if (completionRate < 40) {
        insights.push({
            type: 'warning',
            icon: '⚠️',
            title: 'Low Completion Rate',
            message: `Only ${completionRate.toFixed(1)}% of students complete courses. Review course difficulty, pacing, and engagement strategies.`
        });
    }

    // Course popularity insights
    const courses = courseData.courses || [];
    if (courses.length > 0) {
        const getCount = (c) => parseInt(c.total_enrollments || c.enrollment_count || 0);
        const topCourse = courses.reduce((prev, current) =>
            (getCount(prev) > getCount(current)) ? prev : current
        );

        insights.push({
            type: 'info',
            icon: '🏆',
            title: 'Most Popular Course',
            message: `"${topCourse.course_title}" leads with ${getCount(topCourse)} enrollments. Consider creating similar content.`
        });
    }

    // Certificate insights
    const totalCertificates = certificateData.total_certificates || 0;
    const totalEnrollments = enrollmentData.total_enrollments || 1;
    const certificationRate = (totalCertificates / totalEnrollments) * 100;

    if (certificationRate >= 60) {
        insights.push({
            type: 'success',
            icon: '📜',
            title: 'High Certification Rate',
            message: `${certificationRate.toFixed(1)}% of enrollments result in certificates. Students are successfully completing courses!`
        });
    }

    // User acquisition insights
    const activationRate = userData.activation_rate || 0;
    if (activationRate < 50) {
        insights.push({
            type: 'info',
            icon: '💡',
            title: 'Improve User Activation',
            message: `Only ${activationRate.toFixed(1)}% of new users are fully activated. Enhance onboarding flow and first-time user experience.`
        });
    }

    renderInsights(insights);
}

/**
 * Render insights cards
 */
function renderInsights(insights) {
    const container = document.getElementById('insights-container');

    if (insights.length === 0) {
        container.innerHTML = '';
        return;
    }

    container.innerHTML = `
        <h3 style="margin: 2rem 0 1rem 0;">💡 Platform Insights & Recommendations</h3>
        <div style="display: grid; gap: 1rem;">
            ${insights.map(insight => `
                <div class="insight-card insight-${insight.type}">
                    <div class="insight-icon">${insight.icon}</div>
                    <div class="insight-content">
                        <h4>${insight.title}</h4>
                        <p>${insight.message}</p>
                    </div>
                </div>
            `).join('')}
        </div>
    `;

    gsap.from('.insight-card', {
        opacity: 0,
        y: 20,
        duration: 0.5,
        stagger: 0.1,
        ease: 'power2.out'
    });
}

// Utility Functions

// formatDateLabel moved to Utils.js (Phase 11 refactoring)

function getAchievementIcon(name) {
    const iconMap = {
        'First Steps': '👶',
        'Quick Learner': '⚡',
        'Dedicated Student': '📚',
        'Perfect Score': '💯',
        'Course Champion': '🏆',
        'Quiz Master': '🎯',
        'Early Bird': '🌅',
        'Night Owl': '🦉',
        'Week Warrior': '💪',
        'Month Master': '📅',
        'Social Learner': '👥',
        'Solo Achiever': '🥇'
    };

    // Try exact match first
    if (iconMap[name]) {
        return iconMap[name];
    }

    // Try partial match
    for (const key in iconMap) {
        if (name.toLowerCase().includes(key.toLowerCase())) {
            return iconMap[key];
        }
    }

    // Default icon
    return '🏅';
}

// animateCounter moved to Utils.js (Phase 11 refactoring)

// escapeHtml moved to Utils.js (Phase 11 refactoring)

function showError(message) {
    const container = document.querySelector('.analytics-charts-grid');
    container.innerHTML = `
        <div class="error-message" style="grid-column: 1 / -1;">
            ❌ ${Utils.escapeHtml(message)}
        </div>
    `;
}

// ============================================================
// School Filter + School Detail (superadmin only)
// ============================================================

async function initializeSchoolFilter() {
    const wrap = document.getElementById('school-filter-wrap');
    const select = document.getElementById('school-filter-select');
    if (!wrap || !select) return;

    try {
        const response = await API.get('/schools?has_users=1');
        const schools = (response && response.data && response.data.schools) || [];
        SchoolFilter.schools = schools;

        // Sort by organization_name then name for a predictable order
        schools.sort((a, b) => {
            const oa = (a.organization_name || '').toLowerCase();
            const ob = (b.organization_name || '').toLowerCase();
            if (oa !== ob) return oa.localeCompare(ob);
            return (a.name || '').localeCompare(b.name || '');
        });

        schools.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.id;
            const orgLabel = s.organization_name ? ` — ${s.organization_name}` : '';
            opt.textContent = `${s.name}${orgLabel}`;
            select.appendChild(opt);
        });

        wrap.style.display = '';

        select.addEventListener('change', () => {
            SchoolFilter.selectedId = select.value;
            loadAdminAnalytics();
        });

        // Bind school-detail controls once
        const searchInput = document.getElementById('school-students-search');
        const sortSelect = document.getElementById('school-students-sort');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                clearTimeout(SchoolDetail.searchDebounce);
                SchoolDetail.searchDebounce = setTimeout(() => {
                    SchoolDetail.search = searchInput.value.trim();
                    SchoolDetail.page = 1;
                    if (SchoolDetail.schoolId) loadSchoolDetail();
                }, 300);
            });
        }
        if (sortSelect) {
            sortSelect.addEventListener('change', () => {
                SchoolDetail.sort = sortSelect.value;
                SchoolDetail.page = 1;
                if (SchoolDetail.schoolId) loadSchoolDetail();
            });
        }
    } catch (error) {
        console.error('Failed to load schools:', error);
    }
}

async function loadSchoolDetail() {
    const section = document.getElementById('school-detail-section');
    if (!section || !SchoolDetail.schoolId) return;

    section.style.display = '';

    const tableWrap = document.getElementById('school-students-table-wrap');
    if (tableWrap) tableWrap.innerHTML = '<div class="loading-spinner">Loading students…</div>';

    const qs = new URLSearchParams({
        page: SchoolDetail.page,
        per_page: SchoolDetail.perPage,
        sort: SchoolDetail.sort
    });
    if (SchoolDetail.search) qs.set('search', SchoolDetail.search);

    try {
        const response = await API.get(`/analytics/admin/school/${SchoolDetail.schoolId}/overview?${qs.toString()}`);
        if (!response || !response.success) {
            throw new Error((response && response.message) || 'Failed to load school detail');
        }
        renderSchoolDetail(response.data);
    } catch (error) {
        console.error('Failed to load school detail:', error);
        if (tableWrap) {
            tableWrap.innerHTML = `<div class="error-message">❌ ${Utils.escapeHtml(error.message || 'Failed to load')}</div>`;
        }
    }
}

function renderSchoolDetail(data) {
    const school = data.school || {};
    const stats = data.stats || {};
    const students = (data.students && data.students.data) || [];
    const pagination = (data.students && data.students.pagination) || { page: 1, total_pages: 1, total: 0 };

    document.getElementById('school-detail-title').textContent = `🏫 ${school.name || 'School'}`;

    const metaParts = [];
    if (school.organization_name) metaParts.push(`<strong>${Utils.escapeHtml(school.organization_name)}</strong>`);
    if (school.city) metaParts.push(Utils.escapeHtml(school.city));
    if (school.school_type) metaParts.push(Utils.escapeHtml(school.school_type));
    document.getElementById('school-detail-meta').innerHTML = metaParts.join(' · ');

    const statsGrid = document.getElementById('school-detail-stats');
    const studentCount = parseInt(stats.total_students || school.total_students || 0);
    const active30 = parseInt(stats.active_30d || 0);
    const active7 = parseInt(stats.active_7d || 0);
    const avgProgress = parseFloat(stats.avg_progress || 0);
    const avgQuiz = parseFloat(stats.avg_quiz_score || 0);
    const certificates = parseInt(stats.total_certificates || 0);
    const recent = parseInt(stats.recent_signups || 0);
    const completed = parseInt(stats.completed_courses || 0);

    statsGrid.innerHTML = `
        ${renderStatCard('👥', studentCount, 'Students')}
        ${renderStatCard('🟢', active30, 'Active (30d)')}
        ${renderStatCard('⚡', active7, 'Active (7d)')}
        ${renderStatCard('📈', avgProgress.toFixed(1) + '%', 'Avg Progress')}
        ${renderStatCard('📊', avgQuiz.toFixed(1) + '%', 'Avg Quiz Score')}
        ${renderStatCard('✅', completed, 'Completed Courses')}
        ${renderStatCard('📜', certificates, 'Certificates')}
        ${renderStatCard('🆕', recent, 'Signups (30d)')}
    `;

    const tableWrap = document.getElementById('school-students-table-wrap');
    if (students.length === 0) {
        tableWrap.innerHTML = '<div class="no-data-message">No students match this filter.</div>';
        document.getElementById('school-students-pagination').innerHTML = '';
        return;
    }

    const rows = students.map(s => {
        const name = Utils.escapeHtml(s.name || '(no name)');
        const email = Utils.escapeHtml(s.email || '');
        const progress = parseFloat(s.avg_progress || 0);
        const progressWidth = Math.max(0, Math.min(100, progress));
        const lastLogin = formatLastLogin(s.last_login_at);
        const lastLoginBadge = lastLoginBadgeClass(s.last_login_at);
        const enrolled = parseInt(s.courses_enrolled || 0);
        const certs = parseInt(s.certificates_earned || 0);

        return `
            <tr>
                <td>
                    <div style="font-weight:600;">${name}</div>
                    <div style="font-size:0.8rem; color:#777;">${email}</div>
                </td>
                <td><span class="school-students-badge ${lastLoginBadge.cls}">${lastLoginBadge.label}</span>
                    <div style="font-size:0.75rem; color:#888; margin-top:2px;">${Utils.escapeHtml(lastLogin)}</div></td>
                <td>${enrolled}</td>
                <td class="progress-cell">
                    <div>${progress.toFixed(1)}%</div>
                    <div class="school-students-progress-bar"><div style="width:${progressWidth}%"></div></div>
                </td>
                <td>${certs}</td>
            </tr>
        `;
    }).join('');

    tableWrap.innerHTML = `
        <table class="school-students-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Last Login</th>
                    <th>Courses</th>
                    <th>Progress</th>
                    <th>Certs</th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
        </table>
    `;

    renderSchoolPagination(pagination);
}

function renderStatCard(icon, value, label) {
    return `
        <div class="platform-stat-card">
            <div class="platform-stat-icon">${icon}</div>
            <div class="platform-stat-value">${value}</div>
            <div class="platform-stat-label">${label}</div>
        </div>
    `;
}

function renderSchoolPagination(pagination) {
    const el = document.getElementById('school-students-pagination');
    if (!el) return;
    const { page, total_pages, total } = pagination;
    if (total_pages <= 1) {
        el.innerHTML = `<span style="color:#888; font-size:0.85rem;">${total} student${total === 1 ? '' : 's'}</span>`;
        return;
    }

    el.innerHTML = `
        <span style="color:#888; font-size:0.85rem; margin-right:auto;">
            ${total} student${total === 1 ? '' : 's'}
        </span>
        <button class="school-pagination-btn" ${page <= 1 ? 'disabled' : ''} data-page="${page - 1}">◀ Prev</button>
        <span style="font-size:0.85rem;">Page ${page} of ${total_pages}</span>
        <button class="school-pagination-btn" ${page >= total_pages ? 'disabled' : ''} data-page="${page + 1}">Next ▶</button>
    `;

    el.querySelectorAll('button[data-page]').forEach(btn => {
        btn.addEventListener('click', () => {
            const next = parseInt(btn.dataset.page);
            if (!isNaN(next) && next >= 1) {
                SchoolDetail.page = next;
                loadSchoolDetail();
            }
        });
    });
}

function formatLastLogin(ts) {
    if (!ts) return 'Never logged in';
    const date = new Date(ts.replace(' ', 'T') + 'Z');
    if (isNaN(date.getTime())) return ts;
    return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

function lastLoginBadgeClass(ts) {
    if (!ts) return { cls: 'never', label: 'Never' };
    const date = new Date(ts.replace(' ', 'T') + 'Z');
    if (isNaN(date.getTime())) return { cls: 'never', label: 'Unknown' };
    const days = (Date.now() - date.getTime()) / (1000 * 60 * 60 * 24);
    if (days <= 7) return { cls: 'active', label: 'Active' };
    if (days <= 30) return { cls: 'active', label: 'Recent' };
    if (days <= 90) return { cls: 'stale', label: 'Stale' };
    return { cls: 'never', label: 'Dormant' };
}

// ============================================================
// Schools Overview (All-Schools card grid + modal)
// ============================================================

async function loadSchoolsOverview() {
    const grid = document.getElementById('schools-grid');
    if (!grid) return;
    grid.innerHTML = '<div class="loading-spinner">Loading schools…</div>';

    // Bind delegated click/keydown handlers once — survives grid re-renders.
    if (!grid.dataset.handlersBound) {
        grid.addEventListener('click', (e) => {
            const card = e.target.closest('.school-card');
            if (!card || !grid.contains(card)) return;
            const id = parseInt(card.dataset.schoolId, 10);
            if (!id) return;
            openSchoolStatsModal(id, card.dataset.schoolName || '');
        });
        grid.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            const card = e.target.closest('.school-card');
            if (!card || !grid.contains(card)) return;
            e.preventDefault();
            const id = parseInt(card.dataset.schoolId, 10);
            if (!id) return;
            openSchoolStatsModal(id, card.dataset.schoolName || '');
        });
        grid.dataset.handlersBound = '1';
    }

    try {
        const res = await API.get('/analytics/admin/schools-overview');
        const schools = (res.data && res.data.schools) || [];
        SchoolsList.schools = schools;
        SchoolsList.loaded = true;
        renderSchoolsGrid(schools);
    } catch (err) {
        console.error('Failed to load schools overview:', err);
        grid.innerHTML = '<div class="error-message">Failed to load schools.</div>';
    }
}

function renderSchoolsGrid(schools) {
    const grid = document.getElementById('schools-grid');
    if (!grid) return;
    if (!schools.length) {
        grid.innerHTML = '<div class="empty-state">No schools found.</div>';
        return;
    }
    grid.innerHTML = schools.map(s => {
        const name = Utils.escapeHtml(s.name || '—');
        const org = Utils.escapeHtml(s.organization_name || '');
        const progress = Number(s.avg_progress || 0).toFixed(1);
        return `
            <div class="school-card" data-school-id="${s.id}" data-school-name="${name}" role="button" tabindex="0">
                <div class="school-card-name">${name}</div>
                <div class="school-card-org">${org}</div>
                <div class="school-card-stats">
                    <div class="school-card-stat"><strong>${s.total_students || 0}</strong>Students</div>
                    <div class="school-card-stat"><strong>${s.total_teachers || 0}</strong>Teachers</div>
                    <div class="school-card-stat"><strong>${s.total_admins || 0}</strong>Admins</div>
                    <div class="school-card-stat"><strong>${progress}%</strong>Avg Progress</div>
                </div>
                <div class="school-card-footer">
                    <i class="fas fa-user-clock"></i> ${s.active_30d || 0} active in last 30 days
                </div>
            </div>
        `;
    }).join('');
}

async function openSchoolStatsModal(schoolId, schoolName) {
    const modal = document.getElementById('school-stats-modal');
    const titleEl = document.getElementById('school-stats-modal-title');
    const body = document.getElementById('school-stats-modal-body');
    if (!modal || !body) return;

    if (titleEl) titleEl.textContent = schoolName || 'School Stats';
    body.innerHTML = '<div class="loading-spinner">Loading…</div>';
    modal.classList.add('active');

    try {
        const res = await API.get(`/analytics/admin/school/${schoolId}/overview?stats_only=1`);
        renderSchoolStatsModal(res.data || {});
    } catch (err) {
        console.error('Failed to load school stats:', err);
        body.innerHTML = '<div class="error-message">Failed to load school stats.</div>';
    }
}

function renderSchoolStatsModal(data) {
    const body = document.getElementById('school-stats-modal-body');
    if (!body) return;
    const school = data.school || {};
    const stats = data.stats || {};

    const totalStudents = stats.total_students || 0;
    const totalTeachers = stats.total_teachers || 0;
    const totalAdmins = stats.total_school_admins || 0;
    const totalUsers = stats.total_users || 0;
    const avgProgress = Number(stats.avg_progress || 0).toFixed(1);
    const completedCourses = stats.completed_courses || 0;
    const totalEnrollments = stats.total_enrollments || 0;
    const totalCertificates = stats.total_certificates || 0;
    const active30d = stats.active_30d || 0;
    const active7d = stats.active_7d || 0;
    const avgQuiz = Number(stats.avg_quiz_score || 0).toFixed(1);
    const quizAttempts = stats.total_quiz_attempts || 0;
    const recentSignups = stats.recent_signups || 0;

    const org = school.organization_name ? Utils.escapeHtml(school.organization_name) : '';

    body.innerHTML = `
        ${org ? `<p style="margin: 0 0 1rem 0; color: #6b7280; font-size: 0.9rem;"><i class="fas fa-building"></i> ${org}</p>` : ''}

        <h3 style="margin: 0 0 0.5rem 0; font-size: 0.95rem; color: #374151;">Users</h3>
        <div class="school-stats-modal-grid">
            <div class="school-stats-modal-stat">
                <div class="value">${totalStudents}</div>
                <div class="label">Students</div>
            </div>
            <div class="school-stats-modal-stat">
                <div class="value">${totalTeachers}</div>
                <div class="label">Teachers</div>
            </div>
            <div class="school-stats-modal-stat">
                <div class="value">${totalAdmins}</div>
                <div class="label">Admins</div>
            </div>
            <div class="school-stats-modal-stat">
                <div class="value">${totalUsers}</div>
                <div class="label">Total Users</div>
            </div>
        </div>

        <h3 style="margin: 1rem 0 0.5rem 0; font-size: 0.95rem; color: #374151;">Overall Progress</h3>
        <div class="school-stats-modal-progress-wrap">
            <div class="school-stats-modal-progress-label">
                <span>Average progress across all enrollments</span>
                <span><strong>${avgProgress}%</strong></span>
            </div>
            <div class="school-stats-modal-progress-bar">
                <div class="school-stats-modal-progress-fill" style="width: ${Math.min(100, Math.max(0, parseFloat(avgProgress)))}%;"></div>
            </div>
        </div>

        <h3 style="margin: 1rem 0 0.5rem 0; font-size: 0.95rem; color: #374151;">Activity & Outcomes</h3>
        <div class="school-stats-modal-grid">
            <div class="school-stats-modal-stat">
                <div class="value">${active30d}</div>
                <div class="label">Active (30d)</div>
            </div>
            <div class="school-stats-modal-stat">
                <div class="value">${active7d}</div>
                <div class="label">Active (7d)</div>
            </div>
            <div class="school-stats-modal-stat">
                <div class="value">${totalEnrollments}</div>
                <div class="label">Enrollments</div>
            </div>
            <div class="school-stats-modal-stat">
                <div class="value">${completedCourses}</div>
                <div class="label">Completed</div>
            </div>
            <div class="school-stats-modal-stat">
                <div class="value">${totalCertificates}</div>
                <div class="label">Certificates</div>
            </div>
            <div class="school-stats-modal-stat">
                <div class="value">${avgQuiz}%</div>
                <div class="label">Avg Quiz</div>
            </div>
            <div class="school-stats-modal-stat">
                <div class="value">${quizAttempts}</div>
                <div class="label">Quiz Attempts</div>
            </div>
            <div class="school-stats-modal-stat">
                <div class="value">${recentSignups}</div>
                <div class="label">Signups (30d)</div>
            </div>
        </div>
    `;
}

function closeSchoolStatsModal() {
    const modal = document.getElementById('school-stats-modal');
    if (modal) modal.classList.remove('active');
}

// Expose for inline onclick handlers
window.closeSchoolStatsModal = closeSchoolStatsModal;
