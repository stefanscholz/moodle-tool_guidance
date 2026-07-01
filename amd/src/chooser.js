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
 * Progressive enhancement for the guidance decision-tree chooser.
 *
 * The page works fully without JavaScript (each answer is a normal link). This
 * module intercepts those links to swap the active node in place and keep the
 * node id in the URL, so the back button and deep-links keep working.
 *
 * @module     tool_guidance/chooser
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    ROOT: '[data-region="tool-guidance-chooser"]',
    REGION: '[data-region="tool-guidance-node"]',
    ANSWER: '.tool-guidance-answer',
    STARTOVER: '[data-action="startover"]',
};

/**
 * Fetch a chooser URL and swap in the fresh node fragment.
 *
 * Falls back to a normal navigation if anything goes wrong.
 *
 * @param {string} url The chooser URL to load.
 * @returns {Promise<void>}
 */
const navigate = async(url) => {
    const region = document.querySelector(SELECTORS.REGION);
    if (!region) {
        window.location.href = url;
        return;
    }

    region.classList.add('tool-guidance-loading');
    try {
        const response = await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
        const html = await response.text();
        const fresh = new DOMParser().parseFromString(html, 'text/html').querySelector(SELECTORS.REGION);
        if (!fresh) {
            window.location.href = url;
            return;
        }
        region.innerHTML = fresh.innerHTML;
        window.history.pushState({}, '', url);
        region.focus({preventScroll: false});
    } catch (e) {
        window.location.href = url;
    } finally {
        region.classList.remove('tool-guidance-loading');
    }
};

/**
 * Initialise the chooser behaviour.
 */
export const init = () => {
    const root = document.querySelector(SELECTORS.ROOT);
    if (!root) {
        return;
    }

    root.addEventListener('click', (e) => {
        const link = e.target.closest(`${SELECTORS.ANSWER}, ${SELECTORS.STARTOVER}`);
        if (!link) {
            return;
        }
        e.preventDefault();
        navigate(link.href);
    });

    window.addEventListener('popstate', () => {
        navigate(window.location.href);
    });
};
