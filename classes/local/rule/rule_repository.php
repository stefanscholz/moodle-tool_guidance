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

namespace tool_guidance\local\rule;

use tool_guidance\local\engine;

/**
 * Data-access for suggestion rules. All writes purge the suggestion cache.
 *
 * @package    tool_guidance
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule_repository {

    /** @var string The rule table. */
    const TABLE = 'tool_guidance_rule';

    /**
     * All enabled rules in precedence order.
     *
     * @return rule[]
     */
    public function get_enabled_rules(): array {
        global $DB;
        $records = $DB->get_records(self::TABLE, ['enabled' => 1], 'sortorder ASC, id ASC');
        return array_map([rule::class, 'from_record'], array_values($records));
    }

    /**
     * All rules in precedence order (for the admin list).
     *
     * @return rule[]
     */
    public function get_all_rules(): array {
        global $DB;
        $records = $DB->get_records(self::TABLE, null, 'sortorder ASC, id ASC');
        return array_map([rule::class, 'from_record'], array_values($records));
    }

    /**
     * Fetch one rule or null.
     *
     * @param int $id
     * @return rule|null
     */
    public function get(int $id): ?rule {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['id' => $id]);
        return $record ? rule::from_record($record) : null;
    }

    /**
     * Insert or update a rule, returning its id.
     *
     * @param rule $rule
     * @return int
     */
    public function save(rule $rule): int {
        global $DB;
        $record = $rule->to_record();
        $now = time();
        if (!empty($record->id)) {
            $record->timemodified = $now;
            $DB->update_record(self::TABLE, $record);
            $id = (int) $record->id;
        } else {
            $record->timecreated = $now;
            $record->timemodified = $now;
            $id = (int) $DB->insert_record(self::TABLE, $record);
        }
        engine::purge_all();
        return $id;
    }

    /**
     * Delete a rule and any dismissals referencing it.
     *
     * @param int $id
     */
    public function delete(int $id): void {
        global $DB;
        $DB->delete_records('tool_guidance_dismissed', ['ruleid' => $id]);
        $DB->delete_records(self::TABLE, ['id' => $id]);
        engine::purge_all();
    }

    /**
     * Toggle a rule's enabled flag.
     *
     * @param int $id
     * @param bool $enabled
     */
    public function set_enabled(int $id, bool $enabled): void {
        global $DB;
        $DB->set_field(self::TABLE, 'enabled', (int) $enabled, ['id' => $id]);
        $DB->set_field(self::TABLE, 'timemodified', time(), ['id' => $id]);
        engine::purge_all();
    }

    /**
     * Move a rule one place up or down by swapping sortorder with its neighbour.
     *
     * @param int $id
     * @param int $direction -1 to move up, +1 to move down
     */
    public function move(int $id, int $direction): void {
        global $DB;
        $rules = $this->get_all_rules();
        $index = null;
        foreach ($rules as $i => $rule) {
            if ($rule->id === $id) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            return;
        }
        $swap = $index + ($direction < 0 ? -1 : 1);
        if ($swap < 0 || $swap >= count($rules)) {
            return;
        }
        $a = $rules[$index];
        $b = $rules[$swap];
        // Swap sortorders; if equal, nudge to guarantee a strict change.
        $aorder = $a->sortorder;
        $border = $b->sortorder;
        if ($aorder === $border) {
            $border += $direction < 0 ? -1 : 1;
        }
        $DB->set_field(self::TABLE, 'sortorder', $border, ['id' => $a->id]);
        $DB->set_field(self::TABLE, 'sortorder', $aorder, ['id' => $b->id]);
        engine::purge_all();
    }

    /**
     * The next sortorder value (max + 1), for appending new rules.
     *
     * @return int
     */
    public function next_sortorder(): int {
        global $DB;
        $max = $DB->get_field_sql('SELECT MAX(sortorder) FROM {' . self::TABLE . '}');
        return (int) $max + 1;
    }

    /**
     * Total rule count.
     *
     * @return int
     */
    public function count(): int {
        global $DB;
        return $DB->count_records(self::TABLE);
    }

    /**
     * Delete all rules (and their dismissals) and re-seed from the shipped CSV.
     *
     * @return int the number of rules seeded
     */
    public function reset_to_defaults(): int {
        global $DB, $CFG;

        $DB->delete_records('tool_guidance_dismissed');
        $DB->delete_records(self::TABLE);

        $count = 0;
        $path = $CFG->dirroot . '/admin/tool/guidance/db/seed_rules.csv';
        if (is_readable($path) && ($handle = fopen($path, 'r')) !== false) {
            fgetcsv($handle); // Skip the header row.
            $now = time();
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 8) {
                    continue;
                }
                [$sortorder, $enabled, $signaltype, $name, $condition, $suggest, $rationale, $preconfig] = $row;
                $DB->insert_record(self::TABLE, (object) [
                    'sortorder' => (int) $sortorder,
                    'enabled' => (int) $enabled,
                    'signaltype' => trim($signaltype),
                    'name' => trim($name),
                    'conditiontext' => trim($condition),
                    'suggestmod' => trim($suggest),
                    'rationale' => trim($rationale),
                    'preconfig' => trim($preconfig),
                    'targettype' => !empty($row[8]) ? trim($row[8]) : 'activity',
                    'targetvalue' => isset($row[9]) ? trim($row[9]) : '',
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
                $count++;
            }
            fclose($handle);
        }

        engine::purge_all();
        return $count;
    }
}
