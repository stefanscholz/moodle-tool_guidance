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
 * Persistent model for a guidance link (edge = answer).
 *
 * @package    tool_guidance
 * @copyright  2026 Lily Asshauer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guidance;

/**
 * Represents a directed edge from a question node to a child node, carrying an answer.
 */
class link extends \core\persistent {
    /** @var string Table name. */
    const TABLE = 'tool_guidance_link';

    /**
     * Property definitions.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'graphid' => [
                'type' => PARAM_INT,
                'description' => 'Owning graph.',
            ],
            'parentnodeid' => [
                'type' => PARAM_INT,
                'description' => 'Source question node.',
            ],
            'childnodeid' => [
                'type' => PARAM_INT,
                'description' => 'Target node; null while the answer is dangling.',
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'answerlabel' => [
                'type' => PARAM_TEXT,
                'description' => 'Answer shown on the edge.',
            ],
            'sortorder' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'posx' => [
                'type' => PARAM_FLOAT,
                'description' => 'Answer box canvas X position.',
                'default' => 0,
            ],
            'posy' => [
                'type' => PARAM_FLOAT,
                'description' => 'Answer box canvas Y position.',
                'default' => 0,
            ],
        ];
    }

    /**
     * The parent must be an existing question node in the same graph.
     *
     * @param int $value
     * @return true|\lang_string
     */
    protected function validate_parentnodeid($value) {
        $parent = node::get_record(['id' => $value]);
        if (!$parent) {
            return new \lang_string('error:nodenotfound', 'tool_guidance');
        }
        if (!$parent->is_question()) {
            return new \lang_string('error:parentnotquestion', 'tool_guidance');
        }
        if ((int) $parent->get('graphid') !== (int) $this->get('graphid')) {
            return new \lang_string('error:crossgraphlink', 'tool_guidance');
        }
        return true;
    }

    /**
     * The child, when set, must be an existing node in the same graph and not
     * equal to the parent. A null/empty child means the answer is dangling.
     *
     * @param int|null $value
     * @return true|\lang_string
     */
    protected function validate_childnodeid($value) {
        if ($value === null || (int) $value === 0) {
            return true;
        }
        if ((int) $value === (int) $this->get('parentnodeid')) {
            return new \lang_string('error:selflink', 'tool_guidance');
        }
        $child = node::get_record(['id' => $value]);
        if (!$child) {
            return new \lang_string('error:nodenotfound', 'tool_guidance');
        }
        if ((int) $child->get('graphid') !== (int) $this->get('graphid')) {
            return new \lang_string('error:crossgraphlink', 'tool_guidance');
        }
        return true;
    }
}
