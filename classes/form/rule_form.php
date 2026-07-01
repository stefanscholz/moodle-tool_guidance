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

namespace tool_guidance\form;

use tool_guidance\local\condition\evaluator;
use tool_guidance\local\profile\course_profile;
use tool_guidance\local\profile\fact_catalogue;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Add/edit form for a suggestion rule.
 *
 * @package    tool_guidance
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule_form extends \moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'name', get_string('rule_name', 'tool_guidance'),
            ['size' => 50]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $signals = [
            'gap'        => get_string('signal_gap', 'tool_guidance'),
            'lifecycle'  => get_string('signal_lifecycle', 'tool_guidance'),
            'engagement' => get_string('signal_engagement', 'tool_guidance'),
        ];
        $mform->addElement('select', 'signaltype', get_string('rule_signal', 'tool_guidance'), $signals);

        $mform->addElement('select', 'suggestmod', get_string('rule_suggest', 'tool_guidance'),
            self::module_options());

        $mform->addElement('textarea', 'conditiontext', get_string('rule_condition', 'tool_guidance'),
            ['rows' => 3, 'cols' => 70, 'style' => 'font-family:monospace;']);
        $mform->setType('conditiontext', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('conditiontext', 'rule_condition', 'tool_guidance');

        $mform->addElement('static', 'facts', get_string('availablefacts', 'tool_guidance'),
            self::facts_hint());

        $mform->addElement('textarea', 'rationale', get_string('rule_rationale', 'tool_guidance'),
            ['rows' => 2, 'cols' => 70]);
        $mform->setType('rationale', PARAM_TEXT);
        $mform->addRule('rationale', null, 'required', null, 'client');

        $mform->addElement('text', 'preconfig', get_string('rule_preconfig', 'tool_guidance'),
            ['size' => 70]);
        $mform->setType('preconfig', PARAM_TEXT);
        $mform->addHelpButton('preconfig', 'rule_preconfig', 'tool_guidance');

        $mform->addElement('text', 'sortorder', get_string('rule_sortorder', 'tool_guidance'),
            ['size' => 6]);
        $mform->setType('sortorder', PARAM_INT);

        $mform->addElement('advcheckbox', 'enabled', get_string('rule_enabled', 'tool_guidance'));
        $mform->setDefault('enabled', 1);

        $this->add_action_buttons();
    }

    /**
     * Validate the condition expression and chosen module.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $result = evaluator::validate($data['conditiontext'] ?? '');
        if ($result !== true) {
            $errors['conditiontext'] = get_string('conditioninvalid', 'tool_guidance', $result);
        }

        if (!array_key_exists($data['suggestmod'] ?? '', self::module_options())) {
            $errors['suggestmod'] = get_string('error');
        }

        return $errors;
    }

    /**
     * Enabled activity modules as id => display name.
     *
     * @return array<string, string>
     */
    private static function module_options(): array {
        $options = [];
        foreach (\core\plugin_manager::instance()->get_enabled_plugins('mod') as $modname) {
            $options[$modname] = get_string('pluginname', 'mod_' . $modname);
        }
        \core_collator::asort($options);
        return $options;
    }

    /**
     * A short hint listing the available facts for rule authors.
     *
     * @return string
     */
    private static function facts_hint(): string {
        $keys = array_keys(fact_catalogue::scalar_facts());
        $keys[] = fact_catalogue::MODULE_COUNT_PREFIX . '<modname>';
        return \html_writer::tag('small', s(implode(', ', $keys)));
    }
}
