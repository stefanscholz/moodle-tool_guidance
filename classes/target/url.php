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
 * Target that links to an external URL.
 *
 * @package    tool_guidance
 * @copyright  2026 Lily Asshauer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guidance\target;

/**
 * Points at an arbitrary external URL.
 *
 * Config: ['url' => string, 'newwindow' => bool].
 */
class url extends base {
    #[\Override]
    public function get_type(): string {
        return 'url';
    }

    #[\Override]
    public function validate_config() {
        $value = trim((string) ($this->config['url'] ?? ''));
        if ($value === '') {
            return new \lang_string('error:urlrequired', 'tool_guidance');
        }
        if (!filter_var($value, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $value)) {
            return new \lang_string('error:urlinvalid', 'tool_guidance');
        }
        return true;
    }

    #[\Override]
    public function add_config_form_elements(\MoodleQuickForm $mform): void {
        $mform->addElement(
            'text',
            'target_url',
            get_string('target:url:url', 'tool_guidance'),
            ['size' => 60]
        );
        $mform->setType('target_url', PARAM_URL);

        $mform->addElement(
            'advcheckbox',
            'target_newwindow',
            get_string('target:url:newwindow', 'tool_guidance')
        );
        $mform->setType('target_newwindow', PARAM_BOOL);
    }

    #[\Override]
    public function get_action_url(): ?\moodle_url {
        $value = trim((string) ($this->config['url'] ?? ''));
        if ($value === '') {
            return null;
        }
        return new \moodle_url($value);
    }

    /**
     * Whether the link should open in a new window.
     *
     * @return bool
     */
    public function opens_in_new_window(): bool {
        return !empty($this->config['newwindow']);
    }
}
