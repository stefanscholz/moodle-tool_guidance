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
 * Target that recommends a bundled activity preset.
 *
 * @package    tool_guidance
 * @copyright  2026 Lily Asshauer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guidance\target;

/**
 * Points at a named preset shipped by the guidanceaddon_preset subplugin.
 *
 * Config: ['shortname' => string]. The preset addon is optional, so this target
 * only stores and validates the short name; the concrete "apply" URL needs a
 * course context and is resolved by the teacher-facing traversal. When the
 * preset addon is absent the target degrades to a display-only recommendation.
 */
class preset extends base {

    /** @var string Fully-qualified preset manager class provided by the addon. */
    const MANAGER = '\\guidanceaddon_preset\\local\\preset_manager';

    #[\Override]
    public function get_type(): string {
        return 'preset';
    }

    #[\Override]
    public function validate_config() {
        $shortname = trim((string) ($this->config['shortname'] ?? ''));
        if ($shortname === '') {
            return new \lang_string('error:presetshortnamerequired', 'tool_guidance');
        }
        // A short name is all that is stored. We deliberately do not require the
        // preset to exist yet: the preset addon is optional and its bundled
        // backups may be seeded later, so a leaf can reference a preset ahead of
        // time. The chooser degrades to a display-only card until it resolves.
        return true;
    }

    #[\Override]
    public function add_config_form_elements(\MoodleQuickForm $mform): void {
        if (class_exists(self::MANAGER)) {
            $manager = self::MANAGER;
            $options = [];
            foreach ($manager::get_enabled() as $record) {
                $options[$record->shortname] = $record->title . ' (' . $record->shortname . ')';
            }
            $mform->addElement(
                'select',
                'target_shortname',
                get_string('target:preset:shortname', 'tool_guidance'),
                $options
            );
            $mform->setType('target_shortname', PARAM_ALPHANUMEXT);
            return;
        }
        // Addon not installed: fall back to a free-text short name.
        $mform->addElement(
            'text',
            'target_shortname',
            get_string('target:preset:shortname', 'tool_guidance'),
            ['size' => 40]
        );
        $mform->setType('target_shortname', PARAM_ALPHANUMEXT);
    }

    #[\Override]
    public function get_action_url(): ?\moodle_url {
        // Resolved by the teacher-facing traversal with a course context.
        return null;
    }

    /**
     * The configured preset short name.
     *
     * @return string
     */
    public function get_shortname(): string {
        return trim((string) ($this->config['shortname'] ?? ''));
    }
}
