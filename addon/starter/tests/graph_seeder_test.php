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
 * Tests for the starter graph seeder.
 *
 * @package    guidanceaddon_starter
 * @copyright  2026 Lily Asshauer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \guidanceaddon_starter\local\graph_seeder
 */

namespace guidanceaddon_starter\local;

use tool_guidance\api;
use tool_guidance\graph;
use tool_guidance\link;
use tool_guidance\node;

/**
 * Unit tests for graph_seeder.
 */
final class graph_seeder_test extends \advanced_testcase {

    /**
     * The bundled starter graph is seeded on install and wired to the chooser.
     */
    public function test_bundled_graph_seeded(): void {
        $this->resetAfterTest();

        $graph = graph::get_record(['idnumber' => 'starter-activity-chooser']);
        $this->assertNotFalse($graph, 'Bundled starter graph should exist after install.');

        $graphid = (int) $graph->get('id');
        // The definition ships 19 nodes and 19 links.
        $this->assertCount(19, node::get_records(['graphid' => $graphid]));
        $this->assertCount(19, link::get_records(['graphid' => $graphid]));

        // The entry node is top-level and the chooser starts from it.
        $entry = api::get_chooser_entry_node();
        $this->assertNotNull($entry);
        $this->assertSame($graphid, (int) $entry->get('graphid'));
        $this->assertTrue(api::is_top_level_node($entry));
        $this->assertSame('What would you like to do?', $entry->get('title'));
    }

    /**
     * Re-running the seeder never duplicates an existing graph.
     */
    public function test_seed_is_idempotent(): void {
        $this->resetAfterTest();

        $graphs = graph::count_records();
        $nodes = node::count_records();
        $links = link::count_records();

        graph_seeder::seed();

        $this->assertSame($graphs, graph::count_records());
        $this->assertSame($nodes, node::count_records());
        $this->assertSame($links, link::count_records());
    }

    /**
     * A custom definition file is built into graph/node/link records, and the
     * seeder does not clobber a chooser entry that already points at a wired graph.
     */
    public function test_seed_custom_definition(): void {
        global $CFG;
        $this->resetAfterTest();

        // Whatever install seeded, the entry now points at a graph with answers.
        $before = api::get_chooser_entry_node();
        $this->assertNotNull($before);
        $beforeid = (int) $before->get('id');

        $definition = [
            'graphs' => [[
                'idnumber' => 'unit-test-graph',
                'name' => 'Unit test graph',
                'entry' => 'q1',
                'nodes' => [
                    ['key' => 'q1', 'type' => 'question', 'title' => 'Pick one'],
                    ['key' => 'l1', 'type' => 'leaf', 'title' => 'A page',
                        'target' => ['type' => 'activity', 'modname' => 'page']],
                ],
                'links' => [
                    ['from' => 'q1', 'to' => 'l1', 'label' => 'This one'],
                ],
            ]],
        ];
        $file = $CFG->tempdir . '/guidance_starter_test_' . uniqid() . '.json';
        file_put_contents($file, json_encode($definition));

        graph_seeder::seed($file);

        $graph = graph::get_record(['idnumber' => 'unit-test-graph']);
        $this->assertNotFalse($graph);
        $graphid = (int) $graph->get('id');
        $this->assertCount(2, node::get_records(['graphid' => $graphid]));
        $this->assertCount(1, link::get_records(['graphid' => $graphid]));

        // Leaf target survived the round-trip.
        $leaf = node::get_record(['graphid' => $graphid, 'type' => node::TYPE_LEAF]);
        $this->assertSame('activity', $leaf->get('targettype'));
        $this->assertSame(['modname' => 'page'], $leaf->get_targetconfig_array());

        // The pre-existing wired entry must not be clobbered.
        $after = api::get_chooser_entry_node();
        $this->assertSame($beforeid, (int) $after->get('id'));

        unlink($file);
    }

    /**
     * With no chooser entry set, the seeder adopts the new graph's entry node.
     */
    public function test_seed_sets_entry_when_none(): void {
        global $CFG, $DB;
        $this->resetAfterTest();

        // Clear any entry seeded on install.
        unset_config(api::CHOOSER_ENTRY_CONFIG, 'tool_guidance');
        $this->assertNull(api::get_chooser_entry_node());

        $definition = [
            'graphs' => [[
                'idnumber' => 'unit-test-graph-entry',
                'name' => 'Entry graph',
                'entry' => 'start',
                'nodes' => [
                    ['key' => 'start', 'type' => 'question', 'title' => 'Start here'],
                    ['key' => 'end', 'type' => 'leaf', 'title' => 'Done',
                        'target' => ['type' => 'activity', 'modname' => 'page']],
                ],
                'links' => [
                    ['from' => 'start', 'to' => 'end', 'label' => 'Go'],
                ],
            ]],
        ];
        $file = $CFG->tempdir . '/guidance_starter_entry_' . uniqid() . '.json';
        file_put_contents($file, json_encode($definition));

        graph_seeder::seed($file);

        $entry = api::get_chooser_entry_node();
        $this->assertNotNull($entry);
        $this->assertSame('Start here', $entry->get('title'));
        $this->assertTrue(api::is_top_level_node($entry));

        unlink($file);
    }
}
