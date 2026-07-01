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
 * External function: rename an answer link.
 *
 * @package    tool_guidance
 * @copyright  2026 Lily Asshauer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guidance\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use tool_guidance\link;

/**
 * Updates the answer label on an existing link.
 */
class update_link extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Link id'),
            'answerlabel' => new external_value(PARAM_TEXT, 'Answer label'),
        ]);
    }

    /**
     * Update the label.
     *
     * @param int $id
     * @param string $answerlabel
     * @return bool
     */
    public static function execute(int $id, string $answerlabel): bool {
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['id' => $id, 'answerlabel' => $answerlabel]
        );
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('tool/guidance:manage', $context);

        $link = new link($params['id']);
        $link->set('answerlabel', $params['answerlabel']);
        $link->update();
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
