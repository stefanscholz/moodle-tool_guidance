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
 * External function: point an answer at a child node (or clear it).
 *
 * @package    tool_guidance
 * @copyright  2026 Lily Asshauer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guidance\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use tool_guidance\api;
use tool_guidance\link;

/**
 * Attaches a dangling answer to a child node, replaces its target, or clears it.
 *
 * Returns an empty error on success, or a populated error (e.g. a cycle) so the
 * canvas can refuse the connection without a fatal error.
 */
class link_answer extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Answer (link) id'),
            'childnodeid' => new external_value(PARAM_INT, 'Child node id, 0 to clear'),
        ]);
    }

    /**
     * Set or clear the child.
     *
     * @param int $id
     * @param int $childnodeid
     * @return array
     */
    public static function execute(int $id, int $childnodeid): array {
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['id' => $id, 'childnodeid' => $childnodeid]
        );
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('tool/guidance:manage', $context);

        try {
            api::set_answer_child(new link($params['id']), $params['childnodeid']);
        } catch (\moodle_exception | \core\invalid_persistent_exception $e) {
            return ['error' => $e->getMessage()];
        }

        return ['error' => ''];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'error' => new external_value(PARAM_RAW, 'Rejection reason, empty on success'),
        ]);
    }
}
