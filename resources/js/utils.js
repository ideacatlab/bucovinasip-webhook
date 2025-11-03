/**
 * @file Utility functions to simplify DOM manipulation.
 *
 * This module provides shorthand functions for querying the DOM and toggling CSS classes.
 * By centralizing these helpers, we ensure consistent behavior and reduce boilerplate code
 * throughout the application.
 *
 * @module utils
 */

/**
 * A shorthand for querySelector, scoped to a parent element or the document.
 * @param {string} selector - The CSS selector to match.
 * @param {Element|Document} [scope=document] - The element to search within. Defaults to the whole document.
 * @returns {Element|null} The first element matching the selector, or null if not found.
 */
export const $ = (selector, scope = document) => scope.querySelector(selector);

/**
 * A shorthand for querySelectorAll, scoped to a parent element or the document.
 * @param {string} selector - The CSS selector to match.
 * @param {Element|Document} [scope=document] - The element to search within. Defaults to the whole document.
 * @returns {NodeListOf<Element>} A NodeList of elements matching the selector.
 */
export const $$ = (selector, scope = document) => scope.querySelectorAll(selector);

/**
 * Adds or removes a CSS class from an element based on a condition.
 * @param {Element} el - The target HTML element.
 * @param {string} className - The CSS class name to toggle.
 * @param {boolean} force - If true, adds the class; if false, removes it.
 */
export const toggleClass = (el, className, force) => {
  if (el) {
    el.classList.toggle(className, force);
  }
};