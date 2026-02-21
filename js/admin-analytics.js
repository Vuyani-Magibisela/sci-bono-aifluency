/**
 * Admin Analytics Dashboard
 * Platform-wide metrics, enrollment trends, course popularity, user acquisition, and certificates
 */

// State management
let chartInstances = {};

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
    const filterParams = AnalyticsFilters.getFilterParams();
    const loadingMessage = '<div class="loading-spinner">Loading analytics data...</div>';

    try {
        // Show loading states
        document.getElementById('enrollmentTrendsChart').parentElement.innerHTML =
            '<canvas id="enrollmentTrendsChart"></canvas>';
        document.getElementById('userAcquisitionChart').parentElement.innerHTML =
            '<canvas id="userAcquisitionChart"></canvas>';
        document.getElementById('platformUsageChart').parentElement.innerHTML =
            '<canvas id="platformUsageChart"></canvas>';
        document.getElementById('certificateTrendsChart').parentElement.innerHTML =
            '<canvas id="certificateTrendsChart"></canvas>';
        document.getElementById('course-popularity-container').innerHTML = loadingMessage;
        document.getElementById('achievement-distribution-container').innerHTML = loadingMessage;

        // Fetch all data in parallel
        // API.get() returns {success, data}, unwrap .data from each response
        const [
            enrollmentData,
            coursePopularityData,
            userAcquisitionData,
            achievementData,
            platformUsageData,
            certificateData
        ] = (await Promise.all([
            API.get(`/analytics/admin/enrollment-trends?${filterParams}`),
            API.get(`/analytics/admin/course-popularity?${filterParams}`),
            API.get(`/analytics/admin/user-acquisition?${filterParams}`),
            API.get(`/analytics/admin/achievement-distribution?${filterParams}`),
            API.get(`/analytics/admin/platform-usage?${filterParams}`),
            API.get(`/analytics/admin/certificate-trends?${filterParams}`)
        ])).map(r => r.data || {});

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
 * Render enrollment trends line chart
 */
function renderEnrollmentTrends(data) {
    const trends = data.trends || [];

    if (trends.length === 0) {
        document.getElementById('enrollmentTrendsChart').parentElement.innerHTML =
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
        document.getElementById('userAcquisitionChart').parentElement.innerHTML =
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
        document.getElementById('platformUsageChart').parentElement.innerHTML =
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
        document.getElementById('certificateTrendsChart').parentElement.innerHTML =
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
