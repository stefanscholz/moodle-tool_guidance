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
 * Persistent model for a guidance node.
 *
 * @package    tool_guidance
 * @copyright  2026 Lily Asshauer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guidance;

use tool_guidance\target\manager as targetmanager;

/**
 * Represents a single node: either a question or a leaf.
 */
class node extends \core\persistent {
    /** @var string Table name. */
    const TABLE = 'tool_guidance_node';

    /** @var string A question node (has outgoing answer links). */
    const TYPE_QUESTION = 'question';

    /** @var string A leaf node (terminal, has a target). */
    const TYPE_LEAF = 'leaf';

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
            'type' => [
                'type' => PARAM_ALPHA,
                'choices' => [self::TYPE_QUESTION, self::TYPE_LEAF],
            ],
            'title' => [
                'type' => PARAM_TEXT,
                'description' => 'Question text or leaf heading.',
            ],
            'description' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => '',
            ],
            'descriptionformat' => [
                'type' => PARAM_INT,
                'choices' => [FORMAT_HTML, FORMAT_MOODLE, FORMAT_PLAIN, FORMAT_MARKDOWN],
                'default' => FORMAT_HTML,
            ],
            'targettype' => [
                'type' => PARAM_ALPHA,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'targetconfig' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'posx' => [
                'type' => PARAM_FLOAT,
                'default' => 0,
            ],
            'posy' => [
                'type' => PARAM_FLOAT,
                'default' => 0,
            ],
        ];
    }

    /**
     * A leaf must declare a known target type; a question must not.
     *
     * @param string|null $value
     * @return true|\lang_string
     */
    protected function validate_targettype($value) {
        if ($this->get('type') === self::TYPE_LEAF) {
            if ($value === null || $value === '') {
                return new \lang_string('error:leafneedstarget', 'tool_guidance');
            }
            if (!targetmanager::type_exists($value)) {
                return new \lang_string('error:unknowntargettype', 'tool_guidance', $value);
            }
        } else if (!empty($value)) {
            return new \lang_string('error:questionhastarget', 'tool_guidance');
        }
        return true;
    }

    /**
     * Validate the JSON config against the chosen target type.
     *
     * @param string|null $value
     * @return true|\lang_string
     */
    protected function validate_targetconfig($value) {
        if ($this->get('type') !== self::TYPE_LEAF) {
            return true;
        }
        $targettype = $this->get('targettype');
        if (!$targettype || !targetmanager::type_exists($targettype)) {
            // Reported by validate_targettype(); nothing more to check here.
            return true;
        }
        $config = $value === null || $value === '' ? [] : json_decode($value, true);
        if (!is_array($config)) {
            return new \lang_string('error:invalidtargetconfig', 'tool_guidance');
        }
        if ($config === []) {
            // A freshly added leaf has no target details yet; allow it as a draft.
            // Full target validation happens once the author configures the leaf.
            return true;
        }
        return targetmanager::get_target($targettype, $config)->validate_config();
    }

    /**
     * Is this node a question?
     *
     * @return bool
     */
    public function is_question(): bool {
        return $this->get('type') === self::TYPE_QUESTION;
    }

    /**
     * Is this node a leaf?
     *
     * @return bool
     */
    public function is_leaf(): bool {
        return $this->get('type') === self::TYPE_LEAF;
    }

    /**
     * Return the decoded target config (leaf nodes only).
     *
     * @return array
     */
    public function get_targetconfig_array(): array {
        $raw = $this->get('targetconfig');
        if ($raw === null || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
