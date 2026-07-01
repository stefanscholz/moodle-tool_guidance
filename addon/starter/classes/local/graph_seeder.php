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
 * Seeds bundled starter graphs into tool_guidance from a JSON definition.
 *
 * @package    guidanceaddon_starter
 * @copyright  2026 Lily Asshauer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace guidanceaddon_starter\local;

use tool_guidance\api;
use tool_guidance\graph;
use tool_guidance\link;
use tool_guidance\node;

/**
 * Builds graphs described in assets/graph.json using the tool_guidance API.
 *
 * Seeding is idempotent: a graph whose idnumber already exists is left alone,
 * so this is safe to call on install and upgrade.
 */
class graph_seeder {

    /** @var int Horizontal spacing between node columns on the canvas. */
    const COL_WIDTH = 260;

    /** @var int Vertical spacing between nodes in a column on the canvas. */
    const ROW_HEIGHT = 150;

    /**
     * Seed every graph described in the bundled definition file.
     *
     * @param string|null $file Path to a JSON definition; defaults to the bundled asset.
     * @return void
     */
    public static function seed(?string $file = null): void {
        global $CFG;

        $file = $file ?? $CFG->dirroot . '/admin/tool/guidance/addon/starter/assets/graph.json';
        if (!is_readable($file)) {
            return;
        }
        $data = json_decode(file_get_contents($file), true);
        if (!is_array($data) || empty($data['graphs']) || !is_array($data['graphs'])) {
            return;
        }

        foreach ($data['graphs'] as $definition) {
            if (is_array($definition)) {
                self::seed_graph($definition);
            }
        }
    }

