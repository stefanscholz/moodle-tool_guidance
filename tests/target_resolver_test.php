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

namespace tool_guidance;

use tool_guidance\local\admin_links;
use tool_guidance\local\suggestion;
use tool_guidance\local\target_resolver;

/**
 * Tests for the CTA target resolver and admin-link registry.
 *
 * @package    tool_guidance
 * @covers     \tool_guidance\local\target_resolver
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class target_resolver_test extends \advanced_testcase {

    /**
     * Build a suggestion with the given target.
     *
     * @param string $targettype
     * @param string $targetvalue
     * @param string $modname
     * @return suggestion
     */
    private function suggestion(string $targettype, string $targetvalue, string $modname = 'forum'): suggestion {
        return new suggestion(1, $modname, '', 'why', 'gap', '', $targettype, $targetvalue);
    }

    public function test_admin_links(): void {
        $this->resetAfterTest();
        $this->assertTrue(admin_links::exists('settings'));
        $this->assertFalse(admin_links::exists('bogus'));
        $this->assertCount(6, admin_links::menu());
        $url = admin_links::url('groups', 42);
        $this->assertStringContainsString('/group/index.php', $url->out(false));
        $this->assertSame('42', $url->param('id'));
        $this->assertNull(admin_links::url('bogus', 42));
    }

    public function test_activity_target(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $resolved = target_resolver::resolve($this->suggestion('activity', ''), $course->id);
        $this->assertFalse($resolved['ischooser']);
        $out = $resolved['url']->out(false);
        $this->assertStringContainsString('/course/modedit.php', $out);
        $this->assertStringContainsString('add=forum', $out);
    }

    public function test_node_target_valid(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $graph = new graph(0, (object) ['name' => 'G', 'enabled' => 1]);
        $graph->create();
        $node = new node(0, (object) ['graphid' => $graph->get('id'), 'type' => 'question', 'title' => 'Q']);
        $node->create();

        $resolved = target_resolver::resolve($this->suggestion('node', (string) $node->get('id')), $course->id);
        $this->assertTrue($resolved['ischooser']);
        $out = $resolved['url']->out(false);
        $this->assertStringContainsString('/admin/tool/guidance/chooser.php', $out);
        $this->assertStringContainsString('node=' . $node->get('id'), $out);
    }

    public function test_node_target_in_disabled_graph_falls_back_to_activity(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $graph = new graph(0, (object) ['name' => 'D', 'enabled' => 0]);
        $graph->create();
        $node = new node(0, (object) ['graphid' => $graph->get('id'), 'type' => 'question', 'title' => 'Q']);
        $node->create();

        $resolved = target_resolver::resolve($this->suggestion('node', (string) $node->get('id')), $course->id);
        $this->assertFalse($resolved['ischooser']);
        $this->assertStringContainsString('/course/modedit.php', $resolved['url']->out(false));
    }

    public function test_node_target_missing_falls_back_to_activity(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $resolved = target_resolver::resolve($this->suggestion('node', '999999'), $course->id);
        $this->assertFalse($resolved['ischooser']);
        $this->assertStringContainsString('/course/modedit.php', $resolved['url']->out(false));
    }

    public function test_adminlink_target(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $resolved = target_resolver::resolve($this->suggestion('adminlink', 'settings'), $course->id);
        $this->assertFalse($resolved['ischooser']);
        $this->assertStringContainsString('/course/edit.php', $resolved['url']->out(false));
    }

    public function test_unavailable_activity_falls_back_to_course_settings(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        // A module that is not installed/enabled cannot be created, so we land on settings.
        $resolved = target_resolver::resolve($this->suggestion('activity', '', 'nonexistentmod'), $course->id);
        $this->assertFalse($resolved['ischooser']);
        $this->assertStringContainsString('/course/edit.php', $resolved['url']->out(false));
    }
}
