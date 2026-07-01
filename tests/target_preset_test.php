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
 * Tests for the preset leaf target.
 *
 * @package    tool_guidance
 * @copyright  2026 Lily Asshauer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_guidance\target\preset
 */

namespace tool_guidance\target;

/**
 * Unit tests for the preset target.
 */
final class target_preset_test extends \advanced_testcase {

    /**
     * The preset target type is registered in the manager.
     */
    public function test_registered(): void {
        $this->assertTrue(manager::type_exists('preset'));
        $this->assertInstanceOf(preset::class, manager::get_target('preset', ['shortname' => 'x']));
    }

    /**
     * A short name matching an enabled preset validates.
     */
    public function test_validate_known_shortname(): void {
        $this->resetAfterTest();

        $this->getDataGenerator()->get_plugin_generator('guidanceaddon_preset')
            ->create_preset(['shortname' => 'unit_preset', 'status' => 1]);

        $target = manager::get_target('preset', ['shortname' => 'unit_preset']);
        $this->assertTrue($target->validate_config());
    }

    /**
     * An empty short name is rejected.
     */
    public function test_validate_empty_shortname(): void {
        $target = manager::get_target('preset', ['shortname' => '']);
        $this->assertNotTrue($target->validate_config());
    }

    /**
     * A short name with no matching preset is still accepted: presets are
     * optional and may be seeded later, so leaves may reference them ahead of time.
     */
    public function test_validate_unresolved_shortname_is_allowed(): void {
        $this->resetAfterTest();

        $target = manager::get_target('preset', ['shortname' => 'seeded_later']);
        $this->assertTrue($target->validate_config());
    }

    /**
     * The target never resolves an action URL on its own (needs course context).
     */
    public function test_action_url_is_null(): void {
        $target = manager::get_target('preset', ['shortname' => 'unit_preset']);
        $this->assertNull($target->get_action_url());
    }
}
