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
 * Tests for the tool_guidance external (web service) layer.
 *
 * @package    guidanceaddon_editor
 * @copyright  2026 Lily Asshauer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \guidanceaddon_editor\external\save_node
 * @covers     \guidanceaddon_editor\external\create_link
 * @covers     \guidanceaddon_editor\external\link_answer
 */

namespace guidanceaddon_editor;

use tool_guidance\graph;
use tool_guidance\node;
use guidanceaddon_editor\external\create_link;
use guidanceaddon_editor\external\delete_node;
use guidanceaddon_editor\external\get_graph;
use guidanceaddon_editor\external\link_answer;
use guidanceaddon_editor\external\move_node;
use guidanceaddon_editor\external\save_node;

/**
 * External layer round-trip tests.
 */
final class external_test extends \advanced_testcase {
    /**
     * Create a graph for tests.
     *
     * @return int Graph id.
     */
    private function make_graph(): int {
        $graph = new graph(0, (object) ['name' => 'G']);
        $graph->create();
        return (int) $graph->get('id');
    }

    public function test_full_round_trip(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $graphid = $this->make_graph();

        $q = save_node::execute($graphid, 0, 'question', 'Pick one', '', '', '', 10, 20);
        $this->assertEquals('', $q['error']);
        $this->assertGreaterThan(0, $q['id']);

        // First node becomes the graph root.
        $graph = new graph($graphid);
        $this->assertEquals($q['id'], $graph->get('rootnodeid'));

        $leaf = save_node::execute(
            $graphid,
            0,
            'leaf',
            'Use a file',
            '',
            'url',
            json_encode(['url' => 'https://example.com']),
            300,
            20
        );
        $this->assertEquals('', $leaf['error']);

        $link = create_link::execute($graphid, $q['id'], $leaf['id'], 'File');
        $this->assertEquals('', $link['error']);
        $this->assertGreaterThan(0, $link['id']);

        $data = get_graph::execute($graphid);
        $this->assertCount(2, $data['nodes']);
        $this->assertCount(1, $data['links']);
        $this->assertEquals('File', $data['links'][0]['answerlabel']);

        $this->assertTrue(move_node::execute($q['id'], 99, 88));
        $moved = new node($q['id']);
        $this->assertEquals(99.0, (float) $moved->get('posx'));

        $this->assertTrue(delete_node::execute($leaf['id']));
        $this->assertCount(1, get_graph::execute($graphid)['nodes']);
        $this->assertCount(0, get_graph::execute($graphid)['links']);
    }

    public function test_create_link_rejects_cycle(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $graphid = $this->make_graph();

        $q1 = save_node::execute($graphid, 0, 'question', 'Q1', '', '', '', 0, 0);
        $q2 = save_node::execute($graphid, 0, 'question', 'Q2', '', '', '', 0, 0);
        $this->assertEquals('', create_link::execute($graphid, $q1['id'], $q2['id'], 'a')['error']);

        $cycle = create_link::execute($graphid, $q2['id'], $q1['id'], 'b');
        $this->assertEquals(0, $cycle['id']);
        $this->assertNotEquals('', $cycle['error']);
    }

    public function test_dangling_answer_then_link(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $graphid = $this->make_graph();

        $q = save_node::execute($graphid, 0, 'question', 'Q', '', '', '', 0, 0);
        $leaf = save_node::execute($graphid, 0, 'leaf', 'L', '', 'url',
            json_encode(['url' => 'https://example.com']), 0, 0);

        // Create a dangling answer (no child), positioned on the canvas.
        $ans = create_link::execute($graphid, $q['id'], 0, 'Maybe', 120, 200);
        $this->assertEquals('', $ans['error']);
        $this->assertGreaterThan(0, $ans['id']);

        $data = get_graph::execute($graphid);
        $this->assertEquals(0, $data['links'][0]['childnodeid']);
        $this->assertEquals(120.0, $data['links'][0]['posx']);

        // Point it at the leaf.
        $this->assertEquals('', link_answer::execute($ans['id'], $leaf['id'])['error']);
        $this->assertEquals($leaf['id'], get_graph::execute($graphid)['links'][0]['childnodeid']);

        // Clear it again (dangling).
        $this->assertEquals('', link_answer::execute($ans['id'], 0)['error']);
        $this->assertEquals(0, get_graph::execute($graphid)['links'][0]['childnodeid']);
    }

    public function test_link_answer_rejects_cycle(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $graphid = $this->make_graph();

        $q1 = save_node::execute($graphid, 0, 'question', 'Q1', '', '', '', 0, 0);
        $q2 = save_node::execute($graphid, 0, 'question', 'Q2', '', '', '', 0, 0);
        create_link::execute($graphid, $q1['id'], $q2['id'], 'a');

        // q2's answer back to q1 would loop.
        $ans = create_link::execute($graphid, $q2['id'], 0, 'b');
        $res = link_answer::execute($ans['id'], $q1['id']);
        $this->assertNotEquals('', $res['error']);
    }

    public function test_save_node_allows_unconfigured_leaf(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $graphid = $this->make_graph();

        // A freshly added leaf has a type but no target details yet (draft).
        $res = save_node::execute($graphid, 0, 'leaf', 'New', '', 'activity', '{}', 0, 0);
        $this->assertEquals('', $res['error']);
        $this->assertGreaterThan(0, $res['id']);
    }

    public function test_save_node_rejects_bad_target(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $graphid = $this->make_graph();

        $res = save_node::execute(
            $graphid,
            0,
            'leaf',
            'Bad',
            '',
            'url',
            json_encode(['url' => 'not-a-url']),
            0,
            0
        );
        $this->assertNotEquals('', $res['error']);
    }
}
