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
 * Registry mapping target type keys to their implementing classes.
 *
 * @package    tool_guidance
 * @copyright  2026 Lily Asshauer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guidance\target;

/**
 * Resolves target type keys to target objects.
 *
 * To add a new target type, add a subclass of base and register it here. No
 * database schema change is required.
 */
class manager {
    /** @var array<string,class-string<base>> Map of type key => class. */
    const TYPES = [
        'activity' => activity::class,
        'preset'   => preset::class,
        'route'    => route::class,
        'url'      => url::class,
    ];

    /**
     * Does a target type with this key exist?
     *
     * @param string $type
     * @return bool
     */
    public static function type_exists(string $type): bool {
        return isset(self::TYPES[$type]);
    }

    /**
     * Build a target object for a type key and config.
     *
     * @param string $type
     * @param array $config
     * @return base
     */
    public static function get_target(string $type, array $config = []): base {
        if (!self::type_exists($type)) {
            throw new \coding_exception('Unknown guidance target type: ' . $type);
        }
        $class = self::TYPES[$type];
        return new $class($config);
    }

    /**
     * Options for a target type select element: key => localised name.
     *
     * @return array<string,string>
     */
    public static function get_menu(): array {
        $menu = [];
        foreach (array_keys(self::TYPES) as $type) {
            $menu[$type] = get_string('target:' . $type, 'tool_guidance');
        }
        return $menu;
    }
}
