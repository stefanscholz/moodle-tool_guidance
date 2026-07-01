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
 * Tests for the tool_guidance API.
 *
 * @package    tool_guidance
 * @copyright  2026 Lily Asshauer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_guidance\api
 */

namespace tool_guidance;

/**
 * API unit tests.
 */
final class api_test extends \advanced_testcase {
    /**
     * Create a graph for tests.
     *
     * @param string $name
     * @return graph
     */
    private function make_graph(string $name = 'Graph'): graph {
        $graph = new graph(0, (object) ['name' => $name]);
        $graph->create();
        return $graph;
    }

    /**
     * Create a question node in a graph.
     *
     * @param int $graphid
     * @param string $title
     * @return node
     */
    private function make_question(int $graphid, string $title): node {
        $node = new node(0, (object) [
            'graphid' => $graphid,
            'type' => node::TYPE_QUESTION,
            'title' => $title,
        ]);
        $node->create();
        return $node;
    }

    /**
     * Create a link via the API helper.
     *
     * @param int $graphid
     * @param int $parentid
     * @param int $childid
     * @return link
     */
    private function make_link(int $graphid, int $parentid, int $childid): link {
        return api::create_link((object) [
            'graphid' => $graphid,
            'parentnodeid' => $parentid,
            'childnodeid' => $childid,
            'answerlabel' => 'answer',
            'sortorder' => 0,
        ]);
    }

    public function test_create_and_traverse_chain(): void {
        $this->resetAfterTest();
        $graph = $this->make_graph();
        $q1 = $this->make_question($graph->get('id'), 'Q1');
        $q2 = $this->make_question($graph->get('id'), 'Q2');
        $this->make_link($graph->get('id'), $q1->get('id'), $q2->get('id'));

        $links = api::get_child_links($q1->get('id'));
        $this->assertCount(1, $links);
        $this->assertEquals($q2->get('id'), reset($links)->get('childnodeid'));
    }

    public function test_direct_cycle_detected(): void {
        $this->resetAfterTest();
        $graph = $this->make_graph();
        $q1 = $this->make_question($graph->get('id'), 'Q1');
        $q2 = $this->make_question($graph->get('id'), 'Q2');
        $this->make_link($graph->get('id'), $q1->get('id'), $q2->get('id'));

        $this->assertTrue(api::would_create_cycle($graph->get('id'), $q2->get('id'), $q1->get('id')));
    }

    public function test_transitive_cycle_detected(): void {
        $this->resetAfterTest();
        $graph = $this->make_graph();
        $q1 = $this->make_question($graph->get('id'), 'Q1');
        $q2 = $this->make_question($graph->get('id'), 'Q2');
        $q3 = $this->make_question($graph->get('id'), 'Q3');
        $this->make_link($graph->get('id'), $q1->get('id'), $q2->get('id'));
        $this->make_link($graph->get('id'), $q2->get('id'), $q3->get('id'));

        // Edge q3 -> q1 would close the loop q1->q2->q3->q1.
        $this->assertTrue(api::would_create_cycle($graph->get('id'), $q3->get('id'), $q1->get('id')));
    }

    public function test_shared_child_is_not_a_cycle(): void {
        $this->resetAfterTest();
        $graph = $this->make_graph();
        $q1 = $this->make_question($graph->get('id'), 'Q1');
        $q2 = $this->make_question($graph->get('id'), 'Q2');
        $q3 = $this->make_question($graph->get('id'), 'Q3');
        $this->make_link($graph->get('id'), $q1->get('id'), $q3->get('id'));

        // Edge q2 -> q3 gives q3 two parents: a DAG, not a cycle.
        $this->assertFalse(api::would_create_cycle($graph->get('id'), $q2->get('id'), $q3->get('id')));
    }

    public function test_create_link_rejects_cycle(): void {
        $this->resetAfterTest();
        $graph = $this->make_graph();
        $q1 = $this->make_question($graph->get('id'), 'Q1');
        $q2 = $this->make_question($graph->get('id'), 'Q2');
        $this->make_link($graph->get('id'), $q1->get('id'), $q2->get('id'));

        $this->expectException(\moodle_exception::class);
        $this->make_link($graph->get('id'), $q2->get('id'), $q1->get('id'));
    }

