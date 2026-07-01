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
}
