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

/**
 * The published catalogue of facts a rule condition may reference.
 *
 * This is the single source of truth shared by the condition evaluator (to decide
 * whether an operand is a fact reference or a literal) and the admin UI (to advertise
 * the available facts to rule authors).
 *
 * @package    tool_guidance
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fact_catalogue {

    /** Prefix for the parametric per-module-type count facts (e.g. module_count.forum). */
    const MODULE_COUNT_PREFIX = 'module_count.';

    /**
     * The scalar fact keys, mapped to their type.
     *
     * Types: bool, int, float, enum (string).
     *
     * @return array<string, string> fact key => type
     */
    public static function scalar_facts(): array {
        return [
            // Structural booleans (pedagogical purposes).
            'has_purpose_assessment'        => 'bool',
            'has_purpose_communication'     => 'bool',
            'has_purpose_collaboration'     => 'bool',
            'has_purpose_content'           => 'bool',
            'has_purpose_interactivecontent' => 'bool',
            'has_feedback_activity'         => 'bool',
            // Settings booleans.
            'enablecompletion'              => 'bool',
            'is_empty_shell'                => 'bool',
            'has_groups'                    => 'bool',
            // Structural counts.
            'activity_count'                => 'int',
            'resource_count'                => 'int',
            'interactive_count'             => 'int',
            'assessment_count'              => 'int',
            'section_count'                 => 'int',
            'empty_section_count'           => 'int',
            'distinct_module_types'         => 'int',
            'graded_items_count'            => 'int',
            'completion_tracked_count'      => 'int',
            'course_age_days'               => 'int',
            'enrolled_students'             => 'int',
            // Engagement (computed only when enabled).
            'dead_activity_count'           => 'int',
            'recent_events_7d'              => 'int',
            'active_students_7d'            => 'int',
            'completion_rate'               => 'int',
            'forum_post_count'              => 'int',
            'quiz_attempt_count'            => 'int',
            'assignment_submission_rate'    => 'int',
            // Enums.
            'term_stage'                    => 'enum',
            'groupmode'                     => 'enum',
            'format'                        => 'enum',
        ];
    }

    /**
     * Whether the given key names a fact (scalar or a per-module count).
     *
     * @param string $key
     * @return bool
     */
    public static function is_fact(string $key): bool {
        if (str_starts_with($key, self::MODULE_COUNT_PREFIX)) {
            return true;
        }
        return array_key_exists($key, self::scalar_facts());
    }

    /**
     * Ordered fact groups: group key => human label.
     *
     * @return array<string, string>
     */
    public static function groups(): array {
        return [
            'purpose'     => get_string('factgroup_purpose', 'tool_guidance'),
            'structure'   => get_string('factgroup_structure', 'tool_guidance'),
            'settings'    => get_string('factgroup_settings', 'tool_guidance'),
            'lifecycle'   => get_string('factgroup_lifecycle', 'tool_guidance'),
            'engagement'  => get_string('factgroup_engagement', 'tool_guidance'),
            'modulecount' => get_string('factgroup_modulecount', 'tool_guidance'),
        ];
    }

    /**
     * Which group each scalar fact belongs to.
     *
     * @return array<string, string> fact key => group key
     */
    private static function fact_group_map(): array {
        return [
            'has_purpose_assessment' => 'purpose', 'has_purpose_communication' => 'purpose',
            'has_purpose_collaboration' => 'purpose', 'has_purpose_content' => 'purpose',
            'has_purpose_interactivecontent' => 'purpose', 'has_feedback_activity' => 'purpose',
            'activity_count' => 'structure', 'resource_count' => 'structure', 'interactive_count' => 'structure',
            'assessment_count' => 'structure', 'distinct_module_types' => 'structure', 'section_count' => 'structure',
            'empty_section_count' => 'structure', 'graded_items_count' => 'structure', 'completion_tracked_count' => 'structure',
            'enablecompletion' => 'settings', 'has_groups' => 'settings', 'groupmode' => 'settings', 'format' => 'settings',
            'term_stage' => 'lifecycle', 'course_age_days' => 'lifecycle', 'is_empty_shell' => 'lifecycle',
            'enrolled_students' => 'lifecycle',
            'dead_activity_count' => 'engagement', 'recent_events_7d' => 'engagement', 'active_students_7d' => 'engagement',
            'completion_rate' => 'engagement', 'forum_post_count' => 'engagement', 'quiz_attempt_count' => 'engagement',
            'assignment_submission_rate' => 'engagement',
        ];
    }

    /**
     * Human labels for scalar facts.
     *
     * @return array<string, string> fact key => label
     */
    private static function fact_labels(): array {
        return [
            'has_purpose_assessment' => 'Has an assessment activity',
            'has_purpose_communication' => 'Has a communication activity',
            'has_purpose_collaboration' => 'Has a collaboration activity',
            'has_purpose_content' => 'Has content/resources',
            'has_purpose_interactivecontent' => 'Has interactive content',
            'has_feedback_activity' => 'Has a feedback/choice/survey',
            'enablecompletion' => 'Completion tracking is on',
            'is_empty_shell' => 'Is an (almost) empty course',
            'has_groups' => 'Uses groups',
            'activity_count' => 'Number of activities',
            'resource_count' => 'Number of passive resources',
            'interactive_count' => 'Number of interactive activities',
            'assessment_count' => 'Number of assessment activities',
            'section_count' => 'Number of sections',
            'empty_section_count' => 'Number of empty sections',
            'distinct_module_types' => 'Distinct activity types used',
            'graded_items_count' => 'Number of graded items',
            'completion_tracked_count' => 'Activities with completion tracked',
            'course_age_days' => 'Course age (days)',
            'enrolled_students' => 'Enrolled students',
            'dead_activity_count' => 'Activities never viewed',
            'recent_events_7d' => 'Events in the last 7 days',
            'active_students_7d' => 'Active students (7 days)',
            'completion_rate' => 'Completion rate (%)',
            'forum_post_count' => 'Forum posts',
            'quiz_attempt_count' => 'Quiz attempts',
            'assignment_submission_rate' => 'Assignment submission rate (%)',
            'term_stage' => 'Stage in the term',
            'groupmode' => 'Group mode',
            'format' => 'Course format',
        ];
    }

    /**
     * Operators offered for a fact type.
     *
     * @param string $type bool|int|float|enum
     * @return array<int, string>
     */
    public static function operators_for_type(string $type): array {
        return match ($type) {
            'bool' => ['==', '!='],
            'enum' => ['==', '!=', 'in'],
            default => ['==', '!=', '<', '<=', '>', '>='], // int/float
        };
    }

    /**
     * Allowed values for an enum fact ([] for non-enums). Some are runtime.
     *
     * @param string $key
     * @return array<int, string>
     */
    public static function enum_values(string $key): array {
        switch ($key) {
            case 'term_stage':
                return ['prestart', 'week1', 'early', 'mid', 'late', 'postend', 'undated'];
            case 'groupmode':
                return ['none', 'separate', 'visible'];
            case 'format':
                return array_values(\core\plugin_manager::instance()->get_enabled_plugins('format') ?: []);
            default:
                return [];
        }
    }

    /**
     * The full fact metadata for the builder UI, grouped and enriched with the
     * runtime module-count facts.
     *
     * @return array{groups: array<int, array{key: string, label: string, facts: array}>}
     */
    public static function for_form(): array {
        $types = self::scalar_facts();
        $groupmap = self::fact_group_map();
        $labels = self::fact_labels();

        $groups = [];
        foreach (self::groups() as $gkey => $glabel) {
            $groups[$gkey] = ['key' => $gkey, 'label' => $glabel, 'facts' => []];
        }

        foreach ($types as $key => $type) {
            if (!isset($groupmap[$key])) {
                throw new \coding_exception("fact_group_map() is missing a group for fact: $key");
            }
            $groups[$groupmap[$key]]['facts'][] = [
                'key' => $key,
                'label' => $labels[$key] ?? $key,
                'type' => $type,
                'operators' => self::operators_for_type($type),
                'values' => self::enum_values($key),
            ];
        }

        foreach (\core\plugin_manager::instance()->get_enabled_plugins('mod') as $modname) {
            $groups['modulecount']['facts'][] = [
                'key' => self::MODULE_COUNT_PREFIX . $modname,
                'label' => get_string('pluginname', 'mod_' . $modname),
                'type' => 'int',
                'operators' => self::operators_for_type('int'),
                'values' => [],
            ];
        }

        return ['groups' => array_values($groups)];
    }
}
