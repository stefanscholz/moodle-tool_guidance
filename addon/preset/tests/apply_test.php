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
 * Tests for the preset apply (restore) flow.
 *
 * @package    guidanceaddon_preset
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace guidanceaddon_preset;

use guidanceaddon_preset\local\apply;

/**
 * @covers \guidanceaddon_preset\local\apply
 */
final class apply_test extends \advanced_testcase {

    /**
     * Back up a single activity and return the resulting .mbz stored file.
     *
     * @return \stored_file
     */
    protected function backup_source_activity(): \stored_file {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

        $generator = $this->getDataGenerator();
        $sourcecourse = $generator->create_course();
        $page = $generator->create_module('page', [
            'course' => $sourcecourse->id,
            'name' => 'Source page',
            'intro' => 'Source page intro',
        ]);
        $cm = get_coursemodule_from_instance('page', $page->id);

        $bc = new \backup_controller(
            \backup::TYPE_1ACTIVITY,
            $cm->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $bc->execute_plan();
        $results = $bc->get_results();
        $bc->destroy();

        $this->assertArrayHasKey('backup_destination', $results);
        return $results['backup_destination'];
    }

    /**
     * Applying a preset restores the activity into the requested section.
     */
    public function test_apply_creates_activity_in_section(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $backup = $this->backup_source_activity();

        /** @var \guidanceaddon_preset_generator $presetgen */
        $presetgen = $this->getDataGenerator()->get_plugin_generator('guidanceaddon_preset');
        $preset = $presetgen->create_preset(['shortname' => 'demo_page', 'modname' => 'page'], $backup);

        $targetcourse = $this->getDataGenerator()->create_course(['numsections' => 3]);

        $result = apply::apply($preset->id, $targetcourse, 2);

        $this->assertSame('page', $result->modname);
        $this->assertNotEmpty($result->cmid);

        $modinfo = get_fast_modinfo($targetcourse->id);
        $cm = $modinfo->get_cm($result->cmid);
        $this->assertSame('page', $cm->modname);
        $this->assertSame(2, $cm->sectionnum);
    }

    /**
     * Applying a preset with no backup file throws.
     */
    public function test_apply_without_backup_throws(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var \guidanceaddon_preset_generator $presetgen */
        $presetgen = $this->getDataGenerator()->get_plugin_generator('guidanceaddon_preset');
        $preset = $presetgen->create_preset(['shortname' => 'nobackup']);

        $course = $this->getDataGenerator()->create_course();

        $this->expectException(\moodle_exception::class);
        apply::apply($preset->id, $course, 0);
    }
}
