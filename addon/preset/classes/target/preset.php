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
 * Leaf target that creates an activity from a stored preset.
 *
 * @package    guidanceaddon_preset
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace guidanceaddon_preset\target;

use guidanceaddon_preset\local\preset_manager;

/**
 * Config: ['presetid' => int]. Reaching this leaf applies the preset — restoring
 * the stored activity backup into the teacher's course/section.
 */
class preset extends \tool_guidance\target\base {

    #[\Override]
    public function get_type(): string {
        return 'preset';
    }

    #[\Override]
    public function get_menu_label(): string {
        return get_string('target:preset', 'guidanceaddon_preset');
    }

    #[\Override]
    public function get_action_label(): string {
        return get_string('target:preset:action', 'guidanceaddon_preset');
    }

    #[\Override]
    public function validate_config() {
        $presetid = (int) ($this->config['presetid'] ?? 0);
        if (!$presetid) {
            return new \lang_string('error:presetrequired', 'guidanceaddon_preset');
        }
        if (!preset_manager::get($presetid)) {
            return new \lang_string('error:presetunknown', 'guidanceaddon_preset');
        }
        return true;
    }

    #[\Override]
    public function add_config_form_elements(\MoodleQuickForm $mform): void {
        $options = [];
        foreach (preset_manager::get_enabled() as $preset) {
            $options[$preset->id] = format_string($preset->title);
        }
        $mform->addElement(
            'select',
            'target_presetid',
            get_string('target:preset:preset', 'guidanceaddon_preset'),
            $options
        );
        $mform->setType('target_presetid', PARAM_INT);
    }

    #[\Override]
    public function get_action_url(): ?\moodle_url {
        // Needs a course context; resolved in get_action_url_for_course().
        return null;
    }

    #[\Override]
    public function get_action_url_for_course(int $courseid, int $sectionnum = 0): ?\moodle_url {
        $presetid = (int) ($this->config['presetid'] ?? 0);
        if (!$presetid || !preset_manager::get($presetid)) {
            return null;
        }
        return new \moodle_url('/admin/tool/guidance/addon/preset/apply.php', [
            'presetid' => $presetid,
            'courseid' => $courseid,
            'section' => $sectionnum,
            'sesskey' => sesskey(),
        ]);
    }

    #[\Override]
    public function get_icon(\renderer_base $output): \moodle_url {
        $presetid = (int) ($this->config['presetid'] ?? 0);
        $preset = $presetid ? preset_manager::get($presetid) : null;
        if ($preset && $preset->modname) {
            return $output->image_url('monologo', 'mod_' . $preset->modname);
        }
        return parent::get_icon($output);
    }
}
