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
 * Admin settings: registers the guidance graph management page and addon settings.
 *
 * @package    tool_guidance
 * @copyright  2026 Lily Asshauer, bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('tools', new admin_externalpage(
        'tool_guidance_managegraphs',
        get_string('managegraphs', 'tool_guidance'),
        new moodle_url('/admin/tool/guidance/index.php'),
        'tool/guidance:manage'
    ));

    // Load settings pages for guidance addons (subplugins). Core only auto-loads
    // settings for a fixed set of plugin types, so the parent tool must load its
    // own subplugins. Kept generic so any guidanceaddon is picked up.
    foreach (core_plugin_manager::instance()->get_plugins_of_type('guidanceaddon') as $plugin) {
        $plugin->load_settings($ADMIN, 'tools', $hassiteconfig);
    }
}