    public function test_parent_must_be_question(): void {
        $this->resetAfterTest();
        $graph = $this->make_graph();
        $leaf = new node(0, (object) [
            'graphid' => $graph->get('id'),
            'type' => node::TYPE_LEAF,
            'title' => 'Leaf',
            'targettype' => 'url',
            'targetconfig' => json_encode(['url' => 'https://example.com']),
        ]);
        $leaf->create();
        $q1 = $this->make_question($graph->get('id'), 'Q1');

        $this->expectException(\core\invalid_persistent_exception::class);
        $this->make_link($graph->get('id'), $leaf->get('id'), $q1->get('id'));
    }

    public function test_cross_graph_link_rejected(): void {
        $this->resetAfterTest();
        $g1 = $this->make_graph('G1');
        $g2 = $this->make_graph('G2');
        $q1 = $this->make_question($g1->get('id'), 'Q1');
        $q2 = $this->make_question($g2->get('id'), 'Q2');

        $this->expectException(\core\invalid_persistent_exception::class);
        $this->make_link($g1->get('id'), $q1->get('id'), $q2->get('id'));
    }

    public function test_create_dangling_answer(): void {
        $this->resetAfterTest();
        $graph = $this->make_graph();
        $q1 = $this->make_question($graph->get('id'), 'Q1');

        $link = $this->make_link($graph->get('id'), $q1->get('id'), 0);
        $this->assertEmpty($link->get('childnodeid'));
    }

    public function test_set_answer_child_attaches(): void {
        $this->resetAfterTest();
        $graph = $this->make_graph();
        $q1 = $this->make_question($graph->get('id'), 'Q1');
        $q2 = $this->make_question($graph->get('id'), 'Q2');

        $dangling = $this->make_link($graph->get('id'), $q1->get('id'), 0);
        api::set_answer_child($dangling, $q2->get('id'));
        $this->assertEquals($q2->get('id'), (int) $dangling->get('childnodeid'));
    }

    public function test_set_answer_child_rejects_cycle(): void {
        $this->resetAfterTest();
        $graph = $this->make_graph();
        $q1 = $this->make_question($graph->get('id'), 'Q1');
        $q2 = $this->make_question($graph->get('id'), 'Q2');
        $this->make_link($graph->get('id'), $q1->get('id'), $q2->get('id'));

        // Pointing q2's answer back at q1 closes the loop q1->q2->q1.
        $dangling = $this->make_link($graph->get('id'), $q2->get('id'), 0);
        $this->expectException(\moodle_exception::class);
        api::set_answer_child($dangling, $q1->get('id'));
    }

    public function test_delete_child_dangles_incoming_answer(): void {
        global $DB;
        $this->resetAfterTest();
        $graph = $this->make_graph();
        $q1 = $this->make_question($graph->get('id'), 'Q1');
        $q2 = $this->make_question($graph->get('id'), 'Q2');
        $link = $this->make_link($graph->get('id'), $q1->get('id'), $q2->get('id'));

        api::delete_node($q2);

        // q1's answer survives but is now dangling.
        $this->assertTrue($DB->record_exists('tool_guidance_link', ['id' => $link->get('id')]));
        $this->assertNull($DB->get_field('tool_guidance_link', 'childnodeid', ['id' => $link->get('id')]));
    }

    public function test_delete_node_clears_links_and_root(): void {
        global $DB;
        $this->resetAfterTest();
        $graph = $this->make_graph();
        $q1 = $this->make_question($graph->get('id'), 'Q1');
        $q2 = $this->make_question($graph->get('id'), 'Q2');
        $this->make_link($graph->get('id'), $q1->get('id'), $q2->get('id'));
        $graph->set('rootnodeid', $q1->get('id'));
        $graph->update();

        api::delete_node($q1);

        $this->assertFalse($DB->record_exists('tool_guidance_node', ['id' => $q1->get('id')]));
        $this->assertEquals(0, $DB->count_records('tool_guidance_link', ['graphid' => $graph->get('id')]));
        $graph->read();
        $this->assertEmpty($graph->get('rootnodeid'));
    }
}
