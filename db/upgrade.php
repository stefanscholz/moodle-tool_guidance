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

    if ($oldversion < 2026070102) {
        // The graph model shared a version number with the presets work, so on
        // sites installed at 2026070100/01 the graph tables were never created.
        // Create any that are missing straight from install.xml.
        $xmlfile = __DIR__ . '/install.xml';
        foreach (['tool_guidance_graph', 'tool_guidance_node', 'tool_guidance_link'] as $tablename) {
            if (!$dbman->table_exists(new xmldb_table($tablename))) {
                $dbman->install_one_table_from_xmldb_file($xmlfile, $tablename);
            }
        }
        upgrade_plugin_savepoint(true, 2026070102, 'tool', 'guidance');
    }

    if ($oldversion < 2026070103) {
        // Suggestion engine tables (introduced via merge, so create them for
        // existing installs) and seed the default rules if empty.
        $xmlfile = __DIR__ . '/install.xml';
        foreach (['tool_guidance_rule', 'tool_guidance_dismissed'] as $tablename) {
            if (!$dbman->table_exists(new xmldb_table($tablename))) {
                $dbman->install_one_table_from_xmldb_file($xmlfile, $tablename);
            }
        }

        // Seed the default rule table from the shipped CSV if it is still empty.
        if (!$DB->count_records('tool_guidance_rule')) {
            $path = __DIR__ . '/seed_rules.csv';
            if (is_readable($path) && ($handle = fopen($path, 'r')) !== false) {
                fgetcsv($handle); // Skip the header row.
                $now = time();
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) < 8) {
                        continue;
                    }
                    [$sortorder, $enabled, $signaltype, $name, $condition, $suggest, $rationale, $preconfig] = $row;
                    $DB->insert_record('tool_guidance_rule', (object) [
                        'sortorder' => (int) $sortorder,
                        'enabled' => (int) $enabled,
                        'signaltype' => trim($signaltype),
                        'name' => trim($name),
                        'conditiontext' => trim($condition),
                        'suggestmod' => trim($suggest),
                        'rationale' => trim($rationale),
                        'preconfig' => trim($preconfig),
                        'timecreated' => $now,
                        'timemodified' => $now,
                    ]);
                }
                fclose($handle);
            }
        }

        upgrade_plugin_savepoint(true, 2026070103, 'tool', 'guidance');
    }

    if ($oldversion < 2026070105) {
        // "signal" is a reserved word in MySQL/MariaDB (used by SIGNAL/RESIGNAL in
        // stored routines) and broke table creation on those engines. Rename it on
        // any site where the table was already created (e.g. non-MySQL DBs where
        // the previous step above succeeded).
        $table = new xmldb_table('tool_guidance_rule');
        $field = new xmldb_field('signal', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null, 'enabled');
        if ($dbman->table_exists($table) && $dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'signaltype');
        }
        upgrade_plugin_savepoint(true, 2026070105, 'tool', 'guidance');
    }

    return true;
}
