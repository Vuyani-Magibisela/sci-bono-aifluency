/**
 * Walkthrough Step Definitions
 *
 * Driver.js step configurations keyed by role and page.
 * Steps with non-existent target elements are filtered at runtime by walkthrough.js.
 */
const tourSteps = {
    student: {
        dashboard: [
            {
                popover: {
                    title: 'Welcome to the Sci-Bono AI Hub! 👋',
                    description: "Let's take a quick tour so you know where everything is. You can skip anytime — and re-launch this tour from the help button in the header."
                }
            },
            {
                element: '#sidebar',
                popover: {
                    title: 'Your Sidebar',
                    description: 'Jump between your dashboard, courses, projects, quizzes, and certificates from here.',
                    side: 'right',
                    align: 'start'
                }
            },
            {
                element: '#welcome-message',
                popover: {
                    title: 'Welcome Banner',
                    description: 'A quick greeting and a refresh button if you want to reload your stats.',
                    side: 'bottom',
                    align: 'start'
                }
            },
            {
                element: '.dashboard-grid',
                popover: {
                    title: 'Your Progress at a Glance',
                    description: 'Enrolled courses, lessons completed, quiz average, and certificates earned — all in one place.',
                    side: 'bottom',
                    align: 'start'
                }
            },
            {
                element: '#enrolled-courses',
                popover: {
                    title: 'My Courses',
                    description: 'Click any course to jump back into your lessons. We track exactly where you left off.',
                    side: 'top',
                    align: 'start'
                }
            },
            {
                element: '#recent-quiz-attempts',
                popover: {
                    title: 'Recent Quizzes',
                    description: 'Test yourself after each module. You get instant feedback and your scores show up here.',
                    side: 'top',
                    align: 'start'
                }
            },
            {
                element: '#certificates',
                popover: {
                    title: 'Earn Certificates',
                    description: 'Complete a course to earn a certificate for your portfolio.',
                    side: 'top',
                    align: 'start'
                }
            },
            {
                element: '#tour-help-button',
                popover: {
                    title: 'Need This Tour Again?',
                    description: 'Click here anytime to re-launch the tour or get help with the current page.',
                    side: 'left',
                    align: 'end'
                }
            }
        ],
        'course-view': [
            {
                element: '.courses-grid',
                popover: {
                    title: 'Browse Courses',
                    description: 'Each card is a full course. Click "Enroll" to start, or open one to see its modules and lessons.',
                    side: 'top',
                    align: 'start'
                }
            },
            {
                element: '#tour-help-button',
                popover: {
                    title: 'Help Anytime',
                    description: 'Re-launch this tour from the help button whenever you need it.',
                    side: 'left',
                    align: 'end'
                }
            }
        ],
        lesson: [
            {
                popover: {
                    title: 'Lesson Page',
                    description: 'Watch the video, read along, and mark the lesson complete when you\'re done. Your progress is saved automatically.'
                }
            }
        ],
        quiz: [
            {
                popover: {
                    title: 'Take the Quiz',
                    description: 'Answer each question and submit at the end. You\'ll see correct answers and explanations right after.'
                }
            }
        ]
    },

    teacher: {
        dashboard: [
            {
                popover: {
                    title: 'Welcome, Educator! 👋',
                    description: "Here's a quick walkthrough of how to guide your students on the platform."
                }
            },
            {
                element: '#sidebar',
                popover: {
                    title: 'Your Navigation',
                    description: 'Access courses, students, grading, and analytics from here.',
                    side: 'right',
                    align: 'start'
                }
            },
            {
                element: '.dashboard-grid',
                popover: {
                    title: 'Class Overview',
                    description: 'See key metrics about your students and courses at a glance.',
                    side: 'bottom',
                    align: 'start'
                }
            },
            {
                element: '#tour-help-button',
                popover: {
                    title: 'Help Anytime',
                    description: 'Re-launch this tour from the help button whenever you need it.',
                    side: 'left',
                    align: 'end'
                }
            }
        ]
    },

    // School & Org Admins — limited to what they actually have access to
    admin: {
        dashboard: [
            {
                popover: {
                    title: 'Welcome, Admin! 👋',
                    description: "Here's a quick tour of the admin tools available to you."
                }
            },
            {
                element: '#sidebar',
                popover: {
                    title: 'Admin Navigation',
                    description: 'Manage users, courses, quizzes, and view analytics from this sidebar.',
                    side: 'right',
                    align: 'start'
                }
            },
            {
                element: '.dashboard-grid',
                popover: {
                    title: 'Platform Health',
                    description: 'Registrations, completions, and engagement at a glance.',
                    side: 'bottom',
                    align: 'start'
                }
            },
            {
                element: '#tour-help-button',
                popover: {
                    title: 'Help Anytime',
                    description: 'Re-launch this tour from the help button whenever you need it.',
                    side: 'left',
                    align: 'end'
                }
            }
        ]
    },

    // SuperAdmin — full platform access (system settings, all users, all orgs)
    superadmin: {
        dashboard: [
            {
                popover: {
                    title: 'Welcome, Super Admin! 👋',
                    description: "You have full access to the platform. Here's where everything lives."
                }
            },
            {
                element: '#sidebar',
                popover: {
                    title: 'Full Admin Navigation',
                    description: 'Users, organizations, schools, courses, modules, lessons, quizzes, projects, analytics, and feedback — all here.',
                    side: 'right',
                    align: 'start'
                }
            },
            {
                element: '.dashboard-grid',
                popover: {
                    title: 'Platform Stats',
                    description: 'High-level metrics across every organization and school on the platform.',
                    side: 'bottom',
                    align: 'start'
                }
            },
            {
                element: '#tour-help-button',
                popover: {
                    title: 'Help Anytime',
                    description: 'Re-launch this tour from the help button whenever you need it.',
                    side: 'left',
                    align: 'end'
                }
            }
        ]
    }
};

if (typeof window !== 'undefined') {
    window.tourSteps = tourSteps;
}
