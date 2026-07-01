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
 * Module-agnostic preset apply engine.
 *
 * @package    guidanceaddon_preset
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace guidanceaddon_preset\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Restores the .mbz activity backup stored on a preset into a target course.
 */
class apply {

    /**
     * Apply a preset: restore its activity backup into the course and move it to the section.
     *
     * @param int $presetid Preset id.
     * @param \stdClass $course Target course record.
     * @param int $sectionnum Relative section number to place the activity in.
     * @return \stdClass Object with {cmid, instanceid, contextid, modname}.
     */
    public static function apply(int $presetid, \stdClass $course, int $sectionnum = 0): \stdClass {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->dirroot . '/course/lib.php');

        $preset = preset_manager::get($presetid);
        if (!$preset) {
            throw new \moodle_exception('presetnotfound', 'guidanceaddon_preset');
        }

        $file = preset_manager::get_backup_file($presetid);
        if (!$file) {
            throw new \moodle_exception('backupfilemissing', 'guidanceaddon_preset');
        }

        // Extract the .mbz into a uniquely named backup temp directory.
        $tempname = 'guidancepreset_' . $presetid . '_' . random_string(10);
        $tempdir = make_backup_temp_directory($tempname);
        $packer = get_file_packer('application/vnd.moodle.backup');
        $file->extract_to_pathname($packer, $tempdir);

        $controller = null;
        try {
            $controller = new \restore_controller(
                $tempname,
                $course->id,
                \backup::INTERACTIVE_NO,
                \backup::MODE_IMPORT,
                $USER->id,
                \backup::TARGET_CURRENT_ADDING
            );
            $controller->execute_precheck();
            $controller->execute_plan();

            // Locate the first restored activity.
            $result = null;
            foreach ($controller->get_plan()->get_tasks() as $task) {
                if ($task instanceof \restore_activity_task) {
                    $result = (object) [
                        'cmid' => $task->get_moduleid(),
                        'instanceid' => $task->get_activityid(),
                        'contextid' => $task->get_contextid(),
                        'modname' => $task->get_modulename(),
                    ];
                    break;
                }
            }
        } finally {
            if ($controller !== null) {
                $controller->destroy();
            }
            fulldelete($tempdir);
        }

        if ($result === null || empty($result->cmid)) {
            throw new \moodle_exception('norestoredactivity', 'guidanceaddon_preset');
        }

        // Place the activity into the requested section.
        self::move_cm_to_section($course, (int) $result->cmid, $sectionnum, $result->modname);

        rebuild_course_cache($course->id, true);

        return $result;
    }

    /**
     * Move a course module to a section, removing it from whichever section the restore placed it in.
     *
     * @param \stdClass $course Course record.
     * @param int $cmid Course module id.
     * @param int $sectionnum Target relative section number.
     * @param string $modname Module name (avoids an extra DB lookup).
     * @return void
     */
    protected static function move_cm_to_section(\stdClass $course, int $cmid, int $sectionnum, string $modname): void {
        global $DB;

        // Ensure the target section exists and fetch it.
        $target = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $sectionnum]);
        if (!$target) {
            course_create_sections_if_missing($course, $sectionnum);
            $target = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $sectionnum], '*', MUST_EXIST);
        }

        // If the module is already in the target section, nothing to do.
        $currentsectionid = $DB->get_field('course_modules', 'section', ['id' => $cmid]);
        if ((int) $currentsectionid === (int) $target->id) {
            return;
        }

        // Remove the module from its current section sequence, if any.
        if ($currentsectionid && ($current = $DB->get_record('course_sections', ['id' => $currentsectionid]))) {
            $sequence = array_filter(explode(',', (string) $current->sequence), function($id) use ($cmid) {
                return $id !== '' && (int) $id !== $cmid;
            });
            $DB->set_field('course_sections', 'sequence', implode(',', $sequence), ['id' => $current->id]);
        }

        // Append to the target section sequence (core helper updates course_modules.section too).
        course_add_cm_to_section($course, $cmid, $sectionnum, null, $modname);
    }
}
