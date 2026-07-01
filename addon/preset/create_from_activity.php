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
 * Create a guidance activity preset from an existing course activity.
 *
 * @package    guidanceaddon_preset
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use guidanceaddon_preset\form\from_activity_form;
use guidanceaddon_preset\local\preset_manager;

admin_externalpage_setup('guidanceaddon_preset_manage');

$courseid = optional_param('courseid', 0, PARAM_INT);

$context = context_system::instance();
require_capability('guidanceaddon/preset:manage', $context);

$manageurl = new moodle_url('/admin/tool/guidance/addon/preset/manage.php');
$pageurl = new moodle_url('/admin/tool/guidance/addon/preset/create_from_activity.php');
$PAGE->set_url($pageurl);

// Build the activity list for the chosen course.
$activities = [];
$coursename = '';
$modinfo = null;
if ($courseid) {
    $course = get_course($courseid);
    $coursename = format_string(get_course_display_name_for_list($course));
    $modinfo = get_fast_modinfo($courseid);
    foreach ($modinfo->get_cms() as $cm) {
        if ($cm->deletioninprogress) {
            continue;
        }
        $activities[$cm->id] = $cm->get_module_type_name() . ': ' . $cm->get_formatted_name();
    }
}

$formurl = new moodle_url($pageurl, $courseid ? ['courseid' => $courseid] : []);
$mform = new from_activity_form($formurl->out(false), [
    'courseid' => $courseid,
    'coursename' => $coursename,
    'activities' => $activities,
]);

if ($mform->is_cancelled()) {
    redirect($manageurl);
} else if ($data = $mform->get_data()) {
    if (!$courseid) {
        // Phase 1: a course was chosen — reload to pick one of its activities.
        redirect(new moodle_url($pageurl, ['courseid' => (int) $data->courseid]));
    }

    // Phase 2: back up the chosen activity into a new preset.
    $cm = $modinfo->get_cm($data->cmid);
    require_capability('moodle/backup:backupactivity', context_module::instance($cm->id));

    $meta = (object) [
        'shortname' => $data->shortname,
        'title' => $data->title,
        'description' => $data->description_editor['text'],
        'descriptionformat' => $data->description_editor['format'],
        'status' => empty($data->status) ? 0 : 1,
        'sortorder' => $data->sortorder,
    ];
    $preset = preset_manager::create_from_cm($cm, $meta);

    // Persist any files embedded in the description editor.
    $description = file_save_draft_area_files(
        $data->description_editor['itemid'],
        $context->id,
        preset_manager::COMPONENT,
        preset_manager::FILEAREA_DESCRIPTION,
        $preset->id,
        preset_manager::description_editor_options(),
        $meta->description
    );
    $DB->set_field(preset_manager::TABLE, 'description', $description, ['id' => $preset->id]);

    redirect($manageurl, get_string('presetcreatedfromactivity', 'guidanceaddon_preset'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('createfromactivity', 'guidanceaddon_preset'));

if ($courseid && !$activities) {
    // Chosen course has nothing to back up: send the admin back to pick another.
    echo $OUTPUT->notification(get_string('nocourseactivities', 'guidanceaddon_preset'), 'info');
    echo $OUTPUT->single_button($pageurl, get_string('choosecourse', 'guidanceaddon_preset'), 'get');
} else {
    $mform->display();
}

echo $OUTPUT->footer();
