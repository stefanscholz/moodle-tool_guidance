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
 * Target that recommends creating a course activity.
 *
 * @package    tool_guidance
 * @copyright  2026 Lily Asshauer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guidance\target;

/**
 * Points at an activity module type to add (e.g. resource, url, page).
 *
 * Config: ['modname' => string]. The concrete add URL needs a course/section
 * context which the teacher-facing traversal supplies later; the backend only
 * stores and validates the module name.
 */
class activity extends base {
    #[\Override]
    public function get_type(): string {
        return 'activity';
    }

    #[\Override]
    public function validate_config() {
        $modname = $this->config['modname'] ?? '';
        if ($modname === '') {
            return new \lang_string('error:activitymodnamerequired', 'tool_guidance');
        }
        $installed = \core_plugin_manager::instance()->get_installed_plugins('mod');
        if (!isset($installed[$modname])) {
            return new \lang_string('error:activitymodnameunknown', 'tool_guidance', $modname);
        }
        return true;
    }

    #[\Override]
    public function add_config_form_elements(\MoodleQuickForm $mform): void {
        $mods = [];
        foreach (\core_plugin_manager::instance()->get_plugins_of_type('mod') as $plugin) {
            $mods[$plugin->name] = $plugin->displayname;
        }
        \core_collator::asort($mods);
        $mform->addElement(
            'select',
            'target_modname',
            get_string('target:activity:modname', 'tool_guidance'),
            $mods
        );
        $mform->setType('target_modname', PARAM_PLUGIN);
    }

    #[\Override]
    public function get_action_url(): ?\moodle_url {
        // Resolved by the teacher-facing traversal with a course context.
        return null;
    }
}
