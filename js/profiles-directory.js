/**
 * Profiles Directory
 * Phase 8C: Public Profiles + Directory
 */

let allProfiles = [];
let filteredProfiles = [];
let currentPage = 1;
const profilesPerPage = 12;
let currentRole = 'all';
let searchQuery = '';

document.addEventListener('DOMContentLoaded', async () => {
    await loadProfiles();
    setupEventListeners();
});

/**
 * Load all public profiles
 */
async function loadProfiles() {
    try {
        const response = await API.get('/users/profiles/search?public_only=true');

        if (response.success && response.profiles) {
            allProfiles = response.profiles;
            filteredProfiles = allProfiles;

            // Update counts
            updateCounts();

            // Display profiles
            displayProfiles();
        } else {
            showEmptyState();
        }
    } catch (error) {
        console.error('Failed to load profiles:', error);
        showError('Failed to load profiles. Please try again later.');
    }
}

/**
 * Setup event listeners
 */
function setupEventListeners() {
    // Search input
    const searchInput = document.getElementById('search-input');
    searchInput.addEventListener('input', debounce(handleSearch, 300));

    // Clear search button
    document.getElementById('clear-search-btn').addEventListener('click', () => {
        searchInput.value = '';
        searchQuery = '';
        document.getElementById('clear-search-btn').style.display = 'none';
        applyFilters();
    });

    // Role filter buttons
    const filterBtns = document.querySelectorAll('.filter-btn');
    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            // Remove active from all
            filterBtns.forEach(b => b.classList.remove('active'));

            // Add active to clicked
            btn.classList.add('active');

            // Update filter
            currentRole = btn.dataset.role;
            applyFilters();
        });
    });

    // Pagination buttons
    document.getElementById('prev-page-btn').addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            displayProfiles();
            scrollToTop();
        }
    });

    document.getElementById('next-page-btn').addEventListener('click', () => {
        const totalPages = Math.ceil(filteredProfiles.length / profilesPerPage);
        if (currentPage < totalPages) {
            currentPage++;
            displayProfiles();
            scrollToTop();
        }
    });
}

/**
 * Handle search
 */
function handleSearch(e) {
    searchQuery = e.target.value.trim().toLowerCase();

    // Show/hide clear button
    if (searchQuery) {
        document.getElementById('clear-search-btn').style.display = 'block';
    } else {
        document.getElementById('clear-search-btn').style.display = 'none';
    }

    applyFilters();
}

/**
 * Apply filters (search + role)
 */
function applyFilters() {
    filteredProfiles = allProfiles;

    // Apply role filter
    if (currentRole !== 'all') {
        filteredProfiles = filteredProfiles.filter(p => p.role === currentRole);
    }

    // Apply search filter
    if (searchQuery) {
        filteredProfiles = filteredProfiles.filter(profile => {
            const name = (profile.name || '').toLowerCase();
            const headline = (profile.headline || '').toLowerCase();
            const location = (profile.location || '').toLowerCase();

            return name.includes(searchQuery) ||
                   headline.includes(searchQuery) ||
                   location.includes(searchQuery);
        });
    }

    // Reset to page 1
    currentPage = 1;

    // Display filtered results
    displayProfiles();
}

/**
 * Update role counts
 */
function updateCounts() {
    const totalCount = allProfiles.length;
    const studentCount = allProfiles.filter(p => p.role === 'student').length;
    const instructorCount = allProfiles.filter(p => p.role === 'instructor').length;

    document.getElementById('count-all').textContent = totalCount;
    document.getElementById('count-student').textContent = studentCount;
    document.getElementById('count-instructor').textContent = instructorCount;
}

/**
 * Display profiles
 */
