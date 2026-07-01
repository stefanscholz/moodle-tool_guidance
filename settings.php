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
 * Admin settings and rule-management link for the Guidance tool.
 *
 * @package    tool_guidance
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Register the plugin under Site administration > Plugins > Admin tools.
    $ADMIN->add('tools', new admin_category('tool_guidance', get_string('pluginname', 'tool_guidance')));

    $settingspage = new admin_settingpage('tool_guidance_settings', get_string('settings', 'tool_guidance'));

    $settingspage->add(new admin_setting_configcheckbox(
        'tool_guidance/enableai',
        get_string('enableai', 'tool_guidance'),
        get_string('enableai_desc', 'tool_guidance'),
        0));

    $settingspage->add(new admin_setting_configtext(
        'tool_guidance/cooldowndays',
        get_string('cooldowndays', 'tool_guidance'),
        get_string('cooldowndays_desc', 'tool_guidance'),
        30, PARAM_INT));

    $settingspage->add(new admin_setting_configcheckbox(
        'tool_guidance/enableengagementfacts',
        get_string('enableengagementfacts', 'tool_guidance'),
        get_string('enableengagementfacts_desc', 'tool_guidance'),
        1));

    $ADMIN->add('tool_guidance', $settingspage);

    $ADMIN->add('tool_guidance', new admin_externalpage(
        'tool_guidance_managerules',
        get_string('managerules', 'tool_guidance'),
        new moodle_url('/admin/tool/guidance/manage_rules.php'),
        'tool/guidance:managerules'));

    // We added our own category/pages above; prevent the framework adding a default page.
    $settings = null;
}
