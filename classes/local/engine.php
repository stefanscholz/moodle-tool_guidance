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

use context_course;
use tool_guidance\local\condition\evaluator;
use tool_guidance\local\profile\profile_builder;
use tool_guidance\local\rule\rule_repository;

/**
 * The suggestion engine: builds the course profile, walks the rule table in precedence
 * order, filters out unavailable or dismissed rules, optionally lets AI re-rank, and
 * returns the single top suggestion. Results are cached per course.
 *
 * @package    tool_guidance
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class engine {

    /** @var string Sentinel cached when a course has no suggestion. */
    const NONE = 'none';

    /** @var rule_repository */
    private rule_repository $rules;

    /** @var dismissal_manager */
    private dismissal_manager $dismissals;

    /**
     * @param rule_repository|null $rules
     * @param dismissal_manager|null $dismissals
     */
    public function __construct(?rule_repository $rules = null, ?dismissal_manager $dismissals = null) {
        $this->rules = $rules ?? new rule_repository();
        $this->dismissals = $dismissals ?? new dismissal_manager();
    }

    /**
     * Convenience constructor.
     *
     * @return self
     */
    public static function instance(): self {
        return new self();
    }

    /**
     * The top suggestion for a course, or null if none applies.
     *
     * @param int $courseid
     * @return suggestion|null
     */
    public function get_suggestion(int $courseid): ?suggestion {
        $cache = self::cache();
        $cached = $cache->get($courseid);
        if ($cached === self::NONE) {
            return null;
        }
        if (is_array($cached)) {
            return suggestion::from_array($cached);
        }

        $top = $this->compute($courseid);
        $cache->set($courseid, $top === null ? self::NONE : $top->to_array());
        return $top;
    }

    /**
     * Compute the top suggestion from scratch (no cache).
     *
     * @param int $courseid
     * @return suggestion|null
     */
    public function compute(int $courseid): ?suggestion {
        $course = get_course($courseid);
        $profile = profile_builder::build($course);

        $enabledmods = \core\plugin_manager::instance()->get_enabled_plugins('mod') ?: [];

        $candidates = [];
        foreach ($this->rules->get_enabled_rules() as $rule) {
            if (!isset($enabledmods[$rule->suggestmod])) {
                continue;
            }
            if ($this->dismissals->is_active($courseid, $rule->id)) {
                continue;
            }
            if (evaluator::matches($rule->conditiontext, $profile)) {
                $candidates[] = suggestion::from_rule($rule);
            }
        }

        if (!$candidates) {
            return null;
        }

        $candidates = ai_enhancer::rerank($profile, $candidates, context_course::instance($courseid));
        return $candidates[0];
    }

    /**
     * The suggestion cache.
     *
     * @return \cache_application|\cache_session|\cache_store
     */
    public static function cache() {
        return \cache::make('tool_guidance', 'suggestions');
    }

    /**
     * Purge the cached suggestion for one course.
     *
     * @param int $courseid
     */
    public static function purge_course(int $courseid): void {
        self::cache()->delete($courseid);
    }

    /**
     * Purge all cached suggestions (e.g. after a rule change).
     */
    public static function purge_all(): void {
        self::cache()->purge();
    }
}
