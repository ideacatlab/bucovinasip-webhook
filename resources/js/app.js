/**
 * @file Main application entry point.
 *
 * This file imports all necessary JavaScript modules and initializes them
 * after the DOM is fully loaded. It acts as an orchestrator for the
 * application's frontend logic.
 */

import './bootstrap';
import { initNavigation } from './navigation.js';
import { initCarousels } from './carousel.js';

// Wait for the DOM to be ready before running any scripts.
document.addEventListener('DOMContentLoaded', () => {
  initNavigation();
  initCarousels();
});
