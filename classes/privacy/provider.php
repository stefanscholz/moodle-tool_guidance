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
 * Privacy provider for tool_guidance.
 *
 * @package    tool_guidance
 * @copyright  2026 Lily Asshauer, bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guidance\privacy;

use context;
use context_course;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Two kinds of personal data:
 *  - Graph authoring audit references (usermodified) in the system context.
 *  - Course-wide suggestion dismissals (who dismissed which rule) in course contexts.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /** @var string[] Graph tables carrying a usermodified reference (system context). */
    private const AUDIT_TABLES = ['tool_guidance_graph', 'tool_guidance_node', 'tool_guidance_link'];

    /** @var string The course-wide dismissal table. */
    private const DISMISS_TABLE = 'tool_guidance_dismissed';

    /**
     * Describe the data stored by this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $auditfields = [
            'usermodified' => 'privacy:metadata:usermodified',
            'timecreated' => 'privacy:metadata:timecreated',
            'timemodified' => 'privacy:metadata:timemodified',
        ];
        $collection->add_database_table('tool_guidance_graph', $auditfields, 'privacy:metadata:tool_guidance_graph');
        $collection->add_database_table('tool_guidance_node', $auditfields, 'privacy:metadata:tool_guidance_node');
        $collection->add_database_table('tool_guidance_link', $auditfields, 'privacy:metadata:tool_guidance_link');

        $collection->add_database_table(self::DISMISS_TABLE, [
            'courseid' => 'privacy:metadata:dismissed:courseid',
            'ruleid' => 'privacy:metadata:dismissed:ruleid',
            'userid' => 'privacy:metadata:dismissed:userid',
            'timecreated' => 'privacy:metadata:dismissed:timecreated',
        ], 'privacy:metadata:dismissed');

        return $collection;
    }

    /**
     * Contexts holding data for the user: the system context (graph authoring) and
     * any course context where the user dismissed a suggestion.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();

        foreach (self::AUDIT_TABLES as $table) {
            if ($DB->record_exists($table, ['usermodified' => $userid])) {
                $contextlist->add_system_context();
                break;
            }
        }

        $sql = "SELECT ctx.id
                  FROM {" . self::DISMISS_TABLE . "} d
                  JOIN {context} ctx ON ctx.instanceid = d.courseid AND ctx.contextlevel = :courselevel
                 WHERE d.userid = :userid";
        $contextlist->add_from_sql($sql, ['courselevel' => CONTEXT_COURSE, 'userid' => $userid]);

        return $contextlist;
    }

    /**
     * List users with data in the given context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if ($context->contextlevel == CONTEXT_SYSTEM) {
            foreach (self::AUDIT_TABLES as $table) {
                $userlist->add_from_sql(
                    'usermodified',
                    "SELECT usermodified FROM {{$table}} WHERE usermodified <> 0",
                    []
                );
            }
        } else if ($context instanceof context_course) {
            $userlist->add_from_sql(
                'userid',
                "SELECT userid FROM {" . self::DISMISS_TABLE . "} WHERE courseid = :courseid",
                ['courseid' => $context->instanceid]
            );
        }
    }

    /**
     * Export the user's data across the approved contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_SYSTEM) {
                $export = [];
                foreach (self::AUDIT_TABLES as $table) {
                    foreach ($DB->get_records($table, ['usermodified' => $userid]) as $record) {
                        $export[$table][] = [
                            'id' => $record->id,
                            'timecreated' => transform::datetime($record->timecreated),
                            'timemodified' => transform::datetime($record->timemodified),
                        ];
                    }
                }
                if ($export) {
                    writer::with_context($context)->export_data(
                        [get_string('pluginname', 'tool_guidance')],
                        (object) $export
                    );
                }
            } else if ($context instanceof context_course) {
                $records = $DB->get_records(self::DISMISS_TABLE,
                    ['courseid' => $context->instanceid, 'userid' => $userid]);
                if (!$records) {
                    continue;
                }
                $data = array_map(static function($r) {
                    return [
                        'ruleid' => $r->ruleid,
                        'timecreated' => transform::datetime($r->timecreated),
                    ];
                }, array_values($records));
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'tool_guidance')],
                    (object) ['dismissals' => $data]
                );
            }
        }
    }

    /**
     * Delete all data in a context.
     *
     * @param context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            foreach (self::AUDIT_TABLES as $table) {
                $DB->set_field($table, 'usermodified', 0);
            }
        } else if ($context instanceof context_course) {
            $DB->delete_records(self::DISMISS_TABLE, ['courseid' => $context->instanceid]);
        }
    }

    /**
     * Delete one user's data across the approved contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_SYSTEM) {
                foreach (self::AUDIT_TABLES as $table) {
                    $DB->set_field($table, 'usermodified', 0, ['usermodified' => $userid]);
                }
            } else if ($context instanceof context_course) {
                $DB->delete_records(self::DISMISS_TABLE, ['courseid' => $context->instanceid, 'userid' => $userid]);
            }
        }
    }

    /**
     * Delete the listed users' data in a context.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        if ($context->contextlevel == CONTEXT_SYSTEM) {
            foreach (self::AUDIT_TABLES as $table) {
                $DB->set_field_select($table, 'usermodified', 0, "usermodified {$insql}", $inparams);
            }
        } else if ($context instanceof context_course) {
            $inparams['courseid'] = $context->instanceid;
            $DB->delete_records_select(self::DISMISS_TABLE, "courseid = :courseid AND userid {$insql}", $inparams);
        }
    }
}
