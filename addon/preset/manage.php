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
 * Manage the site-wide list of guidance activity presets.
 *
 * @package    guidanceaddon_preset
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use guidanceaddon_preset\form\preset_form;
use guidanceaddon_preset\local\preset_manager;
use guidanceaddon_preset\table\preset_list;

admin_externalpage_setup('guidanceaddon_preset_manage');

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

$context = context_system::instance();
require_capability('guidanceaddon/preset:manage', $context);

$pageurl = new moodle_url('/admin/tool/guidance/addon/preset/manage.php');
$PAGE->set_url($pageurl);

// State-changing list actions require a valid session key.
if (in_array($action, ['delete', 'enable', 'disable', 'up', 'down'], true)) {
    require_sesskey();

    if (!$preset = preset_manager::get($id, false)) {
        throw new moodle_exception('invalidpresetid', 'guidanceaddon_preset');
    }

    switch ($action) {
        case 'delete':
            preset_manager::delete($id);
            redirect($pageurl, get_string('presetdeleted', 'guidanceaddon_preset'));
            break;

        case 'enable':
        case 'disable':
            $DB->set_field(preset_manager::TABLE, 'status', $action === 'enable' ? 1 : 0, ['id' => $id]);
            redirect($pageurl);
            break;

        case 'up':
        case 'down':
            guidanceaddon_preset_reorder($id, $action);
            redirect($pageurl);
            break;
    }
}

// Create / edit form.
if ($action === 'create' || $action === 'edit') {
    $mform = new preset_form($pageurl);

    if ($mform->is_cancelled()) {
        redirect($pageurl);
    } else if ($data = $mform->get_data()) {
        $now = time();
        $record = (object) [
            'shortname' => $data->shortname,
            'title' => $data->title,
            'description' => $data->description_editor['text'],
            'descriptionformat' => $data->description_editor['format'],
            'modname' => $data->modname ?: null,
            'status' => empty($data->status) ? 0 : 1,
            'sortorder' => $data->sortorder,
            'usermodified' => $USER->id,
            'timemodified' => $now,
        ];

        if (!empty($data->id)) {
            $record->id = $data->id;
            $DB->update_record(preset_manager::TABLE, $record);
            $message = get_string('presetupdated', 'guidanceaddon_preset');
        } else {
            $record->timecreated = $now;
            $record->id = $DB->insert_record(preset_manager::TABLE, $record);
            $message = get_string('presetcreated', 'guidanceaddon_preset');
        }

        // Save the description editor files and store the rewritten text.
        $description = file_save_draft_area_files(
            $data->description_editor['itemid'],
            $context->id,
            preset_manager::COMPONENT,
            preset_manager::FILEAREA_DESCRIPTION,
            $record->id,
            preset_manager::description_editor_options(),
            $data->description_editor['text']
        );
        $DB->set_field(preset_manager::TABLE, 'description', $description, ['id' => $record->id]);

        // Save the uploaded .mbz backup and cache its filename.
        file_save_draft_area_files(
            $data->backupfile,
            $context->id,
            preset_manager::COMPONENT,
            preset_manager::FILEAREA_BACKUP,
            $record->id,
            preset_manager::preset_fileoptions()
        );
        if ($file = preset_manager::get_backup_file((int) $record->id)) {
            $DB->set_field(preset_manager::TABLE, 'backupfile', $file->get_filename(), ['id' => $record->id]);
        }

        redirect($pageurl, $message);
    } else if ($action === 'edit') {
        if (!$preset = preset_manager::get($id, false)) {
            throw new moodle_exception('invalidpresetid', 'guidanceaddon_preset');
        }

        // Prepare the description editor draft area (populates $preset->description_editor).
        $preset = file_prepare_standard_editor(
            $preset,
            'description',
            preset_manager::description_editor_options(),
            $context,
            preset_manager::COMPONENT,
            preset_manager::FILEAREA_DESCRIPTION,
            $preset->id
        );

        // Prepare the backup filemanager draft area.
        $draftitemid = file_get_submitted_draft_itemid('backupfile');
        file_prepare_draft_area(
            $draftitemid,
            $context->id,
            preset_manager::COMPONENT,
            preset_manager::FILEAREA_BACKUP,
            $preset->id,
            preset_manager::preset_fileoptions()
        );
        $preset->backupfile = $draftitemid;
        $preset->action = 'edit';

        $mform->set_data($preset);
    } else {
        $mform->set_data(['action' => 'create']);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string($action === 'edit' ? 'editpreset' : 'createpreset', 'guidanceaddon_preset'));
    $mform->display();
    echo $OUTPUT->footer();
    exit;
}

// Default: list view.
$createurl = new moodle_url($pageurl, ['action' => 'create']);
$fromactivityurl = new moodle_url('/admin/tool/guidance/addon/preset/create_from_activity.php');
$PAGE->set_button(
    $OUTPUT->single_button($createurl, get_string('createpreset', 'guidanceaddon_preset'), 'get') .
    $OUTPUT->single_button($fromactivityurl, get_string('createfromactivity', 'guidanceaddon_preset'), 'get')
);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('presetlist', 'guidanceaddon_preset'));

if (!$DB->record_exists(preset_manager::TABLE, [])) {
    echo $OUTPUT->notification(get_string('nopresets', 'guidanceaddon_preset'), 'info');
} else {
    $table = new preset_list('guidanceaddon-preset-list');
    $table->set_sql('*', '{' . preset_manager::TABLE . '}', '1=1');
    $table->define_baseurl($pageurl);
    $table->out(50, false);
}

echo $OUTPUT->footer();

/**
 * Swap a preset's sort order with its neighbour.
 *
 * @param int $id Preset id.
 * @param string $direction 'up' or 'down'.
 * @return void
 */
function guidanceaddon_preset_reorder(int $id, string $direction): void {
    global $DB;

    $presets = array_values($DB->get_records(preset_manager::TABLE, null, 'sortorder ASC, id ASC'));
    $index = null;
    foreach ($presets as $i => $preset) {
        if ((int) $preset->id === $id) {
            $index = $i;
            break;
        }
    }
    if ($index === null) {
        return;
    }

    $swapwith = $direction === 'up' ? $index - 1 : $index + 1;
    if ($swapwith < 0 || $swapwith >= count($presets)) {
        return;
    }

    $current = $presets[$index];
    $neighbour = $presets[$swapwith];

    // Swap the stored sort orders (fall back to positional order if they tie).
    $currentorder = (int) $current->sortorder;
    $neighbourorder = (int) $neighbour->sortorder;
    if ($currentorder === $neighbourorder) {
        $currentorder = $index;
        $neighbourorder = $swapwith;
    }
    $DB->set_field(preset_manager::TABLE, 'sortorder', $neighbourorder, ['id' => $current->id]);
    $DB->set_field(preset_manager::TABLE, 'sortorder', $currentorder, ['id' => $neighbour->id]);
}
