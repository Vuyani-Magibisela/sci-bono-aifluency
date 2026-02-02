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
    if (user.role !== 'instructor' && user.role !== 'admin') {
        alert('Access denied. This page is for instructors only.');
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
        // Fetch instructor's courses
        const coursesResponse = await API.get('/courses');
        const courses = coursesResponse.courses || [];

        if (courses.length === 0) {
            document.getElementById('filters-container').innerHTML = `
                <div class="error-message">
                    No courses found. You must be assigned to courses to view analytics.
                </div>
            `;
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

    try {
        // Show loading states
        document.getElementById('classDistributionChart').parentElement.innerHTML =
            '<canvas id="classDistributionChart"></canvas>';
        document.getElementById('engagementChart').parentElement.innerHTML =
            '<canvas id="engagementChart"></canvas>';
        document.getElementById('questionEffectivenessChart').parentElement.innerHTML =
            '<canvas id="questionEffectivenessChart"></canvas>';
        document.getElementById('at-risk-tbody').innerHTML =
            '<tr><td colspan="7" class="loading-spinner">Loading at-risk students...</td></tr>';
        document.getElementById('grading-workload-container').innerHTML = loadingMessage;

        // Fetch all data in parallel
        const [
            distributionData,
            engagementData,
            questionData,
            atRiskData,
            gradingData
        ] = await Promise.all([
            API.get(`/analytics/instructor/class/${currentCourseId}/distribution?${filterParams}`),
            API.get(`/analytics/instructor/class/${currentCourseId}/engagement?${filterParams}`),
            API.get(`/analytics/instructor/class/${currentCourseId}/question-effectiveness?${filterParams}`),
            API.get(`/analytics/instructor/class/${currentCourseId}/at-risk-students?${filterParams}`),
            API.get(`/analytics/instructor/class/${currentCourseId}/grading-workload?${filterParams}`)
        ]);

        // Update summary cards
        updateSummaryCards(distributionData, atRiskData, gradingData);

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
function updateSummaryCards(distributionData, atRiskData, gradingData) {
    const totalStudents = distributionData.total_students || 0;
    const avgScore = distributionData.class_average || 0;
    const atRiskCount = atRiskData.at_risk_students?.length || 0;
    const pendingGrading = gradingData.pending_grading_tasks || 0;

    Utils.animateCounter('total-students', totalStudents, 0);
    Utils.animateCounter('avg-class-score', avgScore, 1, '%');
    Utils.animateCounter('at-risk-count', atRiskCount, 0);
    Utils.animateCounter('pending-grading', pendingGrading, 0);
}

/**
 * Render class performance distribution histogram
 */
function renderClassDistribution(data) {
    const distribution = data.distribution || [];

    if (distribution.length === 0) {
        document.getElementById('classDistributionChart').parentElement.innerHTML =
            '<div class="no-data-message">No performance data available for this course.</div>';
        return;
    }

    const labels = distribution.map(d => d.score_range);
    const values = distribution.map(d => d.student_count);

    const chartData = {
        labels: labels,
        datasets: [{
            label: 'Number of Students',
            data: values,
            backgroundColor: labels.map((label, index) => {
                // Color code by performance level
                if (label.includes('90-100')) return 'rgba(75, 251, 157, 0.8)'; // Excellent - Green
                if (label.includes('80-89')) return 'rgba(75, 110, 251, 0.8)'; // Good - Blue
                if (label.includes('70-79')) return 'rgba(110, 75, 251, 0.8)'; // Average - Purple
                if (label.includes('60-69')) return 'rgba(255, 165, 0, 0.8)'; // Below Average - Orange
                return 'rgba(251, 75, 75, 0.8)'; // Poor - Red
            }),
            borderColor: labels.map((label) => {
                if (label.includes('90-100')) return '#4BFB9D';
                if (label.includes('80-89')) return '#4B6EFB';
                if (label.includes('70-79')) return '#6E4BFB';
                if (label.includes('60-69')) return '#FFA500';
                return '#FB4B4B';
            }),
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
                        const percentage = ((context.parsed.y / data.total_students) * 100).toFixed(1);
                        return `${context.parsed.y} students (${percentage}%)`;
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
                    text: 'Number of Students'
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
        document.getElementById('engagementChart').parentElement.innerHTML =
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
        document.getElementById('questionEffectivenessChart').parentElement.innerHTML =
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
    const workloadItems = data.workload_breakdown || [];

    if (workloadItems.length === 0) {
        container.innerHTML = '<div class="no-data-message">No grading tasks at this time.</div>';
        return;
    }

    container.innerHTML = workloadItems.map(item => `
        <div class="grading-workload-item">
            <div class="workload-info">
                <h4>${Utils.escapeHtml(item.quiz_title)}</h4>
                <div class="workload-stats">
                    <span>✅ Graded: ${item.graded_count}</span>
                    <span>⏳ Pending: ${item.pending_count}</span>
                    <span>📊 Total Attempts: ${item.total_attempts}</span>
                    <span>⏱️ Avg Time: ${Utils.formatDuration(item.avg_grading_time)}</span>
                </div>
            </div>
            <div class="workload-badge">
                ${item.pending_count}
            </div>
        </div>
    `).join('');

    // Animate workload items
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
    const classAvg = distributionData.class_average || 0;
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

function contactStudent(email) {
    window.location.href = `mailto:${email}?subject=Course Support - Let's Connect`;
}