function displayProfiles() {
    const grid = document.getElementById('profiles-grid');
    const emptyState = document.getElementById('empty-state');
    const resultsCount = document.getElementById('results-count');

    // No results
    if (filteredProfiles.length === 0) {
        grid.style.display = 'none';
        emptyState.style.display = 'block';
        document.getElementById('pagination').style.display = 'none';
        resultsCount.textContent = 'No profiles found';
        return;
    }

    // Calculate pagination
    const totalPages = Math.ceil(filteredProfiles.length / profilesPerPage);
    const startIndex = (currentPage - 1) * profilesPerPage;
    const endIndex = Math.min(startIndex + profilesPerPage, filteredProfiles.length);
    const pageProfiles = filteredProfiles.slice(startIndex, endIndex);

    // Update results count
    resultsCount.textContent = `Showing ${startIndex + 1}-${endIndex} of ${filteredProfiles.length} profiles`;

    // Render profile cards
    grid.innerHTML = pageProfiles.map(profile => createProfileCard(profile)).join('');
    grid.style.display = 'grid';
    emptyState.style.display = 'none';

    // Animate cards
    gsap.from('.profile-card', {
        opacity: 0,
        y: 20,
        duration: 0.4,
        stagger: 0.05,
        ease: 'power2.out'
    });

    // Setup card click handlers
    document.querySelectorAll('.profile-card').forEach(card => {
        card.addEventListener('click', () => {
            const userId = card.dataset.userId;
            window.location.href = `profile-view.html?id=${userId}`;
        });
    });

    // Update pagination controls
    updatePagination(totalPages);
}

/**
 * Create profile card HTML
 */
function createProfileCard(profile) {
    const initials = profile.name
        ? profile.name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase()
        : 'U';

    const roleIcon = profile.role === 'instructor'
        ? 'fas fa-chalkboard-teacher'
        : 'fas fa-user-graduate';

    const roleLabel = profile.role
        ? profile.role.charAt(0).toUpperCase() + profile.role.slice(1)
        : 'Student';

    const avatarHtml = profile.avatar_url
        ? `<img src="${escapeHtml(profile.avatar_url)}" alt="${escapeHtml(profile.name)}">`
        : `<span class="avatar-initials">${initials}</span>`;

    return `
        <div class="profile-card" data-user-id="${profile.id}">
            <div class="profile-card-avatar">
                ${avatarHtml}
            </div>
            <div class="profile-card-content">
                <h3 class="profile-card-name">${escapeHtml(profile.name || 'Anonymous')}</h3>

                ${profile.headline ? `
                    <p class="profile-card-headline">${escapeHtml(profile.headline)}</p>
                ` : ''}

                <div class="profile-card-meta">
                    <span class="profile-card-role">
                        <i class="${roleIcon}"></i> ${roleLabel}
                    </span>

                    ${profile.location ? `
                        <span class="profile-card-location">
                            <i class="fas fa-map-marker-alt"></i> ${escapeHtml(profile.location)}
                        </span>
                    ` : ''}
                </div>

                <div class="profile-card-stats">
                    <span>
                        <i class="fas fa-eye"></i> ${profile.profile_views_count || 0} views
                    </span>
                </div>
            </div>
        </div>
    `;
}

/**
 * Update pagination controls
 */
function updatePagination(totalPages) {
    const prevBtn = document.getElementById('prev-page-btn');
    const nextBtn = document.getElementById('next-page-btn');
    const pageInfo = document.getElementById('page-info');
    const pagination = document.getElementById('pagination');

    if (totalPages <= 1) {
        pagination.style.display = 'none';
        return;
    }

    pagination.style.display = 'flex';
    pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;

    // Previous button
    prevBtn.disabled = currentPage === 1;

    // Next button
    nextBtn.disabled = currentPage === totalPages;
}

/**
 * Show empty state
 */
function showEmptyState() {
    document.getElementById('profiles-grid').style.display = 'none';
    document.getElementById('empty-state').style.display = 'block';
    document.getElementById('pagination').style.display = 'none';
    document.getElementById('results-count').textContent = 'No profiles found';
}

/**
 * Show error message
 */
function showError(message) {
    const grid = document.getElementById('profiles-grid');
    grid.innerHTML = `
        <div class="error-container">
            <i class="fas fa-exclamation-circle fa-3x"></i>
            <p>${escapeHtml(message)}</p>
        </div>
    `;
}

/**
 * Scroll to top of profiles
 */
function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

/**
 * Debounce utility
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
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
