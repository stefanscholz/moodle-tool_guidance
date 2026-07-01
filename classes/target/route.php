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
 * Target that links to an internal Moodle route/settings page.
 *
 * @package    tool_guidance
 * @copyright  2026 Lily Asshauer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guidance\target;

/**
 * Points at a Moodle-relative path, e.g. /admin/settings.php?section=modsettingurl.
 *
 * Config: ['path' => string] where path is a site-relative URL beginning with "/".
 */
class route extends base {
    #[\Override]
    public function get_type(): string {
        return 'route';
    }

    #[\Override]
    public function validate_config() {
        $path = trim((string) ($this->config['path'] ?? ''));
        if ($path === '') {
            return new \lang_string('error:routepathrequired', 'tool_guidance');
        }
        if (strpos($path, '/') !== 0) {
            return new \lang_string('error:routepathrelative', 'tool_guidance');
        }
        return true;
    }

    #[\Override]
    public function add_config_form_elements(\MoodleQuickForm $mform): void {
        $mform->addElement(
            'text',
            'target_path',
            get_string('target:route:path', 'tool_guidance'),
            ['size' => 60]
        );
        $mform->setType('target_path', PARAM_LOCALURL);
        $mform->addHelpButton('target_path', 'target:route:path', 'tool_guidance');
    }

    #[\Override]
    public function get_action_url(): ?\moodle_url {
        $path = trim((string) ($this->config['path'] ?? ''));
        if ($path === '') {
            return null;
        }
        // The moodle_url constructor parses any query string included in the path.
        return new \moodle_url($path);
    }
}
