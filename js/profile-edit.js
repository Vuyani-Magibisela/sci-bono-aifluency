/**
 * Profile Edit Page
 * Phase 8: Profile Building & Viewing
 */

Auth.requireAuth();

let currentUser = null;
let currentAvatarFileId = null;

document.addEventListener('DOMContentLoaded', async () => {
    currentUser = Auth.getUser();

    if (currentUser) {
        await loadUserProfile();
        await loadProfileCompletion();
        setupFormHandlers();
        setupAvatarUpload();
        setupCharacterCounters();
    }
});

/**
 * Load user profile data
 */
async function loadUserProfile() {
    try {
        const response = await API.get(`/users/${currentUser.id}`);

        if (response.success && response.user) {
            const user = response.user;

            // Populate basic info
            document.getElementById('headline').value = user.headline || '';
            document.getElementById('bio').value = user.bio || '';
            document.getElementById('location').value = user.location || '';

            // Populate social links
            document.getElementById('website_url').value = user.website_url || '';
            document.getElementById('github_url').value = user.github_url || '';
            document.getElementById('linkedin_url').value = user.linkedin_url || '';
            document.getElementById('twitter_url').value = user.twitter_url || '';

            // Populate privacy settings
            document.getElementById('is_public_profile').checked = user.is_public_profile == 1;
            document.getElementById('show_email').checked = user.show_email == 1;
            document.getElementById('show_achievements').checked = user.show_achievements == 1;
            document.getElementById('show_certificates').checked = user.show_certificates == 1;

            // Load avatar
            await loadAvatar();

            // Update character counters
            updateCharacterCounters();
        }
    } catch (error) {
        console.error('Failed to load profile:', error);
        showNotification('Failed to load profile data', 'error');
    }
}

/**
 * Load profile completion percentage
 */
async function loadProfileCompletion() {
    try {
        const response = await API.get(`/users/${currentUser.id}/profile/completion`);

        if (response.success) {
            const completion = response.completion_percentage;

            // Update UI
            document.getElementById('completion-percentage').textContent = `${completion}%`;

            // Animate progress bar
            gsap.to('#completion-progress', {
                width: `${completion}%`,
                duration: 1,
                ease: 'power2.out'
            });

            // Color code based on completion
            const progressBar = document.getElementById('completion-progress');
            if (completion < 30) {
                progressBar.style.backgroundColor = '#FB4B4B'; // Red
            } else if (completion < 70) {
                progressBar.style.backgroundColor = '#FFA500'; // Orange
            } else {
                progressBar.style.backgroundColor = '#4BFB9D'; // Green
            }
        }
    } catch (error) {
        console.error('Failed to load completion:', error);
    }
}

/**
 * Setup form handlers
 */
function setupFormHandlers() {
    // Save profile button
    document.getElementById('save-profile-btn').addEventListener('click', handleProfileSave);

    // Real-time URL validation
    const urlInputs = ['website_url', 'github_url', 'linkedin_url', 'twitter_url'];
    urlInputs.forEach(inputId => {
        document.getElementById(inputId).addEventListener('blur', validateUrlInput);
    });
}

/**
 * Handle profile save
 */
async function handleProfileSave() {
    const saveBtn = document.getElementById('save-profile-btn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    try {
        // Collect profile data
        const profileData = {
            bio: document.getElementById('bio').value,
            headline: document.getElementById('headline').value,
            location: document.getElementById('location').value,
            website_url: document.getElementById('website_url').value,
            github_url: document.getElementById('github_url').value,
            linkedin_url: document.getElementById('linkedin_url').value,
            twitter_url: document.getElementById('twitter_url').value
        };

        // Validate URLs
        if (!validateUrls(profileData)) {
            throw new Error('Invalid URL format');
        }

        // Save profile
        const profileResponse = await API.put(`/users/${currentUser.id}/profile`, profileData);

        if (!profileResponse.success) {
            throw new Error(profileResponse.message || 'Failed to update profile');
        }

        // Collect privacy settings
        const privacyData = {
            is_public_profile: document.getElementById('is_public_profile').checked,
            show_email: document.getElementById('show_email').checked,
            show_achievements: document.getElementById('show_achievements').checked,
            show_certificates: document.getElementById('show_certificates').checked
        };

        // Save privacy settings
        const privacyResponse = await API.put(`/users/${currentUser.id}/profile/privacy`, privacyData);

        if (!privacyResponse.success) {
            throw new Error(privacyResponse.message || 'Failed to update privacy settings');
        }

        // Show success with animation
        showNotification('Profile updated successfully!', 'success');

        // Reload completion
        await loadProfileCompletion();

        // Pulse save button
        gsap.fromTo(saveBtn,
            { scale: 1 },
            { scale: 1.1, duration: 0.2, yoyo: true, repeat: 1 }
        );

    } catch (error) {
        console.error('Profile save error:', error);
        showNotification(error.message || 'Failed to save profile', 'error');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
    }
}

/**
 * Validate URL inputs
 */
function validateUrls(profileData) {
    const urlPattern = /^https?:\/\/.+/i;
    const urlFields = ['website_url', 'github_url', 'linkedin_url', 'twitter_url'];

    for (const field of urlFields) {
        const value = profileData[field];
        if (value && !urlPattern.test(value)) {
            const fieldName = field.replace('_url', '').replace('_', ' ');
            showNotification(`Invalid URL: ${fieldName}`, 'error');
            return false;
        }
    }

    return true;
}

