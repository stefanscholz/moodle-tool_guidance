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
 * Tests for the preset_manager lookup API.
 *
 * @package    guidanceaddon_preset
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace guidanceaddon_preset;

use guidanceaddon_preset\local\preset_manager;

/**
 * @covers \guidanceaddon_preset\local\preset_manager
 */
final class preset_manager_test extends \advanced_testcase {

    /**
     * get_by_shortnames preserves request order and drops unknown/disabled presets.
     */
    public function test_get_by_shortnames_order_and_filtering(): void {
        $this->resetAfterTest();
        /** @var \guidanceaddon_preset_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('guidanceaddon_preset');

        $generator->create_preset(['shortname' => 'alpha']);
        $generator->create_preset(['shortname' => 'beta']);
        $generator->create_preset(['shortname' => 'gamma', 'status' => 0]);

        // Requested order is respected regardless of insertion order.
        $result = preset_manager::get_by_shortnames(['beta', 'alpha']);
        $this->assertSame(['beta', 'alpha'], array_keys($result));

        // Disabled and unknown short names are dropped.
        $result = preset_manager::get_by_shortnames(['alpha', 'gamma', 'missing']);
        $this->assertSame(['alpha'], array_keys($result));

        // Empty input yields an empty result.
        $this->assertSame([], preset_manager::get_by_shortnames([]));
    }

    /**
     * get() and get_by_shortname() respect the enabled filter.
     */
    public function test_get_respects_status(): void {
        $this->resetAfterTest();
        /** @var \guidanceaddon_preset_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('guidanceaddon_preset');

        $enabled = $generator->create_preset(['shortname' => 'enabled', 'status' => 1]);
        $disabled = $generator->create_preset(['shortname' => 'disabled', 'status' => 0]);

        $this->assertNotNull(preset_manager::get($enabled->id));
        $this->assertNull(preset_manager::get($disabled->id));
        $this->assertNotNull(preset_manager::get($disabled->id, false));

        $this->assertNotNull(preset_manager::get_by_shortname('enabled'));
        $this->assertNull(preset_manager::get_by_shortname('disabled'));
    }

    /**
     * delete() removes the record and its stored files.
     */
    public function test_delete_removes_record_and_files(): void {
        global $DB;
        $this->resetAfterTest();
        /** @var \guidanceaddon_preset_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('guidanceaddon_preset');

        $preset = $generator->create_preset(['shortname' => 'todelete']);

        // Attach a dummy backup file.
        $fs = get_file_storage();
        $fs->create_file_from_string((object) [
            'contextid' => \context_system::instance()->id,
            'component' => preset_manager::COMPONENT,
            'filearea' => preset_manager::FILEAREA_BACKUP,
            'itemid' => $preset->id,
            'filepath' => '/',
            'filename' => 'dummy.mbz',
        ], 'not a real backup');

        $this->assertNotNull(preset_manager::get_backup_file($preset->id));

        preset_manager::delete($preset->id);

        $this->assertFalse($DB->record_exists(preset_manager::TABLE, ['id' => $preset->id]));
        $this->assertNull(preset_manager::get_backup_file($preset->id));
    }
}
