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
 * Upgrade steps for tool_guidance.
 *
 * @package    tool_guidance
 * @copyright  2026 Lily Asshauer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin database.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool
 */
function xmldb_tool_guidance_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026063001) {
        $table = new xmldb_table('tool_guidance_node');
        foreach (['posx', 'posy'] as $fieldname) {
            $field = new xmldb_field(
                $fieldname,
                XMLDB_TYPE_NUMBER,
                '10, 2',
                null,
                XMLDB_NOTNULL,
                null,
                '0',
                'targetconfig'
            );
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_plugin_savepoint(true, 2026063001, 'tool', 'guidance');
    }

    if ($oldversion < 2026063003) {
        $table = new xmldb_table('tool_guidance_link');

        // Allow a dangling answer (a link with no child yet).
        $childfield = new xmldb_field('childnodeid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'parentnodeid');
        if ($dbman->field_exists($table, $childfield)) {
            $dbman->change_field_notnull($table, $childfield);
        }

        // Answer boxes carry their own canvas position.
        foreach (['posx', 'posy'] as $fieldname) {
            $field = new xmldb_field(
                $fieldname,
                XMLDB_TYPE_NUMBER,
                '10, 2',
                null,
                XMLDB_NOTNULL,
                null,
                '0',
                'sortorder'
            );
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_plugin_savepoint(true, 2026063003, 'tool', 'guidance');
    }

    return true;
}
