/**
 * Breadcrumb Navigation Component
 * Phase 5D Priority 2
 *
 * Provides dynamic breadcrumb navigation trail for:
 * - Course → Module → Lesson
 * - Course → Module → Quiz
 *
 * Usage:
 * <div id="breadcrumb-container"></div>
 * <script>
 *   Breadcrumb.render('breadcrumb-container', {
 *     course: { id: 1, title: 'AI Fluency' },
 *     module: { id: 1, title: 'AI Foundations' },
 *     lesson: { id: 1, title: 'AI History' }
 *   });
 * </script>
 */

const Breadcrumb = (function() {
    'use strict';

    /**
     * Render breadcrumb navigation
     * @param {string} containerId - ID of container element
     * @param {Object} trail - Breadcrumb trail data
     * @param {Object} trail.course - Course info {id, title}
     * @param {Object} trail.module - Module info {id, title}
     * @param {Object} trail.lesson - Optional lesson info {id, title}
     * @param {Object} trail.quiz - Optional quiz info {id, title}
     */
    function render(containerId, trail) {
        const container = document.getElementById(containerId);
        if (!container) {
            console.error(`Breadcrumb: Container #${containerId} not found`);
            return;
        }

        // Build breadcrumb items
        const items = [];

        // Home link
        items.push({
            label: 'Home',
            url: 'aifluencystart.html',
            icon: 'fa-home'
        });

        // Course (if provided)
        if (trail.course) {
            items.push({
                label: trail.course.title || 'Course',
                url: trail.course.id ? `module-dynamic.html?module_id=${trail.module?.id || ''}` : null,
                icon: 'fa-book'
            });
        }

        // Module (if provided)
        if (trail.module) {
            items.push({
                label: trail.module.title || 'Module',
                url: trail.module.id ? `module-dynamic.html?module_id=${trail.module.id}` : null,
                icon: 'fa-layer-group'
            });
        }

        // Lesson or Quiz (if provided)
        if (trail.lesson) {
            items.push({
                label: trail.lesson.title || 'Lesson',
                url: null, // Current page, no link
                icon: 'fa-file-alt',
                current: true
            });
        } else if (trail.quiz) {
            items.push({
                label: trail.quiz.title || 'Quiz',
                url: null, // Current page, no link
                icon: 'fa-question-circle',
                current: true
            });
        }

        // Generate HTML
        const breadcrumbHTML = `
            <nav class="breadcrumb" aria-label="Breadcrumb navigation">
                <ol class="breadcrumb-list">
                    ${items.map((item, index) => `
                        <li class="breadcrumb-item ${item.current ? 'current' : ''}">
                            ${item.url ? `
                                <a href="${item.url}" class="breadcrumb-link">
                                    <i class="fas ${item.icon}"></i>
                                    <span class="breadcrumb-label">${escapeHtml(item.label)}</span>
                                </a>
                            ` : `
                                <span class="breadcrumb-text">
                                    <i class="fas ${item.icon}"></i>
                                    <span class="breadcrumb-label">${escapeHtml(item.label)}</span>
                                </span>
                            `}
                            ${index < items.length - 1 ? '<i class="fas fa-chevron-right breadcrumb-separator"></i>' : ''}
                        </li>
                    `).join('')}
                </ol>
            </nav>
        `;

        container.innerHTML = breadcrumbHTML;
    }

    /**
     * Render breadcrumb from URL parameters (module-dynamic.html, lesson-dynamic.html)
     * Automatically detects current page and extracts IDs from query params
     * @param {string} containerId - ID of container element
     * @param {Object} additionalData - Additional data not in URL (titles, etc.)
     */
    async function renderFromURL(containerId, additionalData = {}) {
        const urlParams = new URLSearchParams(window.location.search);
        const moduleId = urlParams.get('module_id');
        const lessonId = urlParams.get('lesson_id');
        const quizId = urlParams.get('quiz_id');

        // Determine page type
        const currentPage = window.location.pathname.split('/').pop();

        const trail = {
            course: {
                id: 1,
                title: additionalData.courseTitle || 'AI Fluency Course'
            }
        };

        // Fetch module data if module_id present
        if (moduleId) {
            try {
                const response = await API.get(`/courses/1/modules/${moduleId}`);
                if (response.success) {
                    trail.module = {
                        id: moduleId,
                        title: response.data.title
                    };
                }
            } catch (error) {
                console.error('Breadcrumb: Failed to fetch module data', error);
                trail.module = {
                    id: moduleId,
                    title: additionalData.moduleTitle || 'Module'
                };
            }
        }

        // Fetch lesson data if lesson_id present
        if (lessonId) {
            try {
                const response = await API.get(`/lessons/${lessonId}`);
                if (response.success) {
                    trail.lesson = {
                        id: lessonId,
                        title: response.data.title
                    };
                }
            } catch (error) {
                console.error('Breadcrumb: Failed to fetch lesson data', error);
                trail.lesson = {
                    id: lessonId,
                    title: additionalData.lessonTitle || 'Lesson'
                };
            }
        }

        // Fetch quiz data if quiz_id present
        if (quizId) {
            try {
                const response = await API.get(`/quizzes/${quizId}`);
                if (response.success) {
                    trail.quiz = {
                        id: quizId,
                        title: response.data.title
                    };
                }
            } catch (error) {
                console.error('Breadcrumb: Failed to fetch quiz data', error);
                trail.quiz = {
                    id: quizId,
                    title: additionalData.quizTitle || 'Quiz'
                };
            }
        }

        // Render breadcrumb
        render(containerId, trail);
    }

    /**
     * Escape HTML to prevent XSS
     * @param {string} text - Text to escape
     * @returns {string} Escaped text
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Update breadcrumb dynamically (for single-page apps)
     * @param {string} containerId - ID of container element
     * @param {Object} newTrail - New breadcrumb trail data
     */
    function update(containerId, newTrail) {
        render(containerId, newTrail);
    }

    // Public API
    return {
        render: render,
        renderFromURL: renderFromURL,
        update: update
    };
})();

// Make available globally
window.Breadcrumb = Breadcrumb;
