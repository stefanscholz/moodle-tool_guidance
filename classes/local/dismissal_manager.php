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

namespace tool_guidance\local;

/**
 * Records and queries course-wide dismissals of a suggestion rule.
 *
 * @package    tool_guidance
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dismissal_manager {

    /** @var string The dismissal table. */
    const TABLE = 'tool_guidance_dismissed';

    /** @var int Default cooldown in days when unconfigured. */
    const DEFAULT_COOLDOWN_DAYS = 30;

    /**
     * Record a dismissal of a rule for a whole course (upserting the timestamp).
     *
     * @param int $courseid
     * @param int $ruleid
     * @param int $userid the teacher who dismissed it
     */
    public function dismiss(int $courseid, int $ruleid, int $userid): void {
        global $DB;
        $existing = $DB->get_record(self::TABLE, ['courseid' => $courseid, 'ruleid' => $ruleid]);
        if ($existing) {
            $existing->userid = $userid;
            $existing->timecreated = time();
            $DB->update_record(self::TABLE, $existing);
        } else {
            $DB->insert_record(self::TABLE, (object) [
                'courseid'    => $courseid,
                'ruleid'      => $ruleid,
                'userid'      => $userid,
                'timecreated' => time(),
            ]);
        }
        engine::purge_course($courseid);
    }

    /**
     * Whether a rule is currently suppressed for a course (within the cooldown window).
     *
     * @param int $courseid
     * @param int $ruleid
     * @return bool
     */
    public function is_active(int $courseid, int $ruleid): bool {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['courseid' => $courseid, 'ruleid' => $ruleid]);
        if (!$record) {
            return false;
        }
        return (time() - (int) $record->timecreated) < $this->cooldown_seconds();
    }

    /**
     * Whether the course has any suggestion currently suppressed (i.e. anything to reset).
     *
     * @param int $courseid
     * @return bool
     */
    public function has_active_dismissals(int $courseid): bool {
        global $DB;
        $since = time() - $this->cooldown_seconds();
        return $DB->record_exists_select(self::TABLE,
            'courseid = ? AND timecreated > ?', [$courseid, $since]);
    }

    /**
     * Clear all dismissals for a course, so every suggestion can appear again.
     *
     * @param int $courseid
     */
    public function reset(int $courseid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['courseid' => $courseid]);
        engine::purge_course($courseid);
    }

    /**
     * The configured cooldown window in seconds.
     *
     * @return int
     */
    private function cooldown_seconds(): int {
        $days = (int) get_config('tool_guidance', 'cooldowndays');
        if ($days < 1) {
            $days = self::DEFAULT_COOLDOWN_DAYS;
        }
        return $days * DAYSECS;
    }
}
