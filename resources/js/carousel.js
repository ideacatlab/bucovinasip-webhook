/**
 * @file A feature-rich, reusable carousel module.
 *
 * This module provides a flexible carousel (slider) component that can be
 * configured using data-* attributes directly in the HTML. It supports
 * autoplay, looping, navigation controls (next/prev, dots), keyboard
 * navigation, and touch/swipe gestures.
 *
 * To use, add the `data-carousel` attribute to your main slider element.
 *
 * @module Carousel
 * @see README.md for detailed HTML structure and configuration options.
 */

import { $, $$ } from './utils.js';

class Carousel {
  constructor(element) {
    this.element = element;
    this.options = this.parseOptions();

    this.track = $('[data-carousel-track]', this.element);
    this.slides = Array.from(this.track.children);
    this.prevBtn = $('[data-carousel-prev]', this.element);
    this.nextBtn = $('[data-carousel-next]', this.element);
    this.dotsContainer = $('[data-carousel-dots]', this.element);
    this.dots = this.dotsContainer ? Array.from(this.dotsContainer.children) : [];

    if (!this.track || this.slides.length === 0) {
      console.error('Carousel error: No track or slides found for', this.element);
      return;
    }

    this.currentIndex = 0;
    this.slideCount = this.slides.length;
    this.autoplayInterval = null;

    // Swipe/Drag state
    this.isDragging = false;
    this.startPos = 0;
    this.currentTranslate = 0;
    this.prevTranslate = 0;
    this.pointerId = null;

    this.bindEvents();
    this.update();
    if (this.options.autoplay) {
      this.startAutoplay();
    }
  }

  parseOptions() {
    const defaults = {
      autoplay: false,
      autoplaySpeed: 5000,
      loop: true,
      transition: 'slide',
    };

    const dataset = this.element.dataset;
    return {
      autoplay: dataset.carouselAutoplay === 'true',
      autoplaySpeed: parseInt(dataset.carouselAutoplaySpeed, 10) || defaults.autoplaySpeed,
      loop: dataset.carouselLoop !== 'false',
      transition: dataset.carouselTransition || defaults.transition,
    };
  }

  bindEvents() {
    this.prevBtn?.addEventListener('click', () => this.prev());
    this.nextBtn?.addEventListener('click', () => this.next());

    this.dots.forEach((dot, index) => {
      dot.addEventListener('click', () => this.goTo(index));
    });

    if (this.options.autoplay) {
      const interactionEvents = ['mouseenter', 'focusin', 'pointerdown'];
      const endInteractionEvents = ['mouseleave', 'focusout', 'pointerup'];
      interactionEvents.forEach(e => this.element.addEventListener(e, () => this.pauseAutoplay()));
      endInteractionEvents.forEach(e => this.element.addEventListener(e, () => this.startAutoplay()));
    }

    this.element.addEventListener('keydown', (e) => this.handleKeyDown(e));
    this.track.addEventListener('pointerdown', (e) => this.handlePointerDown(e));
    this.track.addEventListener('pointermove', (e) => this.handlePointerMove(e));
    this.track.addEventListener('pointerup', (e) => this.handlePointerUp(e));
    this.track.addEventListener('pointerleave', (e) => this.handlePointerUp(e));
  }

  next() {
    let nextIndex = this.currentIndex + 1;
    if (this.options.loop) {
      nextIndex %= this.slideCount;
    } else {
      nextIndex = Math.min(nextIndex, this.slideCount - 1);
    }
    this.goTo(nextIndex);
  }

  prev() {
    let prevIndex = this.currentIndex - 1;
    if (this.options.loop) {
      prevIndex = (prevIndex + this.slideCount) % this.slideCount;
    } else {
      prevIndex = Math.max(prevIndex, 0);
    }
    this.goTo(prevIndex);
  }

  goTo(index) {
    if (index < 0 || index >= this.slideCount) return;
    this.currentIndex = index;
    this.update();
  }

  update() {
    this.track.style.transform = `translateX(-${this.currentIndex * 100}%)`;
    this.prevTranslate = -this.currentIndex * this.track.offsetWidth;

    this.dots.forEach((dot, index) => {
      const isCurrent = index === this.currentIndex;
      dot.classList.toggle('bg-white/30', !isCurrent);
      dot.classList.toggle('opacity-60', !isCurrent);
      dot.classList.toggle('bg-gradient-to-r', isCurrent);
      dot.classList.toggle('from-teal-300', isCurrent);
      dot.classList.toggle('to-indigo-300', isCurrent);
      dot.classList.toggle('opacity-100', isCurrent);
      dot.setAttribute('aria-current', isCurrent ? 'true' : 'false');
    });
  }

  startAutoplay() {
    if (this.autoplayInterval || !this.options.autoplay) return;
    this.autoplayInterval = setInterval(() => this.next(), this.options.autoplaySpeed);
  }

  pauseAutoplay() {
    clearInterval(this.autoplayInterval);
    this.autoplayInterval = null;
  }

  handleKeyDown(e) {
    if (e.key === 'ArrowRight') this.next();
    else if (e.key === 'ArrowLeft') this.prev();
  }

  handlePointerDown(e) {
    this.isDragging = true;
    this.pointerId = e.pointerId;
    this.startPos = e.clientX;
    this.track.style.transition = 'none';
    this.track.setPointerCapture(this.pointerId);
  }

  handlePointerMove(e) {
    if (!this.isDragging) return;
    const currentPosition = e.clientX;
    this.currentTranslate = this.prevTranslate + currentPosition - this.startPos;
    this.track.style.transform = `translateX(${this.currentTranslate}px)`;
  }

  handlePointerUp(e) {
    if (!this.isDragging) return;
    this.isDragging = false;
    this.track.releasePointerCapture(this.pointerId);
    this.track.style.transition = 'transform 0.5s ease-out';
    const movedBy = this.currentTranslate - this.prevTranslate;

    if (Math.abs(movedBy) > this.track.clientWidth * 0.15) {
      if (movedBy < 0) this.next();
      else this.prev();
    } else {
      this.update(); // Snap back if not swiped far enough
    }
  }
}

export function initCarousels() {
  $$('[data-carousel]').forEach(el => new Carousel(el));
}