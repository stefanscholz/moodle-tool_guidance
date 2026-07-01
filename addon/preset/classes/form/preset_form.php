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
 * Create / edit form for a guidance activity preset.
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
 * Preset create / update form definition.
 */
class preset_form extends \moodleform {

    /**
     * Define the form elements.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_ALPHA);

        // Title.
        $mform->addElement('text', 'title', get_string('title', 'guidanceaddon_preset'), ['size' => 60]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', get_string('required'), 'required');

        // Short name (stable identifier referenced by the decision tree).
        $mform->addElement('text', 'shortname', get_string('shortname', 'guidanceaddon_preset'));
        $mform->setType('shortname', PARAM_ALPHANUMEXT);
        $mform->addRule('shortname', get_string('required'), 'required');
        $mform->addHelpButton('shortname', 'shortname', 'guidanceaddon_preset');

        // Description.
        $mform->addElement(
            'editor',
            'description_editor',
            get_string('presetdescription', 'guidanceaddon_preset'),
            null,
            preset_manager::description_editor_options()
        );
        $mform->setType('description_editor', PARAM_RAW);

        // Activity type (optional; used for the chooser icon).
        $mform->addElement('text', 'modname', get_string('modname', 'guidanceaddon_preset'));
        $mform->setType('modname', PARAM_PLUGIN);
        $mform->addHelpButton('modname', 'modname', 'guidanceaddon_preset');

        // Activity backup file (.mbz).
        $mform->addElement(
            'filemanager',
            'backupfile',
            get_string('backupfile', 'guidanceaddon_preset'),
            null,
            preset_manager::preset_fileoptions()
        );
        $mform->addHelpButton('backupfile', 'backupfile', 'guidanceaddon_preset');
        $mform->addRule('backupfile', get_string('backuprequired', 'guidanceaddon_preset'), 'required');

        // Status.
        $mform->addElement(
            'checkbox',
            'status',
            get_string('status', 'guidanceaddon_preset'),
            get_string('statuslabel', 'guidanceaddon_preset')
        );
        $mform->setType('status', PARAM_INT);
        $mform->setDefault('status', 1);

        // Sort order.
        $mform->addElement('text', 'sortorder', get_string('presetorder', 'guidanceaddon_preset'), ['size' => 4]);
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 0);

        $this->add_action_buttons();
    }

    /**
     * Validate the submitted data.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        global $DB;
        $errors = parent::validation($data, $files);

        // Short name must be unique across presets.
        if (!empty($data['shortname'])) {
            $params = ['shortname' => $data['shortname']];
            $select = 'shortname = :shortname';
            if (!empty($data['id'])) {
                $select .= ' AND id <> :id';
                $params['id'] = $data['id'];
            }
            if ($DB->record_exists_select(preset_manager::TABLE, $select, $params)) {
                $errors['shortname'] = get_string('shortnametaken', 'guidanceaddon_preset');
            }
        }

        return $errors;
    }
}
