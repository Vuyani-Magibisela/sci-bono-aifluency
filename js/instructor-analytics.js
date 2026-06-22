/**
 * Instructor Analytics Dashboard
 * Displays class performance, engagement, question effectiveness, at-risk students, and grading workload
 */

// State management
let currentCourseId = null;
let chartInstances = {};

// Initialize dashboard
document.addEventListener('DOMContentLoaded', async () => {
    // Check authentication and role
    if (!Auth.isAuthenticated()) {
        window.location.href = 'login.html';
        return;
    }

    const user = Auth.getUser();
    if (!Auth.canManageContent()) {
        alert('Access denied. This page is for content managers only.');
        window.location.href = 'student-dashboard.html';
        return;
    }

    // Initialize filters
    await initializeFilters();

    // Animate page entrance
    gsap.from('.analytics-header', {
        opacity: 0,
        y: -30,
        duration: 0.8,
        ease: 'power3.out'
    });

    gsap.from('.analytics-summary-cards .stat-card', {
        opacity: 0,
        y: 30,
        duration: 0.6,
        stagger: 0.1,
        delay: 0.2,
        ease: 'power3.out'
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
 * Initialize filters with course selection
 */
async function initializeFilters() {
    try {
        const user = Auth.getUser();

        if (!user || !user.primary_school_id) {
            document.getElementById('filters-container').innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-school"></i></div>
                    <h3>Not assigned to a school</h3>
                    <p>Analytics are scoped to your assigned school. Ask a SuperAdmin to assign you via <strong>Admin &rarr; Users</strong>.</p>
                </div>
            `;
            hideAnalyticsSections();
            return;
        }

        // Fetch courses visible to this teacher's school (published only, school-scoped)
        const coursesResponse = await API.get(`/courses?school_id=${user.primary_school_id}&published=true`);
        const raw = coursesResponse.data || {};
        const courses = Array.isArray(raw) ? raw : (raw.items || raw.courses || raw.data || []);

        if (courses.length === 0) {
            document.getElementById('filters-container').innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-chart-bar"></i></div>
                    <h3>No courses to analyse yet</h3>
                    <p>You are not yet listed as the instructor on any course for your school. Once an admin assigns you, analytics will appear here.</p>
                </div>
            `;
            hideAnalyticsSections();
            return;
        }

        // Set first course as default
        currentCourseId = courses[0].id;

        // Create filter bar
        AnalyticsFilters.createFilterBar('filters-container', {
            dateRange: true,
            course: true,
            courses: courses
        });

        // Add filter change callback
        AnalyticsFilters.addCallback(async (filters) => {
            currentCourseId = filters.courseId || courses[0].id;
            await loadInstructorAnalytics();
        });

        // Load initial data
        await loadInstructorAnalytics();

    } catch (error) {
        console.error('Failed to initialize filters:', error);
        document.getElementById('filters-container').innerHTML = `
            <div class="error-message">
                Failed to load courses: ${Utils.escapeHtml(error.message)}
            </div>
        `;
    }
}

/**
 * Load all instructor analytics data
 */
async function loadInstructorAnalytics() {
    if (!currentCourseId) {
        console.error('No course selected');
        return;
    }

    const filterParams = AnalyticsFilters.getFilterParams();
    const loadingMessage = '<div class="loading-spinner">Loading analytics data...</div>';
    const user = Auth.getUser();
    const schoolQuery = user && user.primary_school_id ? `&school_id=${user.primary_school_id}` : '';

    try {
        // Reset chart containers by stable ID. We can't go via the canvas's parentElement,
        // because a previous render with no data may have replaced the canvas with a
        // no-data <div> — getElementById('xxxChart') would then be null.
        resetChartContainer('classDistributionContainer', 'classDistributionChart');
        resetChartContainer('engagementContainer', 'engagementChart');
        resetChartContainer('questionEffectivenessContainer', 'questionEffectivenessChart');
        document.getElementById('at-risk-tbody').innerHTML =
            '<tr><td colspan="7" class="loading-spinner">Loading at-risk students...</td></tr>';
        document.getElementById('grading-workload-container').innerHTML = loadingMessage;

        // Fetch all analytics in parallel. Use allSettled so a single failing endpoint
        // (e.g., question-effectiveness when no quiz is selected) doesn't blank the page.
        const results = await Promise.allSettled([
            API.get(`/analytics/instructor/class/${currentCourseId}/distribution?${filterParams}${schoolQuery}`),
            API.get(`/analytics/instructor/engagement/${currentCourseId}?${filterParams}${schoolQuery}`),
            // Question-effectiveness is quiz-scoped; without a picked quiz we skip.
            Promise.resolve({ data: {} }),
            API.get(`/analytics/instructor/at-risk-students/${currentCourseId}?${filterParams}${schoolQuery}`),
            API.get(`/analytics/instructor/grading-workload?${filterParams}${schoolQuery}`)
        ]);

        const [distributionData, engagementData, questionData, atRiskData, gradingData] =
            results.map(r => (r.status === 'fulfilled' ? (r.value.data || {}) : {}));

        // Update summary cards
        updateSummaryCards(distributionData, atRiskData, gradingData, engagementData);

        // Render visualizations
        renderClassDistribution(distributionData);
        renderEngagementMetrics(engagementData);
        renderQuestionEffectiveness(questionData);
        renderAtRiskStudents(atRiskData);
        renderGradingWorkload(gradingData);

        // Generate insights
        generateInstructorInsights(distributionData, engagementData, atRiskData);

    } catch (error) {
        console.error('Failed to load instructor analytics:', error);
        showError('Failed to load analytics data. Please try again.');
    }
}

/**
 * Update summary cards with key metrics
 */
function updateSummaryCards(distributionData, atRiskData, gradingData, engagementData) {
    // total_enrolled comes straight from `enrollments` (school-scoped) and is the right
    // semantic for "Total Students" — every enrolled student, not just those with quiz
    // attempts (distribution.attempts) or lesson activity (engagement.total_students,
    // which depends on v_student_engagement having rows).
    let totalStudents = Number(distributionData?.total_enrolled);
    if (!Number.isFinite(totalStudents) || totalStudents === 0) {
        totalStudents = Number(engagementData?.total_students) || 0;
    }
    if (totalStudents === 0 && Array.isArray(distributionData?.attempts)) {
        const distinct = new Set(
            distributionData.attempts
                .map(a => a.user_id)
                .filter(id => id != null)
        );
        totalStudents = distinct.size;
    }
    const avgScore = distributionData.average_score || 0;
    const atRiskCount = atRiskData.at_risk_students?.length || 0;
    const pendingGrading = gradingData.total_pending || 0;

    Utils.animateCounter('total-students', totalStudents, 0);
    Utils.animateCounter('avg-class-score', avgScore, 1, '%');
    Utils.animateCounter('at-risk-count', atRiskCount, 0);
    Utils.animateCounter('pending-grading', pendingGrading, 0);
}

/**
 * Render class performance distribution histogram
 */
function renderClassDistribution(data) {
    const raw = data.distribution || [];
    // Backend returns an object map { '0-20%': 3, '21-40%': 5, ... } from
    // AdvancedAnalyticsController::getClassDistribution. Normalise to a stable
    // array of {score_range, student_count} regardless of which shape arrives.
    const RANGE_ORDER = ['0-20%', '21-40%', '41-60%', '61-80%', '81-100%'];
    let distribution;
    if (Array.isArray(raw)) {
        distribution = raw;
    } else {
        distribution = RANGE_ORDER
            .filter(r => Object.prototype.hasOwnProperty.call(raw, r))
            .map(r => ({ score_range: r, student_count: Number(raw[r]) || 0 }));
    }

    const totalCount = distribution.reduce((sum, d) => sum + (Number(d.student_count) || 0), 0);
    if (distribution.length === 0 || totalCount === 0) {
        document.getElementById('classDistributionContainer').innerHTML =
            '<div class="no-data-message">No performance data available for this course.</div>';
        return;
    }

    const labels = distribution.map(d => d.score_range);
    const values = distribution.map(d => d.student_count);

    // Colour bands match the actual range labels returned by the backend.
    const colorFor = (label) => {
        if (label === '81-100%') return { bg: 'rgba(75, 251, 157, 0.8)', border: '#4BFB9D' }; // Excellent
        if (label === '61-80%')  return { bg: 'rgba(75, 110, 251, 0.8)', border: '#4B6EFB' }; // Good
        if (label === '41-60%')  return { bg: 'rgba(110, 75, 251, 0.8)', border: '#6E4BFB' }; // Average
        if (label === '21-40%')  return { bg: 'rgba(255, 165, 0, 0.8)',  border: '#FFA500' }; // Below
        return { bg: 'rgba(251, 75, 75, 0.8)', border: '#FB4B4B' };                            // Poor
    };

    const chartData = {
        labels: labels,
        datasets: [{
            label: 'Quiz Attempts',
            data: values,
            backgroundColor: labels.map(l => colorFor(l).bg),
            borderColor: labels.map(l => colorFor(l).border),
            borderWidth: 2,
            borderRadius: 8
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
                        const percentage = ((context.parsed.y / totalCount) * 100).toFixed(1);
                        return `${context.parsed.y} attempts (${percentage}%)`;
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1,
                    callback: function(value) {
                        return Number.isInteger(value) ? value : '';
                    }
                },
                title: {
                    display: true,
                    text: 'Quiz Attempts'
                }
            },
            x: {
                title: {
                    display: true,
                    text: 'Score Range'
                }
            }
        }
    };

    Charts.createBarChart('classDistributionChart', chartData, options);
}

/**
 * Render student engagement metrics
 */
function renderEngagementMetrics(data) {
    const engagementData = data.engagement_data || [];

    if (engagementData.length === 0) {
        document.getElementById('engagementContainer').innerHTML =
            '<div class="no-data-message">No engagement data available.</div>';
        return;
    }

    // Calculate average engagement scores by category
    const avgTimeSpent = calculateAverage(engagementData, 'total_time_spent');
    const avgNotesCount = calculateAverage(engagementData, 'notes_count');
    const avgBookmarksCount = calculateAverage(engagementData, 'bookmarks_count');
    const avgCompletionRate = calculateAverage(engagementData, 'completion_rate');
    const avgEngagementScore = data.avg_engagement_score || 0;

    const chartData = {
        labels: [
            'Overall Engagement',
            'Completion Rate',
            'Time Investment',
            'Note Taking',
            'Bookmarking'
        ],
        datasets: [{
            label: 'Engagement Score',
            data: [
                avgEngagementScore,
                avgCompletionRate,
                Math.min(avgTimeSpent / 60, 100), // Convert minutes to score (cap at 100)
                Math.min(avgNotesCount * 10, 100), // Scale notes to 100
                Math.min(avgBookmarksCount * 20, 100) // Scale bookmarks to 100
            ],
            backgroundColor: 'rgba(75, 110, 251, 0.2)',
            borderColor: '#4B6EFB',
            borderWidth: 2,
            pointBackgroundColor: '#4B6EFB',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 5
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
            r: {
                beginAtZero: true,
                max: 100,
                ticks: {
                    stepSize: 20
                }
            }
        }
    };

    Charts.createRadarChart('engagementChart', chartData, options);
}

/**
 * Render question effectiveness scatter plot
 */
function renderQuestionEffectiveness(data) {
    const questions = data.questions || [];

    if (questions.length === 0) {
        document.getElementById('questionEffectivenessContainer').innerHTML =
            '<div class="no-data-message">No quiz questions found for this course.</div>';
        return;
    }

    // Create scatter plot data
    const scatterData = questions.map(q => ({
        x: q.difficulty_index * 100, // Convert to percentage
        y: q.discrimination_index * 100, // Convert to percentage
        label: q.question_text?.substring(0, 50) || 'Question',
        total_attempts: q.total_attempts
    }));

    const chartData = {
        datasets: [{
            label: 'Questions',
            data: scatterData,
            backgroundColor: scatterData.map(d => {
                // Color code by effectiveness quadrant
                if (d.x >= 50 && d.y >= 30) return 'rgba(75, 251, 157, 0.7)'; // Good difficulty, good discrimination
                if (d.x < 50 && d.y >= 30) return 'rgba(75, 110, 251, 0.7)'; // Easy, good discrimination
                if (d.x >= 50 && d.y < 30) return 'rgba(255, 165, 0, 0.7)'; // Hard, poor discrimination
                return 'rgba(251, 75, 75, 0.7)'; // Easy, poor discrimination
            }),
            borderColor: '#4B6EFB',
            borderWidth: 1,
            pointRadius: 8,
            pointHoverRadius: 10
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
                        const point = context.raw;
                        return [
                            `Difficulty: ${point.x.toFixed(1)}%`,
                            `Discrimination: ${point.y.toFixed(1)}%`,
                            `Attempts: ${point.total_attempts}`
                        ];
                    }
                }
            }
        },
        scales: {
            x: {
                title: {
                    display: true,
                    text: 'Difficulty Index (%)'
                },
                min: 0,
                max: 100
            },
            y: {
                title: {
                    display: true,
                    text: 'Discrimination Index (%)'
                },
                min: 0,
                max: 100
            }
        }
    };

    // Create chart using basic scatter approach
    const canvas = document.getElementById('questionEffectivenessChart');
    const ctx = canvas.getContext('2d');

    if (chartInstances['questionEffectivenessChart']) {
        chartInstances['questionEffectivenessChart'].destroy();
    }

    chartInstances['questionEffectivenessChart'] = new Chart(ctx, {
        type: 'scatter',
        data: chartData,
        options: options
    });

    Charts.animateChartEntrance(canvas);
}

/**
 * Render at-risk students table
 */
function renderAtRiskStudents(data) {
    const atRiskStudents = data.at_risk_students || [];
    const tbody = document.getElementById('at-risk-tbody');

    if (atRiskStudents.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="no-data-message">
                    ✅ Great news! No students are currently at risk.
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = atRiskStudents.map(student => {
        const riskLevel = getRiskLevel(student.risk_score);
        const lastActivity = student.last_activity_date
            ? Utils.formatDate(student.last_activity_date)
            : 'Never';

        return `
            <tr>
                <td>
                    <strong>${Utils.escapeHtml(student.student_name)}</strong><br>
                    <small style="color: #666;">${Utils.escapeHtml(student.email)}</small>
                </td>
                <td>
                    <span class="risk-badge ${riskLevel.class}">
                        ${riskLevel.label}
                    </span>
                </td>
                <td>
                    <strong>${student.risk_score.toFixed(0)}</strong>/100
                </td>
                <td>${lastActivity}</td>
                <td>
                    <div class="engagement-score-bar">
                        <div class="engagement-score-fill"
                             style="width: ${student.completion_rate}%"></div>
                    </div>
                    <small>${student.completion_rate.toFixed(0)}%</small>
                </td>
                <td>${student.quiz_avg_score?.toFixed(1) || 'N/A'}%</td>
                <td>
                    <button class="btn btn-sm" onclick="contactStudent('${student.email}')">
                        📧 Contact
                    </button>
                </td>
            </tr>
        `;
    }).join('');

    // Animate table rows
    gsap.from('.at-risk-table tbody tr', {
        opacity: 0,
        x: -20,
        duration: 0.5,
        stagger: 0.05,
        ease: 'power2.out'
    });
}

/**
 * Render grading workload overview
 */
function renderGradingWorkload(data) {
    const container = document.getElementById('grading-workload-container');
    const pendingProjects = Number(data.pending_projects) || 0;
    const pendingQuizzes = Number(data.pending_quizzes) || 0;
    const totalPending = Number(data.total_pending) || 0;
    const avgGradingHours = Number(data.avg_grading_time_hours) || 0;
    const recent = Array.isArray(data.recent_grading) ? data.recent_grading : [];

    if (totalPending === 0 && recent.length === 0) {
        container.innerHTML = '<div class="no-data-message">No grading tasks at this time.</div>';
        return;
    }

    const summaryHtml = `
        <div class="grading-workload-summary">
            <div class="grading-workload-stat">
                <span class="grading-workload-stat-label">Pending Projects</span>
                <span class="grading-workload-stat-value">${pendingProjects}</span>
            </div>
            <div class="grading-workload-stat">
                <span class="grading-workload-stat-label">Pending Quizzes</span>
                <span class="grading-workload-stat-value">${pendingQuizzes}</span>
            </div>
            <div class="grading-workload-stat">
                <span class="grading-workload-stat-label">Avg Turnaround</span>
                <span class="grading-workload-stat-value">${avgGradingHours.toFixed(1)}h</span>
            </div>
        </div>
    `;

    const recentHtml = recent.length === 0
        ? ''
        : `
            <h4 class="grading-workload-recent-heading">Recent grading activity</h4>
            ${recent.map(item => {
                const turnaround = item.grading_time_hours != null
                    ? `${Number(item.grading_time_hours).toFixed(1)}h`
                    : '—';
                const gradedAt = item.graded_at ? Utils.formatDate(item.graded_at) : '—';
                return `
                    <div class="grading-workload-item">
                        <div class="workload-info">
                            <h4>${Utils.escapeHtml(item.item_title || 'Untitled')}</h4>
                            <div class="workload-stats">
                                <span>👤 ${Utils.escapeHtml(item.student_name || 'Unknown')}</span>
                                <span>📂 ${Utils.escapeHtml(item.item_type || '')}</span>
                                <span>✅ Graded ${gradedAt}</span>
                                <span>⏱️ Turnaround ${turnaround}</span>
                            </div>
                        </div>
                        <div class="workload-badge">
                            ${turnaround}
                        </div>
                    </div>
                `;
            }).join('')}
        `;

    container.innerHTML = summaryHtml + recentHtml;

    // Animate workload items (no-op if there are no recent items)
    gsap.from('.grading-workload-item', {
        opacity: 0,
        y: 20,
        duration: 0.5,
        stagger: 0.1,
        ease: 'power2.out'
    });
}

/**
 * Generate instructor insights
 */
function generateInstructorInsights(distributionData, engagementData, atRiskData) {
    const insights = [];

    // Performance insights
    const classAvg = distributionData.average_score || 0;
    if (classAvg >= 80) {
        insights.push({
            type: 'success',
            icon: '🎉',
            title: 'Excellent Class Performance',
            message: `Your class is performing exceptionally well with an average score of ${classAvg.toFixed(1)}%. Keep up the great teaching!`
        });
    } else if (classAvg < 60) {
        insights.push({
            type: 'warning',
            icon: '📚',
            title: 'Class Needs Support',
            message: `Class average is ${classAvg.toFixed(1)}%. Consider reviewing difficult topics or providing additional resources.`
        });
    }

    // Engagement insights
    const avgEngagement = engagementData.avg_engagement_score || 0;
    if (avgEngagement >= 70) {
        insights.push({
            type: 'success',
            icon: '🎯',
            title: 'High Student Engagement',
            message: `Students are highly engaged with an average score of ${avgEngagement.toFixed(1)}. Your content is resonating well!`
        });
    } else if (avgEngagement < 40) {
        insights.push({
            type: 'info',
            icon: '💡',
            title: 'Boost Engagement',
            message: `Engagement score is ${avgEngagement.toFixed(1)}. Try adding interactive elements, quizzes, or discussion prompts.`
        });
    }

    // At-risk students insights
    const atRiskCount = atRiskData.at_risk_students?.length || 0;
    const criticalCount = atRiskData.at_risk_students?.filter(s => s.risk_score >= 80).length || 0;

    if (criticalCount > 0) {
        insights.push({
            type: 'warning',
            icon: '⚠️',
            title: 'Critical: Students Need Immediate Attention',
            message: `${criticalCount} student(s) at critical risk. Reach out immediately to prevent dropout.`
        });
    } else if (atRiskCount > 0) {
        insights.push({
            type: 'info',
            icon: '👀',
            title: 'Monitor At-Risk Students',
            message: `${atRiskCount} student(s) showing signs of struggle. Consider proactive check-ins.`
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
        <h3 style="margin: 2rem 0 1rem 0;">💡 Insights & Recommendations</h3>
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

/**
 * Reset a chart container to a fresh canvas, regardless of whether the previous
 * render replaced the canvas with a no-data <div>. Idempotent across reloads.
 */
function resetChartContainer(containerId, canvasId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    // Tear down any Chart.js instance still bound to the old canvas before we
    // throw the canvas away.
    if (chartInstances[canvasId]) {
        try { chartInstances[canvasId].destroy(); } catch (e) { /* noop */ }
        delete chartInstances[canvasId];
    }
    container.innerHTML = `<canvas id="${canvasId}"></canvas>`;
}

function getRiskLevel(score) {
    if (score >= 80) return { label: 'Critical', class: 'critical' };
    if (score >= 60) return { label: 'High', class: 'high' };
    if (score >= 40) return { label: 'Moderate', class: 'moderate' };
    return { label: 'Low', class: 'low' };
}

function calculateAverage(array, key) {
    if (!array || array.length === 0) return 0;
    const sum = array.reduce((acc, item) => acc + (parseFloat(item[key]) || 0), 0);
    return sum / array.length;
}

// formatDate moved to Utils.js (Phase 11 refactoring)

// formatDuration moved to Utils.js (Phase 11 refactoring)

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

/**
 * Hide the analytics summary cards and chart grid when there's nothing to render
 * (teacher unassigned or no courses). The filters-container still shows the empty state.
 */
function hideAnalyticsSections() {
    const summary = document.querySelector('.analytics-summary-cards');
    const grid = document.querySelector('.analytics-charts-grid');
    if (summary) summary.style.display = 'none';
    if (grid) grid.style.display = 'none';
}

function contactStudent(email) {
    window.location.href = `mailto:${email}?subject=Course Support - Let's Connect`;
}
