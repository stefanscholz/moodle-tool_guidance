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

namespace tool_guidance\local\condition;

use tool_guidance\local\profile\course_profile;

/**
 * Evaluator for the deterministic rule condition DSL.
 *
 * Grammar is owned by {@see parser}. This class consumes the structured clause
 * arrays it produces and evaluates them against a course profile.
 *
 * @package    tool_guidance
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class evaluator {

    /**
     * Whether a profile satisfies a condition expression (all clauses, AND-combined).
     *
     * An empty condition matches everything. An unparseable condition never matches.
     *
     * @param string $condition
     * @param course_profile $profile
     * @return bool
     */
    public static function matches(string $condition, course_profile $profile): bool {
        try {
            $clauses = parser::parse($condition);
        } catch (\Throwable $e) {
            return false;
        }
        foreach ($clauses as $clause) {
            if (!self::evaluate_clause($clause, $profile)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Validate a condition expression for the admin form.
     *
     * @param string $condition
     * @return true|string true if valid, otherwise the offending clause text.
     */
    public static function validate(string $condition) {
        try {
            parser::parse($condition);
            return true;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    /**
     * Evaluate one structured clause against the profile.
     *
     * @param array{fact: string, op: string, operandkind: string, value: string|array} $clause
     * @param course_profile $profile
     * @return bool
     */
    private static function evaluate_clause(array $clause, course_profile $profile): bool {
        $left = $profile->get($clause['fact']);

        if ($clause['op'] === 'in') {
            if ($left === null) {
                return false;
            }
            return in_array((string) $left, (array) $clause['value'], true);
        }

        if ($clause['operandkind'] === 'fact') {
            $right = $profile->get($clause['value']);
        } else {
            $right = self::coerce_literal((string) $clause['value']);
        }
        if ($left === null || $right === null) {
            return false;
        }
        return self::compare($left, $clause['op'], $right);
    }

    /**
     * Coerce a literal operand string to bool / number / string.
     *
     * @param string $value
     * @return mixed
     */
    private static function coerce_literal(string $value) {
        $lower = strtolower($value);
        if ($lower === 'true') {
            return true;
        }
        if ($lower === 'false') {
            return false;
        }
        if (is_numeric($value)) {
            return $value + 0;
        }
        return $value;
    }

    /**
     * Compare two resolved values with the given operator.
     *
     * @param mixed $left
     * @param string $op
     * @param mixed $right
     * @return bool
     */
    private static function compare($left, string $op, $right): bool {
        if (is_bool($left) || is_bool($right)) {
            $l = (bool) $left;
            $r = is_bool($right) ? $right : ($right === 'true' || $right === 1 || $right === '1');
            return match ($op) {
                '==' => $l === $r,
                '!=' => $l !== $r,
                default => false,
            };
        }
        if (is_numeric($left) && is_numeric($right)) {
            $l = $left + 0;
            $r = $right + 0;
            return match ($op) {
                '==' => $l == $r,
                '!=' => $l != $r,
                '<'  => $l < $r,
                '<=' => $l <= $r,
                '>'  => $l > $r,
                '>=' => $l >= $r,
                default => false,
            };
        }
        $l = (string) $left;
        $r = (string) $right;
        return match ($op) {
            '==' => $l === $r,
            '!=' => $l !== $r,
            default => false,
        };
    }
}
