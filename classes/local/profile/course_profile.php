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
 * An immutable bag of computed facts about a single course.
 *
 * @package    tool_guidance
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_profile {

    /** @var array<string, mixed> computed fact values keyed by fact name */
    private array $facts;

    /**
     * @param array<string, mixed> $facts
     */
    public function __construct(array $facts) {
        $this->facts = $facts;
    }

    /**
     * Resolve a fact value.
     *
     * Returns 0 for an absent per-module count, and null for any other unknown or
     * uncomputed fact (the evaluator treats a null operand as a non-match).
     *
     * @param string $key
     * @return mixed
     */
    public function get(string $key) {
        if (array_key_exists($key, $this->facts)) {
            return $this->facts[$key];
        }
        if (str_starts_with($key, fact_catalogue::MODULE_COUNT_PREFIX)) {
            return 0;
        }
        return null;
    }

    /**
     * Whether the given key names a fact (delegates to the catalogue).
     *
     * @param string $key
     * @return bool
     */
    public function is_known_fact(string $key): bool {
        return fact_catalogue::is_fact($key);
    }

    /**
     * All computed facts (used for the AI prompt summary and debugging).
     *
     * @return array<string, mixed>
     */
    public function all(): array {
        return $this->facts;
    }
}
