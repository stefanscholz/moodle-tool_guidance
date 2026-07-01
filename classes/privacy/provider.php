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

namespace tool_guidance\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use context;
use context_course;

/**
 * Privacy provider: the plugin stores which user dismissed which suggestion in a course.
 *
 * @package    tool_guidance
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /** @var string The dismissal table. */
    const TABLE = 'tool_guidance_dismissed';

    /**
     * Describe the stored personal data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(self::TABLE, [
            'courseid'    => 'privacy:metadata:dismissed:courseid',
            'ruleid'      => 'privacy:metadata:dismissed:ruleid',
            'userid'      => 'privacy:metadata:dismissed:userid',
            'timecreated' => 'privacy:metadata:dismissed:timecreated',
        ], 'privacy:metadata:dismissed');
        return $collection;
    }

    /**
     * Course contexts in which the user has dismissed a suggestion.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {" . self::TABLE . "} d
                  JOIN {context} ctx ON ctx.instanceid = d.courseid AND ctx.contextlevel = :courselevel
                 WHERE d.userid = :userid";
        $contextlist->add_from_sql($sql, ['courselevel' => CONTEXT_COURSE, 'userid' => $userid]);
        return $contextlist;
    }

    /**
     * Users who have dismissals in the given (course) context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_course) {
            return;
        }
        $userlist->add_from_sql('userid', "SELECT userid FROM {" . self::TABLE . "} WHERE courseid = :courseid",
            ['courseid' => $context->instanceid]);
    }

    /**
     * Export the user's dismissals per course context.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_course) {
                continue;
            }
            $records = $DB->get_records(self::TABLE, ['courseid' => $context->instanceid, 'userid' => $userid]);
            if (!$records) {
                continue;
            }
            $data = array_map(static function($r) {
                return [
                    'ruleid'      => $r->ruleid,
                    'timecreated' => \core_privacy\local\request\transform::datetime($r->timecreated),
                ];
            }, array_values($records));
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'tool_guidance')],
                (object) ['dismissals' => $data]);
        }
    }

    /**
     * Delete all dismissals in a context.
     *
     * @param context $context
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;
        if ($context instanceof context_course) {
            $DB->delete_records(self::TABLE, ['courseid' => $context->instanceid]);
        }
    }

    /**
     * Delete a user's dismissals in the approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_course) {
                $DB->delete_records(self::TABLE, ['courseid' => $context->instanceid, 'userid' => $userid]);
            }
        }
    }

    /**
     * Delete the listed users' dismissals in a context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof context_course) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params['courseid'] = $context->instanceid;
        $DB->delete_records_select(self::TABLE, "courseid = :courseid AND userid $insql", $params);
    }
}