/**
 * Validate single URL input
 */
function validateUrlInput(e) {
    const input = e.target;
    const value = input.value.trim();

    if (value && !value.match(/^https?:\/\/.+/i)) {
        input.classList.add('invalid');
        input.setCustomValidity('URL must start with http:// or https://');
    } else {
        input.classList.remove('invalid');
        input.setCustomValidity('');
    }
}

/**
 * Setup character counters
 */
function setupCharacterCounters() {
    // Bio counter
    document.getElementById('bio').addEventListener('input', () => {
        const bio = document.getElementById('bio');
        const count = bio.value.length;
        document.getElementById('bio-count').textContent = count;

        if (count > 4500) {
            document.getElementById('bio-count').style.color = '#FB4B4B';
        } else {
            document.getElementById('bio-count').style.color = '';
        }
    });

    // Headline counter
    document.getElementById('headline').addEventListener('input', () => {
        const headline = document.getElementById('headline');
        const count = headline.value.length;
        document.getElementById('headline-count').textContent = count;

        if (count > 230) {
            document.getElementById('headline-count').style.color = '#FB4B4B';
        } else {
            document.getElementById('headline-count').style.color = '';
        }
    });
}

/**
 * Update character counters
 */
function updateCharacterCounters() {
    document.getElementById('bio-count').textContent = document.getElementById('bio').value.length;
    document.getElementById('headline-count').textContent = document.getElementById('headline').value.length;
}

/**
 * Setup avatar upload
 */
function setupAvatarUpload() {
    const uploadBtn = document.getElementById('upload-avatar-btn');
    const removeBtn = document.getElementById('remove-avatar-btn');
    const avatarInput = document.getElementById('avatar-input');

    // Upload button click
    uploadBtn.addEventListener('click', () => {
        avatarInput.click();
    });

    // File selected
    avatarInput.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (!file) return;

        // Validate file
        if (!file.type.match(/image\/(jpeg|jpg|png|gif)/)) {
            showNotification('Please select a valid image file (JPG, PNG, or GIF)', 'error');
            return;
        }

        if (file.size > 2 * 1024 * 1024) {
            showNotification('Image must be less than 2MB', 'error');
            return;
        }

        await uploadAvatar(file);
    });

    // Remove avatar
    removeBtn.addEventListener('click', async () => {
        if (confirm('Are you sure you want to remove your profile picture?')) {
            await removeAvatar();
        }
    });
}

/**
 * Load avatar
 */
async function loadAvatar() {
    try {
        const response = await API.get(`/files?type=avatar&user_id=${currentUser.id}`);

        if (response.success && response.files && response.files.length > 0) {
            const avatarFile = response.files[0];
            currentAvatarFileId = avatarFile.id;

            const avatarImg = document.getElementById('avatar-preview');
            avatarImg.src = avatarFile.file_url;
            avatarImg.style.display = 'block';
            document.getElementById('avatar-initials').style.display = 'none';
            document.getElementById('remove-avatar-btn').style.display = 'inline-block';
        } else {
            // Show initials
            const initials = currentUser.name
                ? currentUser.name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase()
                : 'U';
            document.getElementById('avatar-initials').textContent = initials;
        }
    } catch (error) {
        console.error('Failed to load avatar:', error);
    }
}

/**
 * Upload avatar
 */
async function uploadAvatar(file) {
    try {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('type', 'avatar');

        const response = await API.upload('/upload', formData);

        if (response.success) {
            showNotification('Profile picture updated!', 'success');
            await loadAvatar();
            await loadProfileCompletion(); // Avatar upload increases completion
        } else {
            throw new Error(response.message || 'Upload failed');
        }
    } catch (error) {
        console.error('Avatar upload error:', error);
        showNotification('Failed to upload profile picture', 'error');
    }
}

/**
 * Remove avatar
 */
async function removeAvatar() {
    if (!currentAvatarFileId) return;

    try {
        const response = await API.delete(`/files/${currentAvatarFileId}`);

        if (response.success) {
            showNotification('Profile picture removed', 'success');
            document.getElementById('avatar-preview').style.display = 'none';
            document.getElementById('avatar-initials').style.display = 'block';
            document.getElementById('remove-avatar-btn').style.display = 'none';
            currentAvatarFileId = null;
            await loadProfileCompletion(); // Removing avatar decreases completion
        } else {
            throw new Error(response.message || 'Failed to remove avatar');
        }
    } catch (error) {
        console.error('Avatar removal error:', error);
        showNotification('Failed to remove profile picture', 'error');
    }
}

/**
 * Show notification
 */
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
        <span>${message}</span>
    `;

    document.body.appendChild(notification);

    // Animate in
    gsap.fromTo(notification,
        { x: 300, opacity: 0 },
        { x: 0, opacity: 1, duration: 0.3 }
    );

    // Remove after 3 seconds
    setTimeout(() => {
        gsap.to(notification, {
            x: 300,
            opacity: 0,
            duration: 0.3,
            onComplete: () => notification.remove()
        });
    }, 3000);
}
