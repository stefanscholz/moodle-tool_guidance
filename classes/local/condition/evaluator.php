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
 * Parser and evaluator for the deterministic rule condition DSL.
 *
 * Grammar: clauses joined by " AND "; each clause is `fact OP operand`.
 * Operators: == != <= >= < > in. An operand is a literal (true/false/number/enum word),
 * a fact reference (any catalogue fact name), or for `in` a set `(a|b|c)`.
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
            $clauses = self::parse($condition);
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
            self::parse($condition);
            return true;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    /**
     * Parse the expression into a list of clauses.
     *
     * @param string $condition
     * @return array<int, array{fact: string, op: string, operand: string}>
     * @throws \coding_exception on a malformed clause.
     */
    private static function parse(string $condition): array {
        $condition = trim($condition);
        if ($condition === '') {
            return [];
        }
        $clauses = [];
        foreach (preg_split('/\s+AND\s+/', $condition) as $part) {
            $part = trim($part);
            if (!preg_match('/^([\w.]+)\s+(==|!=|<=|>=|<|>|in)\s+(.+)$/', $part, $m)) {
                throw new \coding_exception('Malformed condition clause: ' . $part);
            }
            $clauses[] = ['fact' => $m[1], 'op' => $m[2], 'operand' => trim($m[3])];
        }
        return $clauses;
    }

    /**
     * Evaluate a single clause against the profile.
     *
     * @param array{fact: string, op: string, operand: string} $clause
     * @param course_profile $profile
     * @return bool
     */
    private static function evaluate_clause(array $clause, course_profile $profile): bool {
        $left = $profile->get($clause['fact']);

        if ($clause['op'] === 'in') {
            if ($left === null) {
                return false;
            }
            return in_array((string) $left, self::parse_set($clause['operand']), true);
        }

        $right = self::resolve_operand($clause['operand'], $profile);
        if ($left === null || $right === null) {
            return false;
        }
        return self::compare($left, $clause['op'], $right);
    }

    /**
     * Parse a `(a|b|c)` set operand into its members.
     *
     * @param string $operand
     * @return array<int, string>
     */
    private static function parse_set(string $operand): array {
        $operand = trim($operand);
        if (str_starts_with($operand, '(') && str_ends_with($operand, ')')) {
            $operand = substr($operand, 1, -1);
        }
        return array_map('trim', explode('|', $operand));
    }

    /**
     * Resolve a non-set operand to a literal value or another fact's value.
     *
     * @param string $operand
     * @param course_profile $profile
     * @return mixed
     */
    private static function resolve_operand(string $operand, course_profile $profile) {
        $lower = strtolower($operand);
        if ($lower === 'true') {
            return true;
        }
        if ($lower === 'false') {
            return false;
        }
        if (is_numeric($operand)) {
            return $operand + 0;
        }
        if ($profile->is_known_fact($operand)) {
            return $profile->get($operand);
        }
        return $operand;
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
