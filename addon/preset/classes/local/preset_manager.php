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
 * Preset lookup and storage helper.
 *
 * This is the public API that tool_guidance consumes (guarded by class_exists
 * so the tool has no hard dependency on the subplugin).
 *
 * @package    guidanceaddon_preset
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace guidanceaddon_preset\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Manages the tool_guidance_presets records and their backup files.
 */
class preset_manager {

    /** @var string Database table storing presets. */
    const TABLE = 'tool_guidance_presets';

    /** @var string Component under which preset files are stored. */
    const COMPONENT = 'guidanceaddon_preset';

    /** @var string File area for the .mbz activity backup. */
    const FILEAREA_BACKUP = 'backup';

    /** @var string File area for the description editor files. */
    const FILEAREA_DESCRIPTION = 'description';

    /**
     * File manager options for the backup (.mbz) file.
     *
     * @return array
     */
    public static function preset_fileoptions(): array {
        return [
            'subdirs'        => 0,
            'maxfiles'       => 1,
            'accepted_types' => ['.mbz'],
            'return_types'   => FILE_INTERNAL | FILE_EXTERNAL,
        ];
    }

    /**
     * Editor options for the description field.
     *
     * @return array
     */
    public static function description_editor_options(): array {
        return [
            'subdirs' => 0,
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'context' => \context_system::instance(),
            'trusttext' => true,
        ];
    }

    /**
     * Get a preset record by id.
     *
     * @param int $id Preset id.
     * @param bool $enabledonly Only return the record if it is enabled.
     * @return \stdClass|null
     */
    public static function get(int $id, bool $enabledonly = true): ?\stdClass {
        global $DB;
        $conditions = ['id' => $id];
        if ($enabledonly) {
            $conditions['status'] = 1;
        }
        $record = $DB->get_record(self::TABLE, $conditions);
        return $record ?: null;
    }

