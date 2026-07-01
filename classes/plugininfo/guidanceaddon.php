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
 * Subplugin type definition for the Guidance tool.
 *
 * @package   tool_guidance
 * @copyright 2026 bdecent gmbh <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guidance\plugininfo;

/**
 * Guidanceaddon is a subplugin of tool_guidance.
 */
class guidanceaddon extends \core\plugininfo\base {

    /**
     * Returns whether the subplugin is enabled.
     *
     * An addon is enabled unless its 'enabled' config flag is explicitly set to 0.
     *
     * @return null|bool
     */
    public function is_enabled() {
        $enabled = get_config($this->component, 'enabled');
        // Treat an unset flag as enabled so freshly installed addons work out of the box.
        return ($enabled === false) ? true : (bool) $enabled;
    }

    /**
     * Uninstallation is allowed via the admin UI.
     *
     * @return bool
     */
    public function is_uninstall_allowed() {
        return true;
    }

    /**
     * Load the addon's settings.php into the admin tree.
     *
     * The base plugininfo does not load subplugin settings, so we mirror the
     * pattern used by core's local/mod plugininfo classes.
     *
     * @param \part_of_admin_tree $adminroot
     * @param string $parentnodename
     * @param bool $hassiteconfig
     */
    public function load_settings(\part_of_admin_tree $adminroot, $parentnodename, $hassiteconfig) {
        global $CFG, $USER, $DB, $OUTPUT, $PAGE; // In case settings.php wants to refer to them.
        $ADMIN = $adminroot; // May be used in settings.php.
        $plugininfo = $this; // Also can be used inside settings.php.

        if (!$this->is_installed_and_upgraded()) {
            return;
        }

        if (file_exists($this->full_path('settings.php'))) {
            include($this->full_path('settings.php'));
        }
    }

    /**
     * Return instances of the enabled addons that implement the given method.
     *
     * Mirrors the discovery hook used by tool_skills so future addons can extend
     * behaviour without tool_guidance depending on any single one of them.
     *
     * @param string $method Method name the addon must implement.
     * @return array
     */
    public function get_plugins_base($method) {
        $extend = [];
        $plugins = \core_component::get_plugin_list('guidanceaddon');

        foreach ($plugins as $componentname => $pluginpath) {
            $classname = "guidanceaddon_$componentname\\local\\manager";
            if (!class_exists($classname)) {
                continue;
            }
            if (method_exists($classname, $method)) {
                $extend[] = new $classname();
            }
        }
        return $extend;
    }
}
