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
 * External function: set the entry node of a graph.
 *
 * @package    tool_guidance
 * @copyright  2026 Lily Asshauer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace guidanceaddon_editor\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use tool_guidance\graph;
use tool_guidance\node;

/**
 * Marks a node as the graph's entry node.
 */
class set_root extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'graphid' => new external_value(PARAM_INT, 'Graph id'),
            'nodeid' => new external_value(PARAM_INT, 'Node id'),
        ]);
    }

    /**
     * Set the entry node.
     *
     * @param int $graphid
     * @param int $nodeid
     * @return bool
     */
    public static function execute(int $graphid, int $nodeid): bool {
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['graphid' => $graphid, 'nodeid' => $nodeid]
        );
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('tool/guidance:manage', $context);

        $node = new node($params['nodeid']);
        if ((int) $node->get('graphid') !== $params['graphid']) {
            throw new \invalid_parameter_exception('Node does not belong to the graph');
        }
        $graph = new graph($params['graphid']);
        $graph->set('rootnodeid', $node->get('id'));
        $graph->update();
        return true;
    }

    /**
     * Return value.
     *
     * @return external_value
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_BOOL, 'Success');
    }
}
