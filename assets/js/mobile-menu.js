/**
 * Mobile Menu Handler - WosKaraoke Admin
 * Handles hamburger menu for mobile navigation
 */

(function () {
    'use strict';

    // Wait for DOM
    document.addEventListener('DOMContentLoaded', initMobileMenu);

    function initMobileMenu() {
        // Create hamburger button if not exists
        if (!document.querySelector('.menu-toggle')) {
            const menuBtn = document.createElement('button');
            menuBtn.className = 'menu-toggle';
            menuBtn.setAttribute('aria-label', 'Menu');
            menuBtn.innerHTML = '<span></span><span></span><span></span>';
            document.body.insertBefore(menuBtn, document.body.firstChild);
        }

        // Create overlay if not exists
        if (!document.querySelector('.sidebar-overlay')) {
            const overlay = document.createElement('div');
            overlay.className = 'sidebar-overlay';
            document.body.insertBefore(overlay, document.body.firstChild);
        }

        // Add close button to sidebar if not exists
        const sidebar = document.querySelector('.sidebar');
        if (sidebar && !sidebar.querySelector('.sidebar-close')) {
            const closeBtn = document.createElement('button');
            closeBtn.className = 'sidebar-close';
            closeBtn.innerHTML = '✕';
            closeBtn.setAttribute('aria-label', 'Fechar menu');
            sidebar.insertBefore(closeBtn, sidebar.firstChild);
        }

        // Event handlers
        const menuToggle = document.querySelector('.menu-toggle');
        const sidebarOverlay = document.querySelector('.sidebar-overlay');
        const sidebarClose = document.querySelector('.sidebar-close');

        if (menuToggle) {
            menuToggle.addEventListener('click', openMenu);
        }

        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', closeMenu);
        }

        if (sidebarClose) {
            sidebarClose.addEventListener('click', closeMenu);
        }

        // Close menu on nav link click (mobile)
        document.querySelectorAll('.nav-link, .sidebar a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    closeMenu();
                }
            });
        });

        // Close on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeMenu();
            }
        });

        // Handle resize
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                closeMenu();
            }
        });
    }

    function openMenu() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.sidebar-overlay');

        if (sidebar) sidebar.classList.add('open');
        if (overlay) overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeMenu() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.sidebar-overlay');

        if (sidebar) sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    // Export for manual use
    window.openMobileMenu = openMenu;
    window.closeMobileMenu = closeMenu;
})();
