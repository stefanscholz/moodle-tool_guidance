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
 * Persistent model for a guidance graph.
 *
 * @package    tool_guidance
 * @copyright  2026 Lily Asshauer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guidance;

/**
 * Represents one guidance graph. Root nodes are inferred from the graph shape:
 * any node with no incoming answer link is a top-level entry point.
 */
class graph extends \core\persistent {
    /** @var string Table name. */
    const TABLE = 'tool_guidance_graph';

    /**
     * Property definitions.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'name' => [
                'type' => PARAM_TEXT,
                'description' => 'Human readable graph name.',
            ],
            'idnumber' => [
                'type' => PARAM_RAW,
                'description' => 'Optional machine key.',
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'description' => [
                'type' => PARAM_RAW,
                'description' => 'Longer description.',
                'null' => NULL_ALLOWED,
                'default' => '',
            ],
            'descriptionformat' => [
                'type' => PARAM_INT,
                'choices' => [FORMAT_HTML, FORMAT_MOODLE, FORMAT_PLAIN, FORMAT_MARKDOWN],
                'default' => FORMAT_HTML,
            ],
            'enabled' => [
                'type' => PARAM_BOOL,
                'default' => false,
            ],
        ];
    }

    /**
     * Return the nodes belonging to this graph.
     *
     * @return node[]
     */
    public function get_nodes(): array {
        return node::get_records(['graphid' => $this->get('id')], 'id');
    }

    /**
     * Return the root nodes of this graph (nodes with no incoming answers).
     *
     * @return node[]
     */
    public function get_root_nodes(): array {
        $nodes = node::get_records(['graphid' => $this->get('id')], 'id');
        if (!$nodes) {
            return [];
        }

        $incoming = [];
        foreach (link::get_records(['graphid' => $this->get('id')]) as $link) {
            $childid = (int) $link->get('childnodeid');
            if ($childid) {
                $incoming[$childid] = true;
            }
        }

        return array_filter($nodes, static function(node $node) use ($incoming): bool {
            return empty($incoming[(int) $node->get('id')]);
        });
    }

    /**
     * Return the links belonging to this graph.
     *
     * @return link[]
     */
    public function get_links(): array {
        return link::get_records(['graphid' => $this->get('id')], 'parentnodeid, sortorder');
    }
}
