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
 * Admin settings for the Guidance starter content subplugin.
 *
 * @package    guidanceaddon_starter
 * @copyright  2026 Lily Asshauer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Settings page: enable/disable toggle.
    $settings = new admin_settingpage(
        'guidanceaddon_starter',
        get_string('pluginname', 'guidanceaddon_starter')
    );
    $settings->add(new admin_setting_configcheckbox(
        'guidanceaddon_starter/enabled',
        get_string('enabled', 'guidanceaddon_starter'),
        get_string('enabled_desc', 'guidanceaddon_starter'),
        1
    ));
    $ADMIN->add('tools', $settings);
}
