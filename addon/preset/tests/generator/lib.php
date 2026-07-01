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
 * Test data generator for the Guidance activity presets subplugin.
 *
 * @package    guidanceaddon_preset
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use guidanceaddon_preset\local\preset_manager;

/**
 * Generator: creates preset records and attaches backup files.
 */
class guidanceaddon_preset_generator extends component_generator_base {

    /** @var int Counter for unique short names. */
    protected $presetcount = 0;

    /**
     * Create a preset record, optionally attaching a .mbz backup file.
     *
     * @param array $record Overrides for the preset record.
     * @param \stored_file|null $backup Backup file to store in the preset's backup area.
     * @return \stdClass The created preset record.
     */
    public function create_preset(array $record = [], ?\stored_file $backup = null): \stdClass {
        global $DB;

        $this->presetcount++;
        $i = $this->presetcount;
        $now = time();

        $record = (object) array_merge([
            'shortname' => 'preset_' . $i,
            'title' => 'Preset ' . $i,
            'description' => 'Description ' . $i,
            'descriptionformat' => FORMAT_HTML,
            'modname' => 'page',
            'backupfile' => 'preset_' . $i . '.mbz',
            'status' => 1,
            'sortorder' => $i,
            'usermodified' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ], $record);

        $record->id = $DB->insert_record(preset_manager::TABLE, $record);

        if ($backup !== null) {
            $fs = get_file_storage();
            $fs->create_file_from_storedfile((object) [
                'contextid' => \context_system::instance()->id,
                'component' => preset_manager::COMPONENT,
                'filearea' => preset_manager::FILEAREA_BACKUP,
                'itemid' => $record->id,
                'filepath' => '/',
                'filename' => $record->backupfile,
            ], $backup);
        }

        return $record;
    }
}
