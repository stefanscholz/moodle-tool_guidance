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
 * Tests for building a preset from an existing course activity.
 *
 * @package    guidanceaddon_preset
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace guidanceaddon_preset;

use guidanceaddon_preset\local\apply;
use guidanceaddon_preset\local\preset_manager;

/**
 * @covers \guidanceaddon_preset\local\preset_manager::create_from_cm
 */
final class create_from_activity_test extends \advanced_testcase {

    /**
     * Backing up a course activity produces a working, applyable preset.
     */
    public function test_create_from_cm_then_apply(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Source course with a page activity.
        $sourcecourse = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $sourcecourse->id,
            'name' => 'Reusable page',
            'intro' => 'Template intro content',
        ]);
        $cm = get_fast_modinfo($sourcecourse->id)->get_cm($page->cmid);

        $preset = preset_manager::create_from_cm($cm, (object) [
            'shortname' => 'from_page',
            'title' => 'From page',
            'description' => 'Made from an activity',
            'descriptionformat' => FORMAT_HTML,
            'status' => 1,
            'sortorder' => 0,
        ]);

        // The preset row and its stored backup exist.
        $this->assertTrue($DB->record_exists(preset_manager::TABLE, ['id' => $preset->id]));
        $this->assertSame('page', $preset->modname);
        $file = preset_manager::get_backup_file((int) $preset->id);
        $this->assertNotNull($file);
        $this->assertStringEndsWith('.mbz', $file->get_filename());

        // Applying it recreates the activity in a target course/section.
        $targetcourse = $this->getDataGenerator()->create_course(['numsections' => 3]);
        $result = apply::apply((int) $preset->id, $targetcourse, 2);

        $this->assertSame('page', $result->modname);
        $cm2 = get_fast_modinfo($targetcourse->id)->get_cm($result->cmid);
        $this->assertSame('page', $cm2->modname);
        $this->assertSame(2, $cm2->sectionnum);
    }
}