    /**
     * Seed a single graph definition.
     *
     * @param array $definition Decoded graph definition.
     * @return void
     */
    protected static function seed_graph(array $definition): void {
        $idnumber = trim((string) ($definition['idnumber'] ?? ''));
        if ($idnumber === '' || empty($definition['nodes'])) {
            return;
        }
        // Idempotent: never touch a graph that is already present.
        if (graph::get_record(['idnumber' => $idnumber])) {
            return;
        }

        $graph = new graph(0, (object) [
            'name' => (string) ($definition['name'] ?? $idnumber),
            'idnumber' => $idnumber,
            'description' => (string) ($definition['description'] ?? ''),
            'descriptionformat' => FORMAT_HTML,
            'enabled' => 1,
        ]);
        $graph->create();
        $graphid = (int) $graph->get('id');

        $entrykey = trim((string) ($definition['entry'] ?? ''));
        $positions = self::compute_positions($definition, $entrykey);

        // First pass: create every node, remembering the key => id mapping.
        $nodeids = [];
        foreach ($definition['nodes'] as $nodedef) {
            $key = trim((string) ($nodedef['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            [$x, $y] = $positions[$key] ?? [60, 60];
            $isleaf = ($nodedef['type'] ?? '') === 'leaf';

            $record = (object) [
                'graphid' => $graphid,
                'type' => $isleaf ? node::TYPE_LEAF : node::TYPE_QUESTION,
                'title' => (string) ($nodedef['title'] ?? ''),
                'description' => (string) ($nodedef['description'] ?? ''),
                'descriptionformat' => FORMAT_HTML,
                'isroot' => ($key === $entrykey) ? 1 : 0,
                'posx' => $x,
                'posy' => $y,
            ];
            if ($isleaf && !empty($nodedef['target']) && is_array($nodedef['target'])) {
                $target = $nodedef['target'];
                $record->targettype = (string) ($target['type'] ?? '');
                // Everything except the type key is the target's own config.
                unset($target['type']);
                $record->targetconfig = json_encode($target);
            }

            $node = new node(0, $record);
            $node->create();
            $nodeids[$key] = (int) $node->get('id');
        }

        // Second pass: wire the answers (links) between nodes.
        $sortorders = [];
        foreach ($definition['links'] ?? [] as $linkdef) {
            $fromkey = trim((string) ($linkdef['from'] ?? ''));
            $tokey = trim((string) ($linkdef['to'] ?? ''));
            if (!isset($nodeids[$fromkey])) {
                continue;
            }
            $parentid = $nodeids[$fromkey];
            $childid = $nodeids[$tokey] ?? 0;
            $sortorders[$parentid] = ($sortorders[$parentid] ?? -1) + 1;

            api::create_link((object) [
                'graphid' => $graphid,
                'parentnodeid' => $parentid,
                'childnodeid' => $childid ?: null,
                'answerlabel' => (string) ($linkdef['label'] ?? ''),
                'sortorder' => $sortorders[$parentid],
            ]);
        }

        // Adopt the entry node as the site chooser entry, but only when nothing
        // meaningful is set yet (unset, or still the empty core placeholder).
        if ($entrykey !== '' && isset($nodeids[$entrykey])) {
            self::maybe_set_chooser_entry($nodeids[$entrykey]);
        }
    }

    /**
     * Set the site chooser entry unless an admin already chose a real one.
     *
     * The core install seeds an empty placeholder graph (a single question with
     * no answers); that is safe to replace. A graph that already has answers is
     * treated as a deliberate choice and left untouched.
     *
     * @param int $entrynodeid
     * @return void
     */
    protected static function maybe_set_chooser_entry(int $entrynodeid): void {
        $current = api::get_chooser_entry_node();
        if ($current) {
            $currentgraphid = (int) $current->get('graphid');
            if (link::count_records(['graphid' => $currentgraphid]) > 0) {
                // Current entry belongs to a wired-up graph: leave it alone.
                return;
            }
        }
        api::set_chooser_entry($entrynodeid);
    }

    /**
     * Lay nodes out on the canvas by breadth-first depth from the entry node.
     *
     * Column = depth from the entry; row = order of discovery within the depth.
     * Nodes unreachable from the entry are stacked in a trailing column.
     *
     * @param array $definition Decoded graph definition.
     * @param string $entrykey Entry node key.
     * @return array<string,array{0:float,1:float}> Map of node key => [x, y].
     */
    protected static function compute_positions(array $definition, string $entrykey): array {
        // Build adjacency from the links, preserving order.
        $adjacency = [];
        foreach ($definition['links'] ?? [] as $linkdef) {
            $from = trim((string) ($linkdef['from'] ?? ''));
            $to = trim((string) ($linkdef['to'] ?? ''));
            if ($from !== '' && $to !== '') {
                $adjacency[$from][] = $to;
            }
        }

        $allkeys = [];
        foreach ($definition['nodes'] as $nodedef) {
            $key = trim((string) ($nodedef['key'] ?? ''));
            if ($key !== '') {
                $allkeys[$key] = true;
            }
        }

        $depths = [];
        if ($entrykey !== '' && isset($allkeys[$entrykey])) {
            $queue = [[$entrykey, 0]];
            $depths[$entrykey] = 0;
            while ($queue) {
                [$key, $depth] = array_shift($queue);
                foreach ($adjacency[$key] ?? [] as $child) {
                    if (!isset($depths[$child]) && isset($allkeys[$child])) {
                        $depths[$child] = $depth + 1;
                        $queue[] = [$child, $depth + 1];
                    }
                }
            }
        }

        // Any node not reached from the entry lands one column past the deepest.
        $maxdepth = $depths ? max($depths) : 0;
        foreach (array_keys($allkeys) as $key) {
            if (!isset($depths[$key])) {
                $depths[$key] = $maxdepth + 1;
            }
        }

        // Assign a row within each column in a stable order.
        $rowcounters = [];
        $positions = [];
        foreach (array_keys($allkeys) as $key) {
            $depth = $depths[$key];
            $row = $rowcounters[$depth] ?? 0;
            $rowcounters[$depth] = $row + 1;
            $positions[$key] = [
                60 + $depth * self::COL_WIDTH,
                60 + $row * self::ROW_HEIGHT,
            ];
        }
        return $positions;
    }
}
