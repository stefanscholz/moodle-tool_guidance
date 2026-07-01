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
 * Create an activity in a course from a guidance preset.
 *
 * @package    guidanceaddon_preset
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../../../config.php');

$presetid = required_param('presetid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$sectionnum = optional_param('section', 0, PARAM_INT);

require_sesskey();

$course = get_course($courseid);
require_login($course);

$context = context_course::instance($course->id);
// Creating an activity requires the same capability core requires to add a module.
require_capability('moodle/course:manageactivities', $context);

$result = \guidanceaddon_preset\local\apply::apply($presetid, $course, $sectionnum);

$viewurl = new moodle_url('/mod/' . $result->modname . '/view.php', ['id' => $result->cmid]);
redirect(
    $viewurl,
    get_string('presetapplied', 'guidanceaddon_preset'),
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
