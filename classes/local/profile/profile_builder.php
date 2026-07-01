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

namespace tool_guidance\local\profile;

use context_course;
use completion_info;

/**
 * Builds a {@see course_profile} from a course using core Moodle APIs.
 *
 * Cheap, deterministic facts (structure, settings, purposes) are always computed.
 * The costlier engagement facts (log/analytics queries) are computed only when the
 * admin setting is on, and every such query fails safe to a null fact.
 *
 * @package    tool_guidance
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_builder {

    /** @var int Seconds in a day. */
    const DAY = 86400;

    /**
     * Build the profile for a course.
     *
     * @param \stdClass $course full course record
     * @param bool|null $withengagement override the admin setting (mainly for tests)
     * @return course_profile
     */
    public static function build(\stdClass $course, ?bool $withengagement = null): course_profile {
        $facts = self::structural_facts($course);

        $withengagement ??= (bool) get_config('tool_guidance', 'enableengagementfacts');
        if ($withengagement) {
            $facts += self::engagement_facts($course, $facts['activity_count'], $facts['enrolled_students']);
        }

        return new course_profile($facts);
    }

    /**
     * The always-computed structural and settings facts.
     *
     * @param \stdClass $course
     * @return array<string, mixed>
     */
    private static function structural_facts(\stdClass $course): array {
        global $DB;

        $modinfo = get_fast_modinfo($course);
        $cms = $modinfo->get_cms();

        $modulecounts = [];
        $purposes = [];
        $resourcecount = 0;
        $assessmentcount = 0;
        foreach ($cms as $cm) {
            if ($cm->deletioninprogress) {
                continue;
            }
            $modulecounts[$cm->modname] = ($modulecounts[$cm->modname] ?? 0) + 1;
            $purpose = self::purpose_of($cm->modname, $purposes);
            if ($purpose === MOD_PURPOSE_ASSESSMENT) {
                $assessmentcount++;
            }
            if (self::is_passive($cm->modname)) {
                $resourcecount++;
            }
        }

        $activitycount = array_sum($modulecounts);
        $haspurpose = self::purpose_presence($purposes);

        $sections = $modinfo->get_section_info_all();
        $usedsections = $modinfo->get_sections();
        $emptysections = 0;
        foreach ($sections as $section) {
            if (empty($usedsections[$section->section])) {
                $emptysections++;
            }
        }

        $coursecontext = context_course::instance($course->id);
        $enrolledstudents = count_enrolled_users($coursecontext);

        $facts = [
            'activity_count'                 => $activitycount,
            'distinct_module_types'          => count($modulecounts),
            'resource_count'                 => $resourcecount,
            'interactive_count'              => max(0, $activitycount - $resourcecount),
            'assessment_count'               => $assessmentcount,
            'section_count'                  => count($sections),
            'empty_section_count'            => $emptysections,
            'graded_items_count'             => $DB->count_records('grade_items',
                ['courseid' => $course->id, 'itemtype' => 'mod']),
            'completion_tracked_count'       => self::completion_tracked_count($cms),
            'has_purpose_assessment'         => $haspurpose[MOD_PURPOSE_ASSESSMENT],
            'has_purpose_communication'      => $haspurpose[MOD_PURPOSE_COMMUNICATION],
            'has_purpose_collaboration'      => $haspurpose[MOD_PURPOSE_COLLABORATION],
            'has_purpose_content'            => $haspurpose[MOD_PURPOSE_CONTENT],
            'has_purpose_interactivecontent' => $haspurpose[MOD_PURPOSE_INTERACTIVECONTENT],
            'has_feedback_activity'          => (($modulecounts['feedback'] ?? 0)
                + ($modulecounts['survey'] ?? 0) + ($modulecounts['choice'] ?? 0)) > 0,
            'enablecompletion'               => (bool) $course->enablecompletion,
            'is_empty_shell'                 => $activitycount <= 1,
            'has_groups'                     => self::has_groups($course),
            'groupmode'                      => self::groupmode_name((int) $course->groupmode),
            'format'                         => (string) $course->format,
            'enrolled_students'              => $enrolledstudents,
            'course_age_days'                => self::course_age_days($course),
            'term_stage'                     => self::term_stage($course),
        ];

        foreach ($modulecounts as $modname => $count) {
            $facts[fact_catalogue::MODULE_COUNT_PREFIX . $modname] = $count;
        }

        return $facts;
    }

    /**
     * Look up (and memoise) a module's primary purpose.
     *
     * @param string $modname
     * @param array<string, string> $cache by-reference per-build memo
     * @return string a MOD_PURPOSE_* value
     */
    private static function purpose_of(string $modname, array &$cache): string {
        if (!isset($cache[$modname])) {
            $cache[$modname] = (string) plugin_supports('mod', $modname, FEATURE_MOD_PURPOSE, MOD_PURPOSE_OTHER);
        }
        return $cache[$modname];
    }

    /**
     * Whether a module is passive content (resource archetype).
     *
     * @param string $modname
     * @return bool
     */
    private static function is_passive(string $modname): bool {
        return plugin_supports('mod', $modname, FEATURE_MOD_ARCHETYPE, MOD_ARCHETYPE_OTHER) === MOD_ARCHETYPE_RESOURCE;
    }

    /**
     * Reduce the set of seen purposes to has_purpose_* booleans.
     *
     * @param array<string, string> $purposes modname => purpose
     * @return array<string, bool> purpose => present
     */
    private static function purpose_presence(array $purposes): array {
        $seen = array_values($purposes);
        return [
            MOD_PURPOSE_ASSESSMENT         => in_array(MOD_PURPOSE_ASSESSMENT, $seen, true),
            MOD_PURPOSE_COMMUNICATION      => in_array(MOD_PURPOSE_COMMUNICATION, $seen, true),
            MOD_PURPOSE_COLLABORATION      => in_array(MOD_PURPOSE_COLLABORATION, $seen, true),
            MOD_PURPOSE_CONTENT            => in_array(MOD_PURPOSE_CONTENT, $seen, true),
            MOD_PURPOSE_INTERACTIVECONTENT => in_array(MOD_PURPOSE_INTERACTIVECONTENT, $seen, true),
        ];
    }

    /**
     * Count course modules with completion tracking switched on.
     *
     * @param \cm_info[] $cms
     * @return int
     */
    private static function completion_tracked_count(array $cms): int {
        $count = 0;
        foreach ($cms as $cm) {
            if (!$cm->deletioninprogress && $cm->completion != COMPLETION_TRACKING_NONE) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Whether the course uses groups (course default or any group defined).
     *
     * @param \stdClass $course
     * @return bool
     */
    private static function has_groups(\stdClass $course): bool {
        if ((int) $course->groupmode !== NOGROUPS) {
            return true;
        }
        return count(groups_get_all_groups($course->id)) > 0;
    }

    /**
     * Map a numeric group mode to an enum token.
     *
     * @param int $groupmode
     * @return string none|separate|visible
     */
    private static function groupmode_name(int $groupmode): string {
        return match ($groupmode) {
            SEPARATEGROUPS => 'separate',
            VISIBLEGROUPS  => 'visible',
            default        => 'none',
        };
    }

    /**
     * Course age in whole days since creation.
     *
     * @param \stdClass $course
     * @return int
     */
    private static function course_age_days(\stdClass $course): int {
        if (empty($course->timecreated)) {
            return 0;
        }
        return (int) floor((time() - $course->timecreated) / self::DAY);
    }

    /**
     * Classify where the course sits in its term.
     *
     * @param \stdClass $course
     * @return string prestart|week1|early|mid|late|postend|undated
     */
    private static function term_stage(\stdClass $course): string {
        $now = time();
        $start = (int) $course->startdate;
        $end = (int) $course->enddate;

        if (!$start && !$end) {
            return 'undated';
        }
        if ($start && $now < $start) {
            return 'prestart';
        }
        if ($end && $now > $end) {
            return 'postend';
        }
        if ($start && $now < $start + 7 * self::DAY) {
            return 'week1';
        }
        if ($start && $end && $end > $start) {
            $ratio = ($now - $start) / ($end - $start);
            if ($ratio < 0.34) {
                return 'early';
            }
            return $ratio < 0.67 ? 'mid' : 'late';
        }
        // Started, open-ended: classify by elapsed days.
        $elapsed = ($now - $start) / self::DAY;
        if ($elapsed < 28) {
            return 'early';
        }
        return $elapsed < 84 ? 'mid' : 'late';
    }

    /**
     * The engagement facts, each computed defensively (null on any failure).
     *
     * @param \stdClass $course
     * @param int $activitycount
     * @param int $enrolledstudents
     * @return array<string, mixed>
     */
    private static function engagement_facts(\stdClass $course, int $activitycount, int $enrolledstudents): array {
        return [
            'recent_events_7d'           => self::safe(fn() => self::recent_events($course->id, 7)),
            'active_students_7d'         => self::safe(fn() => self::active_students($course->id, 7)),
            'dead_activity_count'        => self::safe(fn() => self::dead_activities($course)),
            'completion_rate'            => self::safe(fn() => self::completion_rate($course, $enrolledstudents)),
            'forum_post_count'           => self::safe(fn() => self::forum_posts($course->id)),
            'quiz_attempt_count'         => self::safe(fn() => self::quiz_attempts($course->id)),
            'assignment_submission_rate' => self::safe(fn() => self::assignment_rate($course->id, $enrolledstudents)),
        ];
    }

    /**
     * Run a fact callback, returning null if it throws or the log store is absent.
     *
     * @param callable $callback
     * @return int|null
     */
    private static function safe(callable $callback): ?int {
        try {
            $value = $callback();
            return $value === null ? null : (int) $value;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Whether the standard log store table is present.
     *
     * @return bool
     */
    private static function has_log_store(): bool {
        global $DB;
        return $DB->get_manager()->table_exists('logstore_standard_log');
    }

    /**
     * Number of logged events in the course within the last $days days.
     *
     * @param int $courseid
     * @param int $days
     * @return int|null
     */
    private static function recent_events(int $courseid, int $days): ?int {
        global $DB;
        if (!self::has_log_store()) {
            return null;
        }
        return $DB->count_records_select('logstore_standard_log',
            'courseid = ? AND timecreated > ?', [$courseid, time() - $days * self::DAY]);
    }

    /**
     * Number of distinct real users active in the course within the last $days days.
     *
     * @param int $courseid
     * @param int $days
     * @return int|null
     */
    private static function active_students(int $courseid, int $days): ?int {
        global $DB;
        if (!self::has_log_store()) {
            return null;
        }
        return (int) $DB->count_records_sql(
            'SELECT COUNT(DISTINCT userid) FROM {logstore_standard_log}
              WHERE courseid = ? AND timecreated > ? AND userid > 0',
            [$courseid, time() - $days * self::DAY]);
    }

    /**
     * Count of course modules that have never been viewed (per the log store).
     *
     * @param \stdClass $course
     * @return int|null
     */
    private static function dead_activities(\stdClass $course): ?int {
        global $DB;
        if (!self::has_log_store()) {
            return null;
        }
        $modinfo = get_fast_modinfo($course);
        $cmids = array_keys($modinfo->get_cms());
        if (!$cmids) {
            return 0;
        }
        $viewed = $DB->get_fieldset_sql(
            'SELECT DISTINCT contextinstanceid FROM {logstore_standard_log}
              WHERE courseid = ? AND contextlevel = ? AND crud = ?',
            [$course->id, CONTEXT_MODULE, 'r']);
        $viewed = array_flip(array_map('intval', $viewed));
        $dead = 0;
        foreach ($cmids as $cmid) {
            if (!isset($viewed[$cmid])) {
                $dead++;
            }
        }
        return $dead;
    }

    /**
     * Mean activity-completion rate across tracked activities, as a 0-100 percentage.
     *
     * @param \stdClass $course
     * @param int $enrolledstudents
     * @return int|null
     */
    private static function completion_rate(\stdClass $course, int $enrolledstudents): ?int {
        global $DB;
        if (!$course->enablecompletion || $enrolledstudents < 1) {
            return null;
        }
        $completion = new completion_info($course);
        $activities = $completion->get_activities();
        if (!$activities) {
            return null;
        }
        $cmids = array_keys($activities);
        [$insql, $params] = $DB->get_in_or_equal($cmids);
        $completed = (int) $DB->count_records_select('course_modules_completion',
            "coursemoduleid $insql AND completionstate > 0", $params);
        $possible = $enrolledstudents * count($cmids);
        return $possible > 0 ? (int) round(100 * $completed / $possible) : 0;
    }

    /**
     * Total forum posts across the course's forums.
     *
     * @param int $courseid
     * @return int|null
     */
    private static function forum_posts(int $courseid): ?int {
        global $DB;
        if (!$DB->get_manager()->table_exists('forum_posts')) {
            return null;
        }
        return (int) $DB->count_records_sql(
            'SELECT COUNT(fp.id) FROM {forum_posts} fp
               JOIN {forum_discussions} fd ON fd.id = fp.discussion
               JOIN {forum} f ON f.id = fd.forum
              WHERE f.course = ?', [$courseid]);
    }

    /**
     * Total non-preview quiz attempts across the course's quizzes.
     *
     * @param int $courseid
     * @return int|null
     */
    private static function quiz_attempts(int $courseid): ?int {
        global $DB;
        if (!$DB->get_manager()->table_exists('quiz_attempts')) {
            return null;
        }
        return (int) $DB->count_records_sql(
            'SELECT COUNT(qa.id) FROM {quiz_attempts} qa
               JOIN {quiz} q ON q.id = qa.quiz
              WHERE q.course = ? AND qa.preview = 0', [$courseid]);
    }

    /**
     * Percentage of enrolled students who have a submitted assignment, 0-100.
     *
     * @param int $courseid
     * @param int $enrolledstudents
     * @return int|null
     */
    private static function assignment_rate(int $courseid, int $enrolledstudents): ?int {
        global $DB;
        if ($enrolledstudents < 1 || !$DB->get_manager()->table_exists('assign_submission')) {
            return null;
        }
        $submitted = (int) $DB->count_records_sql(
            'SELECT COUNT(DISTINCT s.userid) FROM {assign_submission} s
               JOIN {assign} a ON a.id = s.assignment
              WHERE a.course = ? AND s.status = ? AND s.latest = 1',
            [$courseid, 'submitted']);
        return (int) round(100 * $submitted / $enrolledstudents);
    }
}
