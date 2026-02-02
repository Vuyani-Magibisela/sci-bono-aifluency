/**
 * Student Analytics Dashboard
 * Phase 10: Advanced Analytics Dashboard
 *
 * Displays learning velocity, skill proficiency, time-on-task,
 * and struggle indicators for the logged-in student.
 */

// Initialize dashboard when page loads
document.addEventListener('DOMContentLoaded', async () => {
    // Check authentication
    const user = Auth.getUser();
    if (!user) {
        window.location.href = '/login.html';
        return;
    }

    // Load header
    loadHeader();

    // Initialize filters
    initializeFilters();

    // Load all analytics data
    await loadStudentAnalytics(user.id);

    // Set up animations
    animateDashboard();
});

/**
 * Initialize date range filters
 */
function initializeFilters() {
    AnalyticsFilters.createFilterBar('filters-container', {
        showCourseFilter: false,
        showModuleFilter: false,
        showRoleFilter: false,
        onFilterChange: async (filters) => {
            const user = Auth.getUser();
            if (user) {
                await loadStudentAnalytics(user.id);
            }
        },
        onApply: async (filters) => {
            const user = Auth.getUser();
            if (user) {
                await loadStudentAnalytics(user.id);
            }
        },
        onReset: async () => {
            const user = Auth.getUser();
            if (user) {
                await loadStudentAnalytics(user.id);
            }
        }
    });
}

/**
 * Load all student analytics data
 */
async function loadStudentAnalytics(userId) {
    try {
        // Show loading state
        showLoadingState();

        // Build query params from filters
        const filterParams = AnalyticsFilters.getFilterParams();

        // Fetch all analytics data in parallel
        const [velocityData, timeOnTaskData, proficiencyData, struggleData] = await Promise.all([
            API.get(`/analytics/student/${userId}/velocity?${filterParams}`),
            API.get(`/analytics/student/${userId}/time-on-task?${filterParams}`),
            API.get(`/analytics/student/${userId}/skill-proficiency?${filterParams}`),
            API.get(`/analytics/student/${userId}/struggle-indicators?${filterParams}`)
        ]);

        // Update summary cards
        updateSummaryCards(velocityData, timeOnTaskData, proficiencyData);

        // Render charts
        renderVelocityChart(velocityData);
        renderProficiencyChart(proficiencyData);
        renderTimeDistributionChart(timeOnTaskData);
        renderStruggleIndicators(struggleData);

        // Generate insights
        generateInsights(velocityData, proficiencyData, struggleData);

        // Hide loading state
        hideLoadingState();

    } catch (error) {
        console.error('Error loading student analytics:', error);
        showErrorMessage('Failed to load analytics data. Please try again.');
    }
}

/**
 * Update summary statistic cards
 */
function updateSummaryCards(velocityData, timeOnTaskData, proficiencyData) {
    // Total lessons (count completed from time-on-task data)
    const lessonsCompleted = timeOnTaskData.time_on_task?.filter(item =>
        item.content_type === 'lesson' && item.status === 'completed'
    ).length || 0;
    Utils.animateCounter('total-lessons-stat', lessonsCompleted, 0);

    // Average score from velocity data
    const avgScore = velocityData.velocity_data?.length > 0
        ? velocityData.velocity_data.reduce((sum, day) => sum + parseFloat(day.avg_score || 0), 0) / velocityData.velocity_data.length
        : 0;
    animatePercentage('avg-score-stat', 0, Math.round(avgScore), 1000);

    // Learning streak (days active)
    const daysActive = velocityData.days_active || 0;
    Utils.animateCounter('learning-streak-stat', daysActive, 0);

    // Total time spent
    const totalHours = (timeOnTaskData.total_minutes || 0) / 60;
    const timeElement = document.getElementById('time-spent-stat');
    if (timeElement) {
        gsap.to({ value: 0 }, {
            value: totalHours,
            duration: 1,
            onUpdate: function() {
                timeElement.textContent = this.targets()[0].value.toFixed(1) + ' hrs';
            }
        });
    }
}

/**
 * Render learning velocity line chart
 */
function renderVelocityChart(velocityData) {
    if (!velocityData.velocity_data || velocityData.velocity_data.length === 0) {
        showNoDataMessage('velocityChart', 'No activity data available for the selected period');
        return;
    }

    const labels = velocityData.velocity_data.map(day => {
        const date = new Date(day.completion_date);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    });

    const attemptCounts = velocityData.velocity_data.map(day => day.attempts_count);
    const avgScores = velocityData.velocity_data.map(day => parseFloat(day.avg_score || 0));

    const data = {
        labels: labels,
        datasets: [
            {
                label: 'Quiz Attempts',
                data: attemptCounts,
                borderColor: '#4B6EFB',
                backgroundColor: 'rgba(75, 110, 251, 0.1)',
                yAxisID: 'y',
                tension: 0.4
            },
            {
                label: 'Average Score',
                data: avgScores,
                borderColor: '#4BFB9D',
                backgroundColor: 'rgba(75, 251, 157, 0.1)',
                yAxisID: 'y1',
                tension: 0.4
            }
        ]
    };

    const options = {
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Attempts',
                    font: {
                        family: "'Poppins', sans-serif",
                        size: 12,
                        weight: 'bold'
                    }
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Score (%)',
                    font: {
                        family: "'Poppins', sans-serif",
                        size: 12,
                        weight: 'bold'
                    }
                },
                grid: {
                    drawOnChartArea: false
                }
            }
        }
    };

    Charts.createLineChart('velocityChart', data, options);
}

