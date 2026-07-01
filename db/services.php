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
 * External function definitions for the tool_guidance canvas editor.
 *
 * @package    tool_guidance
 * @copyright  2026 Lily Asshauer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'tool_guidance_get_graph' => [
        'classname' => 'tool_guidance\external\get_graph',
        'description' => 'Load a guidance graph (nodes and links) for the editor.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'tool/guidance:manage',
    ],
    'tool_guidance_save_node' => [
        'classname' => 'tool_guidance\external\save_node',
        'description' => 'Create or update a node.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'tool/guidance:manage',
    ],
    'tool_guidance_move_node' => [
        'classname' => 'tool_guidance\external\move_node',
        'description' => 'Persist a node canvas position.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'tool/guidance:manage',
    ],
    'tool_guidance_delete_node' => [
        'classname' => 'tool_guidance\external\delete_node',
        'description' => 'Delete a node and its links.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'tool/guidance:manage',
    ],
    'tool_guidance_create_link' => [
        'classname' => 'tool_guidance\external\create_link',
        'description' => 'Create an answer link between two nodes.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'tool/guidance:manage',
    ],
    'tool_guidance_update_link' => [
        'classname' => 'tool_guidance\external\update_link',
        'description' => 'Rename an answer link.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'tool/guidance:manage',
    ],
    'tool_guidance_delete_link' => [
        'classname' => 'tool_guidance\external\delete_link',
        'description' => 'Delete an answer link.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'tool/guidance:manage',
    ],
    'tool_guidance_link_answer' => [
        'classname' => 'tool_guidance\external\link_answer',
        'description' => 'Point an answer at a child node or clear it.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'tool/guidance:manage',
    ],
    'tool_guidance_move_answer' => [
        'classname' => 'tool_guidance\external\move_answer',
        'description' => 'Persist an answer box canvas position.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'tool/guidance:manage',
    ],
    'tool_guidance_set_root' => [
        'classname' => 'tool_guidance\external\set_root',
        'description' => 'Set the entry node of a graph.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'tool/guidance:manage',
    ],
];
