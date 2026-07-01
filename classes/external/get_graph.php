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
 * External function: load a guidance graph for the canvas editor.
 *
 * @package    tool_guidance
 * @copyright  2026 Lily Asshauer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guidance\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use tool_guidance\graph;
use tool_guidance\link;
use tool_guidance\node;
use tool_guidance\target\manager as targetmanager;

/**
 * Returns the full structure of one graph: nodes (with positions) and links.
 */
class get_graph extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'graphid' => new external_value(PARAM_INT, 'Graph id'),
        ]);
    }

    /**
     * Load the graph.
     *
     * @param int $graphid
     * @return array
     */
    public static function execute(int $graphid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['graphid' => $graphid]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('tool/guidance:manage', $context);

        $graph = new graph($params['graphid']);

        $nodes = [];
        foreach (node::get_records(['graphid' => $graph->get('id')], 'id') as $n) {
            $nodes[] = [
                'id' => $n->get('id'),
                'type' => $n->get('type'),
                'title' => $n->get('title'),
                'description' => (string) $n->get('description'),
                'targettype' => (string) $n->get('targettype'),
                'targetconfig' => (string) $n->get('targetconfig'),
                'posx' => (float) $n->get('posx'),
                'posy' => (float) $n->get('posy'),
            ];
        }

        $links = [];
        foreach (link::get_records(['graphid' => $graph->get('id')], 'parentnodeid, sortorder') as $l) {
            $links[] = [
                'id' => $l->get('id'),
                'parentnodeid' => $l->get('parentnodeid'),
                'childnodeid' => (int) $l->get('childnodeid'),
                'answerlabel' => $l->get('answerlabel'),
                'posx' => (float) $l->get('posx'),
                'posy' => (float) $l->get('posy'),
            ];
        }

        $targettypes = [];
        foreach (targetmanager::get_menu() as $value => $label) {
            $targettypes[] = ['value' => $value, 'label' => $label];
        }

        $mods = [];
        foreach (\core_plugin_manager::instance()->get_plugins_of_type('mod') as $plugin) {
            $mods[$plugin->name] = $plugin->displayname;
        }
        \core_collator::asort($mods);
        $activitymods = [];
        foreach ($mods as $value => $label) {
            $activitymods[] = ['value' => $value, 'label' => $label];
        }

        return [
            'rootnodeid' => (int) $graph->get('rootnodeid'),
            'nodes' => $nodes,
            'links' => $links,
            'targettypes' => $targettypes,
            'activitymods' => $activitymods,
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'rootnodeid' => new external_value(PARAM_INT, 'Entry node id, 0 if unset'),
            'nodes' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Node id'),
                'type' => new external_value(PARAM_ALPHA, 'question or leaf'),
                'title' => new external_value(PARAM_TEXT, 'Title'),
                'description' => new external_value(PARAM_RAW, 'Description'),
                'targettype' => new external_value(PARAM_ALPHA, 'Target type or empty'),
                'targetconfig' => new external_value(PARAM_RAW, 'Target JSON config or empty'),
                'posx' => new external_value(PARAM_FLOAT, 'Canvas X'),
                'posy' => new external_value(PARAM_FLOAT, 'Canvas Y'),
            ])),
            'links' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Link id'),
                'parentnodeid' => new external_value(PARAM_INT, 'Parent node id'),
                'childnodeid' => new external_value(PARAM_INT, 'Child node id, 0 if dangling'),
                'answerlabel' => new external_value(PARAM_TEXT, 'Answer label'),
                'posx' => new external_value(PARAM_FLOAT, 'Answer box X'),
                'posy' => new external_value(PARAM_FLOAT, 'Answer box Y'),
            ])),
            'targettypes' => new external_multiple_structure(new external_single_structure([
                'value' => new external_value(PARAM_ALPHA, 'Target type key'),
                'label' => new external_value(PARAM_TEXT, 'Localised label'),
            ])),
            'activitymods' => new external_multiple_structure(new external_single_structure([
                'value' => new external_value(PARAM_PLUGIN, 'Module name'),
                'label' => new external_value(PARAM_TEXT, 'Module display name'),
            ])),
        ]);
    }
}
