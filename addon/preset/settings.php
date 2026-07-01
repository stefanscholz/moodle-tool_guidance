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
 * Admin settings for the Guidance activity presets subplugin.
 *
 * @package    guidanceaddon_preset
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Settings page: enable/disable toggle.
    $settings = new admin_settingpage(
        'guidanceaddon_preset',
        get_string('pluginname', 'guidanceaddon_preset')
    );
    $settings->add(new admin_setting_configcheckbox(
        'guidanceaddon_preset/enabled',
        get_string('enabled', 'guidanceaddon_preset'),
        get_string('enabled_desc', 'guidanceaddon_preset'),
        1
    ));
    $ADMIN->add('tools', $settings);

    // Management page for the preset list.
    $ADMIN->add('tools', new admin_externalpage(
        'guidanceaddon_preset_manage',
        get_string('managepresets', 'guidanceaddon_preset'),
        new moodle_url('/admin/tool/guidance/addon/preset/manage.php'),
        'guidanceaddon/preset:manage'
    ));
}
