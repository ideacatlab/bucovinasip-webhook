/**
 * @file Manages all site navigation interactions.
 *
 * This module initializes all functionality related to the main navigation bar,
 * mobile menu, collapsible sub-menus, and smooth scrolling for anchor links.
 * It's designed to be self-contained and easily dropped into any project
 * that follows the expected HTML structure.
 *
 * @module navigation
 * @see README.md for HTML structure requirements.
 */

import { $, $$, toggleClass } from './utils.js';

/**
 * Initializes all navigation event listeners and functionality.
 */
export function initNavigation() {
  const navbar = $('#navbar');
  const mobileMenuButton = $('#mobile-menu-button');
  const mobileMenu = $('#mobile-menu');

  // --- State Manager for Mobile Menu ---
  const setMobileMenuState = (open) => {
    if (!mobileMenuButton || !mobileMenu) return;

    mobileMenuButton.setAttribute('aria-expanded', String(open));
    toggleClass(mobileMenu, 'hidden', !open);

    const icon = mobileMenuButton.querySelector('svg');
    if (icon) {
      icon.innerHTML = open
        ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>'
        : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>';
    }
  };

  // --- Navbar Scroll Appearance ---
  const applyNavbarStyles = () => {
    if (!navbar) return;
    const isScrolled = window.scrollY > 50;
    toggleClass(navbar, 'bg-gray-900/80', isScrolled);
    toggleClass(navbar, 'shadow-lg', isScrolled);
    toggleClass(navbar, 'border-white/20', isScrolled);
    toggleClass(navbar, 'bg-gray-900/50', !isScrolled);
    toggleClass(navbar, 'border-white/10', !isScrolled);
  };

  // --- Mobile Menu Toggle & UX ---
  const setupMobileMenu = () => {
    if (!mobileMenuButton || !mobileMenu) return;
    // Toggle on click
    mobileMenuButton.addEventListener('click', () => {
      const isOpen = !mobileMenu.classList.contains('hidden');
      setMobileMenuState(!isOpen);
    });

    // Close on outside click
    document.addEventListener('click', (e) => {
      const isOpen = !mobileMenu.classList.contains('hidden');
      if (!isOpen) return;
      const isClickInside = mobileMenu.contains(e.target) || mobileMenuButton.contains(e.target);
      if (!isClickInside) setMobileMenuState(false);
    });

    // Close on 'Escape' key press
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') setMobileMenuState(false);
    });

    // Close when a link inside the menu is clicked
    $$('#mobile-menu a').forEach(link =>
      link.addEventListener('click', () => setMobileMenuState(false))
    );
  };

  // --- Mobile Collapsible Sub-menus ---
  const setupCollapsible = (toggleSel, menuSel, caretSel) => {
    const toggle = $(toggleSel);
    const menu = $(menuSel);
    const caret = $(caretSel);
    if (!toggle || !menu) return;

    const setState = (open) => {
      toggleClass(menu, 'hidden', !open);
      if (caret) caret.style.transform = open ? 'rotate(180deg)' : 'rotate(0deg)';
      toggle.setAttribute('aria-expanded', String(open));
    };

    toggle.addEventListener('click', (e) => {
      e.preventDefault();
      const isOpen = !menu.classList.contains('hidden');
      setState(!isOpen);
    });

    setState(false); // Initial state
  };

  // --- Smooth Scroll for Same-Page Anchors ---
  const setupSmoothScroll = () => {
    $$('a[href^="#"]').forEach((link) => {
      link.addEventListener('click', (e) => {
        const href = link.getAttribute('href');
        if (!href || href.length <= 1) return;
        
        const target = $(href);
        if (!target) return;

        e.preventDefault();
        const headerHeight = navbar ? navbar.offsetHeight : 0;
        const targetPosition = target.getBoundingClientRect().top + window.scrollY - headerHeight - 20;
        
        window.scrollTo({
          top: targetPosition,
          behavior: 'smooth'
        });
        
        // Also close the mobile menu if it's open
        setMobileMenuState(false);
      });
    });
  };

  // --- Initialize All Features ---
  applyNavbarStyles(); // Initial check
  window.addEventListener('scroll', applyNavbarStyles, { passive: true });
  
  setMobileMenuState(false); // Initial state
  setupMobileMenu();
  
  setupCollapsible('#mobile-marketplace-toggle', '#mobile-marketplace-menu', '#mobile-marketplace-caret');
  setupCollapsible('#mobile-services-toggle', '#mobile-services-menu', '#mobile-services-caret');
  
  setupSmoothScroll();
}
