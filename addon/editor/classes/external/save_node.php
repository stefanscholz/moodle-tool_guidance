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
 * External function: create or update a node from the canvas editor.
 *
 * @package    tool_guidance
 * @copyright  2026 Lily Asshauer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace guidanceaddon_editor\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use tool_guidance\api;
use tool_guidance\graph;
use tool_guidance\node;

/**
 * Persists a node's content and position; returns its id (and any validation error).
 */
class save_node extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'graphid' => new external_value(PARAM_INT, 'Graph id'),
            'id' => new external_value(PARAM_INT, 'Node id, 0 to create', VALUE_DEFAULT, 0),
            'type' => new external_value(PARAM_ALPHA, 'question or leaf'),
            'title' => new external_value(PARAM_TEXT, 'Title'),
            'description' => new external_value(PARAM_RAW, 'Description', VALUE_DEFAULT, ''),
            'targettype' => new external_value(PARAM_ALPHA, 'Target type (leaf)', VALUE_DEFAULT, ''),
            'targetconfig' => new external_value(PARAM_RAW, 'Target JSON (leaf)', VALUE_DEFAULT, ''),
            'posx' => new external_value(PARAM_FLOAT, 'Canvas X', VALUE_DEFAULT, 0),
            'posy' => new external_value(PARAM_FLOAT, 'Canvas Y', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Save the node.
     *
     * @param int $graphid
     * @param int $id
     * @param string $type
     * @param string $title
     * @param string $description
     * @param string $targettype
     * @param string $targetconfig
     * @param float $posx
     * @param float $posy
     * @return array
     */
    public static function execute(
        int $graphid,
        int $id,
        string $type,
        string $title,
        string $description,
        string $targettype,
        string $targetconfig,
        float $posx,
        float $posy
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'graphid' => $graphid, 'id' => $id, 'type' => $type, 'title' => $title,
            'description' => $description, 'targettype' => $targettype,
            'targetconfig' => $targetconfig, 'posx' => $posx, 'posy' => $posy,
        ]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('tool/guidance:manage', $context);

        $isleaf = $params['type'] === node::TYPE_LEAF;
        $record = (object) [
            'graphid' => $params['graphid'],
            'type' => $params['type'],
            'title' => $params['title'],
            'description' => $params['description'],
            'descriptionformat' => FORMAT_HTML,
            'targettype' => $isleaf && $params['targettype'] !== '' ? $params['targettype'] : null,
            'targetconfig' => $isleaf && $params['targetconfig'] !== '' ? $params['targetconfig'] : null,
            'posx' => $params['posx'],
            'posy' => $params['posy'],
        ];

        $error = '';
        try {
            if ($params['id']) {
                $node = new node($params['id']);
                $node->from_record($record);
                $node->update();
            } else {
                $graph = new graph($params['graphid']);
                $hadroot = (bool) $graph->get_root_nodes();
                // The first node of a graph is top-level because it has no
                // incoming answers yet.
                $node = new node(0, $record);
                $node->create();
                // If the site has no chooser entry yet, adopt this first
                // top-level node so "Help me choose" works straight away.
                if (!$hadroot && !api::get_chooser_entry_node()) {
                    api::set_chooser_entry((int) $node->get('id'));
                }
            }
        } catch (\core\invalid_persistent_exception $e) {
            return ['id' => $params['id'], 'error' => $e->getMessage()];
        }

        return ['id' => (int) $node->get('id'), 'error' => $error];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Saved node id'),
            'error' => new external_value(PARAM_RAW, 'Validation error, empty on success'),
        ]);
    }
}
