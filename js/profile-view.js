/**
 * Public Profile View
 * Phase 8C: Public Profiles + Directory
 */

let profileUserId = null;
let currentUser = null;

document.addEventListener('DOMContentLoaded', async () => {
    // Get user ID from URL parameter
    const urlParams = new URLSearchParams(window.location.search);
    profileUserId = urlParams.get('id');

    if (!profileUserId) {
        showError('Invalid Profile', 'No user ID provided.');
        return;
    }

    // Get current user (if logged in)
    currentUser = Auth.getUser();

    // Load profile
    await loadPublicProfile();
});

/**
 * Load public profile data
 */
async function loadPublicProfile() {
    try {
        const response = await API.get(`/users/${profileUserId}/profile/public`);

        if (!response.success || !response.user) {
            showError('Profile Not Available', response.message || 'This profile is private or does not exist.');
            return;
        }

        const user = response.user;

        // Track profile view (only if logged in and not viewing own profile)
        if (currentUser && currentUser.id != profileUserId) {
            trackView();
        }

        // Display profile
        displayProfile(user);

        // Load additional data based on privacy settings
        if (user.show_achievements) {
            await loadAchievements();
        }

        if (user.show_certificates) {
            await loadCertificates();
        }

        // Load stats
        await loadStats();

        // Hide loading, show content
        document.getElementById('loading-state').style.display = 'none';
        document.getElementById('profile-content').style.display = 'block';

        // Animate in
        gsap.from('#profile-content', {
            opacity: 0,
            y: 20,
            duration: 0.5,
            ease: 'power2.out'
        });

    } catch (error) {
        console.error('Failed to load profile:', error);
        showError('Error Loading Profile', error.message || 'An unexpected error occurred.');
    }
}

/**
 * Display profile information
 */
function displayProfile(user) {
    // Avatar
    if (user.avatar_url) {
        document.getElementById('avatar-image').src = user.avatar_url;
        document.getElementById('avatar-image').style.display = 'block';
        document.getElementById('avatar-initials').style.display = 'none';
    } else {
        const initials = user.name
            ? user.name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase()
            : 'U';
        document.getElementById('avatar-initials').textContent = initials;
    }

    // Basic Info
    document.getElementById('profile-name').textContent = user.name || 'Anonymous User';
    document.getElementById('profile-views').textContent = user.profile_views_count || 0;

    // Headline
    if (user.headline) {
        document.getElementById('profile-headline').textContent = user.headline;
        document.getElementById('profile-headline').style.display = 'block';
    }

    // Role
    if (user.role) {
        const roleText = user.role.charAt(0).toUpperCase() + user.role.slice(1);
        document.getElementById('profile-role').innerHTML = `<i class="fas fa-user-tag"></i> ${roleText}`;
    }

    // Location
    if (user.location) {
        document.getElementById('location-text').textContent = user.location;
        document.getElementById('profile-location').style.display = 'inline-block';
    }

    // Email (if show_email is true)
    if (user.show_email && user.email) {
        document.getElementById('email-text').textContent = user.email;
        document.getElementById('profile-email').style.display = 'inline-block';
    }

    // Bio
    if (user.bio) {
        document.getElementById('profile-bio').textContent = user.bio;
        document.getElementById('about-section').style.display = 'block';
    }

    // Social Links
    const socialLinks = [];
    if (user.website_url) {
        socialLinks.push({
            icon: 'fas fa-globe',
            label: 'Website',
            url: user.website_url
        });
    }
    if (user.github_url) {
        socialLinks.push({
            icon: 'fab fa-github',
            label: 'GitHub',
            url: user.github_url
        });
    }
    if (user.linkedin_url) {
        socialLinks.push({
            icon: 'fab fa-linkedin',
            label: 'LinkedIn',
            url: user.linkedin_url
        });
    }
    if (user.twitter_url) {
        socialLinks.push({
            icon: 'fab fa-twitter',
            label: 'Twitter',
            url: user.twitter_url
        });
    }

    if (socialLinks.length > 0) {
        const socialGrid = document.getElementById('social-links-grid');
        socialGrid.innerHTML = socialLinks.map(link => `
            <a href="${escapeHtml(link.url)}" target="_blank" rel="noopener noreferrer" class="social-link-card">
                <i class="${link.icon}"></i>
                <span>${link.label}</span>
            </a>
        `).join('');
        document.getElementById('social-section').style.display = 'block';
    }

    // Member Since
    if (user.created_at) {
        const memberSince = new Date(user.created_at);
        const year = memberSince.getFullYear();
        document.getElementById('stat-member-since').textContent = year;
    }
}

