/**
 * @file Handles the initialization of Lottie animations.
 *
 * This module imports the Lottie player, loads animation data from a JSON file,
 * and renders it into a specified container element on the page.
 *
 * @module lottie
 * @see README.md for HTML structure requirements.
 */

import lottie from 'lottie-web/build/player/lottie_light';
import timelineAnimationData from '../animations/timeline.json';
import { $ } from './utils.js';

/**
 * Finds the Lottie container and initializes the timeline animation.
 */
export function initLottieAnimations() {
  const timelineContainer = $('#lottie-timeline');
  
  if (timelineContainer) {
    lottie.loadAnimation({
      container: timelineContainer,
      renderer: 'svg',
      loop: true,
      autoplay: true,
      animationData: timelineAnimationData,
    });
  }
}
