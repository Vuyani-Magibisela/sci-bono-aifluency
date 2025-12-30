/**
 * GSAP Animations Library
 * Centralized animation utilities for AI Fluency LMS
 *
 * Features:
 * - Animated counters (numbers, percentages)
 * - Progress bar animations
 * - Chart transitions
 * - Card fade-in/slide-in effects
 * - Achievement unlock animations
 * - Page transitions
 *
 * Requirements: GSAP 3.x (loaded via CDN)
 * Usage: Call Animations.init() on page load
 */

const Animations = {
    /**
     * Default animation settings
     */
    defaults: {
        duration: 1.2,
        ease: 'power2.out',
        stagger: 0.1,
        delay: 0.2
    },

    /**
     * Initialize animations
     * Call this on page load to set up default animations
     */
    init() {
        if (typeof gsap === 'undefined') {
            console.error('Animations: GSAP not loaded. Please include GSAP CDN.');
            return false;
        }

        console.log('Animations: Initializing GSAP animations...');

        // Register GSAP plugins if available
        if (typeof ScrollTrigger !== 'undefined') {
            gsap.registerPlugin(ScrollTrigger);
        }

        // Set up page entrance animations
        this.animatePageEntrance();

        console.log('Animations: Initialization complete');
        return true;
    },

    /**
     * Animate page entrance (fade in main content)
     */
    animatePageEntrance() {
        const mainContent = document.querySelector('.dashboard-content, .main-content');
        if (!mainContent) return;

        gsap.from(mainContent, {
            opacity: 0,
            y: 20,
            duration: 0.6,
            ease: 'power2.out'
        });
    },

    /**
     * Animate number counter from 0 to target value
     * @param {string|HTMLElement} element - Element selector or DOM element
     * @param {number} endValue - Target number
     * @param {object} options - Animation options
     */
    animateCounter(element, endValue, options = {}) {
        const el = typeof element === 'string' ? document.querySelector(element) : element;
        if (!el) {
            console.warn('Animations: Counter element not found:', element);
            return;
        }

        const duration = options.duration || this.defaults.duration;
        const ease = options.ease || this.defaults.ease;
        const suffix = options.suffix || '';
        const prefix = options.prefix || '';
        const decimals = options.decimals || 0;

        // Create counter object
        const counter = { value: 0 };

        gsap.to(counter, {
            value: endValue,
            duration: duration,
            ease: ease,
            onUpdate: function() {
                const displayValue = decimals > 0
                    ? counter.value.toFixed(decimals)
                    : Math.round(counter.value);
                el.textContent = prefix + displayValue + suffix;
            },
            onComplete: function() {
                // Ensure final value is exact
                const displayValue = decimals > 0
                    ? endValue.toFixed(decimals)
                    : endValue;
                el.textContent = prefix + displayValue + suffix;
            }
        });
    },

    /**
     * Animate percentage counter (0% to target%)
     * @param {string|HTMLElement} element - Element selector or DOM element
     * @param {number} percentage - Target percentage (0-100)
     * @param {object} options - Animation options
     */
    animatePercentage(element, percentage, options = {}) {
        this.animateCounter(element, percentage, {
            ...options,
            suffix: '%'
        });
    },

    /**
     * Animate progress bar fill
     * @param {string|HTMLElement} progressBar - Progress bar element
     * @param {number} percentage - Target percentage (0-100)
     * @param {object} options - Animation options
     */
    animateProgressBar(progressBar, percentage, options = {}) {
        const bar = typeof progressBar === 'string'
            ? document.querySelector(progressBar)
            : progressBar;

        if (!bar) {
            console.warn('Animations: Progress bar not found:', progressBar);
            return;
        }

        const duration = options.duration || this.defaults.duration;
        const ease = options.ease || 'power2.inOut';
        const delay = options.delay || 0;
        const color = options.color || null;

        // Animate width
        gsap.to(bar, {
            width: percentage + '%',
            duration: duration,
            ease: ease,
            delay: delay,
            onStart: function() {
                if (color) {
                    bar.style.backgroundColor = color;
                }
            }
        });

        // Optional: Pulse effect on completion
        if (options.pulse) {
            gsap.to(bar, {
                scale: 1.05,
                duration: 0.2,
                delay: duration + delay,
                yoyo: true,
                repeat: 1,
                ease: 'power2.inOut'
            });
        }
    },

    /**
     * Animate circular progress (SVG circle)
     * @param {string|HTMLElement} circle - SVG circle element
     * @param {number} percentage - Target percentage (0-100)
     * @param {object} options - Animation options
     */
    animateCircularProgress(circle, percentage, options = {}) {
        const circleEl = typeof circle === 'string'
            ? document.querySelector(circle)
            : circle;

        if (!circleEl) {
            console.warn('Animations: Circular progress element not found:', circle);
            return;
        }

        const radius = parseFloat(circleEl.getAttribute('r')) || 90;
        const circumference = 2 * Math.PI * radius;
        const offset = circumference - (percentage / 100) * circumference;

        const duration = options.duration || this.defaults.duration;
        const ease = options.ease || 'power2.inOut';

        // Set initial state
        circleEl.style.strokeDasharray = circumference;
        circleEl.style.strokeDashoffset = circumference;

        // Animate to target
        gsap.to(circleEl, {
            strokeDashoffset: offset,
            duration: duration,
            ease: ease
        });
    },

    /**
     * Fade in elements with stagger effect
     * @param {string|NodeList} elements - Elements to animate
     * @param {object} options - Animation options
     */
    fadeInStagger(elements, options = {}) {
        const els = typeof elements === 'string'
            ? document.querySelectorAll(elements)
            : elements;

        if (!els || els.length === 0) {
            console.warn('Animations: No elements found for fadeInStagger:', elements);
            return;
        }

        const duration = options.duration || 0.8;
        const stagger = options.stagger || this.defaults.stagger;
        const delay = options.delay || 0;
        const y = options.y || 30;

        gsap.from(els, {
            opacity: 0,
            y: y,
            duration: duration,
            stagger: stagger,
            delay: delay,
            ease: 'power2.out'
        });
    },

    /**
     * Slide in elements from direction
     * @param {string|NodeList} elements - Elements to animate
     * @param {string} direction - 'left', 'right', 'up', 'down'
     * @param {object} options - Animation options
     */
    slideIn(elements, direction = 'up', options = {}) {
        const els = typeof elements === 'string'
            ? document.querySelectorAll(elements)
            : elements;

        if (!els || els.length === 0) {
            console.warn('Animations: No elements found for slideIn:', elements);
            return;
        }

        const duration = options.duration || 0.8;
        const stagger = options.stagger || this.defaults.stagger;
        const distance = options.distance || 50;

        const fromProps = { opacity: 0 };

        switch(direction) {
            case 'left':
                fromProps.x = -distance;
                break;
            case 'right':
                fromProps.x = distance;
                break;
            case 'up':
                fromProps.y = distance;
                break;
            case 'down':
                fromProps.y = -distance;
                break;
        }

        gsap.from(els, {
            ...fromProps,
            duration: duration,
            stagger: stagger,
            ease: 'power2.out'
        });
    },

    /**
     * Achievement unlock animation (pop + bounce)
     * @param {string|HTMLElement} element - Achievement element
     * @param {object} options - Animation options
     */
    achievementUnlock(element, options = {}) {
        const el = typeof element === 'string'
            ? document.querySelector(element)
            : element;

        if (!el) {
            console.warn('Animations: Achievement element not found:', element);
            return;
        }

        const duration = options.duration || 0.6;

        // Create timeline for complex animation
        const tl = gsap.timeline();

        // Start small and invisible
        gsap.set(el, { scale: 0, opacity: 0 });

        // Pop in with bounce
        tl.to(el, {
            scale: 1.2,
            opacity: 1,
            duration: duration * 0.5,
            ease: 'back.out(2)'
        })
        .to(el, {
            scale: 1,
            duration: duration * 0.5,
            ease: 'elastic.out(1, 0.5)'
        });

        // Add glow effect
        if (options.glow) {
            tl.to(el, {
                boxShadow: '0 0 20px rgba(76, 175, 80, 0.6)',
                duration: 0.3,
                yoyo: true,
                repeat: 1
            }, '-=0.3');
        }

        // Add confetti effect (if callback provided)
        if (options.onUnlock && typeof options.onUnlock === 'function') {
            tl.call(options.onUnlock);
        }

        return tl;
    },

    /**
     * Card hover animation
     * @param {string|HTMLElement} card - Card element
     */
    cardHover(card) {
        const cardEl = typeof card === 'string'
            ? document.querySelector(card)
            : card;

        if (!cardEl) return;

        cardEl.addEventListener('mouseenter', () => {
            gsap.to(cardEl, {
                y: -5,
                boxShadow: '0 8px 16px rgba(0,0,0,0.15)',
                duration: 0.3,
                ease: 'power2.out'
            });
        });

        cardEl.addEventListener('mouseleave', () => {
            gsap.to(cardEl, {
                y: 0,
                boxShadow: '0 2px 8px rgba(0,0,0,0.1)',
                duration: 0.3,
                ease: 'power2.out'
            });
        });
    },

    /**
     * Pulse animation (attention grabber)
     * @param {string|HTMLElement} element - Element to pulse
     * @param {object} options - Animation options
     */
    pulse(element, options = {}) {
        const el = typeof element === 'string'
            ? document.querySelector(element)
            : element;

        if (!el) return;

        const scale = options.scale || 1.1;
        const duration = options.duration || 0.5;
        const repeat = options.repeat || 2;

        gsap.to(el, {
            scale: scale,
            duration: duration,
            yoyo: true,
            repeat: repeat,
            ease: 'power2.inOut'
        });
    },

    /**
     * Shake animation (error state)
     * @param {string|HTMLElement} element - Element to shake
     * @param {object} options - Animation options
     */
    shake(element, options = {}) {
        const el = typeof element === 'string'
            ? document.querySelector(element)
            : element;

        if (!el) return;

        const distance = options.distance || 10;
        const duration = options.duration || 0.5;

        gsap.to(el, {
            x: distance,
            duration: duration / 6,
            yoyo: true,
            repeat: 5,
            ease: 'power2.inOut',
            onComplete: () => {
                gsap.set(el, { x: 0 });
            }
        });
    },

    /**
     * Loading spinner animation
     * @param {string|HTMLElement} spinner - Spinner element
     */
    spin(spinner) {
        const el = typeof spinner === 'string'
            ? document.querySelector(spinner)
            : spinner;

        if (!el) return;

        gsap.to(el, {
            rotation: 360,
            duration: 1,
            repeat: -1,
            ease: 'linear'
        });
    },

    /**
     * Notification slide-in animation
     * @param {string|HTMLElement} notification - Notification element
     * @param {string} position - 'top-right', 'top-left', 'bottom-right', 'bottom-left'
     * @param {object} options - Animation options
     */
    notificationSlideIn(notification, position = 'top-right', options = {}) {
        const el = typeof notification === 'string'
            ? document.querySelector(notification)
            : notification;

        if (!el) return;

        const duration = options.duration || 0.5;
        const autoHide = options.autoHide || false;
        const autoHideDuration = options.autoHideDuration || 3000;

        // Determine slide direction based on position
        let fromProps = { opacity: 0 };
        if (position.includes('right')) {
            fromProps.x = 100;
        } else if (position.includes('left')) {
            fromProps.x = -100;
        }

        if (position.includes('top')) {
            fromProps.y = -100;
        } else if (position.includes('bottom')) {
            fromProps.y = 100;
        }

        // Slide in
        const tl = gsap.timeline();
        tl.from(el, {
            ...fromProps,
            duration: duration,
            ease: 'power2.out'
        });

        // Auto-hide if enabled
        if (autoHide) {
            tl.to(el, {
                opacity: 0,
                x: fromProps.x || 0,
                duration: duration,
                delay: autoHideDuration / 1000,
                ease: 'power2.in',
                onComplete: () => {
                    el.style.display = 'none';
                }
            });
        }

        return tl;
    },

    /**
     * Chart bar animation (for bar charts)
     * @param {string|NodeList} bars - Bar elements
     * @param {object} options - Animation options
     */
    animateChartBars(bars, options = {}) {
        const barEls = typeof bars === 'string'
            ? document.querySelectorAll(bars)
            : bars;

        if (!barEls || barEls.length === 0) return;

        const duration = options.duration || 1;
        const stagger = options.stagger || 0.1;

        gsap.from(barEls, {
            scaleY: 0,
            transformOrigin: 'bottom',
            duration: duration,
            stagger: stagger,
            ease: 'power2.out'
        });
    },

    /**
     * Text reveal animation (line by line)
     * @param {string|HTMLElement} element - Text element
     * @param {object} options - Animation options
     */
    textReveal(element, options = {}) {
        const el = typeof element === 'string'
            ? document.querySelector(element)
            : element;

        if (!el) return;

        const duration = options.duration || 0.8;
        const stagger = options.stagger || 0.05;

        // Split text into characters/words
        const text = el.textContent;
        const chars = text.split('');

        el.innerHTML = chars.map(char =>
            `<span style="display: inline-block; opacity: 0;">${char === ' ' ? '&nbsp;' : char}</span>`
        ).join('');

        const spans = el.querySelectorAll('span');

        gsap.to(spans, {
            opacity: 1,
            duration: duration,
            stagger: stagger,
            ease: 'power2.out'
        });
    },

    /**
     * Parallax effect on scroll
     * @param {string|HTMLElement} element - Element to parallax
     * @param {object} options - Animation options
     */
    parallax(element, options = {}) {
        const el = typeof element === 'string'
            ? document.querySelector(element)
            : element;

        if (!el || typeof ScrollTrigger === 'undefined') return;

        const speed = options.speed || 0.5;

        gsap.to(el, {
            y: () => window.innerHeight * speed,
            ease: 'none',
            scrollTrigger: {
                trigger: el,
                start: 'top bottom',
                end: 'bottom top',
                scrub: true
            }
        });
    },

    /**
     * Badge bounce animation
     * @param {string|HTMLElement} badge - Badge element
     */
    badgeBounce(badge) {
        const el = typeof badge === 'string'
            ? document.querySelector(badge)
            : badge;

        if (!el) return;

        gsap.from(el, {
            scale: 0,
            duration: 0.5,
            ease: 'elastic.out(1, 0.5)',
            delay: 0.2
        });
    },

    /**
     * Kill all active animations
     */
    killAll() {
        gsap.killTweensOf('*');
    }
};

// Auto-initialize if GSAP is loaded
if (typeof gsap !== 'undefined' && document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => Animations.init());
} else if (typeof gsap !== 'undefined') {
    Animations.init();
}
