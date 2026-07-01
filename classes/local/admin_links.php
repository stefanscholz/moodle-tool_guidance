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

namespace tool_guidance\local;

/**
 * The catalogue of generic course-admin pages a rule can target.
 *
 * Each entry is a stable key mapped to a course-scoped admin URL, resolved with the
 * current course id at click time. Add entries here to offer more admin destinations.
 *
 * @package    tool_guidance
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_links {

    /**
     * Stable key => admin page path. Every page takes the course id as `id`.
     *
     * @return array<string, string>
     */
    private static function definitions(): array {
        return [
            'settings'     => '/course/edit.php',
            'enrolmethods' => '/enrol/instances.php',
            'enrolusers'   => '/user/index.php',
            'groups'       => '/group/index.php',
            'grades'       => '/grade/edit/tree/index.php',
            'completion'   => '/course/completion.php',
        ];
    }

    /**
     * Key => human label, for the rule form's admin-link dropdown.
     *
     * @return array<string, string>
     */
    public static function menu(): array {
        $menu = [];
        foreach (array_keys(self::definitions()) as $key) {
            $menu[$key] = get_string('adminlink_' . $key, 'tool_guidance');
        }
        return $menu;
    }

    /**
     * Whether a key names a known admin link.
     *
     * @param string $key
     * @return bool
     */
    public static function exists(string $key): bool {
        return array_key_exists($key, self::definitions());
    }

    /**
     * Resolve an admin-link key to a course-scoped URL, or null if unknown.
     *
     * @param string $key
     * @param int $courseid
     * @return \moodle_url|null
     */
    public static function url(string $key, int $courseid): ?\moodle_url {
        $definitions = self::definitions();
        if (!isset($definitions[$key])) {
            return null;
        }
        return new \moodle_url($definitions[$key], ['id' => $courseid]);
    }
}
