/**
 * Footer Template Module
 * Dynamically generates footer HTML for all pages
 * Part of Phase 1: Frontend-Backend Integration
 * Date: November 11, 2025
 */

const FooterTemplate = {
    /**
     * Render footer HTML
     * @param {string} containerId - ID of container element (default: 'footer-placeholder')
     */
    render(containerId = 'footer-placeholder') {
        const container = document.getElementById(containerId);

        if (!container) {
            console.warn(`Footer container #${containerId} not found`);
            return;
        }

        const currentYear = new Date().getFullYear();

        const footerHTML = `
            <footer>
                <div class="footer-content">
                    <div class="footer-main">
                        <div class="footer-section footer-about">
                            <h3>Sci-Bono Ai Hub</h3>
                            <p>Empowering students with artificial intelligence literacy for the future.</p>
                            <div class="footer-social">
                                <a href="https://www.facebook.com/SciBono01" aria-label="Facebook" title="Facebook" target="_blank" rel="noopener noreferrer">
                                    <i class="fab fa-facebook"></i>
                                </a>
                                <a href="https://www.x.com/SciBono" aria-label="X" title="X" target="_blank" rel="noopener noreferrer">
                                    <i class="fab fa-x-twitter"></i>
                                </a>
                                <a href="https://www.instagram.com/scibono_discovery_centre/" aria-label="Instagram" title="Instagram" target="_blank" rel="noopener noreferrer">
                                    <i class="fab fa-instagram"></i>
                                </a>
                                <a href="https://www.youtube.com/@SciBono01" aria-label="YouTube" title="YouTube" target="_blank" rel="noopener noreferrer">
                                    <i class="fab fa-youtube"></i>
                                </a>
                            </div>
                        </div>

                        <div class="footer-section footer-links">
                            <h4>Quick Links</h4>
                            <ul>
                                <li><a href="/index.html">Home</a></li>
                                <li><a href="/about-course.html">Courses</a></li>
                                <li><a href="/student/projects/index.html">Projects</a></li>
                            </ul>
                        </div>

                        <div class="footer-section footer-contact">
                            <h4>Contact</h4>
                            <ul>
                                <li>
                                    <i class="fas fa-envelope"></i>
                                    <a href="mailto:vuyani.magibisela@sci-bono.co.za">vuyani.magibisela@sci-bono.co.za</a>
                                </li>
                                <li>
                                    <i class="fas fa-phone"></i>
                                    <span>+27 (0)11 639 8400</span>
                                </li>
                                <li>
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span>Newtown, Johannesburg</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="footer-bottom">
                        <p>&copy; ${currentYear} Sci-Bono Discovery Centre. All rights reserved.</p>
                        <div class="footer-legal">
                            <a href="/privacy-policy.html">Privacy Policy</a>
                            <span class="separator">|</span>
                            <a href="/terms-of-use.html">Terms of Use</a>
                            <span class="separator">|</span>
                            <a href="/cookie-policy.html">Cookie Policy</a>
                        </div>
                    </div>
                </div>
            </footer>
        `;

        container.innerHTML = footerHTML;
        console.log('Footer template rendered');
    },

    /**
     * Initialize footer on page load
     */
    init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.render());
        } else {
            this.render();
        }
    }
};

// Auto-initialize footer when script loads
FooterTemplate.init();

// Export for use in other modules (if needed)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = FooterTemplate;
}
