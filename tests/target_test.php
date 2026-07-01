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
 * Tests for tool_guidance leaf targets.
 *
 * @package    tool_guidance
 * @copyright  2026 Lily Asshauer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_guidance\target\manager
 */

namespace tool_guidance;

use tool_guidance\target\manager;

/**
 * Target validation tests.
 */
final class target_test extends \advanced_testcase {
    public function test_known_types(): void {
        $this->assertTrue(manager::type_exists('activity'));
        $this->assertTrue(manager::type_exists('route'));
        $this->assertTrue(manager::type_exists('url'));
        $this->assertFalse(manager::type_exists('bogus'));
    }

    public function test_activity_validation(): void {
        $this->assertNotTrue(manager::get_target('activity', [])->validate_config());
        $this->assertNotTrue(manager::get_target('activity', ['modname' => 'doesnotexist'])->validate_config());
        $this->assertTrue(manager::get_target('activity', ['modname' => 'url'])->validate_config());
    }

    public function test_route_validation(): void {
        $this->assertNotTrue(manager::get_target('route', [])->validate_config());
        $this->assertNotTrue(manager::get_target('route', ['path' => 'admin/x.php'])->validate_config());
        $route = manager::get_target('route', ['path' => '/admin/settings.php?section=modsettingurl']);
        $this->assertTrue($route->validate_config());
        $this->assertInstanceOf(\moodle_url::class, $route->get_action_url());
    }

    public function test_url_validation(): void {
        $this->assertNotTrue(manager::get_target('url', [])->validate_config());
        $this->assertNotTrue(manager::get_target('url', ['url' => 'not a url'])->validate_config());
        $this->assertNotTrue(manager::get_target('url', ['url' => 'ftp://example.com'])->validate_config());
        $url = manager::get_target('url', ['url' => 'https://example.com/help']);
        $this->assertTrue($url->validate_config());
        $this->assertInstanceOf(\moodle_url::class, $url->get_action_url());
    }

    public function test_leaf_node_rejects_bad_config(): void {
        $this->resetAfterTest();
        $graph = new graph(0, (object) ['name' => 'G']);
        $graph->create();

        $node = new node(0, (object) [
            'graphid' => $graph->get('id'),
            'type' => node::TYPE_LEAF,
            'title' => 'Leaf',
            'targettype' => 'url',
            'targetconfig' => json_encode(['url' => 'nonsense']),
        ]);
        $this->expectException(\core\invalid_persistent_exception::class);
        $node->create();
    }

    public function test_question_node_rejects_target(): void {
        $this->resetAfterTest();
        $graph = new graph(0, (object) ['name' => 'G']);
        $graph->create();

        $node = new node(0, (object) [
            'graphid' => $graph->get('id'),
            'type' => node::TYPE_QUESTION,
            'title' => 'Q',
            'targettype' => 'url',
        ]);
        $this->expectException(\core\invalid_persistent_exception::class);
        $node->create();
    }
}
