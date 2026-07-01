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

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Guidance graphs are site configuration authored by admins. The only personal
 * data is the usermodified audit reference recording who last edited each row.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** @var string[] Tables that carry a usermodified reference. */
    private const TABLES = ['tool_guidance_graph', 'tool_guidance_node', 'tool_guidance_link'];

    /**
     * Describe the data stored by this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $fields = [
            'usermodified' => 'privacy:metadata:usermodified',
            'timecreated' => 'privacy:metadata:timecreated',
            'timemodified' => 'privacy:metadata:timemodified',
        ];
        $collection->add_database_table('tool_guidance_graph', $fields, 'privacy:metadata:tool_guidance_graph');
        $collection->add_database_table('tool_guidance_node', $fields, 'privacy:metadata:tool_guidance_node');
        $collection->add_database_table('tool_guidance_link', $fields, 'privacy:metadata:tool_guidance_link');
        return $collection;
    }

    /**
     * The plugin only ever stores data in the system context.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        foreach (self::TABLES as $table) {
            if ($DB->record_exists($table, ['usermodified' => $userid])) {
                $contextlist->add_system_context();
                break;
            }
        }
        return $contextlist;
    }

    /**
     * List users who have authored a row, within the system context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }
        foreach (self::TABLES as $table) {
            $userlist->add_from_sql(
                'usermodified',
                "SELECT usermodified FROM {{$table}} WHERE usermodified <> 0",
                []
            );
        }
    }

    /**
     * Export the rows the user last modified.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $hassystem = false;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_SYSTEM) {
                $hassystem = true;
                break;
            }
        }
        if (!$hassystem) {
            return;
        }

        $context = \context_system::instance();
        $userid = $contextlist->get_user()->id;
        $export = [];
        foreach (self::TABLES as $table) {
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
    }

    /**
     * Anonymise the audit reference rather than deleting shared configuration.
     *
     * @param \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }
        foreach (self::TABLES as $table) {
            $DB->set_field($table, 'usermodified', 0);
        }
    }

    /**
     * Anonymise the audit reference for one user.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_SYSTEM) {
                continue;
            }
            foreach (self::TABLES as $table) {
                $DB->set_field($table, 'usermodified', 0, ['usermodified' => $userid]);
            }
        }
    }

    /**
     * Anonymise the audit reference for a set of users.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        foreach (self::TABLES as $table) {
            $DB->set_field_select($table, 'usermodified', 0, "usermodified {$insql}", $inparams);
        }
    }
}
