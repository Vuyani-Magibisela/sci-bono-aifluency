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
                            <h3>Sci-Bono AI Fluency</h3>
                            <p>Empowering students with artificial intelligence literacy for the future.</p>
                            <div class="footer-social">
                                <a href="#" aria-label="Facebook" title="Facebook">
                                    <i class="fab fa-facebook"></i>
                                </a>
                                <a href="#" aria-label="Twitter" title="Twitter">
                                    <i class="fab fa-twitter"></i>
                                </a>
                                <a href="#" aria-label="LinkedIn" title="LinkedIn">
                                    <i class="fab fa-linkedin"></i>
                                </a>
                                <a href="#" aria-label="YouTube" title="YouTube">
                                    <i class="fab fa-youtube"></i>
                                </a>
                            </div>
                        </div>

                        <div class="footer-section footer-links">
                            <h4>Quick Links</h4>
                            <ul>
                                <li><a href="/index.html">Home</a></li>
                                <li><a href="/courses.html">Courses</a></li>
                                <li><a href="/projects.html">Projects</a></li>
                                <li><a href="#about">About Us</a></li>
                            </ul>
                        </div>

                        <div class="footer-section footer-resources">
                            <h4>Resources</h4>
                            <ul>
                                <li><a href="/module1.html">Module 1: AI Foundations</a></li>
                                <li><a href="/module2.html">Module 2: Generative AI</a></li>
                                <li><a href="/module3.html">Module 3: Advanced Search</a></li>
                                <li><a href="/module4.html">Module 4: Responsible AI</a></li>
                            </ul>
                        </div>

                        <div class="footer-section footer-contact">
                            <h4>Contact</h4>
                            <ul>
                                <li>
                                    <i class="fas fa-envelope"></i>
                                    <a href="mailto:info@scibono.co.za">info@scibono.co.za</a>
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
