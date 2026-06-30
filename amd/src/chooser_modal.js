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
 * Opens the guidance decision-tree chooser in a modal, like the activity chooser.
 *
 * Progressive enhancement: triggers are normal links to chooser.php, so the
 * chooser still works as a full page without JavaScript. When this module is
 * loaded, clicking a trigger opens the chooser in a modal and steps through the
 * tree in place by fetching the chooser node fragment.
 *
 * @module     tool_guidance/chooser_modal
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal';
import {getString} from 'core/str';

const SELECTORS = {
    TRIGGER: '[data-action="guidance-chooser"]',
    REGION: '[data-region="tool-guidance-node"]',
    STEP: '.tool-guidance-answer, [data-action="startover"]',
};

let initialised = false;

/**
 * Fetch a chooser URL and return its node region element.
 *
 * @param {string} url The chooser URL to load.
 * @returns {Promise<Element|null>}
 */
const fetchRegion = async(url) => {
    const response = await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
    const html = await response.text();
    return new DOMParser().parseFromString(html, 'text/html').querySelector(SELECTORS.REGION);
};

/**
 * Open the chooser modal and load the given URL into it.
 *
 * @param {string} url The chooser URL to open.
 * @returns {Promise<void>}
 */
const openModal = async(url) => {
    const modal = await Modal.create({
        title: getString('choosertitle', 'tool_guidance'),
        large: true,
        removeOnClose: true,
    });

    const setRegion = async(target) => {
        const body = modal.getBody()[0];
        if (body) {
            body.classList.add('tool-guidance-loading');
        }
        const fresh = await fetchRegion(target);
        if (!fresh) {
            window.location.href = target;
            return;
        }
        modal.setBodyContent(fresh.outerHTML);
    };

    // Step through the tree in place: each answer/start-over link reloads the modal body.
    modal.getRoot().on('click', SELECTORS.STEP, (e) => {
        e.preventDefault();
        setRegion(e.currentTarget.href);
    });

    await modal.show();
    await setRegion(url);
};

/**
 * Initialise the trigger handling.
 */
export const init = () => {
    if (initialised) {
        return;
    }
    initialised = true;

    document.addEventListener('click', (e) => {
        const trigger = e.target.closest(SELECTORS.TRIGGER);
        if (!trigger) {
            return;
        }
        e.preventDefault();
        openModal(trigger.href);
    });
};