/**
 * Render skill proficiency radar chart
 */
function renderProficiencyChart(proficiencyData) {
    if (!proficiencyData.skill_proficiency || proficiencyData.skill_proficiency.length === 0) {
        showNoDataMessage('proficiencyChart', 'No proficiency data available');
        return;
    }

    const labels = proficiencyData.skill_proficiency.map(module => module.module_title);
    const proficiencyScores = proficiencyData.skill_proficiency.map(module =>
        parseFloat(module.proficiency_score || 0)
    );

    const data = {
        labels: labels,
        datasets: [{
            label: 'Proficiency Score',
            data: proficiencyScores,
            borderColor: '#6E4BFB',
            backgroundColor: 'rgba(110, 75, 251, 0.2)',
            pointBackgroundColor: '#6E4BFB',
            pointBorderColor: '#fff',
            pointHoverBackgroundColor: '#fff',
            pointHoverBorderColor: '#6E4BFB'
        }]
    };

    Charts.createRadarChart('proficiencyChart', data, { max: 100 });
}

/**
 * Render time distribution pie chart
 */
function renderTimeDistributionChart(timeOnTaskData) {
    if (!timeOnTaskData.time_on_task || timeOnTaskData.time_on_task.length === 0) {
        showNoDataMessage('timeDistributionChart', 'No time tracking data available');
        return;
    }

    // Aggregate time by module
    const timeByModule = {};
    timeOnTaskData.time_on_task.forEach(item => {
        const module = item.module_title || 'Other';
        timeByModule[module] = (timeByModule[module] || 0) + parseFloat(item.time_minutes || 0);
    });

    const labels = Object.keys(timeByModule);
    const values = Object.values(timeByModule);

    const data = {
        labels: labels,
        datasets: [{
            data: values,
            backgroundColor: [
                '#4B6EFB',
                '#6E4BFB',
                '#FB4B4B',
                '#4BFB9D',
                '#FFA500',
                '#FFD700'
            ],
            borderWidth: 2,
            borderColor: '#FFFFFF'
        }]
    };

    Charts.createDoughnutChart('timeDistributionChart', data, {
        cutout: '60%',
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    });
}

/**
 * Render struggle indicators list
 */
function renderStruggleIndicators(struggleData) {
    const container = document.getElementById('struggle-indicators-list');
    if (!container) return;

    if (!struggleData.struggles || struggleData.struggles.length === 0) {
        container.innerHTML = `
            <div class="no-data-message">
                <i class="fas fa-check-circle" style="color: #4BFB9D; font-size: 3rem;"></i>
                <p>Great job! No significant struggle areas detected.</p>
            </div>
        `;
        return;
    }

    // Show top 5 struggles
    const topStruggles = struggleData.struggles.slice(0, 5);

    const html = topStruggles.map(struggle => {
        const struggleLevel = parseFloat(struggle.struggle_score);
        let levelClass = 'struggle-low';
        let levelText = 'Minor';
        let levelColor = '#4BFB9D';

        if (struggleLevel >= 80) {
            levelClass = 'struggle-critical';
            levelText = 'Critical';
            levelColor = '#FB4B4B';
        } else if (struggleLevel >= 60) {
            levelClass = 'struggle-high';
            levelText = 'High';
            levelColor = '#FFA500';
        } else if (struggleLevel >= 40) {
            levelClass = 'struggle-moderate';
            levelText = 'Moderate';
            levelColor = '#FFD700';
        }

        return `
            <div class="struggle-item ${levelClass}">
                <div class="struggle-header">
                    <h4>${Utils.escapeHtml(struggle.quiz_title)}</h4>
                    <span class="struggle-badge" style="background: ${levelColor};">${levelText}</span>
                </div>
                <div class="struggle-stats">
                    <div class="stat">
                        <i class="fas fa-redo"></i>
                        <span>${struggle.total_attempts} attempts</span>
                    </div>
                    <div class="stat">
                        <i class="fas fa-times-circle"></i>
                        <span>${struggle.failed_attempts} failed</span>
                    </div>
                    <div class="stat">
                        <i class="fas fa-chart-line"></i>
                        <span>Avg: ${parseFloat(struggle.avg_score).toFixed(1)}%</span>
                    </div>
                </div>
                <div class="struggle-actions">
                    <button class="btn-small btn-primary" onclick="window.location.href='/quiz-dynamic.html?id=${struggle.quiz_id}'">
                        <i class="fas fa-redo"></i> Retry Quiz
                    </button>
                </div>
            </div>
        `;
    }).join('');

    container.innerHTML = html;
}

