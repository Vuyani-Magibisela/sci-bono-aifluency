/**
 * Feedback Widget Module
 * Self-contained floating action button + modal for user feedback
 */

const FeedbackWidget = {
    isOpen: false,
    maxMessageLength: 5000,

    init() {
        this.injectStyles();
        this.injectFAB();
        this.injectModal();
        this.bindEvents();
    },

    injectStyles() {
        // Styles are in styles.css — no inline injection needed
    },

    injectFAB() {
        const fab = document.createElement('button');
        fab.id = 'feedback-fab';
        fab.className = 'feedback-fab';
        fab.setAttribute('aria-label', 'Send feedback');
        fab.setAttribute('title', 'Send feedback or report a bug');
        fab.innerHTML = '<i class="fas fa-comment-dots"></i>';
        document.body.appendChild(fab);
    },

    injectModal() {
        const isLoggedIn = typeof Storage !== 'undefined' && typeof Storage.get === 'function' && Storage.get('access_token');

        const modal = document.createElement('div');
        modal.id = 'feedback-modal';
        modal.className = 'feedback-modal-overlay';
        modal.innerHTML = `
            <div class="feedback-modal">
                <div class="feedback-modal-header">
                    <h3>Send Feedback</h3>
                    <button class="feedback-modal-close" aria-label="Close">&times;</button>
                </div>
                <form id="feedback-form" class="feedback-form">
                    <div class="feedback-field">
                        <label for="feedback-type">Type</label>
                        <select id="feedback-type" name="feedback_type" required>
                            <option value="">Select type...</option>
                            <option value="bug">Bug Report</option>
                            <option value="suggestion">Suggestion</option>
                            <option value="question">Question</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="feedback-field">
                        <label for="feedback-message">Message</label>
                        <textarea id="feedback-message" name="message" rows="5"
                            placeholder="Describe the issue or share your thoughts..." required
                            minlength="10" maxlength="${this.maxMessageLength}"></textarea>
                        <div class="feedback-char-count">
                            <span id="feedback-char-current">0</span> / ${this.maxMessageLength}
                        </div>
                    </div>
                    ${!isLoggedIn ? `
                    <div class="feedback-field">
                        <label for="feedback-email">Email <span class="feedback-optional">(optional)</span></label>
                        <input type="email" id="feedback-email" name="contact_email"
                            placeholder="your@email.com — if you'd like a reply">
                    </div>
                    ` : ''}
                    <div class="feedback-notice">
                        <i class="fas fa-info-circle"></i>
                        <span>Page URL and browser info are captured automatically to help us investigate.</span>
                    </div>
                    <div id="feedback-alert" class="feedback-alert" style="display:none;"></div>
                    <button type="submit" class="feedback-submit" id="feedback-submit-btn">
                        <span class="feedback-submit-text">Submit Feedback</span>
                        <span class="feedback-submit-loading" style="display:none;">
                            <i class="fas fa-spinner fa-spin"></i> Sending...
                        </span>
                    </button>
                </form>
            </div>
        `;
        document.body.appendChild(modal);
    },

    bindEvents() {
        const fab = document.getElementById('feedback-fab');
        const modal = document.getElementById('feedback-modal');
        const closeBtn = modal.querySelector('.feedback-modal-close');
        const form = document.getElementById('feedback-form');
        const textarea = document.getElementById('feedback-message');
        const charCount = document.getElementById('feedback-char-current');

        fab.addEventListener('click', () => this.open());
        closeBtn.addEventListener('click', () => this.close());
        modal.addEventListener('click', (e) => {
            if (e.target === modal) this.close();
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen) this.close();
        });

        textarea.addEventListener('input', () => {
            charCount.textContent = textarea.value.length;
        });

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.submit();
        });
    },

    open() {
        const modal = document.getElementById('feedback-modal');
        modal.classList.add('active');
        this.isOpen = true;
        document.getElementById('feedback-type').focus();
    },

    close() {
        const modal = document.getElementById('feedback-modal');
        modal.classList.remove('active');
        this.isOpen = false;
    },

    showAlert(message, type) {
        const alert = document.getElementById('feedback-alert');
        alert.className = `feedback-alert feedback-alert-${type}`;
        alert.textContent = message;
        alert.style.display = 'block';
    },

    hideAlert() {
        document.getElementById('feedback-alert').style.display = 'none';
    },

    getBrowserInfo() {
        return {
            language: navigator.language,
            platform: navigator.platform,
            cookiesEnabled: navigator.cookieEnabled,
            online: navigator.onLine,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone
        };
    },

    async submit() {
        this.hideAlert();

        const form = document.getElementById('feedback-form');
        const submitBtn = document.getElementById('feedback-submit-btn');
        const submitText = submitBtn.querySelector('.feedback-submit-text');
        const submitLoading = submitBtn.querySelector('.feedback-submit-loading');

        const feedbackType = document.getElementById('feedback-type').value;
        const message = document.getElementById('feedback-message').value.trim();
        const emailField = document.getElementById('feedback-email');
        const contactEmail = emailField ? emailField.value.trim() : '';

        if (!feedbackType) {
            this.showAlert('Please select a feedback type.', 'error');
            return;
        }
        if (message.length < 10) {
            this.showAlert('Message must be at least 10 characters.', 'error');
            return;
        }

        const payload = {
            feedback_type: feedbackType,
            message: message,
            page_url: window.location.href,
            user_agent: navigator.userAgent,
            screen_resolution: `${screen.width}x${screen.height}`,
            browser_info: this.getBrowserInfo()
        };

        if (contactEmail) {
            payload.contact_email = contactEmail;
        }

        // Show loading state
        submitBtn.disabled = true;
        submitText.style.display = 'none';
        submitLoading.style.display = 'inline';

        try {
            let response;
            if (typeof API !== 'undefined' && API.post) {
                response = await API.post('/feedback', payload);
            } else {
                // Fallback: direct fetch
                const baseURL = window.location.pathname.includes('/sci-bono-aifluency/')
                    ? '/sci-bono-aifluency/api'
                    : '/api';
                const res = await fetch(baseURL + '/feedback', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                response = await res.json();
            }

            if (response && response.success) {
                this.showAlert('Thank you for your feedback!', 'success');
                form.reset();
                document.getElementById('feedback-char-current').textContent = '0';
                setTimeout(() => this.close(), 2000);
            } else {
                const msg = response?.message || 'Failed to submit feedback. Please try again.';
                this.showAlert(msg, 'error');
            }
        } catch (err) {
            this.showAlert('Network error. Please check your connection and try again.', 'error');
        } finally {
            submitBtn.disabled = false;
            submitText.style.display = 'inline';
            submitLoading.style.display = 'none';
        }
    }
};

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => FeedbackWidget.init());
} else {
    FeedbackWidget.init();
}
