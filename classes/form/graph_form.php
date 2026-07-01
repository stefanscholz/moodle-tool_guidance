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
 * Create/edit form for a guidance graph.
 *
 * @package    tool_guidance
 * @copyright  2026 Lily Asshauer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guidance\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Graph metadata form.
 */
class graph_form extends \moodleform {
    #[\Override]
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'name', get_string('graphname', 'tool_guidance'), ['size' => 50]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('text', 'idnumber', get_string('graphidnumber', 'tool_guidance'), ['size' => 30]);
        $mform->setType('idnumber', PARAM_RAW);
        $mform->addHelpButton('idnumber', 'graphidnumber', 'tool_guidance');

        $mform->addElement('editor', 'description', get_string('graphdescription', 'tool_guidance'));
        $mform->setType('description', PARAM_RAW);

        $mform->addElement('advcheckbox', 'enabled', get_string('graphenabled', 'tool_guidance'));
        $mform->setType('enabled', PARAM_BOOL);

        $this->add_action_buttons();
    }
}
