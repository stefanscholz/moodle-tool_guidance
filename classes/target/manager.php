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
    /** @var array<string,class-string<base>> Core map of type key => class. */
    const TYPES = [
        'activity' => activity::class,
        'preset'   => preset::class,
        'route'    => route::class,
        'url'      => url::class,
    ];

    /**
     * All known target types (core + those contributed by guidanceaddon subplugins).
     *
     * A guidanceaddon can add target types by shipping a class
     * guidanceaddon_<name>\guidance_targets with a static get_targets() method
     * returning [typekey => classname]. No core change is needed to add a type.
     *
     * @return array<string,class-string<base>>
     */
    public static function all_types(): array {
        $types = self::TYPES;
        foreach (\core_component::get_plugin_list('guidanceaddon') as $name => $unused) {
            $class = "guidanceaddon_{$name}\\guidance_targets";
            if (class_exists($class) && method_exists($class, 'get_targets')) {
                foreach ($class::get_targets() as $key => $targetclass) {
                    $types[$key] = $targetclass;
                }
            }
        }
        return $types;
    }

    /**
     * Does a target type with this key exist?
     *
     * @param string $type
     * @return bool
     */
    public static function type_exists(string $type): bool {
        return isset(self::all_types()[$type]);
    }

    /**
     * Build a target object for a type key and config.
     *
     * @param string $type
     * @param array $config
     * @return base
     */
    public static function get_target(string $type, array $config = []): base {
        $types = self::all_types();
        if (!isset($types[$type])) {
            throw new \coding_exception('Unknown guidance target type: ' . $type);
        }
        $class = $types[$type];
        return new $class($config);
    }

    /**
     * Options for a target type select element: key => localised name.
     *
     * @return array<string,string>
     */
    public static function get_menu(): array {
        $menu = [];
        foreach (array_keys(self::all_types()) as $type) {
            $menu[$type] = self::get_target($type)->get_menu_label();
        }
        return $menu;
    }
}