/**
 * Track profile view
 */
async function trackView() {
    try {
        // This endpoint will be called silently - no need to handle response
        await API.post(`/users/${profileUserId}/profile/view`, {});
    } catch (error) {
        // Silently fail - view tracking is not critical
        console.debug('Failed to track view:', error);
    }
}

/**
 * Load achievements
 */
async function loadAchievements() {
    try {
        const response = await API.get(`/achievements/user/${profileUserId}`);

        const container = document.getElementById('achievements-container');

        if (response.success && response.achievements && response.achievements.length > 0) {
            container.innerHTML = '<div class="achievements-grid"></div>';
            const grid = container.querySelector('.achievements-grid');

            response.achievements.forEach(achievement => {
                const badge = document.createElement('div');
                badge.className = `achievement-badge ${achievement.tier}`;
                badge.innerHTML = `
                    <div class="achievement-icon">
                        <i class="${achievement.badge_icon}"></i>
                    </div>
                    <h4>${escapeHtml(achievement.name)}</h4>
                    <p>${escapeHtml(achievement.description)}</p>
                    <span class="achievement-date">${formatDate(achievement.earned_at)}</span>
                `;
                grid.appendChild(badge);
            });

            // Update stat
            document.getElementById('stat-achievements-count').textContent = response.achievements.length;
            document.getElementById('stat-achievements-item').style.display = 'block';
            document.getElementById('achievements-section').style.display = 'block';
        } else {
            container.innerHTML = '<p class="text-muted">No achievements earned yet.</p>';
            document.getElementById('achievements-section').style.display = 'block';
        }
    } catch (error) {
        console.error('Failed to load achievements:', error);
    }
}

/**
 * Load certificates
 */
async function loadCertificates() {
    try {
        const response = await API.get(`/certificates/user/${profileUserId}`);

        const container = document.getElementById('certificates-container');

        if (response.success && response.certificates && response.certificates.length > 0) {
            container.innerHTML = '<div class="certificates-grid"></div>';
            const grid = container.querySelector('.certificates-grid');

            response.certificates.forEach(cert => {
                const card = document.createElement('div');
                card.className = 'certificate-card';
                card.innerHTML = `
                    <div class="certificate-icon">
                        <i class="fas fa-award"></i>
                    </div>
                    <h4>${escapeHtml(cert.course_title || 'Course Certificate')}</h4>
                    <p class="certificate-date">Issued: ${formatDate(cert.issued_at)}</p>
                    <a href="${escapeHtml(cert.certificate_url)}" target="_blank" class="btn-secondary btn-sm">
                        <i class="fas fa-download"></i> View Certificate
                    </a>
                `;
                grid.appendChild(card);
            });

            // Update stat
            document.getElementById('stat-certificates-count').textContent = response.certificates.length;
            document.getElementById('stat-certificates-item').style.display = 'block';
            document.getElementById('certificates-section').style.display = 'block';
        } else {
            container.innerHTML = '<p class="text-muted">No certificates earned yet.</p>';
            document.getElementById('certificates-section').style.display = 'block';
        }
    } catch (error) {
        console.error('Failed to load certificates:', error);
    }
}

/**
 * Load stats
 */
async function loadStats() {
    try {
        // Load enrollments count (public info)
        const enrollmentsResponse = await API.get(`/enrollments/user/${profileUserId}`);
        if (enrollmentsResponse.success) {
            document.getElementById('stat-courses').textContent = enrollmentsResponse.enrollments?.length || 0;
        }
    } catch (error) {
        console.error('Failed to load stats:', error);
    }
}

/**
 * Show error state
 */
function showError(title, message) {
    document.getElementById('loading-state').style.display = 'none';
    document.getElementById('error-title').textContent = title;
    document.getElementById('error-message').textContent = message;
    document.getElementById('error-state').style.display = 'block';

    // Animate in
    gsap.from('#error-state', {
        opacity: 0,
        y: 20,
        duration: 0.5,
        ease: 'power2.out'
    });
}

/**
 * Format date
 */
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

/**
 * Escape HTML
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
