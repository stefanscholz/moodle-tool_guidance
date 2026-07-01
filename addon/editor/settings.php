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
 * Registers the guidance graph editor management page.
 *
 * Included by tool_guidance\plugininfo\guidanceaddon::load_settings with the
 * shared admin tree ($ADMIN) available; the parent registers the tool_guidance
 * admin category before loading addon settings.
 *
 * @package    guidanceaddon_editor
 * @copyright  2026 Lily Asshauer, bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('tool_guidance', new admin_externalpage(
        'tool_guidance_managegraphs',
        get_string('managegraphs', 'tool_guidance'),
        new moodle_url('/admin/tool/guidance/addon/editor/index.php'),
        'tool/guidance:manage'
    ));
}
