/**
 * Mobile Menu Script for SuperAdmin
 * Adds hamburger button and handles sidebar toggle
 */

(function () {
    'use strict';

    // Only run on mobile
    function initMobileMenu() {
        const sidebar = document.querySelector('.sidebar');
        if (!sidebar) return;

        // Check if elements already exist
        if (document.querySelector('.menu-toggle')) return;

        // Create hamburger button
        const menuToggle = document.createElement('button');
        menuToggle.className = 'menu-toggle';
        menuToggle.setAttribute('aria-label', 'Menu');
        menuToggle.innerHTML = `
            <span></span>
            <span></span>
            <span></span>
        `;

        // Create overlay
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';

        // Create close button inside sidebar
        const closeBtn = document.createElement('button');
        closeBtn.className = 'sidebar-close';
        closeBtn.innerHTML = '&times;';
        closeBtn.setAttribute('aria-label', 'Fechar menu');

        // Insert elements
        document.body.prepend(menuToggle);
        document.body.prepend(overlay);
        sidebar.prepend(closeBtn);

        // Toggle function
        function toggleSidebar(open) {
            sidebar.classList.toggle('open', open);
            overlay.classList.toggle('active', open);
            menuToggle.setAttribute('aria-expanded', open);

            // Prevent body scroll when menu is open
            document.body.style.overflow = open ? 'hidden' : '';
        }

        // Event listeners
        menuToggle.addEventListener('click', () => toggleSidebar(true));
        overlay.addEventListener('click', () => toggleSidebar(false));
        closeBtn.addEventListener('click', () => toggleSidebar(false));

        // Close on nav link click (mobile)
        sidebar.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    toggleSidebar(false);
                }
            });
        });

        // Close on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                toggleSidebar(false);
            }
        });

        // Handle resize
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                toggleSidebar(false);
            }
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMobileMenu);
    } else {
        initMobileMenu();
    }
})();
