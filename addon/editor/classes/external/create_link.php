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
 * External function: create an answer link between two nodes.
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

/**
 * Creates a link, enforcing the no-cycle and parent-is-question rules server-side.
 *
 * Returns the new link id, or id 0 with a populated error message when the link
 * is rejected (so the canvas can refuse the connection without a fatal error).
 */
class create_link extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'graphid' => new external_value(PARAM_INT, 'Graph id'),
            'parentnodeid' => new external_value(PARAM_INT, 'Parent (question) node id'),
            'childnodeid' => new external_value(PARAM_INT, 'Child node id, 0 for a dangling answer', VALUE_DEFAULT, 0),
            'answerlabel' => new external_value(PARAM_TEXT, 'Answer label', VALUE_DEFAULT, ''),
            'posx' => new external_value(PARAM_FLOAT, 'Answer box X', VALUE_DEFAULT, 0),
            'posy' => new external_value(PARAM_FLOAT, 'Answer box Y', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Create the link.
     *
     * @param int $graphid
     * @param int $parentnodeid
     * @param int $childnodeid
     * @param string $answerlabel
     * @return array
     */
    public static function execute(
        int $graphid,
        int $parentnodeid,
        int $childnodeid,
        string $answerlabel,
        float $posx = 0,
        float $posy = 0
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'graphid' => $graphid, 'parentnodeid' => $parentnodeid,
            'childnodeid' => $childnodeid, 'answerlabel' => $answerlabel,
            'posx' => $posx, 'posy' => $posy,
        ]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('tool/guidance:manage', $context);

        try {
            $link = api::create_link((object) [
                'graphid' => $params['graphid'],
                'parentnodeid' => $params['parentnodeid'],
                'childnodeid' => $params['childnodeid'],
                'answerlabel' => $params['answerlabel'],
                'sortorder' => 0,
                'posx' => $params['posx'],
                'posy' => $params['posy'],
            ]);
        } catch (\moodle_exception | \core\invalid_persistent_exception $e) {
            return ['id' => 0, 'error' => $e->getMessage()];
        }

        return ['id' => (int) $link->get('id'), 'error' => ''];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'New link id, 0 if rejected'),
            'error' => new external_value(PARAM_RAW, 'Rejection reason, empty on success'),
        ]);
    }
}
