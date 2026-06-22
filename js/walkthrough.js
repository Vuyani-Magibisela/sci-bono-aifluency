/**
 * Walkthrough Module
 *
 * Driver.js-powered guided tour with role-based steps and server-side
 * first-visit detection. Auto-triggers on the user's dashboard if they
 * haven't seen the tour. A floating help button (rendered by header-template.js)
 * lets users re-launch on demand.
 *
 * Depends on:
 *   - window.tourSteps (from walkthrough-steps.js)
 *   - Driver.js (CDN, loaded by header-template.js)
 *   - Auth, API (existing modules)
 */
const Walkthrough = {
    initialized: false,
    driverInstance: null,
    hasSeenTour: null,

    /**
     * Initialise the walkthrough system. Called by header-template.js after render.
     * Note: bindHelpButton() runs immediately at script load (see bottom of file)
     * so the button is responsive even if Driver.js or the API are slow/unavailable.
     */
    async init() {
        if (this.initialized) return;
        this.initialized = true;

        console.log('[Walkthrough] init called', {
            authed: window.Auth && Auth.isAuthenticated(),
            driverLoaded: typeof window.driver !== 'undefined',
            stepsLoaded: typeof window.tourSteps !== 'undefined',
            currentPage: window.currentPage
        });

        if (!window.Auth || !Auth.isAuthenticated()) return;

        // Fetch live tour status from server (the JWT-stored user may be stale)
        try {
            const res = await API.request('/tour-status');
            this.hasSeenTour = !!(res && res.data && res.data.has_seen_tour);
        } catch (e) {
            console.warn('[Walkthrough] tour-status fetch failed', e);
            this.hasSeenTour = true;
        }

        this.updateNewBadge();

        // Auto-trigger only on the dashboard page, only on first visit
        if (!this.hasSeenTour && window.currentPage === 'dashboard') {
            setTimeout(() => this.startTour(), 1000);
        }
    },

    /**
     * Resolve the current user's tour variant.
     * student → student | teacher → teacher | school/orgadmin → admin | superadmin → superadmin
     */
    resolveTourRole() {
        const user = Auth.getUser();
        if (!user || !user.role) return null;
        const role = String(user.role).toLowerCase();

        if (role === 'student') return 'student';
        if (role === 'teacher' || role === 'instructor') return 'teacher';
        if (role === 'schooladmin' || role === 'orgadmin') return 'admin';
        if (role === 'superadmin' || role === 'admin') return 'superadmin';
        return null;
    },

    /**
     * Build a Driver.js instance with Sci-Bono themed options for a given step set.
     */
    buildDriver(steps) {
        // Driver.js IIFE may expose the constructor as window.driver.driver,
        // window.driver (callable), or window.Driver — handle all shapes.
        const d = window.driver;
        const ctor = (d && d.js && typeof d.js.driver === 'function') ? d.js.driver
                   : (d && typeof d.driver === 'function') ? d.driver
                   : (typeof d === 'function') ? d
                   : (typeof window.Driver === 'function') ? window.Driver
                   : null;
        if (!ctor) {
            console.error('[Walkthrough] Could not find Driver.js constructor. window.driver =', d);
            throw new Error('Driver.js constructor not found');
        }
        return ctor({
            showProgress: true,
            allowClose: true,
            stagePadding: 6,
            stageRadius: 8,
            popoverClass: 'sci-bono-tour-popover',
            overlayColor: 'rgba(10, 14, 39, 0.65)',
            nextBtnText: 'Next →',
            prevBtnText: '← Back',
            doneBtnText: 'Done',
            steps: steps,
            onDestroyed: () => this.markSeen()
        });
    },

    /**
     * Filter out steps whose target element doesn't exist on the current page.
     * Steps without an `element` (centered popovers) are always kept.
     */
    filterAvailableSteps(steps) {
        return steps.filter(step => {
            if (!step.element) return true;
            return document.querySelector(step.element) !== null;
        });
    },

    /**
     * Start the role-appropriate tour for the current page.
     */
    startTour() {
        const role = this.resolveTourRole();
        const page = window.currentPage || 'dashboard';
        console.log('[Walkthrough] startTour', {
            role,
            page,
            driverLoaded: typeof window.driver !== 'undefined',
            stepsLoaded: typeof window.tourSteps !== 'undefined'
        });

        if (typeof window.driver === 'undefined') {
            console.error('[Walkthrough] Driver.js is not loaded — check that the CDN script https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.js.iife.js loaded successfully (Network tab).');
            alert('The tour library failed to load. Please check your internet connection and refresh the page.');
            return;
        }
        if (typeof window.tourSteps === 'undefined') {
            console.error('[Walkthrough] tourSteps not loaded — check /js/walkthrough-steps.js loaded successfully.');
            return;
        }

        if (!role || !window.tourSteps[role]) {
            console.warn('[Walkthrough] no tour for role:', role);
            return;
        }

        const stepsForPage = window.tourSteps[role][page];
        if (!stepsForPage || stepsForPage.length === 0) {
            console.warn('[Walkthrough] no steps for page:', page);
            return;
        }

        const available = this.filterAvailableSteps(stepsForPage);
        console.log('[Walkthrough] steps available:', available.length, 'of', stepsForPage.length);
        if (available.length === 0) return;

        this.driverInstance = this.buildDriver(available);
        this.driverInstance.drive();
    },

    /**
     * Mark the tour as seen on the server (called on tour complete OR skip).
     */
    async markSeen() {
        if (this.hasSeenTour) return;
        this.hasSeenTour = true;
        this.updateNewBadge();
        try {
            await API.request('/tour-status/seen', { method: 'POST' });
        } catch (e) {
            console.warn('Failed to persist tour-seen status:', e);
        }
    },

    /**
     * Bind click handlers via event delegation so they survive header re-renders.
     * (The header markup is replaced on auth events.)
     */
    bindHelpButton() {
        if (this.helpButtonBound) return;
        this.helpButtonBound = true;

        document.addEventListener('click', (e) => {
            const helpBtn = e.target.closest('#tour-help-button');
            const fullAction = e.target.closest('#tour-action-full');
            const pageAction = e.target.closest('#tour-action-page');
            const insideWrapper = e.target.closest('#tour-help-wrapper');

            const menu = document.getElementById('tour-help-menu');
            const btn = document.getElementById('tour-help-button');
            if (!menu || !btn) return;

            if (helpBtn && !fullAction && !pageAction) {
                e.stopPropagation();
                const open = menu.classList.toggle('open');
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                return;
            }

            if (fullAction || pageAction) {
                menu.classList.remove('open');
                btn.setAttribute('aria-expanded', 'false');
                this.startTour();
                return;
            }

            if (!insideWrapper) {
                menu.classList.remove('open');
                btn.setAttribute('aria-expanded', 'false');
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') return;
            const menu = document.getElementById('tour-help-menu');
            const btn = document.getElementById('tour-help-button');
            if (menu && menu.classList.contains('open')) {
                menu.classList.remove('open');
                if (btn) {
                    btn.setAttribute('aria-expanded', 'false');
                    btn.focus();
                }
            }
        });
    },

    /**
     * Show or hide the "NEW" pulsing dot on the help button.
     */
    updateNewBadge() {
        const badge = document.getElementById('tour-new-badge');
        if (!badge) return;
        badge.style.display = this.hasSeenTour ? 'none' : 'block';
    }
};

if (typeof window !== 'undefined') {
    window.Walkthrough = Walkthrough;
    // Bind the help-button click handler immediately, independent of Driver.js.
    // This guarantees the button responds even if the CDN script is slow or fails.
    try {
        Walkthrough.bindHelpButton();
        console.log('[Walkthrough] script loaded, help button delegate bound');
    } catch (e) {
        console.error('[Walkthrough] bindHelpButton failed', e);
    }
}
