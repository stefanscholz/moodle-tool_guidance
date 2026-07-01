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
 * Install hook: seed the default suggestion rules from the shipped CSV.
 *
 * @package    tool_guidance
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Load db/seed_rules.csv into the rule table.
 *
 * Columns: sortorder, enabled, signal, name, condition, suggest, rationale, preconfig.
 *
 * @return bool
 */
function xmldb_tool_guidance_install() {
    global $DB, $CFG;

    $path = $CFG->dirroot . '/admin/tool/guidance/db/seed_rules.csv';
    if (!is_readable($path)) {
        return true;
    }

    $handle = fopen($path, 'r');
    if ($handle === false) {
        return true;
    }

    $header = fgetcsv($handle);
    $now = time();
    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) < 8) {
            continue;
        }
        [$sortorder, $enabled, $signal, $name, $condition, $suggest, $rationale, $preconfig] = $row;
        $DB->insert_record('tool_guidance_rule', (object) [
            'sortorder'     => (int) $sortorder,
            'enabled'       => (int) $enabled,
            'signal'        => trim($signal),
            'name'          => trim($name),
            'conditiontext' => trim($condition),
            'suggestmod'    => trim($suggest),
            'rationale'     => trim($rationale),
            'preconfig'     => trim($preconfig),
            'timecreated'   => $now,
            'timemodified'  => $now,
        ]);
    }
    fclose($handle);

    return true;
}
