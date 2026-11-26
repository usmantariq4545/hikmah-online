// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Home page v2 rows that scroll sideways: topics (with arrows), teachers, and courses on phones.
 * A row only moves when its items overflow it, pauses while hovered, touched or focused, and stays
 * still for visitors who prefer reduced motion.
 *
 * @module     theme_hikmah/home2
 * @copyright  2026 Hikmah Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/**
 * Ease a row to a scroll position. Scroll snapping is paused while it moves, so the glide is one
 * continuous motion instead of the browser's stop-start smooth scroll.
 *
 * @param {HTMLElement} track
 * @param {number} target scrollLeft to end at
 */
const glide = (track, target) => {
    if (reduced()) {
        track.scrollLeft = target;
        return;
    }
    window.cancelAnimationFrame(track.hk2Frame);
    const from = track.scrollLeft;
    const distance = target - from;
    const duration = Math.min(1100, 650 + Math.abs(distance) / 4);
    const started = performance.now();
    const ease = (t) => (t < .5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);
    track.style.scrollSnapType = 'none';
    const tick = (now) => {
        const progress = Math.min(1, (now - started) / duration);
        track.scrollLeft = from + distance * ease(progress);
        if (progress < 1) {
            track.hk2Frame = window.requestAnimationFrame(tick);
        } else {
            track.style.scrollSnapType = '';
        }
    };
    track.hk2Frame = window.requestAnimationFrame(tick);
};

/**
 * Scroll a row by one item, wrapping round at either end.
 *
 * @param {HTMLElement} track
 * @param {number} direction 1 for next, -1 for previous
 */
const step = (track, direction) => {
    const max = track.scrollWidth - track.clientWidth;
    if (max <= 4) {
        return;
    }
    const first = track.firstElementChild;
    const gap = parseFloat(window.getComputedStyle(track).columnGap) || 0;
    const width = first ? first.getBoundingClientRect().width + gap : track.clientWidth;
    let target;
    if (direction > 0 && track.scrollLeft >= max - 4) {
        target = 0;
    } else if (direction < 0 && track.scrollLeft <= 4) {
        target = max;
    } else {
        // Land exactly on a card edge, never half way through one.
        target = Math.min(max, Math.max(0, (Math.round(track.scrollLeft / width) + direction) * width));
    }
    glide(track, target);
};

/**
 * Start the timer for one row and wire its pause, resume and arrow buttons.
 *
 * @param {HTMLElement} track element with data-hk2-track="<name>" and data-hk2-every="<ms>"
 * @param {ParentNode} root
 */
const setupTrack = (track, root) => {
    const name = track.dataset.hk2Track;
    const every = parseInt(track.dataset.hk2Every, 10) || 4000;
    let timer = null;

    const stop = () => {
        window.clearInterval(timer);
        timer = null;
    };
    const start = () => {
        stop();
        if (!reduced()) {
            timer = window.setInterval(() => step(track, 1), every);
        }
    };

    ['mouseenter', 'touchstart', 'focusin'].forEach((type) => track.addEventListener(type, stop, {passive: true}));
    ['mouseleave', 'touchend', 'focusout'].forEach((type) => track.addEventListener(type, start, {passive: true}));

    root.querySelectorAll(`[data-hk2-prev="${name}"], [data-hk2-next="${name}"]`).forEach((button) => {
        button.addEventListener('click', () => {
            step(track, button.hasAttribute('data-hk2-next') ? 1 : -1);
            start();
        });
    });

    start();
};

/**
 * Initialise every scrolling row on the page.
 */
export const init = () => {
    const root = document.querySelector('.hk2');
    if (!root) {
        return;
    }
    root.querySelectorAll('[data-hk2-track]').forEach((track) => setupTrack(track, root));
};