/**
 * Generate personalized insights
 */
function generateInsights(velocityData, proficiencyData, struggleData) {
    const container = document.getElementById('insights-container');
    if (!container) return;

    const insights = [];

    // Velocity trend insight
    if (velocityData.trend) {
        if (velocityData.trend === 'improving') {
            insights.push({
                icon: 'fa-arrow-trend-up',
                title: 'Great Progress!',
                message: `Your performance is improving over time. Keep up the excellent work!`,
                type: 'success'
            });
        } else if (velocityData.trend === 'declining') {
            insights.push({
                icon: 'fa-arrow-trend-down',
                title: 'Need More Practice',
                message: `Your recent scores have decreased. Consider reviewing earlier modules.`,
                type: 'warning'
            });
        }
    }

    // Proficiency insight
    if (proficiencyData.overall_proficiency) {
        const overallScore = parseFloat(proficiencyData.overall_proficiency);
        if (overallScore >= 80) {
            insights.push({
                icon: 'fa-star',
                title: 'Excellent Proficiency',
                message: `You have ${overallScore.toFixed(1)}% overall proficiency. You're mastering the material!`,
                type: 'success'
            });
        } else if (overallScore < 50) {
            insights.push({
                icon: 'fa-book',
                title: 'More Study Needed',
                message: `Your proficiency is at ${overallScore.toFixed(1)}%. Spend more time on lessons and practice quizzes.`,
                type: 'info'
            });
        }
    }

    // Struggle insight
    if (struggleData.quizzes_with_high_struggle > 0) {
        insights.push({
            icon: 'fa-exclamation-circle',
            title: 'Focus Areas Identified',
            message: `You have ${struggleData.quizzes_with_high_struggle} quiz(es) that need attention. Review the "Areas for Improvement" section below.`,
            type: 'warning'
        });
    }

    // Learning consistency insight
    if (velocityData.days_active) {
        const daysActive = velocityData.days_active;
        if (daysActive >= 7) {
            insights.push({
                icon: 'fa-fire',
                title: 'Consistent Learner',
                message: `You've been active for ${daysActive} days! Consistency is key to success.`,
                type: 'success'
            });
        }
    }

    // Render insights
    if (insights.length === 0) {
        container.innerHTML = `
            <div class="no-data-message">
                <i class="fas fa-info-circle"></i>
                <p>Complete more activities to unlock personalized insights.</p>
            </div>
        `;
        return;
    }

    const html = insights.map(insight => `
        <div class="insight-card insight-${insight.type}">
            <div class="insight-icon">
                <i class="fas ${insight.icon}"></i>
            </div>
            <div class="insight-content">
                <h4>${insight.title}</h4>
                <p>${insight.message}</p>
            </div>
        </div>
    `).join('');

    container.innerHTML = html;
}

/**
 * Animate dashboard elements
 */
function animateDashboard() {
    // Fade in stat cards with stagger
    gsap.from('.stat-card', {
        opacity: 0,
        y: 20,
        duration: 0.6,
        stagger: 0.1,
        ease: 'power2.out'
    });

    // Fade in chart cards with stagger
    gsap.from('.chart-card', {
        opacity: 0,
        y: 30,
        duration: 0.8,
        stagger: 0.15,
        delay: 0.3,
        ease: 'power2.out'
    });
}

/**
 * Show loading state
 */
function showLoadingState() {
    document.querySelectorAll('.chart-container').forEach(container => {
        container.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    });
}

/**
 * Hide loading state
 */
function hideLoadingState() {
    document.querySelectorAll('.loading-spinner').forEach(spinner => {
        spinner.remove();
    });
}

/**
 * Show error message
 */
function showErrorMessage(message) {
    const container = document.querySelector('.dashboard-container');
    if (container) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.innerHTML = `
            <i class="fas fa-exclamation-triangle"></i>
            <p>${message}</p>
        `;
        container.insertBefore(errorDiv, container.firstChild);
    }
}

/**
 * Show "no data" message in chart
 */
function showNoDataMessage(canvasId, message) {
    const canvas = document.getElementById(canvasId);
    if (canvas) {
        const container = canvas.parentElement;
        container.innerHTML = `
            <div class="no-data-message">
                <i class="fas fa-chart-line" style="font-size: 3rem; color: #ccc;"></i>
                <p>${message}</p>
            </div>
        `;
    }
}

// escapeHtml moved to Utils.js (Phase 11 refactoring)

// animateCounter moved to Utils.js (Phase 11 refactoring)
// Note: Utils.animateCounter has different signature (targetValue, decimals, suffix)
// Calls updated to match new signature

/**
 * Animate percentage counter
 */
function animatePercentage(elementId, start, end, duration) {
    const element = document.getElementById(elementId);
    if (!element) return;

    gsap.to({ value: start }, {
        value: end,
        duration: duration / 1000,
        onUpdate: function() {
            element.textContent = Math.round(this.targets()[0].value) + '%';
        }
    });
}
