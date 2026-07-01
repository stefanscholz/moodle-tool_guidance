<?php
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
 * Form to create a preset from an existing course activity.
 *
 * @package    guidanceaddon_preset
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace guidanceaddon_preset\form;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

use guidanceaddon_preset\local\preset_manager;

/**
 * Two-phase form: choose a course, then choose one of its activities plus metadata.
 */
class from_activity_form extends \moodleform {

    /**
     * Define the form elements based on whether a course has been chosen.
     *
     * Expects customdata: ['courseid' => int, 'coursename' => string,
     * 'activities' => [cmid => name]].
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;
        $courseid = (int) ($this->_customdata['courseid'] ?? 0);

        if (!$courseid) {
            // Phase 1: pick a course.
            $mform->addElement(
                'course',
                'courseid',
                get_string('choosecourse', 'guidanceaddon_preset'),
                ['multiple' => false]
            );
            $mform->addRule('courseid', get_string('required'), 'required');
            $this->add_action_buttons(true, get_string('listactivities', 'guidanceaddon_preset'));
            return;
        }

        // Phase 2: pick an activity in the chosen course and describe the preset.
        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('static', 'coursename',
            get_string('choosecourse', 'guidanceaddon_preset'),
            $this->_customdata['coursename'] ?? '');

        $mform->addElement(
            'select',
            'cmid',
            get_string('sourceactivity', 'guidanceaddon_preset'),
            $this->_customdata['activities'] ?? []
        );
        $mform->setType('cmid', PARAM_INT);
        $mform->addRule('cmid', get_string('required'), 'required');

        $mform->addElement('text', 'title', get_string('title', 'guidanceaddon_preset'), ['size' => 60]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', get_string('required'), 'required');

        $mform->addElement('text', 'shortname', get_string('shortname', 'guidanceaddon_preset'));
        $mform->setType('shortname', PARAM_ALPHANUMEXT);
        $mform->addRule('shortname', get_string('required'), 'required');
        $mform->addHelpButton('shortname', 'shortname', 'guidanceaddon_preset');

        $mform->addElement(
            'editor',
            'description_editor',
            get_string('presetdescription', 'guidanceaddon_preset'),
            null,
            preset_manager::description_editor_options()
        );
        $mform->setType('description_editor', PARAM_RAW);

        $mform->addElement(
            'checkbox',
            'status',
            get_string('status', 'guidanceaddon_preset'),
            get_string('statuslabel', 'guidanceaddon_preset')
        );
        $mform->setDefault('status', 1);

        $mform->addElement('text', 'sortorder', get_string('presetorder', 'guidanceaddon_preset'), ['size' => 4]);
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 0);

        $this->add_action_buttons(true, get_string('createpreset', 'guidanceaddon_preset'));
    }

    /**
     * Validate the submitted data (phase 2 only).
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        global $DB;
        $errors = parent::validation($data, $files);

        if (!empty($data['shortname'])
                && $DB->record_exists(preset_manager::TABLE, ['shortname' => $data['shortname']])) {
            $errors['shortname'] = get_string('shortnametaken', 'guidanceaddon_preset');
        }

        // The chosen activity must belong to the chosen course.
        if (!empty($data['cmid']) && !array_key_exists($data['cmid'], $this->_customdata['activities'] ?? [])) {
            $errors['cmid'] = get_string('chooseactivity', 'guidanceaddon_preset');
        }

        return $errors;
    }
}