    /**
     * Get an enabled preset by short name.
     *
     * @param string $shortname Preset short name.
     * @return \stdClass|null
     */
    public static function get_by_shortname(string $shortname): ?\stdClass {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['shortname' => $shortname, 'status' => 1]);
        return $record ?: null;
    }

    /**
     * Get enabled presets matching a list of short names, preserving the given order.
     *
     * Short names with no matching enabled preset are silently dropped, so a
     * partially-seeded install degrades gracefully.
     *
     * @param string[] $shortnames Ordered list of short names.
     * @return \stdClass[] Presets keyed by short name, in the requested order.
     */
    public static function get_by_shortnames(array $shortnames): array {
        global $DB;
        if (empty($shortnames)) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($shortnames, SQL_PARAMS_NAMED);
        $params['status'] = 1;
        $records = $DB->get_records_select(
            self::TABLE,
            "shortname $insql AND status = :status",
            $params
        );

        // Re-key by short name and restore the requested order.
        $byshortname = [];
        foreach ($records as $record) {
            $byshortname[$record->shortname] = $record;
        }
        $ordered = [];
        foreach ($shortnames as $shortname) {
            if (isset($byshortname[$shortname])) {
                $ordered[$shortname] = $byshortname[$shortname];
            }
        }
        return $ordered;
    }

    /**
     * Get all enabled presets ordered for display.
     *
     * @return \stdClass[]
     */
    public static function get_enabled(): array {
        global $DB;
        return $DB->get_records(self::TABLE, ['status' => 1], 'sortorder ASC, id ASC');
    }

    /**
     * Get the stored .mbz backup file for a preset.
     *
     * @param int $presetid Preset id.
     * @return \stored_file|null
     */
    public static function get_backup_file(int $presetid): ?\stored_file {
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            \context_system::instance()->id,
            self::COMPONENT,
            self::FILEAREA_BACKUP,
            $presetid,
            'itemid, filepath, filename',
            false
        );
        $file = reset($files);
        return $file ?: null;
    }

    /**
     * Create a preset by backing up an existing course activity.
     *
     * Runs a single-activity backup (without user data, so the preset is a clean
     * template) and stores the resulting .mbz as the preset's backup file. The
     * caller must have verified moodle/backup:backupactivity in the cm context.
     *
     * @param \cm_info $cm The source course module.
     * @param \stdClass $meta Preset metadata: shortname, title, description,
     *                        descriptionformat, status, sortorder.
     * @return \stdClass The created preset record.
     */
    public static function create_from_cm(\cm_info $cm, \stdClass $meta): \stdClass {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

        // Back up the single activity to a stored .mbz, excluding user data.
        $controller = new \backup_controller(
            \backup::TYPE_1ACTIVITY,
            $cm->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        try {
            if ($controller->get_plan()->setting_exists('users')) {
                $controller->get_plan()->get_setting('users')->set_value(false);
            }
            $controller->execute_plan();
            $result = $controller->get_results();
        } finally {
            $backupfile = $result['backup_destination'] ?? null;
            $controller->destroy();
        }

        if (empty($backupfile) || !($backupfile instanceof \stored_file)) {
            throw new \moodle_exception('backupfailed', 'guidanceaddon_preset');
        }

        $now = time();
        $shortname = $meta->shortname;
        $record = (object) [
            'shortname' => $shortname,
            'title' => $meta->title,
            'description' => $meta->description ?? '',
            'descriptionformat' => $meta->descriptionformat ?? FORMAT_HTML,
            'modname' => $cm->modname,
            'backupfile' => $shortname . '.mbz',
            'status' => empty($meta->status) ? 0 : 1,
            'sortorder' => $meta->sortorder ?? 0,
            'usermodified' => $USER->id,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = $DB->insert_record(self::TABLE, $record);

        // Copy the backup into the preset's own file area, then drop the transient copy.
        get_file_storage()->create_file_from_storedfile((object) [
            'contextid' => \context_system::instance()->id,
            'component' => self::COMPONENT,
            'filearea' => self::FILEAREA_BACKUP,
            'itemid' => $record->id,
            'filepath' => '/',
            'filename' => $record->backupfile,
        ], $backupfile);
        $backupfile->delete();

        return $record;
    }

    /**
     * Delete a preset and its associated files.
     *
     * @param int $presetid Preset id.
     * @return void
     */
    public static function delete(int $presetid): void {
        global $DB;
        $fs = get_file_storage();
        $context = \context_system::instance();
        foreach ([self::FILEAREA_BACKUP, self::FILEAREA_DESCRIPTION] as $area) {
            $fs->delete_area_files($context->id, self::COMPONENT, $area, $presetid);
        }
        $DB->delete_records(self::TABLE, ['id' => $presetid]);
    }

    /**
     * Seed the bundled demo presets from assets/presets.xml.
     *
     * Idempotent: a preset whose short name already exists is skipped, so this
     * is safe to call on install and re-install.
     *
     * @return void
     */
    public static function create_default_presets(): void {
        global $CFG, $DB;

        $assetdir = $CFG->dirroot . '/admin/tool/guidance/addon/preset/assets';
        $xmlfile = $assetdir . '/presets.xml';
        if (!file_exists($xmlfile)) {
            return;
        }

        $xml = simplexml_load_file($xmlfile);
        if ($xml === false) {
            return;
        }

        $fs = get_file_storage();
        $context = \context_system::instance();
        $now = time();
        $sortorder = 0;

        foreach ($xml->preset as $node) {
            $shortname = trim((string) $node->shortname);
            $backupfile = trim((string) $node->backupfile);
            if ($shortname === '' || $backupfile === '') {
                continue;
            }
            // Skip presets that already exist (idempotent seeding).
            if ($DB->record_exists(self::TABLE, ['shortname' => $shortname])) {
                continue;
            }
            // Skip if the bundled backup file is missing.
            $backuppath = $assetdir . '/' . $backupfile;
            if (!file_exists($backuppath)) {
                continue;
            }

            $record = (object) [
                'shortname' => $shortname,
                'title' => (string) $node->title,
                'description' => (string) $node->description,
                'descriptionformat' => FORMAT_HTML,
                'modname' => trim((string) $node->modname) ?: null,
                'backupfile' => $backupfile,
                'status' => isset($node->status) ? (int) $node->status : 1,
                'sortorder' => isset($node->sortorder) ? (int) $node->sortorder : $sortorder,
                'usermodified' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $record->id = $DB->insert_record(self::TABLE, $record);
            $sortorder++;

            $fs->create_file_from_pathname((object) [
                'contextid' => $context->id,
                'component' => self::COMPONENT,
                'filearea' => self::FILEAREA_BACKUP,
                'itemid' => $record->id,
                'filepath' => '/',
                'filename' => $backupfile,
            ], $backuppath);
        }
    }
}
