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
use tool_guidance\local\condition\parser;
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
        $mform->addHelpButton('name', 'rule_name', 'tool_guidance');

        $signals = [
            'gap'        => get_string('signal_gap', 'tool_guidance'),
            'lifecycle'  => get_string('signal_lifecycle', 'tool_guidance'),
            'engagement' => get_string('signal_engagement', 'tool_guidance'),
        ];
        $mform->addElement('select', 'signal', get_string('rule_signal', 'tool_guidance'), $signals);
        $mform->addHelpButton('signal', 'rule_signal', 'tool_guidance');

        $mform->addElement('select', 'suggestmod', get_string('rule_suggest', 'tool_guidance'),
            self::module_options());
        $mform->addHelpButton('suggestmod', 'rule_suggest', 'tool_guidance');

        global $PAGE, $OUTPUT;
        $uniqid = \html_writer::random_id('tool_guidance_cond_');
        $builder = $OUTPUT->render_from_template('tool_guidance/condition_builder', [
            'id' => $uniqid,
            'facts' => json_encode(fact_catalogue::for_form()),
            'strings' => json_encode(self::builder_strings()),
        ]);

        $mform->addElement('hidden', 'conditionclauses', '');
        $mform->setType('conditionclauses', PARAM_RAW);

        $mform->addElement('static', 'conditionbuilder', get_string('rule_condition', 'tool_guidance'), $builder);
        $mform->addHelpButton('conditionbuilder', 'rule_condition', 'tool_guidance');

        $PAGE->requires->js_call_amd('tool_guidance/condition_builder', 'init', [$uniqid]);

        $mform->addElement('textarea', 'rationale', get_string('rule_rationale', 'tool_guidance'),
            ['rows' => 2, 'cols' => 70]);
        $mform->setType('rationale', PARAM_TEXT);
        $mform->addRule('rationale', null, 'required', null, 'client');
        $mform->addHelpButton('rationale', 'rule_rationale', 'tool_guidance');

        $mform->addElement('text', 'preconfig', get_string('rule_preconfig', 'tool_guidance'),
            ['size' => 70]);
        $mform->setType('preconfig', PARAM_TEXT);
        $mform->addHelpButton('preconfig', 'rule_preconfig', 'tool_guidance');

        $mform->addElement('text', 'sortorder', get_string('rule_sortorder', 'tool_guidance'),
            ['size' => 6]);
        $mform->setType('sortorder', PARAM_INT);
        $mform->addHelpButton('sortorder', 'rule_sortorder', 'tool_guidance');

        $mform->addElement('advcheckbox', 'enabled', get_string('rule_enabled', 'tool_guidance'));
        $mform->setDefault('enabled', 1);
        $mform->addHelpButton('enabled', 'rule_enabled', 'tool_guidance');

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

        $clauses = json_decode($data['conditionclauses'] ?? '[]', true);
        if (!is_array($clauses)) {
            $clauses = [];
        }

        $incomplete = false;
        foreach ($clauses as $clause) {
            if (empty($clause['fact']) || empty($clause['op'])) {
                $incomplete = true;
                break;
            }
            $kind = $clause['operandkind'] ?? 'literal';
            $value = $clause['value'] ?? '';
            $empty = $kind === 'set' ? !array_filter((array) $value) : (trim((string) $value) === '');
            if ($empty) {
                $incomplete = true;
                break;
            }
        }

        if ($incomplete) {
            $errors['conditionbuilder'] = get_string('conditionincomplete', 'tool_guidance');
        } else {
            $result = evaluator::validate(parser::compile($clauses));
            if ($result !== true) {
                $errors['conditionbuilder'] = get_string('conditioninvalid', 'tool_guidance', $result);
            }
        }

        if (!array_key_exists($data['suggestmod'] ?? '', self::module_options())) {
            $errors['suggestmod'] = get_string('error');
        }

        return $errors;
    }

    /**
     * Compile the submitted clause JSON into a condition DSL string.
     *
     * @param string $json
     * @return string
     */
    public static function compile_clauses(string $json): string {
        $clauses = json_decode($json, true);
        return is_array($clauses) ? parser::compile($clauses) : '';
    }

    /**
     * The translated UI strings the condition builder JS needs, passed to it via a
     * data attribute so the AMD module stays dependency-free.
     *
     * @return array
     */
    private static function builder_strings(): array {
        return [
            'yes' => get_string('condition_yes', 'tool_guidance'),
            'no' => get_string('condition_no', 'tool_guidance'),
            'remove' => get_string('condition_removeclause', 'tool_guidance'),
            'literal' => get_string('condition_valuetype_literal', 'tool_guidance'),
            'fact' => get_string('condition_valuetype_fact', 'tool_guidance'),
            'matchesall' => get_string('condition_matchesall', 'tool_guidance'),
            'incomplete' => get_string('condition_incomplete', 'tool_guidance'),
            'ops' => [
                '==' => get_string('op_eq', 'tool_guidance'),
                '!=' => get_string('op_neq', 'tool_guidance'),
                '<' => get_string('op_lt', 'tool_guidance'),
                '<=' => get_string('op_lte', 'tool_guidance'),
                '>' => get_string('op_gt', 'tool_guidance'),
                '>=' => get_string('op_gte', 'tool_guidance'),
                'in' => get_string('op_in', 'tool_guidance'),
            ],
        ];
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
}
