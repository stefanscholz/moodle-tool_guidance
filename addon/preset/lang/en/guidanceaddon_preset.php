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
 * Strings for the Guidance activity presets subplugin.
 *
 * @package    guidanceaddon_preset
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Activity presets';
$string['preset:manage'] = 'Manage activity presets';
$string['privacy:metadata'] = 'The Activity presets addon only stores site-level preset templates and does not store any personal data.';

// Settings.
$string['enabled'] = 'Enable activity presets';
$string['enabled_desc'] = 'When enabled, the guidance chooser can create activities from these preset templates.';

// Management page.
$string['managepresets'] = 'Manage activity presets';
$string['createpreset'] = 'Create preset';
$string['editpreset'] = 'Edit preset';
$string['presetlist'] = 'Activity presets';
$string['presetcreated'] = 'Preset created';
$string['presetupdated'] = 'Preset updated';
$string['presetdeleted'] = 'Preset deleted';
$string['confirmdeletepreset'] = 'Are you sure you want to delete this preset?';
$string['deletepreset'] = 'Delete preset';
$string['invalidpresetid'] = 'Invalid preset id';
$string['nopresets'] = 'No activity presets have been created yet.';

// Form / list fields.
$string['title'] = 'Title';
$string['shortname'] = 'Short name';
$string['shortname_help'] = 'A stable identifier for this preset (letters, numbers, - and _). The decision tree references presets by short name, so keep it unchanged once in use.';
$string['shortnametaken'] = 'This short name is already used by another preset.';
$string['presetdescription'] = 'Description';
$string['backupfile'] = 'Activity backup (.mbz)';
$string['backupfile_help'] = 'Upload a single-activity backup file (.mbz). Back up one activity from a course (not a whole course) and upload the resulting file here. Applying the preset restores that activity into the teacher\'s course.';
$string['backuprequired'] = 'An activity backup file is required.';
$string['modname'] = 'Activity type';
$string['modname_help'] = 'Optional. The activity module name (e.g. quiz, assign, forum) used for the icon shown in the chooser. Leave blank to detect it from the backup.';
$string['status'] = 'Status';
$string['statuslabel'] = 'Enabled';
$string['presetorder'] = 'Order';
$string['enabledbadge'] = 'Enabled';
$string['disabledbadge'] = 'Disabled';

// Apply flow.
$string['presetapplied'] = 'Activity created from the preset.';
$string['presetnotfound'] = 'The requested preset could not be found or is disabled.';
$string['backupfilemissing'] = 'The preset has no activity backup file.';
$string['norestoredactivity'] = 'The backup did not contain an activity to restore.';
