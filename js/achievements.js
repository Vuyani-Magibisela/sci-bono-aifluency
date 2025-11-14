/**
 * Achievements System JavaScript
 * Phase 6: Quiz Tracking & Grading System
 *
 * Handles achievement display, notifications, and progress tracking
 */

const AchievementsManager = {
    /**
     * Load all achievements for current user
     */
    async loadUserAchievements() {
        try {
            const token = localStorage.getItem('token');
            if (!token) {
                console.log('No auth token found');
                return null;
            }

            const response = await fetch('/api/achievements/user', {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error('Failed to load achievements');
            }

            const data = await response.json();
            return data.achievements || [];
        } catch (error) {
            console.error('Error loading achievements:', error);
            return [];
        }
    },

    /**
     * Load all available achievements
     */
    async loadAllAchievements() {
        try {
            const response = await fetch('/api/achievements');

            if (!response.ok) {
                throw new Error('Failed to load all achievements');
            }

            const data = await response.json();
            return data.achievements || [];
        } catch (error) {
            console.error('Error loading all achievements:', error);
            return [];
        }
    },

    /**
     * Get user's achievement points
     */
    async getUserPoints() {
        try {
            const token = localStorage.getItem('token');
            if (!token) return null;

            const response = await fetch('/api/achievements/points', {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error('Failed to load points');
            }

            const data = await response.json();
            return data.points || null;
        } catch (error) {
            console.error('Error loading points:', error);
            return null;
        }
    },

    /**
     * Get leaderboard
     */
    async getLeaderboard(limit = 10) {
        try {
            const response = await fetch(`/api/achievements/leaderboard?limit=${limit}`);

            if (!response.ok) {
                throw new Error('Failed to load leaderboard');
            }

            const data = await response.json();
            return data.leaderboard || [];
        } catch (error) {
            console.error('Error loading leaderboard:', error);
            return [];
        }
    },

    /**
     * Check for new achievements after an action
     */
    async checkForNewAchievements(eventType, eventData = {}) {
        try {
            const token = localStorage.getItem('token');
            if (!token) return [];

            const response = await fetch('/api/achievements/check', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    event_type: eventType,
                    event_data: eventData
                })
            });

            if (!response.ok) {
                return [];
            }

            const data = await response.json();
            const newAchievements = data.unlocked || [];

            // Show notifications for newly unlocked achievements
            if (newAchievements.length > 0) {
                this.showAchievementNotifications(newAchievements);
            }

            return newAchievements;
        } catch (error) {
            console.error('Error checking achievements:', error);
            return [];
        }
    },

    /**
     * Show achievement unlock notification
     */
    showAchievementNotifications(achievements) {
        achievements.forEach((achievement, index) => {
            setTimeout(() => {
                this.showSingleNotification(achievement);
            }, index * 2000); // Stagger notifications by 2 seconds
        });
    },

    /**
     * Show single achievement notification
     */
    showSingleNotification(achievement) {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = 'achievement-notification';
        notification.innerHTML = `
            <div class="achievement-notification-content">
                <div class="achievement-icon ${achievement.tier}">
                    <i class="${achievement.badge_icon}"></i>
                </div>
                <div class="achievement-details">
                    <div class="achievement-title">Achievement Unlocked!</div>
                    <div class="achievement-name">${this.escapeHtml(achievement.name)}</div>
                    <div class="achievement-points">+${achievement.points} points</div>
                </div>
            </div>
        `;

        // Add to page
        document.body.appendChild(notification);

        // Animate in
        setTimeout(() => {
            notification.classList.add('show');
        }, 100);

        // Remove after delay
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 5000);
    },

    /**
     * Render achievement badge
     */
    renderBadge(achievement, unlocked = false) {
        const tierColors = {
            'bronze': '#CD7F32',
            'silver': '#C0C0C0',
            'gold': '#FFD700',
            'platinum': '#E5E4E2'
        };

        return `
            <div class="achievement-badge ${unlocked ? 'unlocked' : 'locked'}" data-achievement-id="${achievement.id}">
                <div class="badge-icon" style="background-color: ${achievement.badge_color || tierColors[achievement.tier]};">
                    <i class="${achievement.badge_icon}" style="${unlocked ? '' : 'opacity: 0.3;'}"></i>
                </div>
                <div class="badge-info">
                    <div class="badge-name">${this.escapeHtml(achievement.name)}</div>
                    <div class="badge-tier ${achievement.tier}">${achievement.tier.toUpperCase()}</div>
                    ${unlocked ? `<div class="badge-points">${achievement.points} pts</div>` : ''}
                </div>
                ${!unlocked && !achievement.is_secret ?
                    `<div class="badge-description">${this.escapeHtml(achievement.description)}</div>`
                    : ''}
                ${unlocked ?
                    `<div class="badge-unlocked-date">Unlocked: ${new Date(achievement.unlocked_at).toLocaleDateString()}</div>`
                    : ''}
            </div>
        `;
    },

    /**
     * Render achievement grid
     */
    async renderAchievementGrid(containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;

        container.innerHTML = '<div class="loading">Loading achievements...</div>';

        try {
            const [allAchievements, userAchievements] = await Promise.all([
                this.loadAllAchievements(),
                this.loadUserAchievements()
            ]);

            const unlockedIds = new Set(userAchievements.map(a => a.id));

            // Group by category
            const categories = {};
            allAchievements.forEach(achievement => {
                const catName = achievement.category_name || 'Other';
                if (!categories[catName]) {
                    categories[catName] = [];
                }
                categories[catName].push(achievement);
            });

            let html = '';
            for (const [categoryName, achievements] of Object.entries(categories)) {
                html += `
                    <div class="achievement-category">
                        <h3 class="category-title">
                            <i class="${achievements[0]?.category_icon || 'fas fa-trophy'}"></i>
                            ${this.escapeHtml(categoryName)}
                        </h3>
                        <div class="achievement-grid">
                            ${achievements.map(a => {
                                const unlocked = unlockedIds.has(a.id);
                                const userAch = userAchievements.find(ua => ua.id === a.id);
                                return this.renderBadge(unlocked ? userAch : a, unlocked);
                            }).join('')}
                        </div>
                    </div>
                `;
            }

            container.innerHTML = html;
        } catch (error) {
            console.error('Error rendering achievement grid:', error);
            container.innerHTML = '<div class="error">Failed to load achievements</div>';
        }
    },

    /**
     * Render user points summary
     */
    async renderPointsSummary(containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;

        try {
            const points = await this.getUserPoints();

            if (!points) {
                container.innerHTML = '<div class="no-points">No achievements yet!</div>';
                return;
            }

            container.innerHTML = `
                <div class="points-summary">
                    <div class="total-points">
                        <div class="points-value">${points.total_points || 0}</div>
                        <div class="points-label">Total Points</div>
                    </div>
                    <div class="achievements-count">
                        <div class="count-value">${points.achievements_count || 0}</div>
                        <div class="count-label">Achievements</div>
                    </div>
                    <div class="tier-breakdown">
                        <div class="tier-item">
                            <i class="fas fa-medal" style="color: #E5E4E2;"></i>
                            <span>${points.platinum_count || 0}</span>
                        </div>
                        <div class="tier-item">
                            <i class="fas fa-medal" style="color: #FFD700;"></i>
                            <span>${points.gold_count || 0}</span>
                        </div>
                        <div class="tier-item">
                            <i class="fas fa-medal" style="color: #C0C0C0;"></i>
                            <span>${points.silver_count || 0}</span>
                        </div>
                        <div class="tier-item">
                            <i class="fas fa-medal" style="color: #CD7F32;"></i>
                            <span>${points.bronze_count || 0}</span>
                        </div>
                    </div>
                </div>
            `;
        } catch (error) {
            console.error('Error rendering points summary:', error);
            container.innerHTML = '<div class="error">Failed to load points</div>';
        }
    },

    /**
     * Render leaderboard
     */
    async renderLeaderboard(containerId, limit = 10) {
        const container = document.getElementById(containerId);
        if (!container) return;

        container.innerHTML = '<div class="loading">Loading leaderboard...</div>';

        try {
            const leaderboard = await this.getLeaderboard(limit);

            if (leaderboard.length === 0) {
                container.innerHTML = '<div class="no-data">No leaderboard data yet</div>';
                return;
            }

            let html = '<div class="leaderboard">';
            leaderboard.forEach((user, index) => {
                const rank = index + 1;
                const medalIcon = rank === 1 ? '>G' : rank === 2 ? '>H' : rank === 3 ? '>I' : '';

                html += `
                    <div class="leaderboard-entry rank-${rank}">
                        <div class="rank">${medalIcon || rank}</div>
                        <div class="user-info">
                            <div class="user-name">${this.escapeHtml(user.user_name)}</div>
                            <div class="user-achievements">${user.achievements_count} achievements</div>
                        </div>
                        <div class="user-points">${user.total_points} pts</div>
                    </div>
                `;
            });
            html += '</div>';

            container.innerHTML = html;
        } catch (error) {
            console.error('Error rendering leaderboard:', error);
            container.innerHTML = '<div class="error">Failed to load leaderboard</div>';
        }
    },

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Auto-check achievements on page load if authenticated
if (typeof window !== 'undefined') {
    window.AchievementsManager = AchievementsManager;
}
